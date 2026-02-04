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
        Schema::create('temp_alias_forwardings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('temp_alias_id');
            $table->longText('recipients'); // User ki personal email
            $table->boolean('is_active')->default(true);
            $table->foreign('temp_alias_id')->references('id')->on('temp_aliases')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temp_alias_forwardings');
    }
};
