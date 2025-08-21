<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Tournament;
use App\Models\Match;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of users (admin only).
     */
    public function index(Request $request)
    {
        // Check if user is admin
        if (!Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access',
            ], 403);
        }
        
        $perPage = $request->per_page ?? 15;
        $search = $request->search;
        $role = $request->role;
        
        $query = User::orderBy('created_at', 'desc');
        
        // Search by name or email
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        // Filter by role
        if ($role) {
            $query->whereHas('roles', function($q) use ($role) {
                $q->where('name', $role);
            });
        }
        
        $users = $query->paginate($perPage);
        
        return response()->json($users);
    }

    /**
     * Display the specified user profile.
     */
    public function show(string $id)
    {
        $user = User::findOrFail($id);
        
        // Include user stats
        $stats = [
            'tournaments_played' => $user->tournamentEntries()->count(),
            'tournaments_won' => Tournament::where('winner_id', $user->id)->count(),
            'matches_played' => Match::where('player1_id', $user->id)
                ->orWhere('player2_id', $user->id)
                ->count(),
            'matches_won' => Match::where('winner_id', $user->id)->count(),
        ];
        
        return response()->json([
            'user' => $user,
            'stats' => $stats,
        ]);
    }

    /**
     * Get authenticated user's profile.
     */
    public function profile()
    {
        $user = Auth::user();
        
        // Include user stats
        $stats = [
            'tournaments_played' => $user->tournamentEntries()->count(),
            'tournaments_won' => Tournament::where('winner_id', $user->id)->count(),
            'matches_played' => Match::where('player1_id', $user->id)
                ->orWhere('player2_id', $user->id)
                ->count(),
            'matches_won' => Match::where('winner_id', $user->id)->count(),
        ];
        
        // Include wallet balance
        $wallet = $user->wallet;
        
        return response()->json([
            'user' => $user,
            'stats' => $stats,
            'wallet' => $wallet,
        ]);
    }

    /**
     * Update user profile.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'username' => 'string|max:50|unique:users,username,' . $user->id,
            'bio' => 'nullable|string|max:500',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'phone' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Handle avatar upload if provided
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar && !str_contains($user->avatar, 'default')) {
                Storage::disk('public')->delete($user->avatar);
            }
            
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $avatarPath;
        }
        
        // Update user fields
        if ($request->has('name')) $user->name = $request->name;
        if ($request->has('username')) $user->username = $request->username;
        if ($request->has('bio')) $user->bio = $request->bio;
        if ($request->has('phone')) $user->phone = $request->phone;
        if ($request->has('country')) $user->country = $request->country;
        if ($request->has('city')) $user->city = $request->city;
        
        $user->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }

    /**
     * Change user password.
     */
    public function changePassword(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check if current password is correct
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 422);
        }
        
        // Update password
        $user->password = Hash::make($request->new_password);
        $user->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Get user's tournament history.
     */
    public function tournamentHistory(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->per_page ?? 10;
        
        $tournaments = $user->tournamentEntries()
            ->with('tournament')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
            
        return response()->json($tournaments);
    }

    /**
     * Get user's match history.
     */
    public function matchHistory(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->per_page ?? 10;
        
        $matches = Match::where('player1_id', $user->id)
            ->orWhere('player2_id', $user->id)
            ->with(['tournament', 'player1', 'player2', 'team1', 'team2'])
            ->orderBy('scheduled_time', 'desc')
            ->paginate($perPage);
            
        return response()->json($matches);
    }

    /**
     * Update user role (admin only).
     */
    public function updateRole(Request $request, string $id)
    {
        // Check if user is admin
        if (!Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access',
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'role' => 'required|string|in:admin,moderator,user',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $user = User::findOrFail($id);
        
        // Remove all current roles
        $user->roles()->detach();
        
        // Assign new role
        $user->assignRole($request->role);
        
        return response()->json([
            'success' => true,
            'message' => 'User role updated successfully',
            'user' => $user->load('roles'),
        ]);
    }

    /**
     * Ban/unban user (admin only).
     */
    public function toggleBan(Request $request, string $id)
    {
        // Check if user is admin
        if (!Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access',
            ], 403);
        }
        
        $user = User::findOrFail($id);
        
        // Cannot ban yourself
        if ($user->id === Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot ban yourself',
            ], 422);
        }
        
        $user->is_banned = !$user->is_banned;
        $user->save();
        
        $message = $user->is_banned ? 'User banned successfully' : 'User unbanned successfully';
        
        return response()->json([
            'success' => true,
            'message' => $message,
            'is_banned' => $user->is_banned,
        ]);
    }
}
