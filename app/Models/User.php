<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property CarbonInterface|null $subscription_until
 * @property bool $unlimited
 * @property CarbonInterface|null $unlimited_until
 * @property int $id
 * @property string|null $google_id
 * @property string $name
 * @property string $email
 * @property string|null $avatar
 * @property string|null $password hanya untuk akun login manual (dibuat admin)
 * @property string $role pengguna|admin — diberikan lewat panel admin atau `php artisan riset:admin`
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'google_id', 'avatar'])]
#[Hidden(['google_id', 'password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return ['password' => 'hashed', 'subscription_until' => 'datetime', 'unlimited_until' => 'datetime', 'unlimited' => 'boolean'];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
