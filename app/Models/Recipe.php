<?php

namespace App\Models;

use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

    /** E18: Rezeptbuch des Hosts (alle Bestandsrezepte, Spalten-Default). */
    public const TYPE_HOST = 'host';

    /** E18: von einem Nutzer eingereichtes Community-Rezept. */
    public const TYPE_COMMUNITY = 'community';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * `type`, `status`, `user_id` und die Prüf-Felder sind bewusst NICHT
     * massenzuweisbar (E18): Sie werden nur gezielt im Controller bzw. in
     * App\Services\CommunityRecipes gesetzt.
     */
    protected $fillable = [
        'title',
        'description',
        'instructions',
        'duration_minutes',
        'servings',
    ];

    /**
     * Gleiche Werte wie die Spalten-Defaults, damit neue Instanzen sie schon
     * vor dem Speichern kennen (z. B. isCommunity()).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => self::TYPE_HOST,
        'status' => self::STATUS_APPROVED,
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'servings' => 'integer',
        'user_id' => 'integer',
        'reviewed_by' => 'integer',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    // E18: Typ und Sichtbarkeit

    public function isCommunity(): bool
    {
        return $this->type === self::TYPE_COMMUNITY;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isAuthoredBy(?User $user): bool
    {
        return $user !== null && $this->user_id !== null && (int) $this->user_id === (int) $user->id;
    }

    /**
     * Host-Rezepte und freigegebene Community-Rezepte sieht jeder; andere
     * Community-Rezepte nur der Autor und der Host.
     */
    public function isVisibleTo(?User $user): bool
    {
        if (! $this->isCommunity() || $this->isApproved()) {
            return true;
        }

        return $user !== null && ($user->is_host || $this->isAuthoredBy($user));
    }

    /** Nur das Rezeptbuch des Hosts (Liste, Startseite, Zutatensuche). */
    public function scopeHostRecipes(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('type'), self::TYPE_HOST);
    }

    /** Freigegebene Community-Rezepte (öffentliche Community-Liste). */
    public function scopePublishedCommunity(Builder $query): Builder
    {
        return $query
            ->where($query->qualifyColumn('type'), self::TYPE_COMMUNITY)
            ->where($query->qualifyColumn('status'), self::STATUS_APPROVED);
    }

    /** Gleiche Regel wie isVisibleTo(), als Abfrage (z. B. Sammlungen). */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->is_host) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where($q->qualifyColumn('type'), self::TYPE_HOST)
                ->orWhere($q->qualifyColumn('status'), self::STATUS_APPROVED);

            if ($user !== null) {
                $q->orWhere($q->qualifyColumn('user_id'), $user->id);
            }
        });
    }

    // Beziehungen

    public function images()
    {
        return $this->hasMany(RecipeImage::class);
    }

    public function recipeIngredients()
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function ingredientAlternatives()
    {
        return $this->hasMany(IngredientAlternative::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'recipe_categories');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function ratings()
    {
        return $this->hasMany(RecipeRating::class);
    }

    public function collections()
    {
        return $this->belongsToMany(Collection::class, 'collection_recipes');
    }

    /** E18: Autor eines Community-Rezepts (Host-Rezepte: null). */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** E18: Geschmacks-Bewertungen der Nutzer (nur Community-Rezepte). */
    public function userRatings(): HasMany
    {
        return $this->hasMany(RecipeUserRating::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(RecipeReport::class);
    }
}
