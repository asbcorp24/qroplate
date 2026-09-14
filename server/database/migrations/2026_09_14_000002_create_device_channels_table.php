<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('device_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id_fk')->constrained('devices')->cascadeOnDelete();
            $table->unsignedTinyInteger('channel');
            $table->string('name');
            $table->string('status')->default('free');
            $table->boolean('enabled')->default(true);
            $table->string('current_session_id')->nullable();
            $table->string('current_payment_id')->nullable();
            $table->timestamp('reserved_until')->nullable();
            $table->timestamp('occupied_until')->nullable();
            $table->timestamp('last_device_report_at')->nullable();
            $table->timestamps();
            $table->unique(['device_id_fk', 'channel']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('device_channels');
    }
};
