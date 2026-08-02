<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'id',
        'type',
        'brand',
        'model',
        'capacity',
        'quantity',
    ];

    public function scopeFilter(Builder|QueryBuilder $query, array $filters): Builder|QueryBuilder
    {
        return $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('id', 'like', '%'.$search.'%')
                    ->orWhere('type', 'like', '%'.$search.'%')
                    ->orWhere('brand', 'like', '%'.$search.'%')
                    ->orWhere('model', 'like', '%'.$search.'%')
                    ->orWhere('capacity', 'like', '%'.$search.'%');
            });
        });
    }
}
