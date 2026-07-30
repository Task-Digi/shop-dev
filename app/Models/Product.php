<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Product extends Model
{
    public $timestamps = false; // Disables timestamps
    protected $table = 'products';

    /**
     * Strip leading asterisks/spaces from ERP-imported names (e.g. "*JOTUN GRUNNING").
     */
    public static function normalizeName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $normalized = ltrim($name, " \t\n\r\0\x0B*");

        return $normalized === '' ? null : $normalized;
    }

    protected $fillable = [
        'product_id',
        'product_name',
        'price',
        'retail',
        'ean_code'
    ];
}

