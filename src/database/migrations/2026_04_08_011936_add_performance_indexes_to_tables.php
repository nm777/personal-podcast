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
        // Add missing index on feeds.user_guid for RSS feed lookups
        // Other indexes (library_items.source_url, library_items.media_file_id+user_id,
        // media_files.source_url) were already created in previous migrations
        Schema::table('feeds', function (Blueprint $table) {
            $table->index('user_guid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feeds', function (Blueprint $table) {
            $table->dropIndex(['user_guid']);
        });
    }
};
