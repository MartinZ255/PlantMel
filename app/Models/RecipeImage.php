<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecipeImage extends Model
{
    protected $table = 'recipe_images';

    protected $fillable = [
        'recipe_id',
        'image_path',
    ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }
}
