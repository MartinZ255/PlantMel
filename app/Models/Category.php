<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function recipes()
    {
        return $this->belongsToMany(Recipe::class, 'recipe_categories');
    }

    public function ingredients()
    {
        return $this->belongsToMany(Ingredient::class, 'category_ingredient');
    }
}
