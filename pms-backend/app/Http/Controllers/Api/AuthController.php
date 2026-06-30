<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Role;
use App\Models\AuditLog;
use App\Models\ProponentRegistrationDocument;
use App\Services\NotificationService;
use App\Services\UserAgentParserService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
            $this->logFailedLoginAttempt($request, $request->email, 'Account is pending approval or deactivated');
            
            return response()->json(['message' => 'Account is pending admin approval or deactivated'], 403);
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
            'username' => 'nullable|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
            'first_name' => 'required',
            'last_name' => 'required',
            'phone_number' => 'required|string|max:20',
            'organization_name' => 'required|string|max:255',
            'organization_type' => 'required|string|max:80',
            'organization_registration_no' => 'required|string|max:255',
            'proponent_profile' => 'nullable|array',
            'proponent_profile.business_summary' => 'nullable|string|max:5000',
            'proponent_profile.project_experience' => 'nullable|string|max:5000',
            'proponent_profile.previous_projects' => 'nullable|string|max:12000',
            'proponent_profile.major_clients' => 'nullable|string|max:5000',
            'proponent_profile.certifications' => 'nullable|string|max:5000',
            'previous_projects' => 'nullable|array',
            'previous_projects.*.title' => 'required|string|max:255',
            'previous_projects.*.description' => 'nullable|string',
            'previous_projects.*.client_partner' => 'nullable|string|max:255',
            'previous_projects.*.project_value' => 'nullable|string|max:255',
            'previous_projects.*.start_date' => 'nullable|date',
            'previous_projects.*.end_date' => 'nullable|date',
            'previous_projects.*.status' => 'nullable|string|max:255',
            'address' => 'required|string|max:1000',
            'authority_confirmed' => 'accepted',
            'registration_document' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg,webp|max:10240',
            'authorization_document' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg,webp|max:10240',
            'company_profile_document' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg,webp|max:10240',
        ]);

        $proponentRoleId = Role::where('name', 'Proponent')->value('id') ?? 7;
        $username = $request->username ?: strtolower(strtok($request->email, '@')) . '-' . substr(md5($request->email), 0, 6);

        $user = DB::transaction(function () use ($request, $username, $proponentRoleId) {
            $user = User::create([
                'username' => $username,
                'email' => $request->email,
                'password_hash' => Hash::make($request->password),
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone_number' => $request->phone_number,
                'address' => $request->address,
                'organization_name' => $request->organization_name,
                'organization_type' => $request->organization_type,
                'organization_registration_no' => $request->organization_registration_no,
                'proponent_profile' => $request->input('proponent_profile', []),
                'department' => $request->organization_name,
                'position' => 'External Proponent Representative',
                'default_role_id' => $proponentRoleId,
                'is_active' => false,
            ]);

            if ($request->has('previous_projects') && is_array($request->previous_projects)) {
                foreach ($request->previous_projects as $proj) {
                    $user->previousProjects()->create([
                        'title' => $proj['title'],
                        'description' => $proj['description'] ?? null,
                        'client_partner' => $proj['client_partner'] ?? null,
                        'project_value' => $proj['project_value'] ?? null,
                        'start_date' => $proj['start_date'] ?? null,
                        'end_date' => $proj['end_date'] ?? null,
                        'status' => $proj['status'] ?? 'Completed',
                    ]);
                }
            }

            $this->storeRegistrationDocuments($request, $user);

            return $user;
        });

        // Log user registration
        $this->logRegistration($request, $user);

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            Log::warning('Email verification notification failed.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            $administrators = User::query()
                ->where('is_active', true)
                ->whereIn('default_role_id', [1, 2])
                ->get();

            app(NotificationService::class)->notifyUsers(
                $administrators,
                'account_registered',
                'New proponent registration',
                "{$user->full_name} from {$user->organization_name} registered and is waiting for account approval. Required registration and representative authorization documents were uploaded.",
                $user->load('registrationDocuments')
            );
        } catch (\Throwable $exception) {
            Log::warning('Account registration notification failed.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Registration submitted. An NDC administrator must approve the account before sign in.',
            'user' => new UserResource($user->load(['defaultRole', 'registrationDocuments'])),
        ], 201);
    }

    private function storeRegistrationDocuments(Request $request, User $user): void
    {
        $documents = [
            'registration_document' => [
                'document_type' => 'registration_proof',
                'title' => 'Business registration proof',
            ],
            'authorization_document' => [
                'document_type' => 'representative_authorization',
                'title' => 'Representative authorization',
            ],
            'company_profile_document' => [
                'document_type' => 'company_profile',
                'title' => 'Company profile / capability statement',
            ],
        ];

        foreach ($documents as $input => $metadata) {
            if (!$request->hasFile($input)) {
                continue;
            }

            $file = $request->file($input);
            $path = $file->store("registration-documents/{$user->id}", 'public');

            ProponentRegistrationDocument::create([
                'user_id' => $user->id,
                'document_type' => $metadata['document_type'],
                'title' => $metadata['title'],
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'file_type' => $file->getMimeType(),
                'review_status' => 'pending',
                'uploaded_at' => now(),
            ]);
        }
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

    public function verifyEmail(Request $request, int $id, string $hash)
    {
        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid email verification link.'], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email address is already verified.']);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'message' => 'Email address verified. Your account still requires NDC admin approval before sign in.',
        ]);
    }

    public function resendEmailVerification(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email address is already verified.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link sent.']);
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
