<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionDetail extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'lbi_ods.t_o_orderhis';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'orderid';

    /**
     * Indicates if the model's ID is auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     */
    protected $keyType = 'string'; // Keep as string for Laravel compatibility

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'orderid',
        'businesstype',
        'bussinesscategory',
        'exemode',
        'orderstate',
        'completed_reason',
        'createtime',
        'lastupddate',
        'suspendtime',
        'begintime',
        'endtime',
        'expiretime',
        'commandid',
        'procdefid',
        'procdefversion',
        'procinstid',
        'procstate',
        'suspendactivityid',
        'chainid',
        'businessscope',
        'tranactiontype',
        'product_index',
        'servicecode',
        'service_index',
        'reason_type',
        'initiator_id',
        'initiator_type',
        'initiating_device_type',
        'initiating_device_value',
        'initiating_till_no',
        'initiator_identifier_type',
        'initiator_identifier_value',
        'initiator_org_shortcode',
        'initiator_mnemonic',
        'receiver_id',
        'receiver_type',
        'receiver_identifier_type',
        'receiver_identifier_value',
        'receiver_org_shortcode',
        'receiver_mnemonic',
        'primary_party_id',
        'primary_party_type',
        'primary_party_identifier_type',
        'primary_identifier_value',
        'primary_mnemonic',
        'crenettype',
        'crenetid',
        'eventsource',
        'thirdpartyid',
        'thirdpartyip',
        'accesspointip',
        'thirdpartyreqtime',
        'accesspointreqtime',
        'channel',
        'version',
        'traceinfo',
        'languagecode',
        'origconversationid',
        'conversationid',
        'remark',
        'linkedtype',
        'linkedorderid',
        'parentorderid',
        'sessionid',
        'params',
        'errorcode',
        'errormessage',
        'errorstack',
        'requester_id',
        'requester_type',
        'requester_identifier_type',
        'requester_identifier_value',
        'requester_mnemoric',
        'reason',
        'reserve1',
        'reserve2',
        'reserve3',
        'reserve4',
        'reserve5',
        'reserve6',
        'reserve7',
        'reserve8',
        'reserve9',
        'reserve10',
        'reserve11',
        'reserve12',
        'reserve13',
        'reserve14',
        'reserve15',
        'reserve16',
        'reserve17',
        'reserve18',
        'reserve19',
        'reserve20',
        'linkedorder_createtime',
        'linkedorder_endtime',
        'load_data_ts',
        'sendingtime_to_partner_sp',
        'receivingtime_from_partner_sp',
        'flowlog',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'orderid' => 'string', // Cast bigint to string for consistency
        'createtime' => 'datetime',
        'lastupddate' => 'datetime',
        'begintime' => 'datetime',
        'endtime' => 'datetime',
        'expiretime' => 'datetime',
        'thirdpartyreqtime' => 'datetime',
        'accesspointreqtime' => 'datetime',
        'businesstype' => 'string',
        'bussinesscategory' => 'integer',
        'exemode' => 'string',
        'completed_reason' => 'integer',
        'procdefversion' => 'integer',
        'businessscope' => 'integer',
        'product_index' => 'integer',
        'service_index' => 'integer',
        'initiator_id' => 'integer',
        'initiator_identifier_type' => 'integer',
        'receiver_id' => 'integer',
        'receiver_identifier_type' => 'integer',
        'primary_party_id' => 'integer',
        'crenetid' => 'integer',
        'linkedorderid' => 'integer',
        'sessionid' => 'integer',
        'reserve7' => 'integer',
        'version' => 'decimal:2',
    ];

    /**
     * Convert the orderid to string when setting
     */
    public function setOrderidAttribute($value)
    {
        $this->attributes['orderid'] = (string) $value;
    }

    /**
     * Get the main transaction record.
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'orderid', 'orderid');
    }

    /**
     * Get the transaction type information.
     */
    public function transactionType()
    {
        return $this->belongsTo(TransactionType::class, 'tranactiontype', 'txn_index');
    }

    /**
     * Get the reason type information.
     */
    public function reasonType()
    {
        return $this->belongsTo(ReasonType::class, 'reason_type', 'reason_index');
    }

    /**
     * Get the transaction type information.
     */

    /**
     * Get the reason type information.
     */

    /**
     * Get the initiator customer.
     */
    public function initiatorCustomer()
    {
        return $this->belongsTo(Customer::class, 'initiator_id', 'customer_id')
            ->where('initiator_type', '1000'); // Customer type
    }

    /**
     * Get the initiator organization.
     */
    public function initiatorOrganization()
    {
        return $this->belongsTo(Organization::class, 'initiator_id', 'biz_org_id')
            ->where('initiator_type', '5000'); // Organization type
    }

    /**
     * Get the receiver customer.
     */
    public function receiverCustomer()
    {
        return $this->belongsTo(Customer::class, 'receiver_id', 'customer_id')
            ->where('receiver_type', '1000'); // Customer type
    }

    /**
     * Get the receiver organization.
     */
    public function receiverOrganization()
    {
        return $this->belongsTo(Organization::class, 'receiver_id', 'biz_org_id')
            ->where('receiver_type', '5000'); // Organization type
    }

    /**
     * Get the primary party customer.
     */
    public function primaryPartyCustomer()
    {
        return $this->belongsTo(Customer::class, 'primary_party_id', 'customer_id')
            ->where('primary_party_type', '1000'); // Customer type
    }

    /**
     * Get the primary party organization.
     */
    public function primaryPartyOrganization()
    {
        return $this->belongsTo(Organization::class, 'primary_party_id', 'biz_org_id')
            ->where('primary_party_type', '5000'); // Organization type
    }

    /**
     * Get the linked transaction.
     */
    public function linkedTransaction()
    {
        return $this->belongsTo(Transaction::class, 'linkedorderid', 'orderid');
    }

    /**
     * Get the parent transaction.
     */
    public function parentTransaction()
    {
        return $this->belongsTo(Transaction::class, 'parentorderid', 'orderid');
    }

    /**
     * Scope for completed orders.
     */
    public function scopeCompleted($query)
    {
        return $query->where('orderstate', 'Completed');
    }

    /**
     * Scope for pending orders.
     */
    public function scopePending($query)
    {
        return $query->where('orderstate', 'Pending');
    }

    /**
     * Scope for failed orders.
     */
    public function scopeFailed($query)
    {
        return $query->where('orderstate', 'Failed');
    }

    /**
     * Scope for orders by business type.
     */
    public function scopeByBusinessType($query, $type)
    {
        return $query->where('businesstype', $type);
    }

    /**
     * Scope for orders by channel.
     */
    public function scopeByChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope for orders by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('createtime', [$startDate, $endDate]);
    }

    /**
     * Scope for orders with errors.
     */
    public function scopeWithErrors($query)
    {
        return $query->whereNotNull('errorcode')
                     ->where('errorcode', '!=', '')
                     ->where('errorcode', '!=', 'NULL');
    }

    /**
     * Get formatted order duration.
     */
    public function getOrderDurationAttribute()
    {
        if ($this->begintime && $this->endtime) {
            return $this->begintime->diffInSeconds($this->endtime);
        }
        return null;
    }

    /**
     * Get order status color for UI.
     */
    public function getOrderStatusColorAttribute()
    {
        return match($this->orderstate) {
            'Completed' => 'green',
            'Pending' => 'yellow',
            'Failed' => 'red',
            'Cancelled' => 'gray',
            'Suspended' => 'orange',
            default => 'gray'
        };
    }

    /**
     * Check if order has errors.
     */
    public function hasErrors()
    {
        return !empty($this->errorcode) &&
               $this->errorcode !== 'NULL' &&
               $this->errorcode !== null;
    }

    /**
     * Get the processing time in seconds.
     */
    public function getProcessingTimeAttribute()
    {
        if ($this->createtime && $this->endtime) {
            return $this->createtime->diffInSeconds($this->endtime);
        }
        return null;
    }

    /**
     * Check if this is a third-party transaction.
     */
    public function isThirdPartyTransaction()
    {
        return !empty($this->thirdpartyid) && $this->thirdpartyid !== 'NULL';
    }

    /**
     * Get the business type name.
     */
    public function getBusinessTypeNameAttribute()
    {
        return match($this->businesstype) {
            '0' => 'Standard Transaction',
            '1' => 'Money Transfer',
            '2' => 'Bill Payment',
            '3' => 'Balance Inquiry',
            '4' => 'Account Management',
            default => 'Unknown'
        };
    }

    /**
     * Get the channel name.
     */
    public function getChannelNameAttribute()
    {
        return match($this->channel) {
            'USSD' => 'USSD',
            'SMS' => 'SMS',
            'WEB' => 'Web Portal',
            'MOBILE' => 'Mobile App',
            'ATM' => 'ATM',
            'POS' => 'Point of Sale',
            'API' => 'API',
            default => $this->channel ?: 'Unknown'
        };
    }
}
