<?php

namespace App\Services;

use App\Models\RatingDimension;
use App\Models\RecipeRating;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Einzige Bewertung eines Rezepts: Geschmack, 1–5 Sterne (Entscheidung E14).
 *
 * Die Dimension wird über ihren Namen gefunden (config `plantmel.rating_dimension`).
 * Bewertungen anderer Dimensionen bleiben in der Datenbank, werden aber weder
 * angezeigt noch eingerechnet. Fehlt die Dimension, gibt es keine Bewertung.
 */
class TasteRating
{
    public static function dimension(): ?RatingDimension
    {
        $name = config('plantmel.rating_dimension');

        if (! is_string($name) || $name === '') {
            return null;
        }

        return RatingDimension::where('name', $name)->first(['id', 'name', 'description']);
    }

    public static function dimensionId(): ?int
    {
        return self::dimension()?->id;
    }

    /**
     * Für die Formulare: 0 oder 1 Eintrag (id, name, description).
     *
     * @return Collection<int, RatingDimension>
     */
    public static function dimensionsForForm(): Collection
    {
        $dimension = self::dimension();

        return collect($dimension ? [$dimension] : []);
    }

    /**
     * Eager-Load-Einschränkung für `ratings`: nur Geschmack (oder nichts).
     *
     * @return \Closure(HasMany): void
     */
    public static function constrain(?int $dimensionId): \Closure
    {
        return function ($query) use ($dimensionId): void {
            if ($dimensionId === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where('dimension_id', $dimensionId);
        };
    }

    /**
     * Ganzzahliger Wert 0–5 für Listen-Karten (0 = nicht bewertet).
     *
     * @param  Collection<int, RecipeRating>  $ratings
     */
    public static function roundedForList(Collection $ratings, ?int $dimensionId): int
    {
        if ($dimensionId === null) {
            return 0;
        }

        $avg = $ratings->where('dimension_id', $dimensionId)->avg('value');

        return $avg ? (int) round($avg) : 0;
    }
}
