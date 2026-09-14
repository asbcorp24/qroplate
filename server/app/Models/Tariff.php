<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tariff extends Model
{
    protected $fillable = ['device_channel_id','code','title','duration_sec','price','currency','enabled','sort_order'];
    protected $casts = ['enabled'=>'boolean','price'=>'decimal:2'];
    public function channel(){ return $this->belongsTo(DeviceChannel::class, 'device_channel_id'); }
}
