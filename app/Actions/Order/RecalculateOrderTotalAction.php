<?php

namespace App\Actions\Order;

use App\Models\Order;

class RecalculateOrderTotalAction
{
    public function __construct(private Order $order)
    {
    }

    public function execute(): void
    {
        $this->order->update(['total' => $this->order->items()->sum('total')]);
    }
}
