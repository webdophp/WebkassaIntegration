<?php

namespace webdophp\WebkassaIntegration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a position within a ticket, containing details such as the position name, quantity, price, and applied adjustments.
 *
 * This model defines a relationship to the Ticket model, signifying that each TicketPosition is associated with a specific Ticket.
 */
class TicketPosition extends Model
{
    protected $fillable = [
        'ticket_id',
        'position_name',
        'count',
        'price',
        'discount_tenge',
        'markup',
        'sum'
    ];

    /**
     * Defines a relationship indicating that this model belongs to a Ticket.
     *
     * @return BelongsTo
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}