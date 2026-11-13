<?php

namespace Tests\KnownDefects\Recipes;

use App\Models\Recipe;
use App\Models\RecipeImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Bekannter Defekt F-19: Rezept löschen entfernt die Bilddateien nicht
 * (RecipeController::delete, app/Http/Controllers/RecipeController.php:717-731);
 * die DB-Zeile verschwindet per Kaskade, die Datei bleibt öffentlich abrufbar.
 *
 * Sollverhalten: Nach dem Löschen ist jede Bilddatei des Rezepts vom public-Disk entfernt;
 * Dateien anderer Rezepte bleiben.
 * Gegenpart (Ist-Verhalten): Tests\Feature\Recipes\RecipeCurrentDefectBehaviourTest (…_f19).
 */
#[Group('known-defect')]
#[Group('F-19')]
class RecipeDeleteImageTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    public function test_deleting_recipe_removes_its_image_files(): void
    {
        Storage::fake('public');
        $this->actingAsHost();

        $this->post(route('recipes.store'), $this->recipePayload([
            'image' => $this->fakePng('bild.png'),
        ]))->assertSessionHasNoErrors();
        $recipe = Recipe::sole();
        $path = RecipeImage::sole()->image_path;
        Storage::disk('public')->assertExists($path);

        $other = Recipe::factory()->create();
        Storage::disk('public')->put('recipes/anderes.png', 'x');
        RecipeImage::factory()->create(['recipe_id' => $other->id, 'image_path' => 'recipes/anderes.png']);

        $this->delete(route('recipes.delete', $recipe))->assertRedirect(route('recipes.index'));

        Storage::disk('public')->assertMissing($path);
        Storage::disk('public')->assertExists('recipes/anderes.png');
    }
}
