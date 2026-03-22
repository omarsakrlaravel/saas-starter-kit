<?php

namespace Wave\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth:sanctum', except: ['login', 'register']),
        ];
    }

    /**
     * Authenticate and return a Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->only(['email', 'password']);

        if (! Auth::attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = Auth::user();
        $expiresAt = now()->addMinutes((int) config('wave.api.auth_token_expires', 60));
        $token = $user->createToken('auth', ['*'], $expiresAt);

        return $this->respondWithToken($token->plainTextToken, $expiresAt);
    }

    /**
     * Log the user out (revoke the current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Register a new user and return a Sanctum token.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), $this->registrationRules())->validate();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'username' => $validated['username'],
            'password' => bcrypt($validated['password']),
        ]);

        $user->notify(new \App\Notifications\WelcomeNotification());

        $expiresAt = now()->addMinutes((int) config('wave.api.auth_token_expires', 60));
        $token = $user->createToken('auth', ['*'], $expiresAt);

        return $this->respondWithToken($token->plainTextToken, $expiresAt);
    }

    /**
     * Get the token array structure.
     */
    protected function respondWithToken(string $token, $expiresAt = null): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('wave.api.auth_token_expires', 60),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function registrationRules(): array
    {
        $minPasswordLength = max(8, (int) config('wave.auth.min_password_length', 8));

        return [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:250|unique:users,username',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => "required|string|min:{$minPasswordLength}|confirmed",
        ];
    }
}
