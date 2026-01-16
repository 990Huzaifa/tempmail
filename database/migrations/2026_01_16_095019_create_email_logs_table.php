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
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained(); // Kaunsa app user hai
            $table->foreignId('temp_alias_id')->constrained(); // Kis alias par mail aayi
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body_html'); // Poori email ka content
            $table->timestamp('received_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
