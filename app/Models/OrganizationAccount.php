<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationAccount extends Model
{
    use HasFactory;

    protected $table = 'lbi_ods.t_o_org_account';
    protected $primaryKey = 'account_no';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'account_no',
        'alias',
        'account_type_id',
        'identity_type',
        'identity_id',
        'value_type',
        'currency',
        'balance',
        'reserved_balance',
        'unclear_balance',
        // Add other fields as needed
    ];

    protected $casts = [
        'account_no' => 'string',
        'balance' => 'decimal:2',
        'reserved_balance' => 'decimal:2',
        'unclear_balance' => 'decimal:2',
    ];

    /**
     * Get the organization that owns this account
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'identity_id', 'biz_org_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('account_status', '03');
    }

    public function scopeWithBalance($query)
    {
        return $query->where('balance', '>', 0);
    }
}