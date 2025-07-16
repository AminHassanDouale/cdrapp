<?php

namespace App\Actions\Order;

use App\Models\OrderItem;

class UpdateOrderItemQuantityAction
{
    public function __construct(private OrderItem $item, private int $quantity)
    {
    }

    public function execute(): void
    {
        $this->item->update([
            'quantity' => $this->quantity,
            'total' => $this->quantity * $this->item->price,
        ]);

        $recalculate = new RecalculateOrderTotalAction($this->item->order);
        $recalculate->execute();
    }
}
