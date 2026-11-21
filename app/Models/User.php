<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, MustVerifyEmailTrait, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * `is_host` ist bewusst NICHT enthalten (F-46): Host-Rechte werden nur
     * gezielt per `php artisan user:make-host` (forceFill) vergeben.
     * `google_id` ebenfalls nicht (E17): sie wird nur im Google-Callback per
     * forceFill gesetzt (App\Services\GoogleLogin).
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
        'google_id',
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_host' => 'boolean',
        ];
    }

    /**
     * E17: Konten, die per Google angelegt wurden, haben zunächst kein
     * Passwort (users.password = null).
     */
    public function hasPassword(): bool
    {
        return $this->password !== null && $this->password !== '';
    }

    /*
    |--------------------------------------------------------------------------
    | E-Mail-Verifizierung (F-08, E2)
    |--------------------------------------------------------------------------
    |
    | Abschaltbar per `plantmel.require_email_verification` (Default: aus).
    | Laravel und Fortify werten die Verifizierung ausschliesslich ueber diese
    | beiden Methoden aus:
    | - Middleware `verified` (EnsureEmailIsVerified) und der Listener
    |   SendEmailVerificationNotification (Event Registered) fragen
    |   hasVerifiedEmail();
    | - Fortify (verification.notice/.send/.verify) fragt hasVerifiedEmail()
    |   bzw. ruft sendEmailVerificationNotification() auf.
    | Bei AUS gilt jedes Konto als verifiziert und es wird nie eine
    | Verifizierungsmail verschickt. Die Spalte email_verified_at bleibt davon
    | unberuehrt (wird bei AUS nur nicht ausgewertet).
    |
    */

    public static function emailVerificationRequired(): bool
    {
        return (bool) config('plantmel.require_email_verification', false);
    }

    public function hasVerifiedEmail(): bool
    {
        if (! static::emailVerificationRequired()) {
            return true;
        }

        return ! is_null($this->email_verified_at);
    }

    public function sendEmailVerificationNotification(): void
    {
        if (! static::emailVerificationRequired()) {
            return;
        }

        $this->notify(new VerifyEmail);
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
