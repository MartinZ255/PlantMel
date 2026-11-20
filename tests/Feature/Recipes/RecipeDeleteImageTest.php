<?php

namespace Tests\Feature\Recipes;

use App\Models\Recipe;
use App\Models\RecipeImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Regressionstest zu F-19 (behoben in Slice 2): Rezept löschen entfernte die
 * Bilddateien nicht; die DB-Zeile verschwand per Kaskade, die Datei blieb
 * öffentlich abrufbar.
 *
 * Sollverhalten: Nach dem Löschen ist jede Bilddatei des Rezepts vom public-Disk entfernt;
 * Dateien anderer Rezepte bleiben. Weitere Fälle: RecipeImageFilesTest.
 */
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
