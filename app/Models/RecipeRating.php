<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecipeRating extends Model
{
    /** @use HasFactory<\Database\Factories\RecipeRatingFactory> */
    use HasFactory;

    protected $table = 'recipe_ratings';

    protected $fillable = [
        'recipe_id',
        'dimension_id',
        'value',
    ];

    protected $casts = [
        'value' => 'integer',
    ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function dimension()
    {
        return $this->belongsTo(RatingDimension::class, 'dimension_id');
    }
}
