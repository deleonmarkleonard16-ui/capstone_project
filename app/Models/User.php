<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'can_proctor',
        'is_active',
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
            'can_proctor' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function roleLookup(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function getAttribute($key)
    {
        if ($key === 'role') {
            return $this->roleLookup?->slug ?? $this->roleLookup?->name;
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        if ($key === 'role') {
            $role = is_numeric($value)
                ? Role::find($value)
                : Role::query()
                    ->where('slug', strtolower((string) $value))
                    ->orWhere('name', $value)
                    ->first();

            $this->attributes['role_id'] = $role?->id;

            return $this;
        }

        return parent::setAttribute($key, $value);
    }
}
