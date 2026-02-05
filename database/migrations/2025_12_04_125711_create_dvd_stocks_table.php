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
        Schema::create('dvd_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('filmId')->unique();
            $table->integer('quantite_totale')->default(0);
            $table->integer('quantite_disponible')->default(0);
            $table->integer('quantite_louee')->default(0);
            $table->decimal('prix_location', 8, 2)->default(3.99);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dvd_stocks');
    }
};
