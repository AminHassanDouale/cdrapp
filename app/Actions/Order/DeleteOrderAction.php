<?php

namespace App\Actions\Order;

use App\Exceptions\AppException;
use App\Models\Order;
use DB;
use Throwable;

class DeleteOrderAction
{
    public function __construct(private Order $order)
    {
    }

    public function execute(): void
    {
        try {
            DB::beginTransaction();

            if ($this->order->id <= 100) {
                throw new AppException("You can not delete this demo record, create a new one.");
            }

            $this->order->items()->delete();
            $this->order->delete();

            DB::commit();
        } catch (AppException $e) {
            throw $e;
        } catch (Throwable $e) {
            DB::rollBack();
            throw new AppException('Whoops!');
        }
    }
}
