<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->foreignId('device_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id_fk')->nullable()->constrained('payments')->nullOnDelete();
            $table->unsignedInteger('duration_sec');
            $table->string('nonce')->unique();
            $table->string('status')->default('created');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('last_device_status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('sessions');
    }
};
