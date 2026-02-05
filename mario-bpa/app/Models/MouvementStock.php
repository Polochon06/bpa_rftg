<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MouvementStock extends Model
{
    protected $table = 'mouvements_stock';

    protected $fillable = [
        'filmId',
        'type',
        'quantite',
        'motif',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'quantite' => 'integer',
        'created_at' => 'datetime',
    ];

    public function stock()
    {
        return $this->belongsTo(DvdStock::class, 'filmId', 'filmId');
    }
}
