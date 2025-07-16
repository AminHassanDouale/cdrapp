<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'lbi_ods.t_o_trans_record';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'orderid';

    /**
     * Indicates if the model's ID is auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     */
    protected $keyType = 'string'; // Keep as string for Laravel compatibility

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'orderid',
        'trans_status',
        'trans_initate_time',
        'trans_end_time',
        'debit_party_id',
        'debit_party_type',
        'debit_party_account',
        'debit_account_type',
        'debit_party_mnemonic',
        'credit_party_id',
        'credit_party_type',
        'credit_party_account',
        'credit_account_type',
        'credit_party_mnemonic',
        'expired_time',
        'request_amount',
        'request_currency',
        'exchange_rate',
        'org_amount',
        'actual_amount',
        'fee',
        'commission',
        'tax',
        'account_unit_type',
        'currency',
        'is_reversed',
        'remark',
        'is_partial_reversed',
        'is_reversing',
        'checker_id',
        'reason_type',
        'last_updated_time',
        'version',
        'load_data_ts',
        'accumulator_update',
        'accumulator_reversal',
        'chg_rating_details',
        'bank_card_id',
        'bank_account_number',
        'bank_account_name',
        'fi_account_info',
        'discount_amount',
        'redeemed_point_type',
        'redeemed_point_amount',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'orderid' => 'string', // Cast bigint to string for consistency
        'trans_initate_time' => 'datetime',
        'trans_end_time' => 'string',
        'expired_time' => 'datetime',
        'last_updated_time' => 'datetime',
        'debit_party_id' => 'integer',
        'credit_party_id' => 'integer',
        'request_amount' => 'decimal:2',
        'org_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'exchange_rate' => 'integer',
        'commission' => 'integer',
        'tax' => 'integer',
        'is_reversed' => 'integer',
        'version' => 'integer',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName()
    {
        return 'orderid';
    }

    /**
     * Convert the orderid to string when setting
     */
    public function setOrderidAttribute($value)
    {
        $this->attributes['orderid'] = (string) $value;
    }

    /**
     * Get the debit party customer (without conditions in relationship).
     */
    public function debitPartyCustomer()
    {
        return $this->belongsTo(Customer::class, 'debit_party_id', 'customer_id');
    }

    /**
     * Get the credit party customer (without conditions in relationship).
     */
    public function creditPartyCustomer()
    {
        return $this->belongsTo(Customer::class, 'credit_party_id', 'customer_id');
    }

    /**
     * Get the debit party organization (without conditions in relationship).
     */
    public function debitPartyOrganization()
    {
        return $this->belongsTo(Organization::class, 'debit_party_id', 'biz_org_id');
    }

    /**
     * Get the credit party organization (without conditions in relationship).
     */
    public function creditPartyOrganization()
    {
        return $this->belongsTo(Organization::class, 'credit_party_id', 'biz_org_id');
    }

    /**
     * Get the debit party (customer or organization) based on type.
     */
    public function getDebitPartyAttribute()
    {
        if ($this->debit_party_type === '1000') {
            return $this->debitPartyCustomer;
        } elseif ($this->debit_party_type === '5000') {
            return $this->debitPartyOrganization;
        }
        return null;
    }

    /**
     * Get the credit party (customer or organization) based on type.
     */
    public function getCreditPartyAttribute()
    {
        if ($this->credit_party_type === '1000') {
            return $this->creditPartyCustomer;
        } elseif ($this->credit_party_type === '5000') {
            return $this->creditPartyOrganization;
        }
        return null;
    }

    /**
     * Check if debit party is a customer.
     */
    public function isDebitPartyCustomer()
    {
        return $this->debit_party_type === '1000';
    }

    /**
     * Check if debit party is an organization.
     */
    public function isDebitPartyOrganization()
    {
        return $this->debit_party_type === '5000';
    }

    /**
     * Check if credit party is a customer.
     */
    public function isCreditPartyCustomer()
    {
        return $this->credit_party_type === '1000';
    }

    /**
     * Check if credit party is an organization.
     */
    public function isCreditPartyOrganization()
    {
        return $this->credit_party_type === '5000';
    }

    /**
     * Get the debit party account.
     */
    public function debitAccount()
    {
        // This will need to be adjusted based on your account structure
        return $this->hasOne(CustomerAccount::class, 'account_no', 'debit_party_account');
    }

    /**
     * Get the credit party account.
     */
    public function creditAccount()
    {
        // This will need to be adjusted based on your account structure
        return $this->hasOne(CustomerAccount::class, 'account_no', 'credit_party_account');
    }

    /**
     * Get the transaction details.
     */
    public function transactionDetails()
    {
        return $this->hasOne(TransactionDetail::class, 'orderid', 'orderid');
    }

    /**
     * Get the transaction type information.
     */


    /**
     * Get the reason type information.
     */



    public function transactionType()
    {
        return $this->belongsTo(TransactionType::class, 'tranactiontype', 'txn_index');
    }

    /**
     * Get the reason type information.
     */
    public function reasonType()
    {
        return $this->belongsTo(ReasonType::class, 'reason_type', 'reason_index');
    }

    /**
     * Get the debit account type information.
     */
    public function debitAccountType()
    {
        return $this->belongsTo(AccountType::class, 'debit_account_type', 'account_type_id');
    }

    /**
     * Get the credit account type information.
     */
    public function creditAccountType()
    {
        return $this->belongsTo(AccountType::class, 'credit_account_type', 'account_type_id');
    }

    /**
     * Scope for successful transactions.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('trans_status', 'Completed');
    }

    /**
     * Scope for pending transactions.
     */
    public function scopePending($query)
    {
        return $query->whereIn('trans_status', ['Pending', 'Pending Authorized']);
    }

    /**
     * Scope for failed transactions.
     */
    public function scopeFailed($query)
    {
        return $query->where('trans_status', 'Failed');
    }

    /**
     * Scope for authorized transactions.
     */
    public function scopeAuthorized($query)
    {
        return $query->where('trans_status', 'Authorized');
    }

    /**
     * Scope for reversed transactions.
     */
    public function scopeReversed($query)
    {
        return $query->where('is_reversed', 1);
    }

    /**
     * Scope for transactions by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('trans_initate_time', [$startDate, $endDate]);
    }

    /**
     * Scope for transactions by currency.
     */
    public function scopeByCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }

    /**
     * Scope for transactions by amount range.
     */
    public function scopeAmountRange($query, $minAmount, $maxAmount)
    {
        return $query->whereBetween('actual_amount', [$minAmount, $maxAmount]);
    }

    /**
     * Get formatted transaction amount.
     */
    public function getFormattedAmountAttribute()
    {
        return number_format($this->actual_amount, 2) . ' ' . $this->currency;
    }

    /**
     * Get transaction status color for UI.
     */
    public function getStatusColorAttribute()
    {
        return match($this->trans_status) {
            'Completed' => 'green',
            'Authorized' => 'blue',
            'Pending', 'Pending Authorized' => 'yellow',
            'Failed' => 'red',
            'Cancelled' => 'gray',
            default => 'gray'
        };
    }

    /**
     * Check if transaction is reversible.
     */
    public function isReversible()
    {
        return $this->trans_status === 'Completed' &&
               $this->is_reversed != 1 &&
               $this->trans_initate_time >= now()->subDays(30); // 30 days reversal window
    }

    /**
     * Get the total transaction value including fees.
     */
    public function getTotalValueAttribute()
    {
        return $this->actual_amount + $this->fee;
    }

    /**
     * Check if this is a high-value transaction.
     */
    public function isHighValue($threshold = 10000)
    {
        return $this->actual_amount >= $threshold;
    }
}
