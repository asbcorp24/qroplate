<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceChannel extends Model
{
    protected $fillable = ['device_id_fk','channel','name','status','enabled','current_session_id','current_payment_id','reserved_until','occupied_until','last_device_report_at'];
    protected $casts = ['enabled'=>'boolean','reserved_until'=>'datetime','occupied_until'=>'datetime','last_device_report_at'=>'datetime'];

    public function device(){ return $this->belongsTo(Device::class, 'device_id_fk'); }
    public function tariffs(){ return $this->hasMany(Tariff::class); }
    public function sessions(){ return $this->hasMany(DeviceSession::class); }

    public function effectiveStatus(): string
    {
        if (!$this->enabled) return 'disabled';
        if ($this->status === 'reserved' && $this->reserved_until && $this->reserved_until->isFuture()) return 'reserved';
        if ($this->status === 'running' && $this->occupied_until && $this->occupied_until->isFuture()) return 'running';
        if ($this->status === 'error') return 'error';
        return 'free';
    }
}
