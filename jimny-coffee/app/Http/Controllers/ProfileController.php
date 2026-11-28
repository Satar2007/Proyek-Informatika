<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Models\UserRoleAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        try {
            DB::transaction(function () use ($request, $user) {
                User::lockRoleRoster();
                $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                UserRoleAudit::create([
                    'actor_user_id' => $lockedUser->id,
                    'actor_name' => $lockedUser->name,
                    'actor_email' => $lockedUser->email,
                    'actor_role' => $lockedUser->role,
                    'target_user_id' => $lockedUser->id,
                    'target_name' => $lockedUser->name,
                    'target_email' => $lockedUser->email,
                    'event' => 'account_deleted',
                    'old_role' => $lockedUser->role,
                    'new_role' => null,
                    'ip_address' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
                ]);

                if (!$lockedUser->delete()) {
                    throw ValidationException::withMessages([
                        'account' => 'Akun tidak dapat dihapus.',
                    ]);
                }
            }, 3);
        } catch (ValidationException $e) {
            return Redirect::route('profile.edit')
                ->withErrors($e->errors(), 'userDeletion');
        }

        Auth::logoutCurrentDevice();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
