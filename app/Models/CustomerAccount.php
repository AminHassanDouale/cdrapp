<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAccount extends Model
{
    use HasFactory;

    protected $table = 'lbi_ods.t_o_customer_account';
    protected $primaryKey = 'account_no';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'partition_hint',
        'account_no',
        'alias',
        'account_type_id',
        'identity_type',
        'identity_id',
        'value_type',
        'currency',
        'balance',
        'reserved_balance',
        'unclear_balance',
        'settlement_date',
        'last_date_balance',
        'account_status',
        'account_rule_profile_id',
        'open_date',
        'close_date',
        'last_date',
        'sign',
        'version',
        'status_last_date',
        'load_data_ts',
        'account_name',
        'default_flag',
        'digest_flag'
    ];

    protected $casts = [
        'account_no' => 'string',
        'balance' => 'decimal:2',
        'reserved_balance' => 'decimal:2',
        'unclear_balance' => 'decimal:2',
        'settlement_date' => 'datetime',
        'last_date_balance' => 'datetime',
        'open_date' => 'datetime',
        'close_date' => 'datetime',
        'last_date' => 'datetime',
        'status_last_date' => 'datetime',
    ];

    /**
     * Get the customer that owns this account
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'identity_id', 'customer_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('account_status', '03');
    }

    public function scopeWithBalance($query)
    {
        return $query->where('balance', '>', 0);
    }

    public function scopeByCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('account_type_id', $type);
    }
}
