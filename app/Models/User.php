<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Operations\OperatorSession;
use App\Domain\Operations\Station;
use App\Domain\Sections\Section;
use App\Domain\Services\Service;
use App\Domain\Users\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'is_active',
    'default_section_id',
    'default_station_id',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function defaultSection(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'default_section_id');
    }

    /**
     * @return BelongsTo<Station, $this>
     */
    public function defaultStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'default_station_id');
    }

    /**
     * @return HasMany<OperatorSession, $this>
     */
    public function operatorSessions(): HasMany
    {
        return $this->hasMany(OperatorSession::class, 'operator_id');
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function authorizedServices(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_user')
            ->withTimestamps();
    }

    public function isAuthorizedForService(Service $service): bool
    {
        return $this->authorizedServices()->whereKey($service->getKey())->exists();
    }
}
