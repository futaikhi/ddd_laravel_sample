<?php

declare(strict_types=1);

namespace Src\Sales\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

final class SaleLineItemModel extends Model
{
    protected $table = 'sale_line_items';

    public $timestamps = false;

    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'unit_price',
        'currency',
    ];
}
