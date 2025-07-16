<?php

namespace App\Actions;

use App\Exceptions\AppException;
use App\Models\Product;

class DeleteProductAction
{
    public function __construct(private Product $product)
    {
    }

    public function execute(): void
    {
        if ($this->product->sales()->count()) {
            throw new AppException("There are some orders associated to this product.");
        }

        $this->product->delete();
    }
}
