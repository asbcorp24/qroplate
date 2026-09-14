<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_channel_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('title');
            $table->unsignedInteger('duration_sec');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('RUB');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('tariffs');
    }
};
