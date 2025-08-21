<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Models\TeamMember;
use App\Models\Notification;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Team::query();
        
        // Filter by name
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }
        
        // Filter by game
        if ($request->has('game_id')) {
            $query->where('game_id', $request->game_id);
        }
        
        // Filter by user's teams
        if ($request->has('my_teams') && $request->my_teams) {
            $user = Auth::user();
            $teamIds = $user->teamMembers()->pluck('team_id');
            $query->whereIn('id', $teamIds);
        }
        
        // Sort by created date
        $query->orderBy('created_at', 'desc');
        
        // Paginate results
        $perPage = $request->per_page ?? 15;
        $teams = $query->with(['members.user', 'game'])->paginate($perPage);
        
        return response()->json($teams);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50|unique:teams',
            'logo' => 'nullable|string',
            'description' => 'nullable|string|max:500',
            'game_id' => 'required|exists:games,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $user = Auth::user();
        
        // Check if user already has a team for this game
        $existingTeam = $user->teamMembers()
            ->whereHas('team', function($query) use ($request) {
                $query->where('game_id', $request->game_id);
            })
            ->where('role', TeamMember::ROLE_CAPTAIN)
            ->exists();
            
        if ($existingTeam) {
            return response()->json([
                'message' => 'You already have a team for this game. You can only be captain of one team per game.'
            ], 400);
        }
        
        // Create team
        $team = Team::create([
            'name' => $request->name,
            'logo' => $request->logo,
            'description' => $request->description,
            'game_id' => $request->game_id,
            'invite_code' => Str::random(8),
        ]);
        
        // Add user as team captain
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => TeamMember::ROLE_CAPTAIN,
            'joined_at' => now(),
        ]);
        
        return response()->json([
            'message' => 'Team created successfully',
            'team' => $team->load(['members.user', 'game']),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $team = Team::with(['members.user', 'game'])->findOrFail($id);
        
        return response()->json($team);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $team = Team::findOrFail($id);
        
        // Check if user is team captain
        $user = Auth::user();
        $isCaptain = $team->members()
            ->where('user_id', $user->id)
            ->where('role', TeamMember::ROLE_CAPTAIN)
            ->exists();
            
        if (!$isCaptain && !$user->hasRole('admin')) {
            return response()->json([
                'message' => 'Only team captains can update team details'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:50|unique:teams,name,' . $id,
            'logo' => 'nullable|string',
            'description' => 'nullable|string|max:500',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Update team
        $team->update($request->only(['name', 'logo', 'description']));
        
        return response()->json([
            'message' => 'Team updated successfully',
            'team' => $team->fresh(['members.user', 'game']),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $team = Team::findOrFail($id);
        
        // Check if user is team captain
        $user = Auth::user();
        $isCaptain = $team->members()
            ->where('user_id', $user->id)
            ->where('role', TeamMember::ROLE_CAPTAIN)
            ->exists();
            
        if (!$isCaptain && !$user->hasRole('admin')) {
            return response()->json([
                'message' => 'Only team captains can delete teams'
            ], 403);
        }
        
        // Check if team is registered for any active tournaments
        $activeRegistrations = $team->tournamentEntries()
            ->whereHas('tournament', function($query) {
                $query->whereIn('status', [
                    Tournament::STATUS_REGISTRATION_OPEN,
                    Tournament::STATUS_REGISTRATION_CLOSED,
                    Tournament::STATUS_IN_PROGRESS
                ]);
            })
            ->exists();
            
        if ($activeRegistrations) {
            return response()->json([
                'message' => 'Cannot delete team that is registered for active tournaments'
            ], 400);
        }
        
        // Delete team and all related records
        DB::transaction(function() use ($team) {
            // Delete team members
            $team->members()->delete();
            
            // Delete team
            $team->delete();
        });
        
        return response()->json([
            'message' => 'Team deleted successfully'
        ]);
    }

    /**
     * Invite a player to join the team
     */
    public function invitePlayer(Request $request, string $id)
    {
        $team = Team::findOrFail($id);
        
        // Check if user is team captain
        $user = Auth::user();
        $isCaptain = $team->members()
            ->where('user_id', $user->id)
            ->where('role', TeamMember::ROLE_CAPTAIN)
            ->exists();
            
        if (!$isCaptain) {
            return response()->json([
                'message' => 'Only team captains can invite players'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Get invited user
        $invitedUser = User::where('email', $request->email)->first();
        
        // Check if user is already a member
        $existingMember = $team->members()
            ->where('user_id', $invitedUser->id)
            ->exists();
            
        if ($existingMember) {
            return response()->json([
                'message' => 'User is already a member of this team'
            ], 400);
        }
        
        // Check if team is full (max 5 members)
        $memberCount = $team->members()->count();
        if ($memberCount >= 5) {
            return response()->json([
                'message' => 'Team is already full (maximum 5 members)'
            ], 400);
        }
        
        // Create notification for invited user
        Notification::create([
            'user_id' => $invitedUser->id,
            'title' => 'Team Invitation',
            'content' => 'You have been invited to join team ' . $team->name,
            'type' => 'team_invite',
            'data' => json_encode([
                'team_id' => $team->id,
                'team_name' => $team->name,
                'invite_code' => $team->invite_code,
                'invited_by' => $user->name,
            ]),
            'is_read' => false,
        ]);
        
        return response()->json([
            'message' => 'Invitation sent successfully'
        ]);
    }
    
    /**
     * Join a team using invite code
     */
    public function joinTeam(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invite_code' => 'required|string|exists:teams,invite_code',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $team = Team::where('invite_code', $request->invite_code)->first();
        $user = Auth::user();
        
        // Check if user is already a member
        $existingMember = $team->members()
            ->where('user_id', $user->id)
            ->exists();
            
        if ($existingMember) {
            return response()->json([
                'message' => 'You are already a member of this team'
            ], 400);
        }
        
        // Check if team is full (max 5 members)
        $memberCount = $team->members()->count();
        if ($memberCount >= 5) {
            return response()->json([
                'message' => 'Team is already full (maximum 5 members)'
            ], 400);
        }
        
        // Add user as team member
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => TeamMember::ROLE_MEMBER,
            'joined_at' => now(),
        ]);
        
        // Notify team captain
        $captain = $team->members()
            ->where('role', TeamMember::ROLE_CAPTAIN)
            ->first()
            ->user;
            
        Notification::create([
            'user_id' => $captain->id,
            'title' => 'New Team Member',
            'content' => $user->name . ' has joined your team ' . $team->name,
            'type' => 'team_join',
            'data' => json_encode([
                'team_id' => $team->id,
                'team_name' => $team->name,
                'user_id' => $user->id,
                'user_name' => $user->name,
            ]),
            'is_read' => false,
        ]);
        
        return response()->json([
            'message' => 'Successfully joined team',
            'team' => $team->fresh(['members.user', 'game']),
        ]);
    }
    
    /**
     * Remove a member from the team
     */
    public function removeMember(Request $request, string $id, string $userId)
    {
        $team = Team::findOrFail($id);
        $user = Auth::user();
        
        // Check if user is team captain or the member being removed
        $isCaptain = $team->members()
            ->where('user_id', $user->id)
            ->where('role', TeamMember::ROLE_CAPTAIN)
            ->exists();
            
        $isSelf = ($user->id == $userId);
            
        if (!$isCaptain && !$isSelf && !$user->hasRole('admin')) {
            return response()->json([
                'message' => 'Only team captains can remove members'
            ], 403);
        }
        
        // Cannot remove captain unless it's an admin
        $memberToRemove = $team->members()->where('user_id', $userId)->first();
        
        if (!$memberToRemove) {
            return response()->json([
                'message' => 'User is not a member of this team'
            ], 404);
        }
        
        if ($memberToRemove->role === TeamMember::ROLE_CAPTAIN && !$user->hasRole('admin')) {
            return response()->json([
                'message' => 'Cannot remove team captain'
            ], 400);
        }
        
        // Remove member
        $memberToRemove->delete();
        
        return response()->json([
            'message' => 'Team member removed successfully',
            'team' => $team->fresh(['members.user', 'game']),
        ]);
    }
}
