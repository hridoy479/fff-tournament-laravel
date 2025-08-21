<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TournamentMatch;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;


class MatchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = TournamentMatch::query();
        
        // Filter by tournament
        if ($request->has('tournament_id')) {
            $query->where('tournament_id', $request->tournament_id);
        }
        
        // Filter by user's matches
        if ($request->has('my_matches') && $request->my_matches) {
            $user = Auth::user();
            $query->where(function($q) use ($user) {
                $q->where('slot_a_user_id', $user->id)
                  ->orWhere('slot_b_user_id', $user->id);
            });
        }
        
        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Filter by round
        if ($request->has('round')) {
            $query->where('round', $request->round);
        }
        
        // Sort by scheduled date
        $query->orderBy('scheduled_at', 'asc');
        
        // Paginate results
        $perPage = $request->per_page ?? 15;
        $matches = $query->with(['tournament', 'slotAUser', 'slotBUser'])->paginate($perPage);
        
        return response()->json($matches);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Only admins can create matches manually
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized. Only admins can create matches.'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'tournament_id' => 'required|exists:tournaments,id',
            'round' => 'required|integer|min:1',
            'match_number' => 'required|integer|min:1',
            'slot_a_user_id' => 'nullable|exists:users,id',
            'slot_b_user_id' => 'nullable|exists:users,id',
            'scheduled_at' => 'required|date',
            'status' => 'required|in:' . implode(',', [
                TournamentMatch::STATUS_PENDING,
                TournamentMatch::STATUS_SCHEDULED,
                TournamentMatch::STATUS_IN_PROGRESS,
                TournamentMatch::STATUS_COMPLETED,
                TournamentMatch::STATUS_CANCELLED
            ]),
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Create match
        $match = TournamentMatch::create([
            'tournament_id' => $request->tournament_id,
            'round' => $request->round,
            'match_number' => $request->match_number,
            'slot_a_user_id' => $request->slot_a_user_id,
            'slot_b_user_id' => $request->slot_b_user_id,
            'scheduled_at' => $request->scheduled_at,
            'status' => $request->status,
        ]);
        
        return response()->json([
            'message' => 'Match created successfully',
            'match' => $match->load(['tournament', 'slotAUser', 'slotBUser']),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $match = TournamentMatch::with(['tournament', 'slotAUser', 'slotBUser'])->findOrFail($id);
        
        return response()->json($match);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $match = TournamentMatch::findOrFail($id);
        
        // Only admins can update matches
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized. Only admins can update matches.'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'slot_a_user_id' => 'nullable|exists:users,id',
            'slot_b_user_id' => 'nullable|exists:users,id',
            'slot_a_score' => 'nullable|integer|min:0',
            'slot_b_score' => 'nullable|integer|min:0',
            'winner_id' => 'nullable|exists:users,id',
            'scheduled_at' => 'nullable|date',
            'status' => 'nullable|in:' . implode(',', [
                TournamentMatch::STATUS_PENDING,
                TournamentMatch::STATUS_SCHEDULED,
                TournamentMatch::STATUS_IN_PROGRESS,
                TournamentMatch::STATUS_COMPLETED,
                TournamentMatch::STATUS_CANCELLED
            ]),
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Update match
        $match->update($request->only([
            'slot_a_user_id',
            'slot_b_user_id',
            'slot_a_score',
            'slot_b_score',
            'winner_id',
            'scheduled_at',
            'status',
        ]));
        
        // If scheduled_at is updated, send notification
        if ($request->has('scheduled_at') && $match->scheduled_at != $request->scheduled_at) {
            $match->scheduled_at = $request->scheduled_at;
            TournamentNotificationService::sendMatchScheduledNotification($match);
        }
        
        $match->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Match updated successfully',
            'match' => $match,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $match = TournamentMatch::findOrFail($id);
        
        // Only admins can delete matches
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized. Only admins can delete matches.'], 403);
        }
        
        // Cannot delete completed matches
        if ($match->status === TournamentMatch::STATUS_COMPLETED) {
            return response()->json([
                'message' => 'Cannot delete completed matches'
            ], 400);
        }
        
        $match->delete();
        
        return response()->json([
            'message' => 'Match deleted successfully'
        ]);
    }

    /**
     * Report match score
     */
    public function rescheduleMatch(Request $request, $id)
    {
        $request->validate([
            'start_time' => 'required|date',
        ]);

        $match = TournamentMatch::findOrFail($id);

        // Add authorization logic here (e.g., check if the user is a tournament admin)
        if (auth()->user()->is_admin) {
            $match->update(['start_time' => $request->start_time]);

            // Notify players of the rescheduled match
            TournamentNotificationService::sendMatchRescheduledNotification($match);

            return response()->json(['message' => 'Match rescheduled successfully.']);
        }

        return response()->json(['message' => 'You are not authorized to reschedule this match.'], 403);
    }

    public function reportScore(Request $request, $id)
    {
        $user = Auth::user();
        $match = TournamentMatch::with(['tournament', 'slotAUser', 'slotBUser'])->findOrFail($id);
        
        // Validate request
        $validator = Validator::make($request->all(), [
            'score_a' => 'required|integer|min:0',
            'score_b' => 'required|integer|min:0',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }
        
        // Update match scores
        $match->score_a = $request->score_a;
        $match->score_b = $request->score_b;
        
        // Determine winner
        $winnerId = null;
        if ($match->score_a > $match->score_b) {
            $winnerId = $match->slot_a_user_id;
        } elseif ($match->score_b > $match->score_a) {
            $winnerId = $match->slot_b_user_id;
        }
        
        // Update match status and determine winner
        $match->status = TournamentMatch::STATUS_COMPLETED;
        $match->winner_user_id = $winnerId;
        $match->save();
        
        // Send match result notification
        TournamentNotificationService::sendMatchResultNotification($match);
        
        // Update next round match
        $this->updateNextRoundMatch($match);
        
        return response()->json([
            'success' => true,
            'message' => 'Score reported successfully',
            'match' => $match->fresh(['tournament', 'slotAUser', 'slotBUser']),
        ]);
    }
    
    /**
     * Update next round match with winner
     */
    private function updateNextRoundMatch($match)
    {
        // Only process if we have a winner
        if (!$match->winner_user_id) {
            return;
        }
        
        $tournament = $match->tournament;
        
        // Handle different bracket types
        if ($match->bracket === TournamentMatch::BRACKET_WINNERS) {
            $this->handleWinnersBracketProgression($match, $tournament);
        } elseif ($match->bracket === TournamentMatch::BRACKET_LOSERS) {
            $this->handleLosersBracketProgression($match, $tournament);
        } elseif ($match->bracket === TournamentMatch::BRACKET_FINALS) {
            $this->handleFinalsProgression($match, $tournament);
        }
    }
    
    /**
     * Handle winners bracket progression
     */
    private function handleWinnersBracketProgression($match, $tournament)
    {
        $nextRound = $match->round + 1;
        $winnerRounds = ceil(log($tournament->max_players, 2));
        
        // If this is the final winners bracket match, winner goes to finals
        if ($match->round == $winnerRounds) {
            // Find the finals match
            $finalsMatch = TournamentMatch::where('tournament_id', $tournament->id)
                ->where('bracket', TournamentMatch::BRACKET_FINALS)
                ->where('match_number', 1)
                ->first();
                
            if ($finalsMatch) {
                $finalsMatch->update([
                    'slot_a_user_id' => $match->winner_user_id,
                    'status' => $finalsMatch->slot_b_user_id ? TournamentMatch::STATUS_SCHEDULED : TournamentMatch::STATUS_PENDING
                ]);
            }
            
            // Loser goes to losers bracket final
            $loserUserId = $match->slot_a_user_id == $match->winner_user_id ? 
                $match->slot_b_user_id : $match->slot_a_user_id;
                
            $losersFinalRound = 2 * $winnerRounds - 1;
            $losersFinalMatch = TournamentMatch::where('tournament_id', $tournament->id)
                ->where('bracket', TournamentMatch::BRACKET_LOSERS)
                ->where('round', $losersFinalRound)
                ->first();
                
            if ($losersFinalMatch) {
                $losersFinalMatch->update([
                    'slot_b_user_id' => $loserUserId,
                    'status' => $losersFinalMatch->slot_a_user_id ? TournamentMatch::STATUS_SCHEDULED : TournamentMatch::STATUS_PENDING
                ]);
            }
            
            return;
        }
        
        // Find the next winners bracket match
        $matchesInCurrentRound = pow(2, $winnerRounds - $match->round);
        $positionInCurrentRound = $match->match_number % $matchesInCurrentRound;
        $nextMatchNumber = ceil($positionInCurrentRound / 2);
        
        $nextMatch = TournamentMatch::where('tournament_id', $tournament->id)
            ->where('bracket', TournamentMatch::BRACKET_WINNERS)
            ->where('round', $nextRound)
            ->where('match_number', $nextMatchNumber)
            ->first();
            
        if ($nextMatch) {
            // Determine which slot to fill (A or B)
            $isEvenMatch = $match->match_number % 2 == 0;
            
            if ($isEvenMatch) {
                $nextMatch->update(['slot_b_user_id' => $match->winner_user_id]);
            } else {
                $nextMatch->update(['slot_a_user_id' => $match->winner_user_id]);
            }
            
            // If both slots are filled, update status to scheduled
            if ($nextMatch->slot_a_user_id && $nextMatch->slot_b_user_id) {
                $nextMatch->update(['status' => TournamentMatch::STATUS_SCHEDULED]);
            }
        }
        
        // Send loser to losers bracket
        $loserUserId = $match->slot_a_user_id == $match->winner_user_id ? 
            $match->slot_b_user_id : $match->slot_a_user_id;
            
        // Calculate which losers bracket round and match
        $loserRound = $match->round * 2 - 1;
        $loserMatchNumber = ceil($match->match_number / 2);
        
        $loserMatch = TournamentMatch::where('tournament_id', $tournament->id)
            ->where('bracket', TournamentMatch::BRACKET_LOSERS)
            ->where('round', $loserRound)
            ->where('match_number', $loserMatchNumber)
            ->first();
            
        if ($loserMatch) {
            // Determine which slot to fill (A or B)
            $isEvenMatch = $match->match_number % 2 == 0;
            
            if ($isEvenMatch) {
                $loserMatch->update(['slot_b_user_id' => $loserUserId]);
            } else {
                $loserMatch->update(['slot_a_user_id' => $loserUserId]);
            }
            
            // If both slots are filled, update status to scheduled
            if ($loserMatch->slot_a_user_id && $loserMatch->slot_b_user_id) {
                $loserMatch->update(['status' => TournamentMatch::STATUS_SCHEDULED]);
            }
        }
    }
    
    /**
     * Handle losers bracket progression
     */
    private function handleLosersBracketProgression($match, $tournament)
    {
        $nextRound = $match->round + 1;
        $winnerRounds = ceil(log($tournament->max_players, 2));
        $loserRounds = 2 * $winnerRounds - 1;
        
        // If this is the final losers bracket match, winner goes to finals
        if ($match->round == $loserRounds) {
            // Find the finals match
            $finalsMatch = TournamentMatch::where('tournament_id', $tournament->id)
                ->where('bracket', TournamentMatch::BRACKET_FINALS)
                ->where('match_number', 1)
                ->first();
                
            if ($finalsMatch) {
                $finalsMatch->update([
                    'slot_b_user_id' => $match->winner_user_id,
                    'status' => $finalsMatch->slot_a_user_id ? TournamentMatch::STATUS_SCHEDULED : TournamentMatch::STATUS_PENDING
                ]);
            }
            
            return;
        }
        
        // Find the next losers bracket match
        $nextMatch = TournamentMatch::where('tournament_id', $tournament->id)
            ->where('bracket', TournamentMatch::BRACKET_LOSERS)
            ->where('round', $nextRound)
            ->first();
            
        if ($nextMatch) {
            // In losers bracket, odd rounds receive from winners bracket
            // Even rounds receive from previous losers bracket round
            if ($nextRound % 2 == 0) {
                // Even round - determine slot based on match number
                $isEvenMatch = $match->match_number % 2 == 0;
                
                if ($isEvenMatch) {
                    $nextMatch->update(['slot_b_user_id' => $match->winner_user_id]);
                } else {
                    $nextMatch->update(['slot_a_user_id' => $match->winner_user_id]);
                }
            } else {
                // Odd round - all winners from previous round go to slot A
                $nextMatch->update(['slot_a_user_id' => $match->winner_user_id]);
            }
            
            // If both slots are filled, update status to scheduled
            if ($nextMatch->slot_a_user_id && $nextMatch->slot_b_user_id) {
                $nextMatch->update(['status' => TournamentMatch::STATUS_SCHEDULED]);
            }
        }
    }
    
    /**
     * Handle finals progression
     */
    private function handleFinalsProgression($match, $tournament)
    {
        // If this is the first finals match
        if ($match->match_number == 1) {
            // If winners bracket champion wins, tournament is over
            if ($match->winner_user_id == $match->slot_a_user_id) {
                $tournament->update([
                    'status' => Tournament::STATUS_COMPLETED,
                    'winner_id' => $match->winner_user_id,
                ]);
                
                // Distribute prize money
                $this->distributePrizes($tournament);
            } else {
                // If losers bracket champion wins, go to second finals match
                $secondFinals = TournamentMatch::where('tournament_id', $tournament->id)
                    ->where('bracket', TournamentMatch::BRACKET_FINALS)
                    ->where('match_number', 2)
                    ->first();
                    
                if ($secondFinals) {
                    $secondFinals->update([
                        'slot_a_user_id' => $match->slot_a_user_id,
                        'slot_b_user_id' => $match->slot_b_user_id,
                        'status' => TournamentMatch::STATUS_SCHEDULED
                    ]);
                }
            }
        } else {
            // This is the second finals match, tournament is over
            $tournament->update([
                'status' => Tournament::STATUS_COMPLETED,
                'winner_id' => $match->winner_user_id,
            ]);
            
            // Distribute prize money
            $this->distributePrizes($tournament);
        }
    }
    
    /**
     * Distribute prizes to winners
     */
    private function distributePrizes($tournament)
    {
        // Get tournament winner
        $winner = User::find($tournament->winner_id);
        if (!$winner) return;
        
        // Default prize distribution: 70% to winner, 20% to runner-up, 10% to third place
        $prizePool = $tournament->prize_pool;
        $winnerPrize = $prizePool * 0.7;
        
        // Add prize to winner's wallet
        DB::transaction(function () use ($winner, $tournament, $winnerPrize) {
            // Add to wallet
            $wallet = $winner->wallet;
            $wallet->balance += $winnerPrize;
            $wallet->save();
            
            // Create transaction record
            $winner->transactions()->create([
                'amount' => $winnerPrize,
                'type' => 'tournament_prize',
                'description' => 'Prize for winning tournament: ' . $tournament->name,
                'status' => 'completed',
            ]);
            
            // Create notification
            $winner->notifications()->create([
                'title' => 'Tournament Prize Awarded',
                'content' => "Congratulations! You've received $winnerPrize coins for winning {$tournament->name}",
                'type' => 'prize_awarded',
                'data' => json_encode([
                    'tournament_id' => $tournament->id,
                    'prize_amount' => $winnerPrize,
                ]),
                'is_read' => false,
            ]);
        });
        
        // TODO: Implement runner-up and third place prizes
    }
}
