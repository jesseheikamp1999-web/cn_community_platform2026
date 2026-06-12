<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'discord_id', 'discord_username',
        'discord_avatar', 'role', 'permissions_locked', 'xp', 'birth_date', 'profile_bio',
        'birthday_visibility', 'birthday_notifications', 'last_seen_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birth_date' => 'date',
            'birthday_notifications' => 'boolean',
            'last_seen_at' => 'datetime',
            'role' => UserRole::class,
            'permissions_locked' => 'boolean',
        ];
    }

    public function getDiscordAvatarUrlAttribute(): ?string
    {
        if (!$this->discord_avatar) {
            return null;
        }

        if (str_starts_with($this->discord_avatar, 'http://') || str_starts_with($this->discord_avatar, 'https://')) {
            return $this->discord_avatar;
        }

        if (!$this->discord_id) {
            return null;
        }

        $extension = str_starts_with($this->discord_avatar, 'a_') ? 'gif' : 'png';

        return sprintf(
            'https://cdn.discordapp.com/avatars/%s/%s.%s?size=256',
            rawurlencode($this->discord_id),
            rawurlencode($this->discord_avatar),
            $extension
        );
    }

    public function nominations(): HasMany
    {
        return $this->hasMany(Nomination::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function absenceRequests(): HasMany
    {
        return $this->hasMany(AbsenceRequest::class);
    }

    public function isCurrentlyAbsent(): bool
    {
        return $this->absenceRequests()
            ->where('status', 'approved')
            ->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today())
            ->exists();
    }

    public function publicPosition(): string
    {
        return $this->staffProfile?->position ?: $this->role->label();
    }

    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class)
            ->withPivot('awarded_at')
            ->withTimestamps();
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->role === UserRole::Owner) {
            return true;
        }

        if (!Schema::hasTable('permissions') || !Schema::hasTable('permission_user')) {
            return false;
        }

        return $this->permissions()->where('name', $permission)->exists();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user');
    }
}
