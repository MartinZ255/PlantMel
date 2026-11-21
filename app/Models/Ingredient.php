<?php

namespace App\Models;

use Database\Factories\IngredientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ingredient extends Model
{
    /** @use HasFactory<IngredientFactory> */
    use HasFactory;

    /** E18: Teil des Katalogs (alle Bestandszutaten, Spalten-Default). */
    public const STATUS_APPROVED = 'approved';

    /** E18: Vorschlag aus einem Community-Rezept, noch nicht im Katalog. */
    public const STATUS_PROPOSED = 'proposed';

    /** `status` und `proposed_by` sind bewusst nicht massenzuweisbar (E18). */
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_APPROVED,
    ];

    protected $casts = [
        'proposed_by' => 'integer',
    ];

    public function isProposed(): bool
    {
        return $this->status === self::STATUS_PROPOSED;
    }

    /** E18: nur Katalog-Zutaten (Autocomplete, Zutatensuche, Alternativen). */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), self::STATUS_APPROVED);
    }

    public function scopeProposed(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), self::STATUS_PROPOSED);
    }

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    public function recipeIngredients()
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function alternatives()
    {
        return $this->hasMany(IngredientAlternative::class, 'ingredient_id');
    }

    public function alternativeFor()
    {
        return $this->hasMany(IngredientAlternative::class, 'alternative_ingredient_id');
    }
}
