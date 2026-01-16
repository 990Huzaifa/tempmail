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
        Schema::create('email_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_log_id')->constrained('email_logs')->onDelete('cascade');
            $table->string('file_name');
            $table->string('file_path'); // VPS ki storage ka path (e.g., attachments/abc.pdf)
            $table->string('file_type'); // mime type (e.g., application/pdf)
            $table->integer('file_size');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_attachments');
    }
};
