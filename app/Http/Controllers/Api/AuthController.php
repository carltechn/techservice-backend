<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use App\Mail\VerifyEmail;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $userRole = Role::where('name', Role::USER)->first();

        $user = User::create([
            'role_id' => $userRole->id,
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $verificationUrl = $this->buildVerificationUrl($user);

        Mail::to($user->email)->send(new VerifyEmail($user, $verificationUrl));

        return response()->json([
            'message' => 'Registration successful. Please check your email to activate your account.',
            'user' => $user->load('role'),
            'requires_verification' => true,
        ], 201);
    }

    /**
     * Login user.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($validated)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !$user->hasVerifiedEmail()) {
            if ($user) {
                // Resend verification link on login attempt by unverified user
                $verificationUrl = $this->buildVerificationUrl($user);
                Mail::to($user->email)->send(new VerifyEmail($user, $verificationUrl));
            }

            $exception = ValidationException::withMessages([
                'email' => ['Please verify your email before signing in. A new verification email has been sent.'],
            ]);
            $exception->status = 403;
            throw $exception;
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user->load('role'),
            'token' => $token,
        ]);
    }

    /**
     * Logout user.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->load('role'),
        ]);
    }

    /**
     * Build a signed verification URL using FRONTEND_URL as base.
     */
    private function buildVerificationUrl(User $user): string
    {
        $originalAppUrl = config('app.url');
        $frontendUrl = rtrim(env('FRONTEND_URL', $originalAppUrl), '/');

        // Temporarily force app.url so signature uses the frontend host
        Config::set('app.url', $frontendUrl);
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );
        Config::set('app.url', $originalAppUrl);

        return $url;
    }
}

