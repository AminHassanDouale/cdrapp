<?php

namespace App\Actions\Order;

use App\Exceptions\AppException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatus;
use App\Models\Product;
use DB;
use Throwable;

class AddItemToOrderAction
{
    public function __construct(private Order $order, private array $item)
    {
    }

    public function execute(): void
    {
        try {
            DB::beginTransaction();

            $alreadyOnOrder = $this->order
                ->items()
                ->where('product_id', $this->item['product_id'])
                ->count();

            if ($alreadyOnOrder) {
                throw new AppException('This item already exists. Or another person just added this same item. Refresh the page.');
            }

            $product = Product::find($this->item['product_id']);

            OrderItem::create([
                'order_id' => $this->order->id,
                'product_id' => $this->item['product_id'],
                'quantity' => $this->item['quantity'],
                'price' => $product->price,
                'total' => $product->price * $this->item['quantity']
            ]);

            $recalculate = new RecalculateOrderTotalAction($this->order);
            $recalculate->execute();

            // Change to a random status (for demo purpose) if still DRAFT
            if ($this->order->status_id == OrderStatus::DRAFT) {
                $this->order->update([
                    'status_id' => OrderStatus::where('id', '<>', OrderStatus::DRAFT)->inRandomOrder()->first()->id
                ]);
            }

            DB::commit();
        } catch (AppException $e) {
            throw $e;
        } catch (Throwable $e) {
            DB::rollBack();
            throw new AppException('Whoops!');
        }
    }
}
