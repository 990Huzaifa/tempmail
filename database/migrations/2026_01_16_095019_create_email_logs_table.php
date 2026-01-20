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
            $table->unsignedBigInteger('user_id'); // Kaunsa app user hai
            $table->unsignedBigInteger('temp_alias_id'); // Kis alias par mail aayi
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body_html'); // Poori email ka content
            $table->timestamp('received_at');
            $table->timestamp('is_read')->nullable();
            $table->bigInteger('mail_size')->default(0); // Mail ka size in bytes
            $table->foreign('temp_alias_id')->references('id')->on('temp_aliases')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
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
