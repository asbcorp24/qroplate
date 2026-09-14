<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = ['device_id','name','type','location','title','subtitle','description','image_url','accent_color','icon','layout','enabled','maintenance_message'];
    protected $casts = ['enabled' => 'boolean'];

    public function channels()
    {
        return $this->hasMany(DeviceChannel::class, 'device_id_fk');
    }
}
