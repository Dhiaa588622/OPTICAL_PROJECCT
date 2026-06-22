<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('slug', $role)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()
            ->where(function ($query) use ($permission): void {
                $query->where('slug', 'erp-admin')
                    ->orWhereHas('permissions', fn ($permissions) => $permissions->where('slug', $permission));
            })
            ->exists();
    }

    public function homeUrl(): string
    {
        $role = $this->roles()->orderBy('roles.id')->value('slug');

        return match ($role) {
            'cashier' => route('sales.app', ['page' => 'pos']),
            'inventory-officer' => route('inventory.app'),
            'receptionist' => route('appointments.app'),
            'optometrist' => route('patients.app'),
            'accountant' => route('erp.app', ['page' => 'accounting']),
            'whatsapp-agent' => route('whatsapp.app'),
            default => route('erp.dashboard'),
        };
    }

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
        ];
    }
}
