<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // General settings
            [
                'key' => 'site_name',
                'value' => 'FFF Gaming Platform',
                'group' => 'general',
                'is_public' => true,
                'description' => 'The name of the site',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'site_description',
                'value' => 'The ultimate gaming tournament platform',
                'group' => 'general',
                'is_public' => true,
                'description' => 'The description of the site',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'maintenance_mode',
                'value' => 'false',
                'group' => 'general',
                'is_public' => true,
                'description' => 'Whether the site is in maintenance mode',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Payment settings
            [
                'key' => 'currency',
                'value' => 'BDT',
                'group' => 'payment',
                'is_public' => true,
                'description' => 'The currency used for payments',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'min_deposit_amount',
                'value' => '100',
                'group' => 'payment',
                'is_public' => true,
                'description' => 'Minimum deposit amount',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'min_withdrawal_amount',
                'value' => '200',
                'group' => 'payment',
                'is_public' => true,
                'description' => 'Minimum withdrawal amount',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'withdrawal_fee_percentage',
                'value' => '2',
                'group' => 'payment',
                'is_public' => true,
                'description' => 'Withdrawal fee percentage',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Tournament settings
            [
                'key' => 'tournament_registration_open',
                'value' => 'true',
                'group' => 'tournament',
                'is_public' => true,
                'description' => 'Whether tournament registration is open',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'platform_fee_percentage',
                'value' => '10',
                'group' => 'tournament',
                'is_public' => true,
                'description' => 'Platform fee percentage for tournaments',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Contact settings
            [
                'key' => 'contact_email',
                'value' => 'support@fffgaming.com',
                'group' => 'contact',
                'is_public' => true,
                'description' => 'Contact email address',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'contact_phone',
                'value' => '+880 1234567890',
                'group' => 'contact',
                'is_public' => true,
                'description' => 'Contact phone number',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Social media settings
            [
                'key' => 'facebook_url',
                'value' => 'https://facebook.com/fffgaming',
                'group' => 'social',
                'is_public' => true,
                'description' => 'Facebook URL',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'twitter_url',
                'value' => 'https://twitter.com/fffgaming',
                'group' => 'social',
                'is_public' => true,
                'description' => 'Twitter URL',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'instagram_url',
                'value' => 'https://instagram.com/fffgaming',
                'group' => 'social',
                'is_public' => true,
                'description' => 'Instagram URL',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Email settings (private)
            [
                'key' => 'mail_driver',
                'value' => 'smtp',
                'group' => 'email',
                'is_public' => false,
                'description' => 'Mail driver',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'mail_host',
                'value' => 'smtp.mailtrap.io',
                'group' => 'email',
                'is_public' => false,
                'description' => 'Mail host',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        
        DB::table('settings')->insert($settings);
    }
}
