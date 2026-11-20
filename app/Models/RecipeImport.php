<?php

namespace App\Models;

use Database\Factories\RecipeImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeImport extends Model
{
    /** @use HasFactory<RecipeImportFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'url',
        'status',
        'source',
        'error',
        'result',
    ];

    protected $casts = [
        'result' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
