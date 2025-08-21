<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentEntry;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\TournamentNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TournamentController extends Controller
{
    /**
     * Register a user for a tournament
     */
    public function registerUser(Request $request, string $id)
    {
        $tournament = Tournament::findOrFail($id);
        $user = Auth::user();
        
        // Check if tournament registration is open
        if ($tournament->status !== Tournament::STATUS_REGISTRATION_OPEN) {
            return response()->json([
                'message' => 'Tournament registration is closed.'
            ], 400);
        }

        // Check if registration deadline has passed
        if ($tournament->registration_deadline && now()->isAfter($tournament->registration_deadline)) {
            return response()->json([
                'message' => 'The registration deadline for this tournament has passed.'
            ], 400);
        }
        
        // Check if tournament is full
        $currentEntries = $tournament->entries()->count();
        if ($currentEntries >= $tournament->max_players) {
            return response()->json([
                'message' => 'Tournament is full.'
            ], 400);
        }
        
        // Check if user is already registered
        $existingEntry = TournamentEntry::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->first();
            
        if ($existingEntry) {
            return response()->json([
                'message' => 'You are already registered for this tournament.'
            ], 400);
        }
        
        // Check if user has enough balance for entry fee
        $wallet = $user->wallet;
        if ($wallet->balance < $tournament->entry_fee) {
            return response()->json([
                'message' => 'Insufficient balance to join this tournament.'
            ], 400);
        }
        
        // Deduct entry fee from wallet
        DB::transaction(function () use ($wallet, $tournament, $user) {
            // Deduct from wallet
            $wallet->balance -= $tournament->entry_fee;
            $wallet->save();
            
            // Create transaction record
            $user->transactions()->create([
                'amount' => -$tournament->entry_fee,
                'type' => 'tournament_entry',
                'description' => 'Entry fee for tournament: ' . $tournament->name,
                'status' => 'completed',
            ]);
            
            // Create tournament entry
            TournamentEntry::create([
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'status' => TournamentEntry::STATUS_ACTIVE,
                'joined_at' => now(),
            ]);
        });
        
        // Send notification to user
        TournamentNotificationService::sendRegistrationNotification($user, $tournament);
        
        return response()->json([
            'message' => 'Successfully registered for the tournament',
            'tournament' => $tournament->fresh(['entries']),
            'wallet' => $wallet->fresh(),
        ]);
    }

    /**
     * Cancel a tournament
     */
    public function cancelTournament(string $id)
    {
        $tournament = Tournament::findOrFail($id);

        // Only admins can cancel tournaments
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized. Only admins can cancel tournaments.'], 403);
        }

        // Check if tournament can be cancelled
        if (!in_array($tournament->status, [Tournament::STATUS_REGISTRATION_OPEN, Tournament::STATUS_REGISTRATION_CLOSED])) {
            return response()->json([
                'message' => 'Cannot cancel tournament. It is either in progress or has already finished.'
            ], 400);
        }

        DB::transaction(function () use ($tournament) {
            // Refund entry fees to all participants
            $entries = $tournament->entries;
            foreach ($entries as $entry) {
                $user = $entry->user;
                $wallet = $user->wallet;
                $wallet->balance += $tournament->entry_fee;
                $wallet->save();

                // Create refund transaction record
                $user->transactions()->create([
                    'amount' => $tournament->entry_fee,
                    'type' => 'tournament_refund',
                    'description' => 'Refund for cancelled tournament: ' . $tournament->name,
                    'status' => 'completed',
                ]);

                // Update entry status
                $entry->status = TournamentEntry::STATUS_CANCELLED;
                $entry->save();
            }

            // Update tournament status
            $tournament->status = Tournament::STATUS_CANCELLED;
            $tournament->save();
        });

        // Send cancellation notification to all participants
        TournamentNotificationService::sendTournamentCancellationNotification($tournament);

        return response()->json([
            'message' => 'Tournament has been cancelled and refunds have been issued.',
            'tournament' => $tournament->fresh(),
        ]);
    }
    
    /**
     * Generate tournament brackets
     */
    public function generateBrackets(string $id)
    {
        $tournament = Tournament::findOrFail($id);
        
        // Only admins can generate brackets
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized. Only admins can generate brackets.'], 403);
        }
        
        // Check if tournament is ready for brackets
        if ($tournament->status !== Tournament::STATUS_REGISTRATION_CLOSED) {
            return response()->json([
                'message' => 'Cannot generate brackets. Tournament registration must be closed first.'
            ], 400);
        }
        
        // Check if brackets already exist
        if ($tournament->matches()->count() > 0) {
            return response()->json([
                'message' => 'Brackets have already been generated for this tournament.'
            ], 400);
        }
        
        // Get active entries
        $entries = $tournament->entries()
            ->where('status', TournamentEntry::STATUS_ACTIVE)
            ->get();
            
        $playerCount = $entries->count();
        
        if ($playerCount < 2) {
            return response()->json([
                'message' => 'Not enough players to generate brackets. Minimum 2 players required.'
            ], 400);
        }
        
        $players = $this->applySeeding($tournament, $entries);

        // Generate brackets based on tournament format
        if ($tournament->format === Tournament::FORMAT_SINGLE_ELIMINATION) {
            $this->generateSingleEliminationBrackets($tournament, $players);
        } elseif ($tournament->format === Tournament::FORMAT_DOUBLE_ELIMINATION) {
            $this->generateDoubleEliminationBrackets($tournament, $players);
        } elseif ($tournament->format === Tournament::FORMAT_ROUND_ROBIN) {
            $this->generateRoundRobinBrackets($tournament, $players);
        }
        
        // Update tournament status
        $tournament->status = Tournament::STATUS_IN_PROGRESS;
        $tournament->save();
        
        // Send tournament start notification to all participants
        TournamentNotificationService::sendTournamentStartNotification($tournament);
        
        return response()->json([
            'message' => 'Tournament brackets generated successfully',
            'tournament' => $tournament->fresh(['matches']),
        ]);
    }

    /**
     * Apply seeding to the list of players based
    private function generateSingleEliminationBrackets($tournament, $players)
     {
         $playerCount = count($players);
        
        // Calculate number of rounds needed
        $rounds = ceil(log($playerCount, 2));
        $matchesInFirstRound = pow(2, $rounds - 1);
        
        // Create first round matches
        $matchNumber = 1;
        for ($i = 0; $i < $matchesInFirstRound; $i++) {
            $slotA = isset($players[$i]) ? $players[$i] : null;
            $slotB = isset($players[$playerCount - 1 - $i]) ? $players[$playerCount - 1 - $i] : null;
            
            // If we have an odd number of players, some players get a bye
            if ($slotA && !$slotB) {
                // This player gets a bye, create a match in the next round instead
                continue;
            }
            
            TournamentMatch::create([
                'tournament_id' => $tournament->id,
                'round' => 1,
                'match_number' => $matchNumber++,
                'slot_a_user_id' => $slotA,
                'slot_b_user_id' => $slotB,
                'status' => TournamentMatch::STATUS_SCHEDULED,
                'scheduled_at' => $tournament->start_date,
            ]);
        }
        
        // Create placeholder matches for subsequent rounds
        for ($round = 2; $round <= $rounds; $round++) {
            $matchesInRound = pow(2, $rounds - $round);
            
            for ($i = 0; $i < $matchesInRound; $i++) {
                TournamentMatch::create([
                    'tournament_id' => $tournament->id,
                    'round' => $round,
                    'match_number' => $matchNumber++,
                    'status' => TournamentMatch::STATUS_PENDING,
                    'scheduled_at' => date('Y-m-d H:i:s', strtotime($tournament->start_date . ' + ' . ($round - 1) . ' days')),
                ]);
            }
        }
    }
    
    /**
     * Generate double elimination brackets
     */
    private function generateDoubleEliminationBrackets($tournament, $players)
     {
         $playerCount = count($players);
        
        // Calculate number of rounds needed for winners bracket
        $winnerRounds = ceil(log($playerCount, 2));
        $matchesInFirstRound = pow(2, $winnerRounds - 1);
        
        $matchNumber = 1;
        
        // Create winners bracket first round matches
        for ($i = 0; $i < $matchesInFirstRound; $i++) {
            $slotA = isset($players[$i]) ? $players[$i] : null;
            $slotB = isset($players[$playerCount - 1 - $i]) ? $players[$playerCount - 1 - $i] : null;
            
            // If we have an odd number of players, some players get a bye
            if ($slotA && !$slotB) {
                // This player gets a bye, create a match in the next round instead
                continue;
            }
            
            TournamentMatch::create([
                'tournament_id' => $tournament->id,
                'round' => 1,
                'bracket' => 'winners',
                'match_number' => $matchNumber++,
                'slot_a_user_id' => $slotA,
                'slot_b_user_id' => $slotB,
                'status' => TournamentMatch::STATUS_SCHEDULED,
                'scheduled_at' => $tournament->start_date,
            ]);
        }
        
        // Create placeholder matches for subsequent rounds in winners bracket
        for ($round = 2; $round <= $winnerRounds; $round++) {
            $matchesInRound = pow(2, $winnerRounds - $round);
            
            for ($i = 0; $i < $matchesInRound; $i++) {
                TournamentMatch::create([
                    'tournament_id' => $tournament->id,
                    'round' => $round,
                    'bracket' => 'winners',
                    'match_number' => $matchNumber++,
                    'status' => TournamentMatch::STATUS_PENDING,
                    'scheduled_at' => date('Y-m-d H:i:s', strtotime($tournament->start_date . ' + ' . ($round - 1) . ' days')),
                ]);
            }
        }
        
        // Create losers bracket matches
        // Number of rounds in losers bracket is 2 * winnerRounds - 1
        $loserRounds = 2 * $winnerRounds - 1;
        $loserMatchNumber = 1;
        
        // First round of losers bracket receives losers from winners bracket round 1
        for ($i = 0; $i < $matchesInFirstRound / 2; $i++) {
            TournamentMatch::create([
                'tournament_id' => $tournament->id,
                'round' => 1,
                'bracket' => 'losers',
                'match_number' => $loserMatchNumber++,
                'status' => TournamentMatch::STATUS_PENDING,
                'scheduled_at' => date('Y-m-d H:i:s', strtotime($tournament->start_date . ' + 1 days')),
            ]);
        }
        
        // Create remaining losers bracket rounds
        for ($round = 2; $round <= $loserRounds; $round++) {
            // In double elimination, the number of matches in each losers round follows a pattern
            // Even rounds have the same number of matches as the previous round
            // Odd rounds (after round 1) have half the matches of the previous round
            $matchesInRound = $round % 2 == 0 ?
                $matchesInFirstRound / pow(2, floor(($round - 1) / 2)) :
                $matchesInFirstRound / pow(2, ceil(($round - 1) / 2));
            
            for ($i = 0; $i < $matchesInRound; $i++) {
                TournamentMatch::create([
                    'tournament_id' => $tournament->id,
                    'round' => $round,
                    'bracket' => 'losers',
                    'match_number' => $loserMatchNumber++,
                    'status' => TournamentMatch::STATUS_PENDING,
                    'scheduled_at' => date('Y-m-d H:i:s', strtotime($tournament->start_date . ' + ' . ($round + 1) . ' days')),
                ]);
            }
        }
        
        // Create grand finals match (winners bracket champion vs losers bracket champion)
        TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'round' => $winnerRounds + 1,
            'bracket' => 'finals',
            'match_number' => 1,
            'status' => TournamentMatch::STATUS_PENDING,
            'scheduled_at' => date('Y-m-d H:i:s', strtotime($tournament->start_date . ' + ' . ($loserRounds + 2) . ' days')),
        ]);
        
        // Create potential second grand finals match (if losers bracket champion wins first finals)
        TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'round' => $winnerRounds + 2,
            'bracket' => 'finals',
            'match_number' => 2,
            'status' => TournamentMatch::STATUS_PENDING,
            'scheduled_at' => date('Y-m-d H:i:s', strtotime($tournament->start_date . ' + ' . ($loserRounds + 3) . ' days')),
        ]);
    }
    
    /**
     * Generate round robin brackets
     */
    private function generateRoundRobinBrackets($tournament, $players)
     {
         $players = $players->pluck('user_id')->toArray();
        $playerCount = count($players);
        
        // If odd number of players, add a "bye" player
        if ($playerCount % 2 != 0) {
            $players[] = null;
            $playerCount++;
        }
        
        $rounds = $playerCount - 1;
        $matchesPerRound = $playerCount / 2;
        
        $matchNumber = 1;
        
        // Generate rounds using circle method
        for ($round = 1; $round <= $rounds; $round++) {
            // First player stays fixed, others rotate
            $roundPlayers = $players;
            
            for ($match = 0; $match < $matchesPerRound; $match++) {
                $slotA = $roundPlayers[$match];
                $slotB = $roundPlayers[$playerCount - 1 - $match];
                
                // Skip matches with bye
                if ($slotA !== null && $slotB !== null) {
                    TournamentMatch::create([
                        'tournament_id' => $tournament->id,
                        'round' => $round,
                        'match_number' => $matchNumber++,
                        'slot_a_user_id' => $slotA,
                        'slot_b_user_id' => $slotB,
                        'status' => TournamentMatch::STATUS_SCHEDULED,
                        'scheduled_at' => date('Y-m-d H:i:s', strtotime($tournament->start_date . ' + ' . ($round - 1) . ' days')),
                    ]);
                }
            }
            
            // Rotate players for next round (first player stays fixed)
            $firstPlayer = $players[0];
            $lastPlayer = array_pop($players);
            array_unshift($players, $lastPlayer);
            array_unshift($players, $firstPlayer);
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Tournament::query();
        
        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by game if provided
        if ($request->has('game_id')) {
            $query->where('game_id', $request->game_id);
        }
        
        // Search by name if provided
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }
        
        // Sort by start date by default, or custom sort if provided
        $sortField = $request->get('sort_by', 'start_date');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);
        
        $tournaments = $query->with(['game'])->paginate(10);
        
        return response()->json($tournaments);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'game_id' => 'required|exists:games,id',
            'entry_fee' => 'required|numeric|min:0',
            'prize_pool' => 'required|numeric|min:0',
            'max_players' => 'required|integer|min:2',
            'start_date' => 'required|date|after:now',
            'registration_deadline' => 'required|date|before:start_date',
            'rules' => 'nullable|string',
            'format' => 'required|string|in:single_elimination,double_elimination,round_robin',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Only admins can create tournaments
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized. Only admins can create tournaments.'], 403);
        }

        $tournament = Tournament::create([
            'name' => $request->name,
            'description' => $request->description,
            'game_id' => $request->game_id,
            'entry_fee' => $request->entry_fee,
            'prize_pool' => $request->prize_pool,
            'max_players' => $request->max_players,
            'start_date' => $request->start_date,
            'registration_deadline' => $request->registration_deadline,
            'rules' => $request->rules,
            'format' => $request->format,
            'status' => Tournament::STATUS_REGISTRATION_OPEN,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Tournament created successfully',
            'tournament' => $tournament
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $tournament = Tournament::with(['game', 'entries.user', 'matches'])->findOrFail($id);
        
        return response()->json($tournament);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $tournament = Tournament::findOrFail($id);
        
        // Only admins can update tournaments
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized. Only admins can update tournaments.'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'game_id' => 'sometimes|required|exists:games,id',
            'entry_fee' => 'sometimes|required|numeric|min:0',
            'prize_pool' => 'sometimes|required|numeric|min:0',
            'max_players' => 'sometimes|required|integer|min:2',
            'start_date' => 'sometimes|required|date',
            'registration_deadline' => 'sometimes|required|date|before:start_date',
            'rules' => 'nullable|string',
            'format' => 'sometimes|required|string|in:single_elimination,double_elimination,round_robin',
            'status' => 'sometimes|required|string|in:registration_open,registration_closed,in_progress,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $tournament->update($request->all());
        
        return response()->json([
            'message' => 'Tournament updated successfully',
            'tournament' => $tournament
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $tournament = Tournament::findOrFail($id);
        
        // Only admins can delete tournaments
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized. Only admins can delete tournaments.'], 403);
        }
        
        // Cannot delete tournaments that are in progress or completed
        if (in_array($tournament->status, [Tournament::STATUS_IN_PROGRESS, Tournament::STATUS_COMPLETED])) {
            return response()->json([
                'message' => 'Cannot delete tournaments that are in progress or completed.'
            ], 400);
        }
        
        $tournament->delete();
        
        return response()->json([
            'message' => 'Tournament deleted successfully'
        ]);
    }

    /**
     * Track tournament progression and update status
     */
    public function trackProgress(string $id)
    {   
        $tournament = Tournament::with(['matches'])->findOrFail($id);
        
        // Only admins can manually track tournament progress
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized. Only admins can track tournament progress.'], 403);
        }
        
        // Check if tournament is in progress
        if ($tournament->status !== Tournament::STATUS_IN_PROGRESS) {
            return response()->json([
                'message' => 'Cannot track progress. Tournament is not in progress.'
            ], 400);
        }
        
        $stats = $this->getTournamentStats($tournament);
        
        return response()->json([
            'message' => 'Tournament progress tracked successfully',
            'tournament' => $tournament,
            'stats' => $stats
        ]);
    }
    
    /**
     * Get tournament statistics
     */
    private function getTournamentStats($tournament)
    {   
        $matches = $tournament->matches;
        
        $totalMatches = $matches->count();
        $completedMatches = $matches->where('status', TournamentMatch::STATUS_COMPLETED)->count();
        $pendingMatches = $matches->where('status', TournamentMatch::STATUS_PENDING)->count();
        $scheduledMatches = $matches->where('status', TournamentMatch::STATUS_SCHEDULED)->count();
        $inProgressMatches = $matches->where('status', TournamentMatch::STATUS_IN_PROGRESS)->count();
        
        $winnersBracketMatches = $matches->where('bracket', TournamentMatch::BRACKET_WINNERS)->count();
        $losersBracketMatches = $matches->where('bracket', TournamentMatch::BRACKET_LOSERS)->count();
        $finalsBracketMatches = $matches->where('bracket', TournamentMatch::BRACKET_FINALS)->count();
        
        $completionPercentage = $totalMatches > 0 ? round(($completedMatches / $totalMatches) * 100, 2) : 0;
        
        // Get current round information
        $currentRounds = [];
        
        if ($tournament->format === Tournament::FORMAT_DOUBLE_ELIMINATION) {
            $currentWinnersRound = $matches->where('bracket', TournamentMatch::BRACKET_WINNERS)
                ->where('status', '!=', TournamentMatch::STATUS_COMPLETED)
                ->min('round') ?? 0;
                
            $currentLosersRound = $matches->where('bracket', TournamentMatch::BRACKET_LOSERS)
                ->where('status', '!=', TournamentMatch::STATUS_COMPLETED)
                ->min('round') ?? 0;
                
            $currentFinalsRound = $matches->where('bracket', TournamentMatch::BRACKET_FINALS)
                ->where('status', '!=', TournamentMatch::STATUS_COMPLETED)
                ->min('round') ?? 0;
                
            $currentRounds = [
                'winners' => $currentWinnersRound,
                'losers' => $currentLosersRound,
                'finals' => $currentFinalsRound
            ];
        } else {
            $currentRound = $matches->where('status', '!=', TournamentMatch::STATUS_COMPLETED)
                ->min('round') ?? 0;
                
            $currentRounds = ['current' => $currentRound];
        }
        
        return [
            'total_matches' => $totalMatches,
            'completed_matches' => $completedMatches,
            'pending_matches' => $pendingMatches,
            'scheduled_matches' => $scheduledMatches,
            'in_progress_matches' => $inProgressMatches,
            'winners_bracket_matches' => $winnersBracketMatches,
            'losers_bracket_matches' => $losersBracketMatches,
            'finals_bracket_matches' => $finalsBracketMatches,
            'completion_percentage' => $completionPercentage,
            'current_rounds' => $currentRounds
        ];
    }
    
    /**
     * Distribute prizes for tournament winners
     */
    public function distributePrizes(string $id)
    {
        $tournament = Tournament::with(['matches'])->findOrFail($id);
        
        // Only admins can distribute prizes
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized. Only admins can distribute prizes.'], 403);
        }
        
        // Check if tournament is completed
        if ($tournament->status !== Tournament::STATUS_COMPLETED) {
            return response()->json([
                'message' => 'Cannot distribute prizes. Tournament is not completed.'
            ], 400);
        }
        
        // Check if tournament has a winner
        if (!$tournament->winner_id) {
            return response()->json([
                'message' => 'Cannot distribute prizes. Tournament does not have a winner.'
            ], 400);
        }
        
        // Get tournament winner
        $winner = User::find($tournament->winner_id);
        if (!$winner) {
            return response()->json([
                'message' => 'Cannot distribute prizes. Winner not found.'
            ], 400);
        }
        
        // Get runner-up (finalist who is not the winner)
        $runnerUp = null;
        $finalsMatch = $tournament->matches()
            ->where('bracket', TournamentMatch::BRACKET_FINALS)
            ->where('status', TournamentMatch::STATUS_COMPLETED)
            ->orderBy('match_number', 'desc')
            ->first();
            
        if ($finalsMatch) {
            $runnerUpId = $finalsMatch->winner_user_id == $finalsMatch->slot_a_user_id ? 
                $finalsMatch->slot_b_user_id : $finalsMatch->slot_a_user_id;
                
            $runnerUp = User::find($runnerUpId);
        }
        
        // Get third place (loser of losers bracket final)
        $thirdPlace = null;
        $losersFinalMatch = $tournament->matches()
            ->where('bracket', TournamentMatch::BRACKET_LOSERS)
            ->orderBy('round', 'desc')
            ->orderBy('match_number', 'desc')
            ->first();
            
        if ($losersFinalMatch) {
            $thirdPlaceId = $losersFinalMatch->winner_user_id == $losersFinalMatch->slot_a_user_id ? 
                $losersFinalMatch->slot_b_user_id : $losersFinalMatch->slot_a_user_id;
                
            $thirdPlace = User::find($thirdPlaceId);
        }
        
        // Default prize distribution: 70% to winner, 20% to runner-up, 10% to third place
        $prizePool = $tournament->prize_pool;
        $winnerPrize = $prizePool * 0.7;
        $runnerUpPrize = $prizePool * 0.2;
        $thirdPlacePrize = $prizePool * 0.1;
        
        // Distribute prizes using database transaction
        DB::transaction(function () use ($winner, $runnerUp, $thirdPlace, $tournament, $winnerPrize, $runnerUpPrize, $thirdPlacePrize) {
            // Award prize to winner
            $this->awardPrize($winner, $tournament, $winnerPrize, 'first');
            
            // Award prize to runner-up if exists
            if ($runnerUp) {
                $this->awardPrize($runnerUp, $tournament, $runnerUpPrize, 'second');
            }
            
            // Award prize to third place if exists
            if ($thirdPlace) {
                $this->awardPrize($thirdPlace, $tournament, $thirdPlacePrize, 'third');
            }
            
            // Mark tournament prizes as distributed
        $tournament->update(['prizes_distributed' => true]);
        
        // Send tournament completion notification to all participants
        TournamentNotificationService::sendTournamentCompletionNotification($tournament);
        });
        
        return response()->json([
            'message' => 'Tournament prizes distributed successfully',
            'tournament' => $tournament->fresh(),
            'distribution' => [
                'winner' => [
                    'user_id' => $winner->id,
                    'username' => $winner->username,
                    'prize' => $winnerPrize
                ],
                'runner_up' => $runnerUp ? [
                    'user_id' => $runnerUp->id,
                    'username' => $runnerUp->username,
                    'prize' => $runnerUpPrize
                ] : null,
                'third_place' => $thirdPlace ? [
                    'user_id' => $thirdPlace->id,
                    'username' => $thirdPlace->username,
                    'prize' => $thirdPlacePrize
                ] : null
            ]
        ]);
    }
    
    /**
     * Award prize to a user
     */
    private function awardPrize($user, $tournament, $amount, $position)
    {
        // Add to wallet
        $wallet = $user->wallet;
        $wallet->balance += $amount;
        $wallet->save();
        
        // Create transaction record
        $user->transactions()->create([
            'amount' => $amount,
            'type' => 'tournament_prize',
            'description' => "Prize for {$position} place in tournament: {$tournament->name}",
            'status' => 'completed',
        ]);
        
        // Create notification
        $user->notifications()->create([
            'title' => 'Tournament Prize Awarded',
            'content' => "Congratulations! You've received {$amount} coins for {$position} place in {$tournament->name}",
            'type' => 'prize_awarded',
            'data' => json_encode([
                'tournament_id' => $tournament->id,
                'prize_amount' => $amount,
                'position' => $position
            ]),
            'is_read' => false,
        ]);
        
        // Send prize notification
        TournamentNotificationService::sendPrizeAwardedNotification($user, $tournament, $amount, $position);
    }

    /**
     * Get tournament bracket visualization data
     */
    public function getBracketData(string $id)
    {
        $tournament = Tournament::with(['matches.slotAUser', 'matches.slotBUser', 'matches.winner'])->findOrFail($id);
        
        // Format data based on tournament type
        if ($tournament->format === Tournament::FORMAT_SINGLE_ELIMINATION) {
            $bracketData = $this->formatSingleEliminationBracket($tournament);
        } elseif ($tournament->format === Tournament::FORMAT_DOUBLE_ELIMINATION) {
            $bracketData = $this->formatDoubleEliminationBracket($tournament);
        } elseif ($tournament->format === Tournament::FORMAT_ROUND_ROBIN) {
            $bracketData = $this->formatRoundRobinBracket($tournament);
        } else {
            return response()->json([
                'message' => 'Unsupported tournament format for bracket visualization'
            ], 400);
        }
        
        return response()->json([
            'tournament' => $tournament->only(['id', 'name', 'format', 'status', 'start_date']),
            'bracket_data' => $bracketData
        ]);
    }
    
    /**
     * Format single elimination bracket data
     */
    private function formatSingleEliminationBracket($tournament)
    {
        $matches = $tournament->matches;
        $rounds = $matches->max('round');
        
        $bracketData = [];
        
        for ($round = 1; $round <= $rounds; $round++) {
            $roundMatches = $matches->where('round', $round);
            $roundData = [];
            
            foreach ($roundMatches as $match) {
                $roundData[] = [
                    'match_id' => $match->id,
                    'match_number' => $match->match_number,
                    'player1' => $match->slotAUser ? [
                        'id' => $match->slotAUser->id,
                        'name' => $match->slotAUser->username,
                        'score' => $match->score_a
                    ] : null,
                    'player2' => $match->slotBUser ? [
                        'id' => $match->slotBUser->id,
                        'name' => $match->slotBUser->username,
                        'score' => $match->score_b
                    ] : null,
                    'winner_id' => $match->winner_user_id,
                    'status' => $match->status,
                    'scheduled_at' => $match->scheduled_at
                ];
            }
            
            $bracketData[] = [
                'round' => $round,
                'name' => $this->getRoundName($round, $rounds),
                'matches' => $roundData
            ];
        }
        
        return $bracketData;
    }
    
    /**
     * Format double elimination bracket data
     */
    private function formatDoubleEliminationBracket($tournament)
    {
        $matches = $tournament->matches;
        
        $winnersBracket = [];
        $losersBracket = [];
        $finalsBracket = [];
        
        // Process winners bracket
        $winnersMatches = $matches->where('bracket', TournamentMatch::BRACKET_WINNERS);
        $winnersRounds = $winnersMatches->max('round');
        
        for ($round = 1; $round <= $winnersRounds; $round++) {
            $roundMatches = $winnersMatches->where('round', $round);
            $roundData = [];
            
            foreach ($roundMatches as $match) {
                $roundData[] = [
                    'match_id' => $match->id,
                    'match_number' => $match->match_number,
                    'player1' => $match->slotAUser ? [
                        'id' => $match->slotAUser->id,
                        'name' => $match->slotAUser->username,
                        'score' => $match->score_a
                    ] : null,
                    'player2' => $match->slotBUser ? [
                        'id' => $match->slotBUser->id,
                        'name' => $match->slotBUser->username,
                        'score' => $match->score_b
                    ] : null,
                    'winner_id' => $match->winner_user_id,
                    'status' => $match->status,
                    'scheduled_at' => $match->scheduled_at
                ];
            }
            
            $winnersBracket[] = [
                'round' => $round,
                'name' => "Winners Round {$round}",
                'matches' => $roundData
            ];
        }
        
        // Process losers bracket
        $losersMatches = $matches->where('bracket', TournamentMatch::BRACKET_LOSERS);
        $losersRounds = $losersMatches->max('round');
        
        for ($round = 1; $round <= $losersRounds; $round++) {
            $roundMatches = $losersMatches->where('round', $round);
            $roundData = [];
            
            foreach ($roundMatches as $match) {
                $roundData[] = [
                    'match_id' => $match->id,
                    'match_number' => $match->match_number,
                    'player1' => $match->slotAUser ? [
                        'id' => $match->slotAUser->id,
                        'name' => $match->slotAUser->username,
                        'score' => $match->score_a
                    ] : null,
                    'player2' => $match->slotBUser ? [
                        'id' => $match->slotBUser->id,
                        'name' => $match->slotBUser->username,
                        'score' => $match->score_b
                    ] : null,
                    'winner_id' => $match->winner_user_id,
                    'status' => $match->status,
                    'scheduled_at' => $match->scheduled_at
                ];
            }
            
            $losersBracket[] = [
                'round' => $round,
                'name' => "Losers Round {$round}",
                'matches' => $roundData
            ];
        }
        
        // Process finals bracket
        $finalsMatches = $matches->where('bracket', TournamentMatch::BRACKET_FINALS);
        
        foreach ($finalsMatches as $match) {
            $finalsBracket[] = [
                'match_id' => $match->id,
                'match_number' => $match->match_number,
                'name' => $match->match_number == 1 ? "Grand Finals" : "Grand Finals (Reset)",
                'player1' => $match->slotAUser ? [
                    'id' => $match->slotAUser->id,
                    'name' => $match->slotAUser->username,
                    'score' => $match->score_a
                ] : null,
                'player2' => $match->slotBUser ? [
                    'id' => $match->slotBUser->id,
                    'name' => $match->slotBUser->username,
                    'score' => $match->score_b
                ] : null,
                'winner_id' => $match->winner_user_id,
                'status' => $match->status,
                'scheduled_at' => $match->scheduled_at
            ];
        }
        
        return [
            'winners_bracket' => $winnersBracket,
            'losers_bracket' => $losersBracket,
            'finals' => $finalsBracket
        ];
    }
    
    /**
     * Format round robin bracket data
     */
    private function formatRoundRobinBracket($tournament)
    {
        $matches = $tournament->matches;
        $rounds = $matches->max('round');
        
        $bracketData = [];
        $standings = [];
        
        // Process each round
        for ($round = 1; $round <= $rounds; $round++) {
            $roundMatches = $matches->where('round', $round);
            $roundData = [];
            
            foreach ($roundMatches as $match) {
                $roundData[] = [
                    'match_id' => $match->id,
                    'match_number' => $match->match_number,
                    'player1' => $match->slotAUser ? [
                        'id' => $match->slotAUser->id,
                        'name' => $match->slotAUser->username,
                        'score' => $match->score_a
                    ] : null,
                    'player2' => $match->slotBUser ? [
                        'id' => $match->slotBUser->id,
                        'name' => $match->slotBUser->username,
                        'score' => $match->score_b
                    ] : null,
                    'winner_id' => $match->winner_user_id,
                    'status' => $match->status,
                    'scheduled_at' => $match->scheduled_at
                ];
                
                // Update standings for completed matches
                if ($match->status === TournamentMatch::STATUS_COMPLETED) {
                    $this->updateRoundRobinStandings($standings, $match);
                }
            }
            
            $bracketData[] = [
                'round' => $round,
                'name' => "Round {$round}",
                'matches' => $roundData
            ];
        }
        
        // Sort standings by points (descending)
        usort($standings, function($a, $b) {
            return $b['points'] <=> $a['points'];
        });
        
        return [
            'rounds' => $bracketData,
            'standings' => array_values($standings)
        ];
    }
    
    /**
     * Update round robin standings
     */
    private function updateRoundRobinStandings(&$standings, $match)
    {
        // Skip if match doesn't have both players
        if (!$match->slotAUser || !$match->slotBUser) {
            return;
        }
        
        $playerAId = $match->slotAUser->id;
        $playerBId = $match->slotBUser->id;
        
        // Initialize player records if not exists
        if (!isset($standings[$playerAId])) {
            $standings[$playerAId] = [
                'player_id' => $playerAId,
                'player_name' => $match->slotAUser->username,
                'matches_played' => 0,
                'wins' => 0,
                'losses' => 0,
                'points' => 0
            ];
        }
        
        if (!isset($standings[$playerBId])) {
            $standings[$playerBId] = [
                'player_id' => $playerBId,
                'player_name' => $match->slotBUser->username,
                'matches_played' => 0,
                'wins' => 0,
                'losses' => 0,
                'points' => 0
            ];
        }
        
        // Update match stats
        $standings[$playerAId]['matches_played']++;
        $standings[$playerBId]['matches_played']++;
        
        if ($match->winner_user_id == $playerAId) {
            $standings[$playerAId]['wins']++;
            $standings[$playerBId]['losses']++;
            $standings[$playerAId]['points'] += 3; // 3 points for a win
        } elseif ($match->winner_user_id == $playerBId) {
            $standings[$playerBId]['wins']++;
            $standings[$playerAId]['losses']++;
            $standings[$playerBId]['points'] += 3; // 3 points for a win
        } else {
            // Draw - both get 1 point
            $standings[$playerAId]['points'] += 1;
            $standings[$playerBId]['points'] += 1;
        }
    }
    
    /**
     * Get round name based on position
     */
    private function getRoundName($round, $totalRounds)
    {
        if ($round == $totalRounds) {
            return 'Finals';
        } elseif ($round == $totalRounds - 1) {
            return 'Semi-Finals';
        } elseif ($round == $totalRounds - 2) {
            return 'Quarter-Finals';
        } else {
            return "Round {$round}";
        }
    }
}
