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
        Schema::create('inventory_counts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id')->nullable(); // For now nullable, or foreign key if sessions table exists
            $table->unsignedBigInteger('asset_id'); // FK to assets table
            $table->string('actual_asset_id')->nullable();
            $table->string('actual_serial')->nullable();
            $table->string('status')->default('Match');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_counts');
    }
};
