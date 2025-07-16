<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationKyc extends Model
{
    use HasFactory;

    protected $table = 'lbi_ods.t_o_org_kyc';
    protected $primaryKey = 'identityid';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'identityid',
        // Add all 102 fields as needed
        'field_1', 'field_2', 'field_3', 'field_4', 'field_5',
        // ... continue for all fields
    ];

    /**
     * Get the organization for this KYC record
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'identityid', 'biz_org_id');
    }
}
