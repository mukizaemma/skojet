<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmailVerificationMail;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    private function canManageAllUsers(): bool
    {
        return auth()->check() && (
            auth()->user()->isSuperAdmin()
            || strtolower((string) auth()->user()->email) === 'admin@iremetech.com'
        );
    }

    private function ensureManageAllUsersOrAbort(): void
    {
        if (! $this->canManageAllUsers()) {
            abort(403, 'Only a super admin can manage users.');
        }
    }

    public function index()
    {
        $this->ensureManageAllUsersOrAbort();

        $isManager = true;
        $users = User::with('role')->latest()->get();
        // Super Admin is seeded only — not assignable here
        $roles = Role::whereIn('slug', ['admin', 'guest'])->orderBy('name')->get();
        $superAdminRole = Role::where('slug', 'super-admin')->first();

        return view('content-management.users.index', compact('users', 'roles', 'superAdminRole', 'isManager'));
    }

    private function usersListUrl(Request $request): string
    {
        $referer = (string) $request->headers->get('referer');
        if (str_contains($referer, '/content-management/users')) {
            return url('/content-management/users');
        }

        return url('/setting').'#users';
    }

    private function assignableRoleIds(): array
    {
        return Role::whereIn('slug', ['admin', 'guest'])->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function store(Request $request)
    {
        $this->ensureManageAllUsersOrAbort();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role_id' => ['required', 'integer', Rule::in($this->assignableRoleIds())],
            'verify_immediately' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please fix the form and try again.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            return redirect()->to($this->usersListUrl($request))
                ->withErrors($validator, 'user')
                ->withInput($request->except('password'));
        }

        $user = new User();
        $user->name = $request->input('name');
        $user->email = $request->input('email');
        $user->password = Hash::make($request->input('password'));
        $user->role_id = (int) $request->input('role_id');
        $user->user_id = (string) \Ramsey\Uuid\Uuid::uuid4();
        $user->status = 'Active';

        if ($request->boolean('verify_immediately')) {
            $user->email_verified_at = now();
            $user->email_verified_by = auth()->id();
        } else {
            $user->verification_token = Str::random(60);
        }

        $user->save();

        $user = User::with('role')->find($user->id);
        if (! $user) {
            $message = 'The account could not be saved. Please try again.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 500);
            }

            return redirect()->to($this->usersListUrl($request))->with('error', $message);
        }

        $roleName = $user->role->name ?? 'user';
        $message = $user->name.' was added as '.$roleName.'.';
        if (! $user->email_verified_at) {
            try {
                Mail::to($user->email)->send(new EmailVerificationMail($user));
                $message .= ' A verification email was sent.';
            } catch (\Throwable) {
                $message .= ' The verification email could not be sent, so verify the address from this page.';
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
        }

        return redirect()->to($this->usersListUrl($request))->with('success', $message);
    }

    public function update(Request $request, $id)
    {
        $this->ensureManageAllUsersOrAbort();

        $user = User::findOrFail($id);
        $allowedRoleIds = Role::whereIn('slug', ['admin', 'guest'])->pluck('id')->all();
        $superAdminRoleId = Role::where('slug', 'super-admin')->value('id');
        if ($superAdminRoleId && (int) $user->role_id === (int) $superAdminRoleId) {
            $allowedRoleIds[] = (int) $superAdminRoleId;
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'role_id' => ['required', Rule::in($allowedRoleIds)],
            'password' => 'nullable|string|min:8',
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->role_id = $request->role_id;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        $saved = User::with('role')->find($user->id);
        $message = ($saved->name ?? 'User').' was updated.';

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'user' => ['id' => $user->id]]);
        }

        return redirect()->to($this->usersListUrl($request))->with('success', $message);
    }

    public function promoteToAdmin($id)
    {
        $this->ensureManageAllUsersOrAbort();

        $user = User::with('role')->findOrFail($id);
        if ($user->isSuperAdmin()) {
            return redirect()->back()->with('error', 'The super admin account stays as it is.');
        }

        $adminRoleId = Role::where('slug', 'admin')->value('id');
        abort_unless($adminRoleId, 500);

        $user->role_id = $adminRoleId;
        $user->save();

        return redirect()->back()->with('success', $user->name.' is now an Admin and will open the hotel dashboard.');
    }

    public function verifyEmail($id)
    {
        $this->ensureManageAllUsersOrAbort();

        $user = User::findOrFail($id);
        $user->email_verified_at = now();
        $user->email_verified_by = auth()->id();
        $user->verification_token = null;
        $user->save();

        return response()->json(['success' => true, 'message' => 'Email verified successfully']);
    }

    public function resendVerification($id)
    {
        $this->ensureManageAllUsersOrAbort();

        $user = User::findOrFail($id);
        
        if (!$user->verification_token) {
            $user->verification_token = Str::random(60);
            $user->save();
        }

        Mail::to($user->email)->send(new EmailVerificationMail($user));

        return response()->json(['success' => true, 'message' => 'Verification email sent successfully']);
    }

    public function destroy($id)
    {
        $this->ensureManageAllUsersOrAbort();

        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['success' => true, 'message' => 'User deleted successfully']);
    }

    public function show($id)
    {
        $this->ensureManageAllUsersOrAbort();

        $user = User::with('role')->findOrFail($id);
        return response()->json($user);
    }

    /**
     * Reset user password without requiring the old password
     * Super Admin can reset any user's password
     */
    public function resetPassword(Request $request, $id)
    {
        $this->ensureManageAllUsersOrAbort();

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::findOrFail($id);
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'success' => true, 
            'message' => 'Password reset successfully. The user can now login with the new password.'
        ]);
    }
}
