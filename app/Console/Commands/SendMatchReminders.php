<?php

namespace App\Console\Commands;

use App\Models\TournamentMatch;
use App\Services\TournamentNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendMatchReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tournament:send-match-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminders for matches scheduled in the next 24 hours';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tomorrow = Carbon::now()->addDay();
        $startOfDay = $tomorrow->copy()->startOfDay();
        $endOfDay = $tomorrow->copy()->endOfDay();

        // Find all matches scheduled for tomorrow
        $matches = TournamentMatch::with(['tournament', 'slotAUser', 'slotBUser'])
            ->where('status', TournamentMatch::STATUS_SCHEDULED)
            ->whereBetween('scheduled_at', [$startOfDay, $endOfDay])
            ->get();

        $this->info("Found {$matches->count()} matches scheduled for tomorrow.");

        foreach ($matches as $match) {
            // Skip matches without players
            if (!$match->slotAUser && !$match->slotBUser) {
                $this->warn("Skipping match ID {$match->id} - no players assigned yet.");
                continue;
            }

            // Send reminder notification
            TournamentNotificationService::sendMatchReminderNotification($match);
            $this->info("Sent reminder for match ID {$match->id} in tournament '{$match->tournament->name}'.");
        }

        $this->info('Match reminders sent successfully.');
        return 0;
    }
}