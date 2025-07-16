<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionType extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'lbi_ods.t_o_transaction_type';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'unique_id';

    /**
     * Indicates if the model's ID is auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'unique_id',
        'txn_index',
        'txn_type_name',
        'alias',
        'description',
        'is_bulk',
        'is_intra',
        'is_reversal',
        'canbe_reversed',
        'debit_identity_type',
        'credit_identity_type',
        'status',
        'last_oper_id',
        'last_oper_time',
        'create_oper_id',
        'create_oper_time',
        'allow_same_identity',
        'can_partial_reverse',
        'is_suppertxn',
        'financial_category',
        'load_data_ts',
        'service_category',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'unique_id' => 'string',
        'txn_index' => 'string',
        'txn_type_name' => 'string',
        'alias' => 'string',
        'description' => 'string',
        'is_bulk' => 'string',
        'is_intra' => 'string',
        'is_reversal' => 'string',
        'canbe_reversed' => 'string',
        'debit_identity_type' => 'string',
        'credit_identity_type' => 'string',
        'status' => 'integer',
        'last_oper_id' => 'string',
        'last_oper_time' => 'datetime',
        'create_oper_id' => 'string',
        'create_oper_time' => 'datetime',
        'allow_same_identity' => 'string',
        'can_partial_reverse' => 'string',
        'is_suppertxn' => 'string',
        'financial_category' => 'string',
        'load_data_ts' => 'string',
        'service_category' => 'string',
    ];

    /**
     * Get transactions using this transaction type.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'tranactiontype', 'txn_index');
    }

    /**
     * Get transaction details using this transaction type.
     */
    public function transactionDetails()
    {
        return $this->hasMany(TransactionDetail::class, 'tranactiontype', 'txn_index');
    }

    /**
     * Get reason types associated with this transaction type.
     */
    public function reasonTypes()
    {
        return $this->hasMany(ReasonType::class, 'txn_index', 'txn_index');
    }

    /**
     * Scope for active transaction types.
     */
    public function scopeActive($query)
    {
        // Try different possible active status values
        return $query->where(function($q) {
            $q->where('status', 1)
              ->orWhere('status', '1')
              ->orWhere('status', 'Active')
              ->orWhere('status', 'active');
        });
    }

    /**
     * Scope for bulk transaction types.
     */
    public function scopeBulk($query)
    {
        return $query->where('is_bulk', 'Y');
    }

    /**
     * Scope for reversible transaction types.
     */
    public function scopeReversible($query)
    {
        return $query->where('canbe_reversed', 'Y');
    }

    /**
     * Scope for intra-organization transaction types.
     */
    public function scopeIntra($query)
    {
        return $query->where('is_intra', 'Y');
    }

    /**
     * Check if transaction type supports bulk operations.
     */
    public function supportsBulk()
    {
        return $this->is_bulk === 'Y';
    }

    /**
     * Check if transaction type is reversible.
     */
    public function isReversible()
    {
        return $this->canbe_reversed === 'Y';
    }

    /**
     * Check if transaction type allows same identity for debit and credit.
     */
    public function allowsSameIdentity()
    {
        return $this->allow_same_identity === 'Y';
    }

    /**
     * Check if transaction type supports partial reversal.
     */
    public function supportsPartialReversal()
    {
        return $this->can_partial_reverse === 'Y';
    }

    /**
     * Check if transaction type is intra-organization.
     */
    public function isIntraOrganization()
    {
        return $this->is_intra === 'Y';
    }

    /**
     * Get the display name for the transaction type.
     */
    public function getDisplayNameAttribute()
    {
        return $this->alias ?: $this->txn_type_name;
    }

    /**
     * Get financial category name.
     */
    public function getFinancialCategoryNameAttribute()
    {
        return match($this->financial_category) {
            '1' => 'Payment',
            '2' => 'Transfer',
            '3' => 'Withdrawal',
            '4' => 'Deposit',
            '5' => 'Fee',
            '6' => 'Reversal',
            default => 'Other'
        };
    }

    /**
     * Get service category name.
     */
    public function getServiceCategoryNameAttribute()
    {
        return match($this->service_category) {
            '1' => 'Banking',
            '2' => 'Mobile Money',
            '3' => 'Bill Payment',
            '4' => 'Remittance',
            '5' => 'Merchant Payment',
            default => 'General'
        };
    }
}
