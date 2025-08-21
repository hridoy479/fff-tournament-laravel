<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Match;
use App\Models\Tournament;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    /**
     * Get chat messages for a match
     */
    public function matchChat(Request $request, string $matchId)
    {
        $perPage = $request->per_page ?? 50;
        $match = Match::findOrFail($matchId);
        
        // Check if user is a participant in the match
        $user = Auth::user();
        $isParticipant = false;
        
        if ($match->player1_id == $user->id || $match->player2_id == $user->id) {
            $isParticipant = true;
        } else {
            // Check if user is in one of the teams
            $team1 = Team::find($match->team1_id);
            $team2 = Team::find($match->team2_id);
            
            if (($team1 && $team1->members()->where('user_id', $user->id)->exists()) ||
                ($team2 && $team2->members()->where('user_id', $user->id)->exists())) {
                $isParticipant = true;
            }
        }
        
        // If not a participant, check if user is admin
        if (!$isParticipant && !$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this chat',
            ], 403);
        }
        
        $messages = Chat::where('match_id', $matchId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
            
        return response()->json($messages);
    }

    /**
     * Get chat messages for a tournament
     */
    public function tournamentChat(Request $request, string $tournamentId)
    {
        $perPage = $request->per_page ?? 50;
        $tournament = Tournament::findOrFail($tournamentId);
        
        $messages = Chat::where('tournament_id', $tournamentId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
            
        return response()->json($messages);
    }

    /**
     * Send a chat message
     */
    public function sendMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:500',
            'match_id' => 'nullable|exists:matches,id',
            'tournament_id' => 'nullable|exists:tournaments,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Either match_id or tournament_id must be provided
        if (!$request->match_id && !$request->tournament_id) {
            return response()->json([
                'success' => false,
                'message' => 'Either match_id or tournament_id must be provided',
            ], 422);
        }
        
        $user = Auth::user();
        
        // If sending to match chat, check if user is a participant
        if ($request->match_id) {
            $match = Match::findOrFail($request->match_id);
            $isParticipant = false;
            
            if ($match->player1_id == $user->id || $match->player2_id == $user->id) {
                $isParticipant = true;
            } else {
                // Check if user is in one of the teams
                $team1 = Team::find($match->team1_id);
                $team2 = Team::find($match->team2_id);
                
                if (($team1 && $team1->members()->where('user_id', $user->id)->exists()) ||
                    ($team2 && $team2->members()->where('user_id', $user->id)->exists())) {
                    $isParticipant = true;
                }
            }
            
            // If not a participant, check if user is admin
            if (!$isParticipant && !$user->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to send messages in this chat',
                ], 403);
            }
        }
        
        // Create the chat message
        $chat = Chat::create([
            'user_id' => $user->id,
            'match_id' => $request->match_id,
            'tournament_id' => $request->tournament_id,
            'message' => $request->message,
        ]);
        
        // In a real implementation, we would broadcast the message via Laravel Echo here
        // event(new NewChatMessage($chat));
        
        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully',
            'chat' => $chat,
        ]);
    }

    /**
     * Delete a chat message
     */
    public function destroy(string $id)
    {
        $user = Auth::user();
        $chat = Chat::findOrFail($id);
        
        // Only the message author or an admin can delete a message
        if ($chat->user_id != $user->id && !$user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this message',
            ], 403);
        }
        
        $chat->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Message deleted successfully',
        ]);
    }

    /**
     * Report a chat message
     */
    public function reportMessage(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $chat = Chat::findOrFail($id);
        $user = Auth::user();
        
        // Update the chat message to mark it as reported
        $chat->update([
            'reported' => true,
            'reported_by' => $user->id,
            'report_reason' => $request->reason,
        ]);
        
        // In a real implementation, we would create a notification for admins here
        
        return response()->json([
            'success' => true,
            'message' => 'Message reported successfully',
        ]);
    }
}
