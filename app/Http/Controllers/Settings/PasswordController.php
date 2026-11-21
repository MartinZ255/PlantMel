<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    /**
     * Show the user's password settings page.
     */
    public function edit(Request $request): Response
    {
        // E17: Konten aus der Google-Anmeldung haben anfangs kein Passwort;
        // dann zeigt die Seite "Passwort festlegen" ohne aktuelles Passwort.
        return Inertia::render('settings/Password', [
            'hasPassword' => $request->user()->hasPassword(),
        ]);
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $hasPassword = $request->user()->hasPassword();

        $validated = $request->validate([
            // E17: ohne bestehendes Passwort gibt es nichts zu bestätigen;
            // ein mitgeschicktes current_password wird dann ignoriert.
            ...($hasPassword ? ['current_password' => ['required', 'current_password']] : []),
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => $validated['password'],
        ]);

        return back()->with('success', $hasPassword ? 'Passwort geändert.' : 'Passwort festgelegt.');
    }
}
