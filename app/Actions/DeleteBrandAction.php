<?php

namespace App\Actions;

use App\Exceptions\AppException;
use App\Models\Brand;

class DeleteBrandAction
{
    public function __construct(private Brand $brand)
    {
    }

    public function execute(): void
    {
        if ($this->brand->products()->count()) {
            throw new AppException("The are some products associated to this brand.");
        }

        $this->brand->delete();
    }
}
