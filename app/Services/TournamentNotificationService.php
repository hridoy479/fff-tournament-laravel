<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\User;

class TournamentNotificationService
{
    /**
     * Send tournament registration notification
     */
    public static function sendRegistrationNotification(User $user, Tournament $tournament)
    {
        Notification::create([
            'user_id' => $user->id,
            'title' => 'Tournament Registration',
            'message' => "You have successfully registered for the tournament: {$tournament->name}",
            'type' => Notification::TYPE_TOURNAMENT,
            'data' => [
                'tournament_id' => $tournament->id,
                'action' => 'registration',
            ],
        ]);
    }

    /**
     * Send tournament start notification
     */
    public static function sendTournamentStartNotification(Tournament $tournament)
    {
        // Get all users registered for this tournament
        $participants = $tournament->entries()->with('user')->get()->pluck('user');
        
        foreach ($participants as $user) {
            Notification::create([
                'user_id' => $user->id,
                'title' => 'Tournament Started',
                'message' => "The tournament {$tournament->name} has started. Check your matches!",
                'type' => Notification::TYPE_TOURNAMENT,
                'data' => [
                    'tournament_id' => $tournament->id,
                    'action' => 'started',
                ],
            ]);
        }
    }

    /**
     * Send match scheduled notification
     */
    public static function sendMatchScheduledNotification(TournamentMatch $match)
    {
        // Notify both players if they exist
        if ($match->slotAUser) {
            Notification::create([
                'user_id' => $match->slotAUser->id,
                'title' => 'Match Scheduled',
                'message' => "Your match in {$match->tournament->name} has been scheduled for " . 
                             $match->scheduled_at->format('Y-m-d H:i'),
                'type' => Notification::TYPE_MATCH,
                'data' => [
                    'tournament_id' => $match->tournament_id,
                    'match_id' => $match->id,
                    'action' => 'scheduled',
                ],
            ]);
        }

        if ($match->slotBUser) {
            Notification::create([
                'user_id' => $match->slotBUser->id,
                'title' => 'Match Scheduled',
                'message' => "Your match in {$match->tournament->name} has been scheduled for " . 
                             $match->scheduled_at->format('Y-m-d H:i'),
                'type' => Notification::TYPE_MATCH,
                'data' => [
                    'tournament_id' => $match->tournament_id,
                    'match_id' => $match->id,
                    'action' => 'scheduled',
                ],
            ]);
        }
    }

    /**
     * Send match reminder notification (24 hours before)
     */
    public static function sendMatchReminderNotification(TournamentMatch $match)
    {
        // Notify both players if they exist
        if ($match->slotAUser) {
            Notification::create([
                'user_id' => $match->slotAUser->id,
                'title' => 'Match Reminder',
                'message' => "Reminder: Your match in {$match->tournament->name} is scheduled for tomorrow at " . 
                             $match->scheduled_at->format('H:i'),
                'type' => Notification::TYPE_MATCH,
                'data' => [
                    'tournament_id' => $match->tournament_id,
                    'match_id' => $match->id,
                    'action' => 'reminder',
                ],
            ]);
        }

        if ($match->slotBUser) {
            Notification::create([
                'user_id' => $match->slotBUser->id,
                'title' => 'Match Reminder',
                'message' => "Reminder: Your match in {$match->tournament->name} is scheduled for tomorrow at " . 
                             $match->scheduled_at->format('H:i'),
                'type' => Notification::TYPE_MATCH,
                'data' => [
                    'tournament_id' => $match->tournament_id,
                    'match_id' => $match->id,
                    'action' => 'reminder',
                ],
            ]);
        }
    }

    /**
     * Send match rescheduled notification
     */
    public static function sendMatchRescheduledNotification(TournamentMatch $match)
    {
        // Notify both players if they exist
        if ($match->slotAUser) {
            Notification::create([
                'user_id' => $match->slotAUser->id,
                'title' => 'Match Rescheduled',
                'message' => "Your match in {$match->tournament->name} has been rescheduled to " . 
                             $match->scheduled_at->format('Y-m-d H:i'),
                'type' => Notification::TYPE_MATCH,
                'data' => [
                    'tournament_id' => $match->tournament_id,
                    'match_id' => $match->id,
                    'action' => 'rescheduled',
                ],
            ]);
        }

        if ($match->slotBUser) {
            Notification::create([
                'user_id' => $match->slotBUser->id,
                'title' => 'Match Rescheduled',
                'message' => "Your match in {$match->tournament->name} has been rescheduled to " . 
                             $match->scheduled_at->format('Y-m-d H:i'),
                'type' => Notification::TYPE_MATCH,
                'data' => [
                    'tournament_id' => $match->tournament_id,
                    'match_id' => $match->id,
                    'action' => 'rescheduled',
                ],
            ]);
        }
    }

    /**
     * Send match result notification
     */
    public static function sendMatchResultNotification(TournamentMatch $match)
    {
        // Only send if the match is completed and has a winner
        if ($match->status !== TournamentMatch::STATUS_COMPLETED || !$match->winner_user_id) {
            return;
        }

        // Notify both players
        if ($match->slotAUser) {
            $isWinner = $match->winner_user_id === $match->slotAUser->id;
            $message = $isWinner 
                ? "Congratulations! You won your match in {$match->tournament->name}!" 
                : "You lost your match in {$match->tournament->name}. Better luck next time!";

            Notification::create([
                'user_id' => $match->slotAUser->id,
                'title' => 'Match Result',
                'message' => $message,
                'type' => Notification::TYPE_MATCH,
                'data' => [
                    'tournament_id' => $match->tournament_id,
                    'match_id' => $match->id,
                    'action' => 'result',
                    'is_winner' => $isWinner,
                ],
            ]);
        }

        if ($match->slotBUser) {
            $isWinner = $match->winner_user_id === $match->slotBUser->id;
            $message = $isWinner 
                ? "Congratulations! You won your match in {$match->tournament->name}!" 
                : "You lost your match in {$match->tournament->name}. Better luck next time!";

            Notification::create([
                'user_id' => $match->slotBUser->id,
                'title' => 'Match Result',
                'message' => $message,
                'type' => Notification::TYPE_MATCH,
                'data' => [
                    'tournament_id' => $match->tournament_id,
                    'match_id' => $match->id,
                    'action' => 'result',
                    'is_winner' => $isWinner,
                ],
            ]);
        }
    }

    /**
     * Send tournament completion notification
     */
    public static function sendTournamentCompletionNotification(Tournament $tournament)
    {
        // Get all users who participated in this tournament
        $participants = $tournament->entries()->with('user')->get()->pluck('user');
        
        // Find the winner
        $winnerMatch = $tournament->matches()
            ->where('status', TournamentMatch::STATUS_COMPLETED)
            ->orderBy('round', 'desc')
            ->first();
            
        $winnerId = $winnerMatch ? $winnerMatch->winner_user_id : null;
        
        foreach ($participants as $user) {
            $isWinner = $winnerId && $user->id === $winnerId;
            $message = $isWinner
                ? "Congratulations! You are the champion of {$tournament->name}!"
                : "The tournament {$tournament->name} has concluded. Thank you for participating!";
                
            Notification::create([
                'user_id' => $user->id,
                'title' => 'Tournament Completed',
                'message' => $message,
                'type' => Notification::TYPE_TOURNAMENT,
                'data' => [
                    'tournament_id' => $tournament->id,
                    'action' => 'completed',
                    'is_winner' => $isWinner,
                ],
            ]);
        }
    }

    /**
     * Send prize awarded notification
     */
    public static function sendPrizeAwardedNotification(User $user, Tournament $tournament, float $amount)
    {
        Notification::create([
            'user_id' => $user->id,
            'title' => 'Prize Awarded',
            'message' => "You have been awarded $" . number_format($amount, 2) . " for your performance in {$tournament->name}!",
            'type' => Notification::TYPE_TOURNAMENT,
            'data' => [
                'tournament_id' => $tournament->id,
                'action' => 'prize_awarded',
                'amount' => $amount,
            ],
        ]);
    }

    /**
     * Send tournament cancellation notification
     */
    public static function sendTournamentCancellationNotification(Tournament $tournament): void
    {
        // Get all users registered for this tournament
        $participants = $tournament->entries()->with('user')->get()->pluck('user');

        foreach ($participants as $user) {
            Notification::create([
                'user_id' => $user->id,
                'title' => 'Tournament Cancelled',
                'message' => "The tournament {$tournament->name} has been cancelled. Your entry fee has been refunded.",
                'type' => Notification::TYPE_TOURNAMENT,
                'data' => [
                    'tournament_id' => $tournament->id,
                    'action' => 'cancelled',
                ],
            ]);
        }
    }
}
