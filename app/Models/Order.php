<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Number;
use Znck\Eloquent\Traits\BelongsToThrough;

class Order extends Model
{
    use HasFactory, BelongsToThrough;

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'status_id');
    }

    // Shortcut to make easy to sort by country name
    public function country()
    {
        return $this->belongsToThrough(Country::class, User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // The order is a CART, for now.
    public function scopeIsCart(Builder $query): Builder
    {
        return $query->where('status_id', OrderStatus::DRAFT);
    }

    // This is a REAL order, not a CART anymore
    public function scopeIsNotCart(Builder $query): Builder
    {
        return $query->where('status_id', '<>', OrderStatus::DRAFT);
    }

    protected function totalHuman(): Attribute
    {
        return Attribute::make(
            get: fn(?float $value) => Number::currency($this->total)
        );
    }

    protected function dateHuman(): Attribute
    {
        return Attribute::make(
            get: fn(?Carbon $value) => $this->created_at->toFormattedDateString()
        );
    }
}
