<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountType extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'lbi_ods.t_o_account_type';

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
        'account_type_id',
        'account_type_name',
        'account_type_alias',
        'account_model',
        'balance_direction',
        'value_type',
        'currency',
        'sp_applied',
        'customer_applied',
        'organization_applied',
        'status_group',
        'for_charge_debit',
        'for_charge_credit',
        'for_tax_debit',
        'for_tax_credit',
        'exclude_limit',
        'is_allow_over_draw',
        'realtime_update',
        'is_sharable',
        'apply_credit_limit',
        'description',
        'status',
        'create_oper_id',
        'create_oper_time',
        'last_oper_id',
        'last_oper_time',
        'load_data_ts',
        'group_applied',
        'isloyaltyprovideracct',
        'debit_query_balance',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'unique_id' => 'string',
        'account_type_id' => 'integer',
        'account_type_name' => 'string',
        'account_type_alias' => 'string',
        'account_model' => 'integer',
        'balance_direction' => 'integer',
        'value_type' => 'integer',
        'currency' => 'string',
        'sp_applied' => 'boolean',
        'customer_applied' => 'boolean',
        'organization_applied' => 'boolean',
        'status_group' => 'string',
        'for_charge_debit' => 'boolean',
        'for_charge_credit' => 'boolean',
        'for_tax_debit' => 'boolean',
        'for_tax_credit' => 'boolean',
        'exclude_limit' => 'boolean',
        'is_allow_over_draw' => 'boolean',
        'realtime_update' => 'boolean',
        'is_sharable' => 'integer',
        'apply_credit_limit' => 'integer',
        'description' => 'string',
        'status' => 'string',
        'create_oper_id' => 'string',
        'create_oper_time' => 'string',
        'last_oper_id' => 'string',
        'last_oper_time' => 'string',
        'load_data_ts' => 'string',
        'group_applied' => 'string',
        'isloyaltyprovideracct' => 'string',
        'debit_query_balance' => 'string',
    ];

    /**
     * Get customer accounts using this account type.
     */
    public function customerAccounts()
    {
        return $this->hasMany(CustomerAccount::class, 'account_type', 'account_type_id');
    }

    /**
     * Get organization accounts using this account type.
     */
    public function organizationAccounts()
    {
        return $this->hasMany(OrganizationAccount::class, 'account_type', 'account_type_id');
    }

    /**
     * Get transactions where this account type is used for debit account.
     */
    public function debitTransactions()
    {
        return $this->hasMany(Transaction::class, 'debit_account_type', 'account_type_id');
    }

    /**
     * Get transactions where this account type is used for credit account.
     */
    public function creditTransactions()
    {
        return $this->hasMany(Transaction::class, 'credit_account_type', 'account_type_id');
    }

    /**
     * Scope for active account types.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    /**
     * Scope for customer-applicable account types.
     */
    public function scopeForCustomers($query)
    {
        return $query->where('customer_applied', true);
    }

    /**
     * Scope for organization-applicable account types.
     */
    public function scopeForOrganizations($query)
    {
        return $query->where('organization_applied', true);
    }

    /**
     * Scope for service provider-applicable account types.
     */
    public function scopeForServiceProviders($query)
    {
        return $query->where('sp_applied', true);
    }

    /**
     * Scope for account types by currency.
     */
    public function scopeByCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }

    /**
     * Scope for account types that allow overdraw.
     */
    public function scopeAllowOverdraw($query)
    {
        return $query->where('is_allow_over_draw', true);
    }

    /**
     * Scope for sharable account types.
     */
    public function scopeSharable($query)
    {
        return $query->where('is_sharable', '>', 0);
    }

    /**
     * Check if account type is applicable for customers.
     */
    public function isForCustomers()
    {
        return $this->customer_applied === true;
    }

    /**
     * Check if account type is applicable for organizations.
     */
    public function isForOrganizations()
    {
        return $this->organization_applied === true;
    }

    /**
     * Check if account type allows overdraw.
     */
    public function allowsOverdraw()
    {
        return $this->is_allow_over_draw === true;
    }

    /**
     * Check if account type supports real-time updates.
     */
    public function supportsRealtimeUpdate()
    {
        return $this->realtime_update === true;
    }

    /**
     * Check if account type can be used for charge debit.
     */
    public function canChargeDebit()
    {
        return $this->for_charge_debit === true;
    }

    /**
     * Check if account type can be used for charge credit.
     */
    public function canChargeCredit()
    {
        return $this->for_charge_credit === true;
    }

    /**
     * Check if account type can be used for tax debit.
     */
    public function canTaxDebit()
    {
        return $this->for_tax_debit === true;
    }

    /**
     * Check if account type can be used for tax credit.
     */
    public function canTaxCredit()
    {
        return $this->for_tax_credit === true;
    }

    /**
     * Check if account type is excluded from limits.
     */
    public function isExcludedFromLimits()
    {
        return $this->exclude_limit === true;
    }

    /**
     * Get the display name for the account type.
     */
    public function getDisplayNameAttribute()
    {
        return $this->account_type_alias ?: $this->account_type_name;
    }

    /**
     * Get balance direction name.
     */
    public function getBalanceDirectionNameAttribute()
    {
        return match($this->balance_direction) {
            1 => 'Debit',
            2 => 'Credit',
            default => 'Neutral'
        };
    }

    /**
     * Get account model name.
     */
    public function getAccountModelNameAttribute()
    {
        return match($this->account_model) {
            1 => 'Standard Account',
            2 => 'Savings Account',
            3 => 'Current Account',
            4 => 'Fixed Deposit',
            5 => 'Loan Account',
            6 => 'Fee Account',
            7 => 'Commission Account',
            8 => 'Tax Account',
            9 => 'Suspense Account',
            10 => 'GL Account',
            default => 'Unknown Model'
        };
    }

    /**
     * Get value type name.
     */
    public function getValueTypeNameAttribute()
    {
        return match($this->value_type) {
            1 => 'Monetary',
            2 => 'Points',
            3 => 'Units',
            4 => 'Percentage',
            default => 'Unknown Type'
        };
    }

    /**
     * Check if this is a loyalty provider account.
     */
    public function isLoyaltyProviderAccount()
    {
        return $this->isloyaltyprovideracct === 'Y';
    }

    /**
     * Check if debit queries balance.
     */
    public function debitQueriesBalance()
    {
        return $this->debit_query_balance === 'Y';
    }
}