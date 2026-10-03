<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    /**
     * Save a new password from the Profile page. Sets
     * `password_changed_at`, which dismisses the default-password banner.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        // Default passwords are the intern's last name and logins accept
        // them case-insensitively — honor the same rule here.
        $current = $request->input('current_password');
        if ($user->last_name && strcasecmp($current, $user->last_name) === 0) {
            $current = $user->last_name;
        }

        if (! Hash::check($current, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'That is not your current password.',
            ]);
        }

        if (strcasecmp($validated['password'], $current) === 0) {
            throw ValidationException::withMessages([
                'password' => 'Pick something different from your current password.',
            ]);
        }

        $user->forceFill([
            'password' => $validated['password'],
            'password_changed_at' => now(),
        ])->save();

        return redirect()
            ->route('profile')
            ->with('status', 'Password updated — you are all set.');
    }
}
