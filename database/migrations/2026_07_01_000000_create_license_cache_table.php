<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_cache', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->text('token')->nullable();
            $table->text('signature')->nullable();
            $table->boolean('is_local')->default(false);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_cache');
    }
};
