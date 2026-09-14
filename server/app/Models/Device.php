<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = ['owner_admin_id','device_id','name','type','location','title','subtitle','description','image_url','accent_color','icon','layout','enabled','maintenance_message'];
    protected $casts = ['enabled' => 'boolean'];

    public function owner()
    {
        return $this->belongsTo(Admin::class, 'owner_admin_id');
    }

    public function channels()
    {
        return $this->hasMany(DeviceChannel::class, 'device_id_fk');
    }
}
