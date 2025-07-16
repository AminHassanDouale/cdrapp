<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organization extends Model
{
    use HasFactory;

    protected $table = 'lbi_ods.t_o_biz_org';
    protected $primaryKey = 'biz_org_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'biz_org_id',
        'organization_type',
        'biz_org_name',
        'organization_code',
        'short_code',
        'trust_level',
        'charge_profile_id',
        'rule_profile_id',
        'charge_distribution_mode_id',
        'is_top',
        'hier_type',
        'max_layer',
        'aggregator_acc',
        'centrally_owned_acc',
        'top_biz_org',
        'parent_id',
        'identity_model',
        'hier_level',
        'active_time',
        'status_change_time',
        'status',
        'sp_id',
        'create_oper_id',
        'create_time',
        'modify_oper_id',
        'modify_time',
        'region_id',
        'person_id',
        'public_name',
        'channel_id'
    ];

    protected $casts = [
        'biz_org_id' => 'string',
        'trust_level' => 'integer',
        'charge_profile_id' => 'integer',
        'rule_profile_id' => 'integer',
        'charge_distribution_mode_id' => 'integer',
        'is_top' => 'boolean',
        'aggregator_acc' => 'integer',
        'centrally_owned_acc' => 'integer',
        'top_biz_org' => 'integer',
        'parent_id' => 'integer',
        'identity_model' => 'integer',
        'hier_level' => 'integer',
        'sp_id' => 'integer',
        'create_oper_id' => 'integer',
        'modify_oper_id' => 'integer',
        'region_id' => 'integer',
        'channel_id' => 'integer',
        // Note: create_time, modify_time, active_time are text fields
    ];

    /**
     * Get all accounts for this organization
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(OrganizationAccount::class, 'identity_id', 'biz_org_id');
    }

    /**
     * Get active accounts for this organization
     */
    public function activeAccounts(): HasMany
    {
        return $this->accounts()->where('account_status', '03');
    }

    /**
     * Get all operators for this organization
     */
    public function operators(): HasMany
    {
        return $this->hasMany(Operator::class, 'owned_identity_id', 'biz_org_id')
            ->where('owned_identity_type', Operator::IDENTITY_TYPE_ORGANIZATION);
    }

    /**
     * Get active operators for this organization
     */
    public function activeOperators(): HasMany
    {
        return $this->operators()->where('status', '03');
    }

    /**
     * Get KYC information for this organization
     */
    public function kyc(): HasOne
    {
        return $this->hasOne(OrganizationKyc::class, 'identityid', 'biz_org_id');
    }

    /**
     * Get total balance across all accounts
     */
    public function getTotalBalanceAttribute(): float
    {
        return $this->accounts()->sum('balance') ?? 0;
    }

    /**
     * Check if organization has complete KYC
     */
    public function getHasKycAttribute(): bool
    {
        return $this->kyc !== null;
    }

    /**
     * Get formatted create time
     */
    public function getCreateTimeFormattedAttribute(): string
    {
        try {
            if ($this->create_time) {
                return \Carbon\Carbon::parse($this->create_time)->format('d/m/Y H:i');
            }
        } catch (\Exception $e) {
            // If parsing fails, return raw value
        }

        return $this->create_time ?? '-';
    }

    /**
     * Get formatted modify time
     */
    public function getModifyTimeFormattedAttribute(): string
    {
        try {
            if ($this->modify_time) {
                return \Carbon\Carbon::parse($this->modify_time)->format('d/m/Y H:i');
            }
        } catch (\Exception $e) {
            // If parsing fails, return raw value
        }

        return $this->modify_time ?? '-';
    }

    /**
     * Get formatted active time
     */
    public function getActiveTimeFormattedAttribute(): string
    {
        try {
            if ($this->active_time) {
                return \Carbon\Carbon::parse($this->active_time)->format('d/m/Y H:i');
            }
        } catch (\Exception $e) {
            // If parsing fails, return raw value
        }

        return $this->active_time ?? '-';
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        $statusLabels = [
            '01' => 'Inactif',
            '03' => 'Actif',
            '05' => 'Suspendu',
            '07' => 'Bloqué',
            '09' => 'Fermé'
        ];

        return $statusLabels[$this->status] ?? $this->status;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', '03');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('organization_type', $type);
    }

    public function scopeTopLevel($query)
    {
        return $query->where('is_top', 1);
    }

    public function scopeWithKyc($query)
    {
        return $query->whereHas('kyc');
    }
}