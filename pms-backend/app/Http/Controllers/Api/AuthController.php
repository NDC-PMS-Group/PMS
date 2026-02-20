<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\UserAgentParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected UserAgentParserService $userAgentParser;

    public function __construct(UserAgentParserService $userAgentParser)
    {
        $this->userAgentParser = $userAgentParser;
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            // Log failed login attempt BEFORE throwing exception
            $this->logFailedLoginAttempt($request, $request->email);
            
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            // Log failed login attempt (inactive account)
            $this->logFailedLoginAttempt($request, $request->email, 'Account is deactivated');
            
            return response()->json(['message' => 'Account is deactivated'], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        $user->update(['last_login' => now()]);

        // Log successful login
        $this->logSuccessfulLogin($request, $user);

        return response()->json([
            'user' => new UserResource($user->load('defaultRole.permissions')),
            'token' => $token,
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'first_name' => 'required',
            'last_name' => 'required',
        ]);

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password_hash' => Hash::make($request->password),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'default_role_id' => 2, // Default to Staff role
            'is_active' => true,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        // Log user registration
        $this->logRegistration($request, $user);

        return response()->json([
            'user' => new UserResource($user->load('defaultRole')),
            'token' => $token,
        ], 201);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        
        // Log logout before deleting token
        $this->logLogout($request, $user);
        
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request)
    {
        return new UserResource(
            $request->user()->load('defaultRole.permissions')
        );
    }

    /**
     * Log successful login
     *
     * @param Request $request
     * @param User $user
     * @return void
     */
    protected function logSuccessfulLogin(Request $request, User $user): void
    {
        try {
            $agentData = $this->userAgentParser->parse($request);

            AuditLog::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'entity_type' => User::class,
                'entity_id' => $user->id,
                'action' => 'login',
                'description' => "{$user->full_name} logged in successfully",
                'ip_address' => $agentData['ip_address'],
                'user_agent' => $agentData['user_agent'],
                'device_type' => $agentData['device_type'],
                'browser' => $agentData['browser'],
                'browser_version' => $agentData['browser_version'],
                'platform' => $agentData['platform'],
                'platform_version' => $agentData['platform_version'],
            ]);
        } catch (\Exception $e) {
            // Log error but don't interrupt the login flow
            \Log::error('Failed to log successful login: ' . $e->getMessage());
        }
    }

    /**
     * Log failed login attempt
     *
     * @param Request $request
     * @param string $email
     * @param string|null $reason
     * @return void
     */
    protected function logFailedLoginAttempt(Request $request, string $email, ?string $reason = null): void
    {
        try {
            $agentData = $this->userAgentParser->parse($request);
            
            $user = User::where('email', $email)->first();
            $description = $reason 
                ? "Failed login attempt for {$email}: {$reason}"
                : "Failed login attempt for {$email}: Invalid credentials";

            // Build the log data - only include entity_id if user exists
            $logData = [
                'email' => $email,
                'entity_type' => User::class,
                'action' => 'login_failed',
                'description' => $description,
                'ip_address' => $agentData['ip_address'],
                'user_agent' => $agentData['user_agent'],
                'device_type' => $agentData['device_type'],
                'browser' => $agentData['browser'],
                'browser_version' => $agentData['browser_version'],
                'platform' => $agentData['platform'],
                'platform_version' => $agentData['platform_version'],
            ];

            // Only add user_id and entity_id if user exists
            if ($user) {
                $logData['user_id'] = $user->id;
                $logData['entity_id'] = $user->id;
            }

            AuditLog::create($logData);
        } catch (\Exception $e) {
            // Log error but don't interrupt the login flow
            \Log::error('Failed to log failed login attempt: ' . $e->getMessage());
        }
    }

    /**
     * Log user logout
     *
     * @param Request $request
     * @param User $user
     * @return void
     */
    protected function logLogout(Request $request, User $user): void
    {
        try {
            $agentData = $this->userAgentParser->parse($request);

            AuditLog::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'entity_type' => User::class,
                'entity_id' => $user->id,
                'action' => 'logout',
                'description' => "{$user->full_name} logged out",
                'ip_address' => $agentData['ip_address'],
                'user_agent' => $agentData['user_agent'],
                'device_type' => $agentData['device_type'],
                'browser' => $agentData['browser'],
                'browser_version' => $agentData['browser_version'],
                'platform' => $agentData['platform'],
                'platform_version' => $agentData['platform_version'],
            ]);
        } catch (\Exception $e) {
            // Log error but don't interrupt the logout flow
            \Log::error('Failed to log logout: ' . $e->getMessage());
        }
    }

    /**
     * Log user registration
     *
     * @param Request $request
     * @param User $user
     * @return void
     */
    protected function logRegistration(Request $request, User $user): void
    {
        try {
            $agentData = $this->userAgentParser->parse($request);

            AuditLog::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'entity_type' => User::class,
                'entity_id' => $user->id,
                'action' => 'register',
                'description' => "{$user->full_name} registered a new account",
                'ip_address' => $agentData['ip_address'],
                'user_agent' => $agentData['user_agent'],
                'device_type' => $agentData['device_type'],
                'browser' => $agentData['browser'],
                'browser_version' => $agentData['browser_version'],
                'platform' => $agentData['platform'],
                'platform_version' => $agentData['platform_version'],
            ]);
        } catch (\Exception $e) {
            // Log error but don't interrupt the registration flow
            \Log::error('Failed to log registration: ' . $e->getMessage());
        }
    }
}