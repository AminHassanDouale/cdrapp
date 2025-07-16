<?php

namespace App\Actions\Order;

use App\Exceptions\AppException;
use App\Models\OrderItem;
use DB;
use Throwable;

class DeleteOrderItemAction
{
    public function __construct(private OrderItem $item)
    {
    }

    public function execute(): void
    {
        try {
            DB::beginTransaction();

            if ($this->item->id <= 200) {
                throw new AppException("You can not delete this demo item, add a new one.");
            }

            $this->item->delete();

            $recalculate = new RecalculateOrderTotalAction($this->item->order);
            $recalculate->execute();

            DB::commit();
        } catch (AppException $e) {
            throw $e;
        } catch (Throwable $e) {
            DB::rollBack();
            throw new AppException('Whoops!');
        }
    }
}
