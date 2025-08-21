<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Redirect the user to the provider authentication page.
     *
     * @param  string  $provider
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirect($provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    /**
     * Obtain the user information from the provider.
     *
     * @param  string  $provider
     * @return \Illuminate\Http\RedirectResponse
     */
    public function callback($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
            
            // Find existing user by provider and provider_id
            $user = User::where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            // If user doesn't exist, check if email exists
            if (!$user) {
                $user = User::where('email', $socialUser->getEmail())->first();
                
                // If user with email exists, update provider details
                if ($user) {
                    $user->update([
                        'provider' => $provider,
                        'provider_id' => $socialUser->getId(),
                        'provider_token' => $socialUser->token,
                    ]);
                } else {
                    // Create new user
                    $user = User::create([
                        'name' => $socialUser->getName(),
                        'email' => $socialUser->getEmail(),
                        'password' => Hash::make(Str::random(16)),
                        'provider' => $provider,
                        'provider_id' => $socialUser->getId(),
                        'provider_token' => $socialUser->token,
                        'role_id' => Role::where('name', 'user')->first()->id ?? null,
                        'email_verified_at' => now(),
                    ]);
                    
                    // Create wallet for new user
                    $user->wallet()->create([
                        'balance' => 0
                    ]);
                }
            }

            // Login user
            Auth::login($user);
            
            return redirect()->intended('dashboard');
            
        } catch (\Exception $e) {
            return redirect('/login')->with('error', 'Social login failed. Please try again.');
        }
    }
}
