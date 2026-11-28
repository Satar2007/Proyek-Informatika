<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserRoleAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AkunController extends Controller
{
    public function index()
    {
        $users = User::whereIn('role', ['kasir', 'admin', 'owner'])
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        return view('admin.akun.index', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => ['required', Password::defaults()],
            'role'     => 'required|in:kasir,admin,owner',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $user = User::create([
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role'     => $validated['role'],
            ]);

            $this->recordRoleAudit(
                $request,
                $user,
                null,
                (string) $user->role,
                'account_created'
            );
        }, 3);

        return redirect()->back()->with('success', 'Akun berhasil ditambahkan!');
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'password' => ['nullable', Password::defaults()],
            'role'     => 'required|in:kasir,admin,owner',
        ]);

        DB::transaction(function () use ($validated, $user, $request) {
            // Lock all accounts in a deterministic order before role mutation.
            $lockedRoster = User::lockRoleRoster();
            $lockedUser = User::query()->findOrFail($user->id);
            $oldRole = (string) $lockedUser->role;

            if ($oldRole !== $validated['role']) {
                User::assertRoleRemovalAllowed(
                    $oldRole,
                    $lockedRoster,
                    'role'
                );
            }

            $data = [
                'name'  => $validated['name'],
                'email' => $validated['email'],
                'role'  => $validated['role'],
            ];

            if ($request->filled('password')) {
                $data['password'] = Hash::make($validated['password']);
            }

            $lockedUser->update($data);

            if ($oldRole !== (string) $lockedUser->role) {
                $this->recordRoleAudit(
                    $request,
                    $lockedUser,
                    $oldRole,
                    (string) $lockedUser->role,
                    'role_changed'
                );
            }
        }, 3);

        return redirect()->back()->with('success', 'Akun berhasil diupdate!');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()?->id) {
            return redirect()->back()->with('error', 'Tidak bisa hapus akun sendiri!');
        }

        try {
            DB::transaction(function () use ($request, $user) {
                // Write the snapshot first; the target FK becomes NULL when
                // the account is deleted, while the audit record is retained.
                $this->recordRoleAudit(
                    $request,
                    $user,
                    (string) $user->role,
                    null,
                    'account_deleted'
                );
                $user->delete();
            }, 3);
        } catch (ValidationException $e) {
            $errors = $e->errors();

            return redirect()->back()->with(
                'error',
                $errors['account'][0] ?? 'Akun tidak dapat dihapus.'
            );
        }

        return redirect()->back()->with('success', 'Akun berhasil dihapus!');
    }

    private function recordRoleAudit(
        Request $request,
        User $target,
        ?string $oldRole,
        ?string $newRole,
        string $event
    ): void {
        $actor = $request->user();

        UserRoleAudit::create([
            'actor_user_id' => $actor?->id,
            'actor_name' => (string) ($actor?->name ?? 'System'),
            'actor_email' => (string) ($actor?->email ?? ''),
           'actor_role' => $actor?->role,
            'target_user_id' => $target->id,
            'target_name' => (string) $target->name,
            'target_email' => (string) $target->email,
            'event' => $event,
            'old_role' => $oldRole,
            'new_role' => $newRole,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
        ]);
    }
}
