<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Get all users (admin only).
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with('role');

        if ($request->has('role')) {
            $query->whereHas('role', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json($users);
    }

    /**
     * Get a specific user.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => $user->load('role'),
        ]);
    }

    /**
     * Create a new user (admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user->load('role'),
        ], 201);
    }

    /**
     * Update a user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();

        // Users can only update themselves, unless admin
        if (!$authUser->isAdmin() && $authUser->id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $rules = [
            'first_name' => 'sometimes|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ];

        // Only admins can change roles
        if ($authUser->isAdmin()) {
            $rules['role_id'] = 'sometimes|exists:roles,id';
        }

        // Password update
        if ($request->has('password')) {
            $rules['password'] = 'string|min:8|confirmed';
            $rules['current_password'] = 'required_with:password|current_password';
        }

        $validated = $request->validate($rules);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user->fresh()->load('role'),
        ]);
    }

    /**
     * Update profile picture.
     */
    public function updateProfilePicture(Request $request): JsonResponse
    {
        $request->validate([
            'profile_picture' => 'required|image|max:2048', // 2MB max
        ]);

        $user = $request->user();

        // Delete old profile picture
        if ($user->profile_picture) {
            Storage::disk('public')->delete($user->profile_picture);
        }

        $path = $request->file('profile_picture')->store('profile-pictures', 'public');
        $user->update(['profile_picture' => $path]);

        return response()->json([
            'message' => 'Profile picture updated successfully',
            'user' => $user->fresh()->load('role'),
        ]);
    }

    /**
     * Delete profile picture.
     */
    public function deleteProfilePicture(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->profile_picture) {
            Storage::disk('public')->delete($user->profile_picture);
            $user->update(['profile_picture' => null]);
        }

        return response()->json([
            'message' => 'Profile picture removed successfully',
            'user' => $user->fresh()->load('role'),
        ]);
    }

    /**
     * Delete a user (admin only).
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();

        // Prevent self-deletion
        if ($authUser->id === $user->id) {
            return response()->json(['message' => 'Cannot delete yourself'], 422);
        }

        // Check for open tickets
        if ($user->tickets()->active()->exists()) {
            return response()->json(['message' => 'Cannot delete user with open tickets'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * Get all roles.
     */
    public function roles(): JsonResponse
    {
        $roles = Role::all();
        return response()->json(['roles' => $roles]);
    }
}

