<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['payment_id','reservation_id','device_channel_id','tariff_id','amount','currency','status','provider','provider_payment_id','provider_payload','paid_at'];
    protected $casts = ['provider_payload'=>'array','paid_at'=>'datetime','amount'=>'decimal:2'];
    public function channel(){ return $this->belongsTo(DeviceChannel::class, 'device_channel_id'); }
    public function tariff(){ return $this->belongsTo(Tariff::class); }
    public function session(){ return $this->hasOne(DeviceSession::class, 'payment_id_fk'); }
}
