<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DvdStock extends Model
{
    protected $fillable = [
        'filmId',
        'quantite_totale',
        'quantite_disponible',
        'quantite_louee',
        'prix_location',
    ];

    protected $casts = [
        'quantite_totale' => 'integer',
        'quantite_disponible' => 'integer',
        'quantite_louee' => 'integer',
        'prix_location' => 'decimal:2',
    ];

    public function mouvements()
    {
        return $this->hasMany(MouvementStock::class, 'filmId', 'filmId');
    }
}
