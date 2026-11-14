<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * `is_host` ist bewusst NICHT enthalten (F-46): Host-Rechte werden nur
     * gezielt per `php artisan user:make-host` (forceFill) vergeben.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * Die Spalten two_factor_* existieren weiter (F-20: 2FA abgeschaltet,
     * keine Migration) und bleiben deshalb verborgen.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'       => 'datetime',
            'password'                => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_host'                 => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Beziehungen
    |--------------------------------------------------------------------------
    */

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function collections()
    {
        return $this->hasMany(Collection::class);
    }
}
