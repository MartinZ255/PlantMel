<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * E18: Geschmacks-Bewertung (1–5) eines Community-Rezepts durch einen
 * angemeldeten Nutzer (nicht den Autor). Je Nutzer und Rezept eine Zeile.
 */
class RecipeUserRating extends Model
{
    protected $table = 'recipe_user_ratings';

    protected $fillable = [
        'recipe_id',
        'user_id',
        'value',
    ];

    protected $casts = [
        'value' => 'integer',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
