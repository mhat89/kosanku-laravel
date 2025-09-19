<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('boarding_houses', function (Blueprint $table) {
            $table->index('name');
            $table->index('address');
        });
    }
    public function down(): void
    {
        Schema::table('boarding_houses', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['address']);
        });
    }
};
