<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RatingDimension extends Model
{
    /** @use HasFactory<\Database\Factories\RatingDimensionFactory> */
    use HasFactory;

    protected $table = 'rating_dimensions';

    protected $fillable = [
        'name',
        'description',
    ];

    public function recipeRatings()
    {
        return $this->hasMany(RecipeRating::class, 'dimension_id');
    }
}
