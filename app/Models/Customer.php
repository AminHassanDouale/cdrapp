<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    use HasFactory;

    protected $table = 'lbi_ods.t_o_customer';
    protected $primaryKey = 'customer_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'customer_id',
        'customer_type',
        'user_name',
        'trust_level',
        'notification_type',
        'language_code',
        'charge_profile_id',
        'rule_profile_id',
        'sp_id',
        'owned_identity_type',
        'owned_identity_id',
        'active_time',
        'status_change_time',
        'create_oper_id',
        'create_time',
        'modify_oper_id',
        'modify_time',
        'status',
        'status_change_reason',
        'load_data_ts',
        'person_id',
        'public_name',
        'first_link_bank_account_time',
        'inviter_identity_id',
        'inviter_identity_type',
        'channel_id'
    ];

    protected $casts = [
        'customer_id' => 'string',
        'active_time' => 'datetime',
        'status_change_time' => 'datetime',
        'create_time' => 'datetime',
        'modify_time' => 'datetime',
        'trust_level' => 'integer',
        'charge_profile_id' => 'integer',
        'rule_profile_id' => 'integer',
    ];

    /**
     * Get all accounts for this customer
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(CustomerAccount::class, 'identity_id', 'customer_id');
    }

    /**
     * Get KYC information for this customer
     */
    public function kyc(): HasOne
    {
        return $this->hasOne(CustomerKyc::class, 'identityid', 'customer_id');
    }

    /**
     * Get active accounts only
     */
    public function activeAccounts(): HasMany
    {
        return $this->accounts()->where('account_status', '03');
    }

    /**
     * Get total balance across all accounts
     */
    public function getTotalBalanceAttribute(): float
    {
        return $this->accounts()->sum('balance') ?? 0;
    }

    /**
     * Check if customer has complete KYC
     */
    public function getHasKycAttribute(): bool
    {
        return $this->kyc !== null;
    }

    /**
     * Get customer segment based on balance
     */
    public function getSegmentAttribute(): string
    {
        $balance = $this->total_balance;

        if ($balance >= 100000) return 'High Value';
        if ($balance >= 10000) return 'Medium Value';
        if ($balance > 0) return 'Low Value';
        return 'Zero Balance';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', '03');
    }

    public function scopeWithBalance($query)
    {
        return $query->whereHas('accounts', function($q) {
            $q->where('balance', '>', 0);
        });
    }

    public function scopeWithoutKyc($query)
    {
        return $query->doesntHave('kyc');
    }
}