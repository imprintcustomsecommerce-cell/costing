<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialCostHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id',
        'cost',
        'previous_cost',
        'change_percentage',
        'effective_from',
        'reason',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:4',
            'previous_cost' => 'decimal:4',
            'change_percentage' => 'decimal:4',
            'effective_from' => 'date',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
