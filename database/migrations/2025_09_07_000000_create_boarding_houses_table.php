<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('boarding_houses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7);   // ±1cm precision sudah cukup
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('price_month')->nullable(); // harga/bulan (opsional)
            $table->json('facilities')->nullable();             // wifi, ac, etc
            $table->timestamps();

            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boarding_houses');
    }
};
