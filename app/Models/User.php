<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'nik', 'phone', 'ktp_path', 'is_approved'])]
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
            'is_approved' => 'boolean',
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function isWarga(): bool
    {
        if ($this->relationLoaded('roles')) {
            return collect($this->roles)->where('name', 'warga')->isNotEmpty();
        }
        return $this->roles()->where('name', 'warga')->exists();
    }

    public function isAdminSurat(): bool
    {
        if ($this->relationLoaded('roles')) {
            return collect($this->roles)->whereIn('name', ['admin_surat', 'Super Admin', 'Admin'])->isNotEmpty();
        }
        return $this->roles()->whereIn('name', ['admin_surat', 'Super Admin', 'Admin'])->exists();
    }
}
