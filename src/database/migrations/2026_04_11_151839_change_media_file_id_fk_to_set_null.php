<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropForeign(['media_file_id']);
            $table->foreign('media_file_id')
                ->references('id')
                ->on('media_files')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropForeign(['media_file_id']);
            $table->foreign('media_file_id')
                ->references('id')
                ->on('media_files')
                ->cascadeOnDelete();
        });
    }
};
