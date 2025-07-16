<?php

namespace App\Actions;

use App\Exceptions\AppException;
use App\Models\Category;

class DeleteCategoryAction
{
    public function __construct(private Category $category)
    {
    }

    public function execute(): void
    {
        if ($this->category->products()->count()) {
            throw new AppException("The are some products associated to this category.");
        }

        $this->category->delete();
    }
}
