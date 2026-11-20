<?php

namespace Tests\Feature\Import;

use App\Models\Category;
use App\Models\RecipeImport;
use App\Models\User;
use Database\Factories\RecipeImportFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Charakterisierung der Closure-Route `recipes.create` mit `?import={id}`
 * (Vorbelegung des Formulars aus einem TikTok-Import).
 */
class RecipeCreateWithImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_own_done_import_prefills_form_and_maps_tags_case_insensitive(): void
    {
        $host = $this->actingAsHost();
        Category::factory()->create(['name' => 'Vegan']);
        Category::factory()->create(['name' => 'Frühstück']);

        $result = RecipeImportFactory::normalizedResult('https://www.tiktok.com/@x/video/1');
        $result['tags'] = ['vegan', 'FRÜHSTÜCK', 'Unbekannt'];
        $import = RecipeImport::factory()->for($host)->done()->create(['result' => $result]);

        $this->get(route('recipes.create', ['import' => $import->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('recipes/Create')
                ->has('ratingDimensions')
                ->has('categories', 2)
                ->has('ingredients')
                ->where('importedRecipe', function ($imported) use ($result) {
                    $imported = collect($imported)->toArray();

                    // Vorhandene Kategorien in deren Schreibweise, unbekannte
                    // Tags bleiben unverändert erhalten (werden nicht verworfen).
                    $this->assertSame(['Vegan', 'Frühstück', 'Unbekannt'], $imported['tags']);

                    $expected = $result;
                    unset($expected['tags']);
                    unset($imported['tags']);
                    $this->assertSame($expected, $imported);

                    return true;
                })
            );
    }

    public function test_without_import_parameter_imported_recipe_is_null(): void
    {
        $this->actingAsHost();

        $this->get(route('recipes.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('recipes/Create')
                ->where('importedRecipe', null)
            );
    }

    public function test_foreign_done_import_is_not_used(): void
    {
        $foreign = RecipeImport::factory()->for(User::factory()->host())->done()->create();
        $this->actingAsHost();

        $this->get(route('recipes.create', ['import' => $foreign->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('importedRecipe', null));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unfinishedStates(): array
    {
        return [
            'pending' => ['pending'],
            'failed' => ['failed'],
        ];
    }

    #[DataProvider('unfinishedStates')]
    public function test_unfinished_own_import_is_not_used(string $state): void
    {
        $host = $this->actingAsHost();
        $import = RecipeImport::factory()->for($host)->{$state}()->create();

        $this->get(route('recipes.create', ['import' => $import->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('importedRecipe', null));
    }

    public function test_running_own_import_with_result_is_not_used(): void
    {
        $host = $this->actingAsHost();
        $import = RecipeImport::factory()->for($host)->done()->create(['status' => 'analyzing']);

        $this->get(route('recipes.create', ['import' => $import->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('importedRecipe', null));
    }

    public function test_unknown_or_non_numeric_import_parameter_yields_null(): void
    {
        $this->actingAsHost();

        $this->get(route('recipes.create', ['import' => 999999]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('importedRecipe', null));

        $this->get(route('recipes.create', ['import' => 'abc']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('importedRecipe', null));
    }
}
