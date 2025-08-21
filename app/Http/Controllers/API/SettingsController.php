<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    /**
     * Get all application settings (admin only).
     */
    public function index()
    {
        // Check if user is admin
        if (!Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access',
            ], 403);
        }
        
        // Get all settings from database
        $settings = DB::table('settings')->get();
        
        // Format settings as key-value pairs
        $formattedSettings = [];
        foreach ($settings as $setting) {
            $value = $setting->value;
            
            // Convert boolean strings to actual booleans
            if ($value === 'true') $value = true;
            if ($value === 'false') $value = false;
            
            // Convert numeric strings to numbers
            if (is_numeric($value)) {
                $value = strpos($value, '.') !== false ? (float) $value : (int) $value;
            }
            
            $formattedSettings[$setting->key] = $value;
        }
        
        return response()->json($formattedSettings);
    }

    /**
     * Get public application settings.
     */
    public function publicSettings()
    {
        // Cache settings for 1 hour to improve performance
        $publicSettings = Cache::remember('public_settings', 3600, function () {
            // Get only public settings from database
            $settings = DB::table('settings')
                ->where('is_public', true)
                ->get();
            
            // Format settings as key-value pairs
            $formatted = [];
            foreach ($settings as $setting) {
                $value = $setting->value;
                
                // Convert boolean strings to actual booleans
                if ($value === 'true') $value = true;
                if ($value === 'false') $value = false;
                
                // Convert numeric strings to numbers
                if (is_numeric($value)) {
                    $value = strpos($value, '.') !== false ? (float) $value : (int) $value;
                }
                
                $formatted[$setting->key] = $value;
            }
            
            return $formatted;
        });
        
        return response()->json($publicSettings);
    }

    /**
     * Update application settings (admin only).
     */
    public function update(Request $request)
    {
        // Check if user is admin
        if (!Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access',
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
            'settings.*' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $settings = $request->settings;
        
        // Update settings in database
        foreach ($settings as $key => $value) {
            // Convert booleans and null to strings for storage
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            if (is_null($value)) {
                $value = '';
            }
            
            DB::table('settings')
                ->updateOrInsert(
                    ['key' => $key],
                    ['value' => (string) $value, 'updated_at' => now()]
                );
        }
        
        // Clear cache
        Cache::forget('public_settings');
        
        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
        ]);
    }

    /**
     * Get user notification settings.
     */
    public function getUserNotificationSettings()
    {
        $user = Auth::user();
        
        // Get user notification settings from database
        $settings = DB::table('user_notification_settings')
            ->where('user_id', $user->id)
            ->first();
        
        // If no settings exist, create default settings
        if (!$settings) {
            $settings = [
                'email_notifications' => true,
                'push_notifications' => true,
                'tournament_reminders' => true,
                'match_reminders' => true,
                'payment_notifications' => true,
                'marketing_emails' => false,
            ];
            
            DB::table('user_notification_settings')->insert([
                'user_id' => $user->id,
                'settings' => json_encode($settings),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $settings = json_decode($settings->settings, true);
        }
        
        return response()->json($settings);
    }

    /**
     * Update user notification settings.
     */
    public function updateUserNotificationSettings(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'email_notifications' => 'boolean',
            'push_notifications' => 'boolean',
            'tournament_reminders' => 'boolean',
            'match_reminders' => 'boolean',
            'payment_notifications' => 'boolean',
            'marketing_emails' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Get current settings
        $currentSettings = DB::table('user_notification_settings')
            ->where('user_id', $user->id)
            ->first();
        
        if ($currentSettings) {
            $settings = json_decode($currentSettings->settings, true);
        } else {
            $settings = [
                'email_notifications' => true,
                'push_notifications' => true,
                'tournament_reminders' => true,
                'match_reminders' => true,
                'payment_notifications' => true,
                'marketing_emails' => false,
            ];
        }
        
        // Update settings with new values
        foreach ($request->all() as $key => $value) {
            if (array_key_exists($key, $settings)) {
                $settings[$key] = (bool) $value;
            }
        }
        
        // Save settings to database
        DB::table('user_notification_settings')
            ->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'settings' => json_encode($settings),
                    'updated_at' => now(),
                ]
            );
        
        return response()->json([
            'success' => true,
            'message' => 'Notification settings updated successfully',
            'settings' => $settings,
        ]);
    }
}
