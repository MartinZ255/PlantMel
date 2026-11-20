<?php

namespace App\Models;

use Database\Factories\RatingDimensionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RatingDimension extends Model
{
    /** @use HasFactory<RatingDimensionFactory> */
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
