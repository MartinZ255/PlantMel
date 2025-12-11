<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngredientAlternative extends Model
{
    protected $table = 'ingredient_alternatives';

    protected $fillable = [
        'recipe_id',
        'ingredient_id',
        'alternative_ingredient_id',
        'note',
    ];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    public function alternativeIngredient()
    {
        return $this->belongsTo(Ingredient::class, 'alternative_ingredient_id');
    }

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }
}
