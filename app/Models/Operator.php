<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Operator extends Model
{
    use HasFactory;

    protected $table = 'lbi_ods.t_o_operator';
    protected $primaryKey = 'operator_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'operator_id',
        'operator_code',
        'owned_identity_type',
        'owned_identity_id',
        'sp_id',
        'user_name',
        'rule_profile_id',
        'status',
        'default_till_id',
        'active_time',
        'status_change_time',
        'create_oper_id',
        'create_time',
        'is_admin',
        'modify_oper_id',
        'modify_time',
        'language_code',
        'access_channel',
        'person_id',
        'public_name',
        'channel_id'
    ];

    protected $casts = [
        'operator_id' => 'string',
        'owned_identity_type' => 'integer',
        'owned_identity_id' => 'string',
        'sp_id' => 'integer',
        'is_admin' => 'boolean',
        'create_oper_id' => 'integer',
        'modify_oper_id' => 'integer',
        'channel_id' => 'integer',
        // Note: active_time, create_time, modify_time are text fields, handle carefully
    ];

    // Constants for owned_identity_type
    const IDENTITY_TYPE_CUSTOMER = 1;
    const IDENTITY_TYPE_ORGANIZATION = 2;
    const IDENTITY_TYPE_OPERATOR = 3;

    /**
     * Get KYC information for this operator
     */
    public function kyc(): HasOne
    {
        return $this->hasOne(OperatorKyc::class, 'identityid', 'operator_id');
    }

    /**
     * Get the organization this operator belongs to (if owned_identity_type is organization)
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'owned_identity_id', 'biz_org_id')
            ->where('owned_identity_type', self::IDENTITY_TYPE_ORGANIZATION);
    }

    /**
     * Check if operator has complete KYC
     */
    public function getHasKycAttribute(): bool
    {
        return $this->kyc !== null;
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
     * Get identity type name
     */
    public function getIdentityTypeNameAttribute(): string
    {
        switch ($this->owned_identity_type) {
            case self::IDENTITY_TYPE_CUSTOMER:
                return 'Client';
            case self::IDENTITY_TYPE_ORGANIZATION:
                return 'Organisation';
            case self::IDENTITY_TYPE_OPERATOR:
                return 'Opérateur';
            default:
                return 'Inconnu';
        }
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', '03');
    }

    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('owned_identity_id', $organizationId)
                    ->where('owned_identity_type', self::IDENTITY_TYPE_ORGANIZATION);
    }

    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('owned_identity_id', $customerId)
                    ->where('owned_identity_type', self::IDENTITY_TYPE_CUSTOMER);
    }

    public function scopeAdmins($query)
    {
        return $query->where('is_admin', 1);
    }
}
