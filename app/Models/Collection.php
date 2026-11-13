<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    /** @use HasFactory<\Database\Factories\CollectionFactory> */
    use HasFactory;

    protected $table = 'collections';

    protected $fillable = [
        'user_id',
        'name',
        'description',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function recipes()
    {
        return $this->belongsToMany(Recipe::class, 'collection_recipes');
    }
}
