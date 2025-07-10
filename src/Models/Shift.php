<?php

namespace webdophp\WebkassaIntegration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a Shift entity.
 *
 * This class is an Eloquent model that corresponds to a shift in the system.
 * It includes attributes such as `cashbox_id`, `shift_number`, `open_date`,
 * and `close_date`, and defines relationships to other models.
 */
class Shift extends Model
{
    protected $fillable = [
        'cashbox_id',
        'shift_number',
        'open_date',
        'close_date'
    ];

    protected $casts = [
        'open_date' => 'datetime',
        'close_date' => 'datetime',
    ];

    /**
     * Defines a one-to-many relationship with the Ticket model.
     *
     * @return HasMany
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }


    /**
     * Define a relationship to the Cashbox model.
     *
     * @return BelongsTo
     */
    public function cashbox(): BelongsTo
    {
        return $this->belongsTo(Cashbox::class);
    }
}