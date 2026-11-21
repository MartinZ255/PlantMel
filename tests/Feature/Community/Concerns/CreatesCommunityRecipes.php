<?php

namespace Tests\Feature\Community\Concerns;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;

/**
 * E18: Testdaten für Community-Rezepte (synthetisch, ohne Factories-Änderung).
 */
trait CreatesCommunityRecipes
{
    protected function communityRecipe(User $author, string $status = Recipe::STATUS_APPROVED, array $attributes = []): Recipe
    {
        $recipe = Recipe::factory()->create($attributes);
        $recipe->forceFill([
            'type' => Recipe::TYPE_COMMUNITY,
            'status' => $status,
            'user_id' => $author->id,
            'submitted_at' => $status === Recipe::STATUS_DRAFT ? null : now(),
            'reviewed_at' => $status === Recipe::STATUS_APPROVED ? now() : null,
        ])->save();

        return $recipe->fresh();
    }

    protected function proposal(string $name, ?User $by = null): Ingredient
    {
        $ingredient = new Ingredient(['name' => $name]);
        $ingredient->forceFill([
            'status' => Ingredient::STATUS_PROPOSED,
            'proposed_by' => $by?->id,
        ])->save();

        return $ingredient;
    }

    protected function useIngredient(Recipe $recipe, Ingredient $ingredient, ?string $amount = null): void
    {
        RecipeIngredient::create([
            'recipe_id' => $recipe->id,
            'ingredient_id' => $ingredient->id,
            'note' => $amount,
        ]);
    }

    /**
     * Gültige Nutzdaten für community.store / community.update.
     *
     * @return array<string, mixed>
     */
    protected function communityPayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Ofen-Kichererbsen',
            'description' => 'Knusprig und schnell.',
            'duration_minutes' => 25,
            'servings' => 2,
            'tags' => [],
            'ingredients' => [['amount' => '200 g', 'name' => 'Kichererbsen']],
            'steps' => ['Rösten.'],
            'notes' => '',
        ], $overrides);
    }

    protected function geminiKey(string $tail = 'abcd'): string
    {
        return 'AIza'.str_repeat('X', 31).$tail;
    }
}
