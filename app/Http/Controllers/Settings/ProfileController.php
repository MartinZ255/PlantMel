<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            // F-08/E2: nur bei eingeschalteter Verifizierung. User implementiert
            // MustVerifyEmail immer, deshalb zaehlt der Schalter, nicht das Interface.
            'mustVerifyEmail' => User::emailVerificationRequired(),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        // F-08/E2: neue Adresse bestaetigen lassen. Bei abgeschalteter
        // Verifizierung ist sendEmailVerificationNotification() wirkungslos.
        if ($emailChanged && User::emailVerificationRequired()) {
            $user->sendEmailVerificationNotification();

            return to_route('profile.edit')->with(
                'success',
                'Profil gespeichert. Bitte bestätige deine neue E-Mail-Adresse über den Link, den wir dir geschickt haben.'
            );
        }

        return to_route('profile.edit')->with('success', 'Profil gespeichert.');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // E1: Konto-Löschung endet auf der öffentlichen Landing-Seite.
        return to_route('landing');
    }
}
