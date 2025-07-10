<?php

namespace webdophp\WebkassaIntegration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a Ticket model in the system.
 *
 * This class defines the structure and relationships for a ticket entity,
 * including fields such as shift information, ticket details, and financial data.
 *
 * Constants are provided to outline operation and payment types, enabling
 * clarity and standardization in working with ticket data.
 *
 * Relationships to other models are included, establishing connections to:
 * - The Shift model, indicating the shift to which a ticket belongs.
 * - The TicketPayment model, representing payments associated with the ticket.
 * - The TicketPosition model, detailing positions or items linked to the ticket.
 */
class Ticket extends Model
{
    protected $fillable = [
        'shift_id',
        'number',
        'order_number',
        'date',
        'operation_type',
        'operation_type_text',
        'total',
        'discount',
        'markup',
        'sent_data',
        'date_sent_data',
        'received_data'
    ];


    protected $casts = [
        'date' => 'datetime',
        'date_sent_data' => 'datetime',
        'received_data' => 'boolean',
    ];


    public const array PAYMENT_TYPES = [
        'Наличные' => 0,
        'Банковская карта' => 1,
        'Оплата в кредит' => 2,
        'Оплата тарой' => 3,
    ];

    /**
     * Defines a relationship indicating that the current model belongs to a Shift model.
     *
     * @return BelongsTo
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * Defines a one-to-many relationship with the TicketPayment model.
     *
     * This method indicates that the current model can have multiple related
     * TicketPayment records, defining the relational mapping.
     *
     * @return HasMany
     */
    public function payments(): HasMany
    {
        return $this->hasMany(TicketPayment::class);
    }

    /**
     * Establishes a one-to-many relationship with the TicketPosition model.
     *
     * This method signifies that the current model can be associated with multiple
     * TicketPosition records, defining the relationship structure.
     *
     * @return HasMany
     */
    public function positions(): HasMany
    {
        return $this->hasMany(TicketPosition::class);
    }
}