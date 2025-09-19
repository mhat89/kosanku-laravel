<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('boarding_house_images', function (Blueprint $table) {
            $table->id();
            $table->char('boarding_house_id', 36); // Change to char(36) to match UUID
            $table->string('image_path');
            $table->string('image_name');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('boarding_house_id')->references('id')->on('boarding_houses')->onDelete('cascade');
            $table->index(['boarding_house_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boarding_house_images');
    }
};