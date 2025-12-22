<?php

namespace webdophp\WebkassaIntegration\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Represents a model for the repeated_tickets table.
 *
 * Provides properties for 'from' and 'to' fields,
 * and ensures they are cast as dates.
 */
class RepeatedTicket extends Model
{


    protected $fillable = [
        'login',
        'from',
        'to',
    ];

    protected $casts = [
        'from' => 'date',
        'to'   => 'date',
    ];
}
