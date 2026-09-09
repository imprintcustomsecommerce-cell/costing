<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The table is named for what it holds rather than for one owner type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('quote_artworks', 'artworks');
    }

    public function down(): void
    {
        Schema::rename('artworks', 'quote_artworks');
    }
};
