<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryCount extends Model
{
    use HasFactory;

    protected $table = 'inventory_counts';

    protected $fillable = [
        'session_id',
        'asset_id',
        'actual_asset_id',
        'actual_serial',
        'status',
        'remarks',
        'counted_by'
    ];
}
