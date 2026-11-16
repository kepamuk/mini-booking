<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            // All rows are active; cancellation deletes the row and releases this key.
            $table->foreignId('slot_id')->unique()->constrained()->restrictOnDelete();
            $table->string('customer_name', 100);
            $table->string('customer_email', 254);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
