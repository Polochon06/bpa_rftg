<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Suppression des tables locales de cache stock.
 * Le stock est désormais géré exclusivement via le Toad API (base peach).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('mouvements_stock');
        Schema::dropIfExists('dvd_stocks');
    }

    public function down(): void
    {
        // Recréation possible via les migrations d'origine si nécessaire
    }
};
