<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\ProjectMember;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    private $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Send/create an invitation.
     */
    public function invite(Request $request, Project $project)
    {
        if (!$this->canManageMembers($request->user(), $project)) {
            return response()->json(['message' => 'Unauthorized to manage project members'], 403);
        }

        $validated = $request->validate([
            'email' => 'required|email',
            'role_id' => 'nullable|integer|exists:roles,id',
            'assignment_type' => 'nullable|string|max:50',
            'can_view' => 'nullable|boolean',
            'can_edit' => 'nullable|boolean',
            'can_delete' => 'nullable|boolean',
            'can_approve' => 'nullable|boolean',
            'can_manage_members' => 'nullable|boolean',
        ]);

        $email = strtolower(trim($validated['email']));

        // Check if user is already a member
        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            $isMember = ProjectMember::where('project_id', $project->id)
                ->where('user_id', $existingUser->id)
                ->whereNull('removed_at')
                ->exists();

            if ($isMember) {
                return response()->json(['message' => 'User is already a member of this project.'], 422);
            }
        }

        // Check if there is already a pending invitation for this email on this project
        $existingInvitation = ProjectInvitation::where('project_id', $project->id)
            ->where('email', $email)
            ->where('status', 'pending')
            ->first();

        if ($existingInvitation) {
            return response()->json(['message' => 'An invitation is already pending for this email.'], 422);
        }

        // Create the invitation
        $invitation = ProjectInvitation::create([
            'project_id' => $project->id,
            'email' => $email,
            'role_id' => $validated['role_id'] ?? null,
            'assignment_type' => $validated['assignment_type'] ?? 'member',
            'can_view' => $validated['can_view'] ?? true,
            'can_edit' => $validated['can_edit'] ?? false,
            'can_delete' => $validated['can_delete'] ?? false,
            'can_approve' => $validated['can_approve'] ?? false,
            'can_manage_members' => $validated['can_manage_members'] ?? false,
            'status' => 'pending',
            'invited_by_id' => $request->user()->id,
        ]);

        // Send notification to the user if they exist in system
        if ($existingUser) {
            try {
                $this->notificationService->notifyUser(
                    $existingUser,
                    'project_member_added',
                    "Project invitation: {$project->project_code}",
                    "You have been invited to join the project '{$project->title}' by {$request->user()->full_name}.",
                    $project,
                    'project_status_change',
                    [
                        'user_name' => $existingUser->full_name,
                        'project_title' => $project->title,
                        'old_status' => 'No Membership',
                        'new_status' => 'Invited',
                        'changed_by' => $request->user()->full_name,
                        'reason' => "You have been invited to join the project.",
                    ]
                );
            } catch (\Throwable $e) {
                // Fail silently on notification error
            }
        }

        return response()->json([
            'message' => 'Invitation sent successfully.',
            'invitation' => $invitation->load('role')
        ], 201);
    }

    /**
     * List user's pending invitations.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $invitations = ProjectInvitation::with(['project', 'invitedBy', 'role'])
            ->where('email', strtolower($user->email))
            ->where('status', 'pending')
            ->get();

        return response()->json($invitations);
    }

    /**
     * Accept invitation.
     */
    public function accept(Request $request, ProjectInvitation $invitation)
    {
        $user = $request->user();

        if (strcasecmp($invitation->email, $user->email) !== 0) {
            return response()->json(['message' => 'Unauthorized to accept this invitation.'], 403);
        }

        if ($invitation->status !== 'pending') {
            return response()->json(['message' => 'This invitation has already been processed.'], 422);
        }

        DB::transaction(function () use ($invitation, $user) {
            // Update invitation status
            $invitation->update(['status' => 'accepted']);

            // Create or restore ProjectMember record
            $project = $invitation->project;

            $existingMember = ProjectMember::where('project_id', $project->id)
                ->where('user_id', $user->id)
                ->first();

            $memberData = [
                'role_id' => $invitation->role_id,
                'assignment_type' => $invitation->assignment_type,
                'can_view' => $invitation->can_view,
                'can_edit' => $invitation->can_edit,
                'can_delete' => $invitation->can_delete,
                'can_approve' => $invitation->can_approve,
                'can_manage_members' => $invitation->can_manage_members,
                'assigned_by' => $invitation->invited_by_id,
                'removed_at' => null,
            ];

            if ($existingMember) {
                $existingMember->update($memberData);
            } else {
                $project->members()->create(array_merge($memberData, ['user_id' => $user->id]));
            }

            // Send notification to inviter/project owner
            $inviter = User::find($invitation->invited_by_id);
            if ($inviter) {
                try {
                    $this->notificationService->notifyUser(
                        $inviter,
                        'project_member_added',
                        "Invitation Accepted: {$project->project_code}",
                        "{$user->full_name} has accepted your invitation to join '{$project->title}'.",
                        $project,
                        'project_status_change',
                        [
                            'user_name' => $inviter->full_name,
                            'project_title' => $project->title,
                            'old_status' => 'Pending Invitation',
                            'new_status' => 'Member Joined',
                            'changed_by' => $user->full_name,
                            'reason' => "Invitation accepted.",
                        ]
                    );
                } catch (\Throwable $e) {
                    // Fail silently
                }
            }
        });

        return response()->json(['message' => 'Invitation accepted. You are now a member of this project.']);
    }

    /**
     * Decline invitation.
     */
    public function decline(Request $request, ProjectInvitation $invitation)
    {
        $user = $request->user();

        if (strcasecmp($invitation->email, $user->email) !== 0) {
            return response()->json(['message' => 'Unauthorized to decline this invitation.'], 403);
        }

        if ($invitation->status !== 'pending') {
            return response()->json(['message' => 'This invitation has already been processed.'], 422);
        }

        $invitation->update(['status' => 'declined']);

        // Send notification to inviter/project owner
        $project = $invitation->project;
        $inviter = User::find($invitation->invited_by_id);
        if ($inviter) {
            try {
                $this->notificationService->notifyUser(
                    $inviter,
                    'project_member_added',
                    "Invitation Declined: {$project->project_code}",
                    "{$user->full_name} has declined your invitation to join '{$project->title}'.",
                    $project,
                    'project_status_change',
                    [
                        'user_name' => $inviter->full_name,
                        'project_title' => $project->title,
                        'old_status' => 'Pending Invitation',
                        'new_status' => 'Invitation Declined',
                        'changed_by' => $user->full_name,
                        'reason' => "Invitation declined.",
                    ]
                );
            } catch (\Throwable $e) {
                // Fail silently
            }
        }

        return response()->json(['message' => 'Invitation declined successfully.']);
    }

    private function canManageMembers(?User $user, Project $project): bool
    {
        if (!$user) return false;

        $project->loadMissing('status');
        $isDraftOwnedByAnotherUser = strcasecmp((string) $project->status?->name, 'Draft') === 0
            && (int) $project->created_by !== (int) $user->id;

        if ($isDraftOwnedByAnotherUser) {
            return false;
        }

        if ((int)$project->created_by === (int)$user->id) {
            return true;
        }

        $roleName = strtolower((string) ($user->defaultRole?->name ?? ''));
        $isSuperAdmin = (int)$user->default_role_id === 1 || $roleName === 'superadmin';

        if ($isSuperAdmin) {
            return true;
        }

        // Check if user has specific permissions
        $permissions = [
            'projects.members.manage',
            'project_members.manage',
            'project_member.manage',
            'manage_members',
        ];

        foreach ($permissions as $permission) {
            if ($user->hasPermissionTo($permission)) {
                return true;
            }
        }

        // Check if user is active member with member managing privileges
        $member = $project->members()->where('user_id', $user->id)->whereNull('removed_at')->first();
        if ($member && ($member->can_manage_members || $member->can_edit)) {
            return true;
        }

        return false;
    }
}
