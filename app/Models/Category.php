<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Category extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function sales(): HasManyThrough
    {
        return $this->hasManyThrough(OrderItem::class, Product::class);
    }

    protected function dateHuman(): Attribute
    {
        return Attribute::make(
            get: fn(?Carbon $value) => $this->created_at->toFormattedDateString()
        );
    }
}
