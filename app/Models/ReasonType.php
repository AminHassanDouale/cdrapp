<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReasonType extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'lbi_ods.t_o_reason_type';

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
        'reason_index',
        'reason_name',
        'alias',
        'description',
        'business_scope',
        'txn_index',
        'expired_day',
        'expired_hour',
        'expired_min',
        'is_link_tran',
        'is_can_behalf',
        'initiator_identity_type',
        'channels',
        'template_id',
        'status',
        'create_oper_id',
        'create_oper_time',
        'last_oper_id',
        'last_oper_time',
        'reverse_mode',
        'time_of_reverse',
        'duration_reverse',
        'reverse_limitrule',
        'exp_id',
        'load_data_ts',
        'group_member_flag',
        'reverse_transaction_tax',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'unique_id' => 'string',
        'reason_index' => 'integer',
        'reason_name' => 'string',
        'alias' => 'string',
        'description' => 'string',
        'business_scope' => 'integer',
        'txn_index' => 'integer',
        'expired_day' => 'integer',
        'expired_hour' => 'integer',
        'expired_min' => 'integer',
        'is_link_tran' => 'boolean',
        'is_can_behalf' => 'string',
        'initiator_identity_type' => 'string',
        'channels' => 'string',
        'template_id' => 'string',
        'status' => 'string',
        'create_oper_id' => 'string',
        'create_oper_time' => 'string',
        'last_oper_id' => 'string',
        'last_oper_time' => 'string',
        'reverse_mode' => 'string',
        'time_of_reverse' => 'string',
        'duration_reverse' => 'integer',
        'reverse_limitrule' => 'integer',
        'exp_id' => 'integer',
        'load_data_ts' => 'string',
        'group_member_flag' => 'string',
        'reverse_transaction_tax' => 'integer',
    ];

    /**
     * Get transactions using this reason type.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'reason_type', 'reason_index');
    }

    /**
     * Get transaction details using this reason type.
     */
    public function transactionDetails()
    {
        return $this->hasMany(TransactionDetail::class, 'reason_type', 'reason_index');
    }

    /**
     * Get the associated transaction type.
     */
    public function transactionType()
    {
        return $this->belongsTo(TransactionType::class, 'txn_index', 'txn_index');
    }

    /**
     * Scope for active reason types.
     */
    public function scopeActive($query)
    {
        // Try different possible active status values
        return $query->where(function($q) {
            $q->where('status', 'Active')
              ->orWhere('status', 'active')
              ->orWhere('status', '1')
              ->orWhere('status', 1);
        });
    }

    /**
     * Scope for reason types by business scope.
     */
    public function scopeByBusinessScope($query, $scope)
    {
        return $query->where('business_scope', $scope);
    }

    /**
     * Scope for reason types by transaction index.
     */
    public function scopeByTransactionType($query, $txnIndex)
    {
        return $query->where('txn_index', $txnIndex);
    }

    /**
     * Scope for linked transaction reason types.
     */
    public function scopeLinkedTransaction($query)
    {
        return $query->where('is_link_tran', true);
    }

    /**
     * Scope for reason types that can be done on behalf.
     */
    public function scopeCanBehalf($query)
    {
        return $query->where('is_can_behalf', 'Y');
    }

    /**
     * Get the total expiration time in minutes.
     */
    public function getTotalExpirationMinutesAttribute()
    {
        return ($this->expired_day * 24 * 60) +
               ($this->expired_hour * 60) +
               $this->expired_min;
    }

    /**
     * Check if this reason type supports linked transactions.
     */
    public function supportsLinkedTransactions()
    {
        return $this->is_link_tran === true;
    }

    /**
     * Check if this reason type can be done on behalf of someone.
     */
    public function canBehalf()
    {
        return $this->is_can_behalf === 'Y';
    }

    /**
     * Get the display name for the reason type.
     */
    public function getDisplayNameAttribute()
    {
        return $this->alias ?: $this->reason_name;
    }

    /**
     * Get channels as array.
     */
    public function getChannelsArrayAttribute()
    {
        if (empty($this->channels)) {
            return [];
        }
        return array_filter(explode(',', $this->channels));
    }

    /**
     * Check if reason type supports a specific channel.
     */
    public function supportsChannel($channel)
    {
        return in_array($channel, $this->channels_array);
    }

    /**
     * Get business scope name.
     */
    public function getBusinessScopeNameAttribute()
    {
        return match($this->business_scope) {
            1 => 'Internal Transfer',
            2 => 'External Transfer',
            3 => 'Bill Payment',
            4 => 'Mobile Money',
            5 => 'International Remittance',
            6 => 'Merchant Payment',
            7 => 'Government Payment',
            8 => 'Utility Payment',
            default => 'General'
        };
    }

    /**
     * Get reverse mode name.
     */
    public function getReverseModeNameAttribute()
    {
        return match($this->reverse_mode) {
            'AUTO' => 'Automatic Reversal',
            'MANUAL' => 'Manual Reversal',
            'PARTIAL' => 'Partial Reversal',
            'FULL' => 'Full Reversal',
            default => 'Standard Reversal'
        };
    }

    /**
     * Check if reason type has reversal tax.
     */
    public function hasReversalTax()
    {
        return $this->reverse_transaction_tax > 0;
    }
}
