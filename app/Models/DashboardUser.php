<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;

class DashboardUser extends Authenticatable implements FilamentUser, HasName
{
    protected $table = 'dashboard_users';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'super_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function getGuardName(): string
    {
        return 'admin';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function getFilamentName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
