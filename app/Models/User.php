<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // Stores owned by this user
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class, 'owner_user_id');
    }

    // Platform administrator
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    // Store owner
    public function isSeller(): bool
    {
        return $this->role === 'seller';
    }

    // Regular buyer
    public function isBuyer(): bool
    {
        return $this->role === 'buyer';
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
