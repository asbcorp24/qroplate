<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceSession extends Model
{
    protected $table = 'sessions';
    protected $fillable = ['session_id','device_channel_id','payment_id_fk','duration_sec','nonce','status','started_at','ends_at','finished_at','last_device_status'];
    protected $casts = ['started_at'=>'datetime','ends_at'=>'datetime','finished_at'=>'datetime','last_device_status'=>'array'];
    public function channel(){ return $this->belongsTo(DeviceChannel::class, 'device_channel_id'); }
    public function payment(){ return $this->belongsTo(Payment::class, 'payment_id_fk'); }
}
