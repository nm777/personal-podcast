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
        Schema::table('library_items', function (Blueprint $table) {
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->after('source_url');
            $table->timestamp('processing_started_at')->nullable()->after('processing_status');
            $table->timestamp('processing_completed_at')->nullable()->after('processing_started_at');
            $table->text('processing_error')->nullable()->after('processing_completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropColumn(['processing_status', 'processing_started_at', 'processing_completed_at', 'processing_error']);
        });
    }
};
