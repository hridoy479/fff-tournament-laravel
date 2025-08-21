<?php

// app/Http/Controllers/Api/TournamentController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TournamentResource;
use App\Models\Tournament;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    public function index()
    {
        $featured = Tournament::with(['game', 'organizer'])
            ->where('status', 'upcoming')
            ->orderBy('start_date', 'asc')
            ->limit(5)
            ->get();

        $ongoing = Tournament::with(['game', 'organizer'])
            ->where('status', 'ongoing')
            ->orderBy('start_date', 'asc')
            ->limit(5)
            ->get();

        return response()->json([
            'featured' => TournamentResource::collection($featured),
            'ongoing' => TournamentResource::collection($ongoing),
        ]);
    }

    public function show($slug)
    {
        $tournament = Tournament::with([
            'game',
            'organizer',
            'teams',
            'matches' => function($query) {
                $query->orderBy('scheduled_time', 'asc');
            },
            'matches.team1',
            'matches.team2',
            'matches.winner'
        ])->where('slug', $slug)->firstOrFail();

        return new TournamentResource($tournament);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'game_id' => 'required|exists:games,id',
            'description' => 'required|string',
            'rules' => 'required|string',
            'prize_pool' => 'required|numeric|min:0',
            'entry_fee' => 'required|numeric|min:0',
            'team_size' => 'required|integer|min:1',
            'total_teams' => 'required|integer|min:2',
            'registration_deadline' => 'required|date|after:now',
            'start_date' => 'required|date|after:registration_deadline',
            'end_date' => 'required|date|after:start_date',
            'bracket_type' => 'required|in:single_elimination,double_elimination',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['slug'] = Str::slug($validated['title']);
        $validated['status'] = 'upcoming';

        $tournament = Tournament::create($validated);

        return new TournamentResource($tournament);
    }
}
