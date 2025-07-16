<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperatorKyc extends Model
{
    use HasFactory;

    protected $table = 'lbi_ods.t_o_org_operator_kyc';
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
     * Get the operator for this KYC record
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class, 'identityid', 'operator_id');
    }
}
