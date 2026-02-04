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
        Schema::create('sent_box_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sent_box_id')->constrained('sent_boxes')->onDelete('cascade');
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
        Schema::dropIfExists('sent_box_attachments');
    }
};
