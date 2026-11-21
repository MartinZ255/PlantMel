<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * E18: Meldung zu einem öffentlichen Community-Rezept (Meldeweg, Art. 16 DSA).
 * Auch Gäste dürfen melden (dann user_id = null). Keine IP-Adresse.
 */
class RecipeReport extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    /** Gründe (Schlüssel => Beschriftung), gleiche Liste im Formular. */
    public const REASONS = [
        'illegal' => 'Rechtswidriger Inhalt',
        'copyright' => 'Urheberrecht oder Bildrechte verletzt',
        'offensive' => 'Beleidigend, hasserfüllt oder diskriminierend',
        'dangerous' => 'Gesundheitlich gefährlich',
        'spam' => 'Spam oder Werbung',
        'other' => 'Etwas anderes',
    ];

    protected $fillable = [
        'recipe_id',
        'user_id',
        'reason',
        'message',
        'contact_email',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_OPEN,
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
