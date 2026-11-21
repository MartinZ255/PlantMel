<?php

namespace App\Policies;

use App\Models\Recipe;
use App\Models\User;

/**
 * E18 / ADR-005: Rechte an Community-Rezepten.
 *
 * Host-Rezepte schützt weiterhin die Middleware `is_host` (Routen in
 * routes/web.php); die Community-Fähigkeiten hier gelten nur für
 * `type = community`. Gäste sind über `?User` erlaubt, wo es passt
 * (Ansehen, Melden).
 */
class RecipePolicy
{
    /** Freigegeben: alle; sonst nur Autor und Host. */
    public function view(?User $user, Recipe $recipe): bool
    {
        return $recipe->isVisibleTo($user);
    }

    /** Eigene Community-Rezepte bearbeiten; der Host alle. */
    public function update(User $user, Recipe $recipe): bool
    {
        return $recipe->isCommunity() && ($user->is_host || $recipe->isAuthoredBy($user));
    }

    public function delete(User $user, Recipe $recipe): bool
    {
        return $this->update($user, $recipe);
    }

    /** Freigeben, ablehnen, ausblenden: nur der Host. */
    public function moderate(User $user, Recipe $recipe): bool
    {
        return $user->is_host && $recipe->isCommunity();
    }

    /** Bewerten: angemeldet, nicht der Autor, nur freigegebene Community-Rezepte. */
    public function rate(User $user, Recipe $recipe): bool
    {
        return $recipe->isCommunity() && $recipe->isApproved() && ! $recipe->isAuthoredBy($user);
    }

    /** Melden: jeder (auch Gäste), nur öffentliche Community-Rezepte. */
    public function report(?User $user, Recipe $recipe): bool
    {
        return $recipe->isCommunity() && $recipe->isApproved();
    }
}
