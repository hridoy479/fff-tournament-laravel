<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class GameController extends Controller
{
    /**
     * Display a listing of available games.
     */
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $category = $request->category;
        $search = $request->search;
        
        $query = Game::where('is_active', true)
            ->orderBy('name', 'asc');
            
        // Filter by category if provided
        if ($category) {
            $query->where('category', $category);
        }
        
        // Search by name if provided
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }
        
        $games = $query->paginate($perPage);
        
        return response()->json($games);
    }

    /**
     * Get all games for admin (including inactive)
     */
    public function adminIndex(Request $request)
    {
        // Check if user is admin
        if (!Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access',
            ], 403);
        }
        
        $perPage = $request->per_page ?? 10;
        $status = $request->status; // active, inactive, all
        $category = $request->category;
        $search = $request->search;
        
        $query = Game::orderBy('created_at', 'desc');
        
        // Filter by status
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }
        
        // Filter by category
        if ($category) {
            $query->where('category', $category);
        }
        
        // Search by name
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }
        
        $games = $query->paginate($perPage);
        
        return response()->json($games);
    }

    /**
     * Store a newly created game.
     */
    public function store(Request $request)
    {
        // Check if user is admin
        if (!Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access',
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:games',
            'description' => 'required|string',
            'category' => 'required|string|in:action,strategy,sports,puzzle,rpg,shooter,moba,battle-royale,card,other',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'rules' => 'required|string',
            'min_players' => 'required|integer|min:1',
            'max_players' => 'required|integer|min:1|gte:min_players',
            'is_team_based' => 'required|boolean',
            'team_size' => 'required_if:is_team_based,true|nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('games', 'public');
        }
        
        // Create game
        $game = Game::create([
            'name' => $request->name,
            'description' => $request->description,
            'category' => $request->category,
            'image_path' => $imagePath,
            'rules' => $request->rules,
            'min_players' => $request->min_players,
            'max_players' => $request->max_players,
            'is_team_based' => $request->is_team_based,
            'team_size' => $request->team_size,
            'is_active' => $request->is_active ?? true,
            'created_by' => Auth::id(),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Game created successfully',
            'game' => $game,
        ], 201);
    }

    /**
     * Display the specified game.
     */
    public function show(string $id)
    {
        $game = Game::findOrFail($id);
        
        // If not admin and game is inactive, return 404
        if (!$game->is_active && !Auth::check() && !Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Game not found',
            ], 404);
        }
        
        return response()->json($game);
    }

    /**
     * Update the specified game.
     */
    public function update(Request $request, string $id)
    {
        // Check if user is admin
        if (!Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access',
            ], 403);
        }
        
        $game = Game::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255|unique:games,name,' . $id,
            'description' => 'string',
            'category' => 'string|in:action,strategy,sports,puzzle,rpg,shooter,moba,battle-royale,card,other',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'rules' => 'string',
            'min_players' => 'integer|min:1',
            'max_players' => 'integer|min:1|gte:min_players',
            'is_team_based' => 'boolean',
            'team_size' => 'required_if:is_team_based,true|nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Handle image upload if new image is provided
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($game->image_path) {
                Storage::disk('public')->delete($game->image_path);
            }
            
            $imagePath = $request->file('image')->store('games', 'public');
            $game->image_path = $imagePath;
        }
        
        // Update game fields
        if ($request->has('name')) $game->name = $request->name;
        if ($request->has('description')) $game->description = $request->description;
        if ($request->has('category')) $game->category = $request->category;
        if ($request->has('rules')) $game->rules = $request->rules;
        if ($request->has('min_players')) $game->min_players = $request->min_players;
        if ($request->has('max_players')) $game->max_players = $request->max_players;
        if ($request->has('is_team_based')) $game->is_team_based = $request->is_team_based;
        if ($request->has('team_size')) $game->team_size = $request->team_size;
        if ($request->has('is_active')) $game->is_active = $request->is_active;
        
        $game->updated_by = Auth::id();
        $game->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Game updated successfully',
            'game' => $game,
        ]);
    }

    /**
     * Toggle game active status.
     */
    public function toggleStatus(string $id)
    {
        // Check if user is admin
        if (!Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access',
            ], 403);
        }
        
        $game = Game::findOrFail($id);
        $game->is_active = !$game->is_active;
        $game->updated_by = Auth::id();
        $game->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Game status updated successfully',
            'is_active' => $game->is_active,
        ]);
    }

    /**
     * Remove the specified game.
     */
    public function destroy(string $id)
    {
        // Check if user is admin
        if (!Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access',
            ], 403);
        }
        
        $game = Game::findOrFail($id);
        
        // Check if game is used in any tournaments
        if ($game->tournaments()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete game that is used in tournaments',
            ], 422);
        }
        
        // Delete image from storage
        if ($game->image_path) {
            Storage::disk('public')->delete($game->image_path);
        }
        
        $game->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Game deleted successfully',
        ]);
    }
    
    /**
     * Get game categories
     */
    public function categories()
    {
        $categories = [
            'action' => 'Action',
            'strategy' => 'Strategy',
            'sports' => 'Sports',
            'puzzle' => 'Puzzle',
            'rpg' => 'RPG',
            'shooter' => 'Shooter',
            'moba' => 'MOBA',
            'battle-royale' => 'Battle Royale',
            'card' => 'Card',
            'other' => 'Other'
        ];
        
        return response()->json($categories);
    }
}
