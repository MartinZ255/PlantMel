<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    protected $fillable = [
        'title',
        'description',
        'instructions',
        'duration_minutes',
        'servings',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'servings'         => 'integer',
    ];

    // Beziehungen

    public function images()
    {
        return $this->hasMany(RecipeImage::class);
    }

    public function recipeIngredients()
    {
        return $this->hasMany(RecipeIngredient::class);
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
}
