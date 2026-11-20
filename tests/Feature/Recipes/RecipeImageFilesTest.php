<?php

namespace Tests\Feature\Recipes;

use App\Models\Recipe;
use App\Models\RecipeImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Feature\Recipes\Concerns\InteractsWithRecipes;
use Tests\TestCase;

/**
 * Regressionstests zu F-19: Bilddateien werden erst nach erfolgreichem
 * Commit gelöscht; bei einem Rollback bleibt die alte Datei erhalten und eine
 * neu hochgeladene wird wieder entfernt. Fehler beim Löschen von Dateien
 * werden geloggt und lassen den Request nicht scheitern.
 *
 * Fehlgeschlagene Transaktionen werden über ein Modell-Event provoziert, das
 * NACH dem Speichern der Datei (RecipeImage::created) eine Ausnahme wirft.
 */
#[Group('F-19')]
class RecipeImageFilesTest extends TestCase
{
    use InteractsWithRecipes;
    use RefreshDatabase;

    private function failWhenImageRecordIsCreated(): void
    {
        RecipeImage::created(function (): void {
            throw new RuntimeException('Simulierter Datenbankfehler nach dem Upload');
        });
    }

    public function test_store_keeps_uploaded_file_on_success(): void
    {
        Storage::fake('public');
        $this->actingAsHost();

        $this->post(route('recipes.store'), $this->recipePayload([
            'image' => $this->fakePng(),
        ]))->assertSessionHasNoErrors();

        Storage::disk('public')->assertExists(RecipeImage::sole()->image_path);
        $this->assertCount(1, Storage::disk('public')->allFiles('recipes'));
    }

    public function test_store_rollback_leaves_no_orphaned_file(): void
    {
        Storage::fake('public');
        $this->actingAsHost();
        $this->failWhenImageRecordIsCreated();

        $this->post(route('recipes.store'), $this->recipePayload([
            'image' => $this->fakePng(),
        ]))->assertServerError();

        $this->assertDatabaseCount('recipes', 0);
        $this->assertDatabaseCount('recipe_images', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('recipes'));
    }

    public function test_update_rollback_keeps_old_file_and_removes_new_upload(): void
    {
        Storage::fake('public');
        $this->actingAsHost();
        $recipe = Recipe::factory()->create(['title' => 'Alter Titel']);
        Storage::disk('public')->put('recipes/alt.png', 'alt');
        $oldImage = RecipeImage::factory()->create(['recipe_id' => $recipe->id, 'image_path' => 'recipes/alt.png']);
        $this->failWhenImageRecordIsCreated();

        $this->post(route('recipes.update', $recipe), $this->recipePayload([
            'name' => 'Neuer Titel',
            'image' => $this->fakePng('neu.png'),
        ]))->assertServerError();

        $this->assertSame('Alter Titel', $recipe->fresh()->title);
        $this->assertSame([$oldImage->id], RecipeImage::pluck('id')->all());
        $this->assertSame(['recipes/alt.png'], Storage::disk('public')->allFiles('recipes'));
    }

    public function test_update_deletes_old_file_only_after_successful_commit(): void
    {
        Storage::fake('public');
        $this->actingAsHost();
        $recipe = Recipe::factory()->create();
        Storage::disk('public')->put('recipes/alt.png', 'alt');
        RecipeImage::factory()->create(['recipe_id' => $recipe->id, 'image_path' => 'recipes/alt.png']);

        $this->post(route('recipes.update', $recipe), $this->recipePayload([
            'image' => $this->fakePng('neu.png'),
        ]))->assertRedirect(route('recipes.show', $recipe));

        $newPath = RecipeImage::sole()->image_path;
        $this->assertSame([$newPath], Storage::disk('public')->allFiles('recipes'));
    }

    public function test_delete_removes_files_but_keeps_paths_still_referenced_elsewhere(): void
    {
        Storage::fake('public');
        $this->actingAsHost();
        $recipe = Recipe::factory()->create();
        $other = Recipe::factory()->create();
        Storage::disk('public')->put('recipes/eigen.png', 'x');
        Storage::disk('public')->put('recipes/geteilt.png', 'y');
        RecipeImage::factory()->create(['recipe_id' => $recipe->id, 'image_path' => 'recipes/eigen.png']);
        RecipeImage::factory()->create(['recipe_id' => $recipe->id, 'image_path' => 'recipes/geteilt.png']);
        RecipeImage::factory()->create(['recipe_id' => $other->id, 'image_path' => 'recipes/geteilt.png']);

        $this->delete(route('recipes.delete', $recipe))->assertRedirect(route('recipes.index'));

        Storage::disk('public')->assertMissing('recipes/eigen.png');
        Storage::disk('public')->assertExists('recipes/geteilt.png');
    }

    public function test_delete_succeeds_and_logs_when_file_deletion_throws(): void
    {
        $this->actingAsHost();
        $recipe = Recipe::factory()->create();
        RecipeImage::factory()->create(['recipe_id' => $recipe->id, 'image_path' => 'recipes/kaputt.png']);

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('delete')->once()->with('recipes/kaputt.png')
            ->andThrow(new RuntimeException('Datenträger nicht erreichbar'));
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);
        Log::spy();

        $this->delete(route('recipes.delete', $recipe))
            ->assertRedirect(route('recipes.index'))
            ->assertSessionHas('success', 'Rezept wurde gelöscht.');

        $this->assertModelMissing($recipe);
        Log::shouldHaveReceived('warning')->once()
            ->withArgs(fn (string $message, array $context) => $context['path'] === 'recipes/kaputt.png');
    }

    public function test_delete_succeeds_and_logs_when_file_deletion_returns_false(): void
    {
        $this->actingAsHost();
        $recipe = Recipe::factory()->create();
        RecipeImage::factory()->create(['recipe_id' => $recipe->id, 'image_path' => 'recipes/weg.png']);

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('delete')->once()->with('recipes/weg.png')->andReturnFalse();
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);
        Log::spy();

        $this->delete(route('recipes.delete', $recipe))->assertRedirect(route('recipes.index'));

        $this->assertModelMissing($recipe);
        Log::shouldHaveReceived('warning')->once();
    }
}
