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
        Schema::table('frame_outputs', function (Blueprint $table) {
            $table->foreign('photo_frame_id')
                ->references('id')
                ->on('photo_frames')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('frame_outputs', function (Blueprint $table) {
            $table->dropForeign(['photo_frame_id']);
        });
    }
};
