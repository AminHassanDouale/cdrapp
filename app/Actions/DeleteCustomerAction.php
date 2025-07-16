<?php

namespace App\Actions;

use App\Exceptions\AppException;
use App\Models\User;

class DeleteCustomerAction
{
    public function __construct(private User $user)
    {
    }

    public function execute(): void
    {
        if ($this->user->id <= 50) {
            throw new AppException("You can not delete this demo record, create a new one.");
        }
        
        if ($this->user->orders()->count()) {
            throw new AppException("There are some orders associated to this customer.");
        }

        $this->user->delete();
    }
}
