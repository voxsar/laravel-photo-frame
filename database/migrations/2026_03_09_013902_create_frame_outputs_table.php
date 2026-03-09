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
        Schema::create('frame_outputs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('photo_frame_id');
            $table->string('original_filename');
            $table->string('fill_path')->nullable();
            $table->string('contain_path')->nullable();
            $table->timestamps();

            $table->index('photo_frame_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('frame_outputs');
    }
};
