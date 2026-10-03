<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * The Profile page — every role gets one. Photo, contact info, and
     * (for staff) name are editable; the password form lives here too.
     */
    public function create(Request $request)
    {
        return view('profile', ['user' => $request->user()]);
    }

    /**
     * Save profile info + optional avatar upload. Intern names and IDs
     * stay admin-managed (they mirror the Registrar's roster) — interns
     * edit their email and photo; staff can also fix their own names.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'first_name' => [$user->isIntern() ? 'prohibited' : 'required', 'string', 'max:255'],
            'last_name' => [$user->isIntern() ? 'prohibited' : 'required', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ], [
            'first_name.prohibited' => 'Intern names are managed by the admin.',
            'last_name.prohibited' => 'Intern names are managed by the admin.',
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $validated['avatar_path'] = $request->file('avatar')->store("avatars/{$user->id}", 'public');
        }

        $user->update([
            'email' => $validated['email'],
            ...($user->isIntern() ? [] : [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
            ]),
            ...(array_key_exists('avatar_path', $validated) ? ['avatar_path' => $validated['avatar_path']] : []),
        ]);

        return redirect()->route('profile')->with('status', 'Profile updated.');
    }

    public function destroyAvatar(Request $request)
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return redirect()->route('profile')->with('status', 'Profile photo removed.');
    }
}
