<?php

namespace webdophp\WebkassaIntegration\Models;

use Illuminate\Database\Eloquent\Model;

class CashboxShift extends Model
{
    protected $fillable = [
        'cashbox_unique_number',
        'shift_number',
    ];
}