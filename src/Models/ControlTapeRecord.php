<?php

namespace webdophp\WebkassaIntegration\Models;

use Illuminate\Database\Eloquent\Model;

class ControlTapeRecord extends Model
{
    protected $fillable = [
        'cashbox_unique_number',
        'shift_number',
        'operation_type',
        'sum',
        'date',
        'employee_code',
        'is_offline',
        'number',
        'sent_data',
        'date_sent_data',
        'received_data',
    ];
}
