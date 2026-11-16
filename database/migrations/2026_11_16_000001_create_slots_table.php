<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slots', function (Blueprint $table): void {
            $table->id();
            // Unix seconds represent absolute instants independently of the DB timezone.
            $table->unsignedBigInteger('starts_at')->unique();
            $table->unsignedBigInteger('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slots');
    }
};
