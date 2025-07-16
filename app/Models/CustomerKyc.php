<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerKyc extends Model
{
    use HasFactory;

    protected $table = 'lbi_ods.t_o_customer_kyc';
    protected $primaryKey = 'identityid';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'identityid',
        // Add all 102 fields as needed
        'field_1', 'field_2', 'field_3', 'field_4', 'field_5',
        'field_6', 'field_7', 'field_8', 'field_9', 'field_10',
        // ... continue for all fields
        'load_data_ts'
    ];

    protected $casts = [
        'identityid' => 'string',
    ];

    /**
     * Get the customer for this KYC record
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'identityid', 'customer_id');
    }

    /**
     * Check if KYC is complete based on required fields
     */
    public function getIsCompleteAttribute(): bool
    {
        // Define your KYC completion criteria
        return !empty($this->field_4) && !empty($this->field_5);
    }
}
