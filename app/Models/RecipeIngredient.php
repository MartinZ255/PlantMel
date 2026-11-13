<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecipeIngredient extends Model
{
    /** @use HasFactory<\Database\Factories\RecipeIngredientFactory> */
    use HasFactory;

    protected $table = 'recipe_ingredients';

    protected $fillable = [
        'recipe_id',
        'ingredient_id',
        'quantity',
        'unit',
        'note',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
