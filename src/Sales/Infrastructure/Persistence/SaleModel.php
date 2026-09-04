<?php

declare(strict_types=1);

namespace Src\Sales\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SaleModel extends Model
{
    protected $table = 'sales';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'customer_id',
        'agent_id',
        'status',
        'total_amount',
        'confirmed_at',
        'cancelled_at',
        'cancellation_reason',
        'completed_at',
        'created_at',
        'payment_method',
        'transaction_id',
        'commission_amount',
        'commission_rate',
        'commission_currency',
    ];

    public function lineItems(): HasMany
    {
        return $this->hasMany(SaleLineItemModel::class, 'sale_id', 'id');
    }
}
