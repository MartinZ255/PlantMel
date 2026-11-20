<?php

namespace App\Models;

use Database\Factories\RecipeImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecipeImage extends Model
{
    /** @use HasFactory<RecipeImageFactory> */
    use HasFactory;

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
