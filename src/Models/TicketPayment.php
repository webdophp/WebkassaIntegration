<?php

namespace webdophp\WebkassaIntegration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a payment made for a ticket.
 *
 * This model manages the details of a payment associated with a specific ticket,
 * including the payment type and amount.
 */
class TicketPayment extends Model
{
    protected $fillable = [
        'ticket_id',
        'sum',
        'payment_type',
        'payment_type_name'
    ];

    /**
     * Establishes a relationship indicating that this model belongs to a Ticket.
     *
     * @return BelongsTo
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}