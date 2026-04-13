<?php

namespace App\Models;

use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
        'email',
        'password',
        'employee_number',
        'college_id',
        'program_id',
        'is_active',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
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
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->isDirty(['first_name', 'last_name'])) {
                $first = trim((string) ($user->first_name ?? ''));
                $last = trim((string) ($user->last_name ?? ''));
                if ($first !== '' || $last !== '') {
                    $user->name = trim($first.' '.$last);
                }
            }
        });
    }

    public function getFullNameAttribute(): string
    {
        return trim(
            (string) ($this->first_name ?? '').' '.
            ($this->middle_name ? $this->middle_name.' ' : '').
            (string) ($this->last_name ?? '').
            ($this->suffix ? ', '.$this->suffix : '')
        );
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upper($value),
        );
    }

    protected function employeeNumber(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upperNullable($value),
        );
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}
