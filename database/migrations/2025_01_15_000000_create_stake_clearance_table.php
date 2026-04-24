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
        Schema::create('stake_clearance', function (Blueprint $table) {
            $table->id();
            $table->text('clearance_cookie');
            $table->text('user_agent');
            $table->timestamp('expires_at');
            $table->string('updated_by')->nullable(); // Email/name of admin who updated
            $table->timestamps();

            // Index for faster lookups
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stake_clearance');
    }
};
