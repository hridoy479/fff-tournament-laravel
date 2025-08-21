<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BannerController extends Controller
{
    /**
     * Display a listing of the active banners.
     */
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $location = $request->location;
        
        $query = Banner::where('is_active', true)
            ->orderBy('priority', 'desc');
            
        // Filter by location if provided
        if ($location) {
            $query->where('location', $location);
        }
        
        $banners = $query->paginate($perPage);
        
        return response()->json($banners);
    }

    /**
     * Get all banners for admin (including inactive)
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
        $location = $request->location;
        
        $query = Banner::orderBy('created_at', 'desc');
        
        // Filter by status
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }
        
        // Filter by location
        if ($location) {
            $query->where('location', $location);
        }
        
        $banners = $query->paginate($perPage);
        
        return response()->json($banners);
    }

    /**
     * Store a newly created banner.
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
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'link_url' => 'nullable|url',
            'location' => 'required|string|in:home,tournament,game,profile',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'priority' => 'required|integer|min:1|max:10',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Handle image upload
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('banners', 'public');
        }
        
        // Create banner
        $banner = Banner::create([
            'title' => $request->title,
            'description' => $request->description,
            'image_path' => $imagePath,
            'link_url' => $request->link_url,
            'location' => $request->location,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'priority' => $request->priority,
            'is_active' => $request->is_active ?? true,
            'created_by' => Auth::id(),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Banner created successfully',
            'banner' => $banner,
        ], 201);
    }

    /**
     * Display the specified banner.
     */
    public function show(string $id)
    {
        $banner = Banner::findOrFail($id);
        
        // If not admin and banner is inactive, return 404
        if (!$banner->is_active && !Auth::user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Banner not found',
            ], 404);
        }
        
        return response()->json($banner);
    }

    /**
     * Update the specified banner.
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
        
        $banner = Banner::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'link_url' => 'nullable|url',
            'location' => 'string|in:home,tournament,game,profile',
            'start_date' => 'date',
            'end_date' => 'date|after:start_date',
            'priority' => 'integer|min:1|max:10',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Handle image upload if new image is provided
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($banner->image_path) {
                Storage::disk('public')->delete($banner->image_path);
            }
            
            $imagePath = $request->file('image')->store('banners', 'public');
            $banner->image_path = $imagePath;
        }
        
        // Update banner fields
        if ($request->has('title')) $banner->title = $request->title;
        if ($request->has('description')) $banner->description = $request->description;
        if ($request->has('link_url')) $banner->link_url = $request->link_url;
        if ($request->has('location')) $banner->location = $request->location;
        if ($request->has('start_date')) $banner->start_date = $request->start_date;
        if ($request->has('end_date')) $banner->end_date = $request->end_date;
        if ($request->has('priority')) $banner->priority = $request->priority;
        if ($request->has('is_active')) $banner->is_active = $request->is_active;
        
        $banner->updated_by = Auth::id();
        $banner->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Banner updated successfully',
            'banner' => $banner,
        ]);
    }

    /**
     * Toggle banner active status.
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
        
        $banner = Banner::findOrFail($id);
        $banner->is_active = !$banner->is_active;
        $banner->updated_by = Auth::id();
        $banner->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Banner status updated successfully',
            'is_active' => $banner->is_active,
        ]);
    }

    /**
     * Remove the specified banner.
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
        
        $banner = Banner::findOrFail($id);
        
        // Delete image from storage
        if ($banner->image_path) {
            Storage::disk('public')->delete($banner->image_path);
        }
        
        $banner->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Banner deleted successfully',
        ]);
    }
}
