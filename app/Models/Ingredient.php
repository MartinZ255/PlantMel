<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    protected $casts = [
    ];

    public function recipeIngredients()
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_ingredient');
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
