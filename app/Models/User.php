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
            'password'          => 'hashed',
            'can_proctor'       => 'boolean',
            'is_active'         => 'boolean',
        ];
    }

    public function roleLookup(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Accessor: $user->role always returns the slug of the linked Role row.
     * Uses the already-loaded relation if available, otherwise queries the DB.
     */
    public function getRoleAttribute(): ?string
    {
        // If relation already loaded (eager or lazy), use it directly.
        if ($this->relationLoaded('roleLookup')) {
            return $this->getRelation('roleLookup')?->slug;
        }

        // Otherwise hit the DB with a single lightweight query.
        if ($this->role_id) {
            return $this->roleLookup()->value('slug');
        }

        return null;
    }

    /**
     * Mutator: $user->role = 'staff'  →  sets role_id from the roles table.
     */
    public function setRoleAttribute(mixed $value): void
    {
        if ($value === null) {
            $this->attributes['role_id'] = null;
            return;
        }

        $role = is_numeric($value)
            ? Role::find($value)
            : Role::query()
                ->where('slug', strtolower((string) $value))
                ->orWhere('name', $value)
                ->first();

        $this->attributes['role_id'] = $role?->id;
    }
}
