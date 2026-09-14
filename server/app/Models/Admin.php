<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Admin extends Model
{
    protected $fillable = ['name', 'login', 'password', 'enabled', 'last_login_at'];
    protected $hidden = ['password'];
    protected $casts = ['enabled' => 'boolean', 'last_login_at' => 'datetime'];

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'owner_admin_id');
    }
}
