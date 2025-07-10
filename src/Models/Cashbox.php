<?php

namespace webdophp\WebkassaIntegration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a cashbox entity with attributes and relationships.
 *
 * Provides functionality to define and manage a cashbox, including
 * its relationships with other models such as shifts.
 */
class Cashbox extends Model
{
    protected $fillable = [
        'cashbox_unique_number',
        'xin',
        'organization_name'
    ];

    /**
     * Defines a one-to-many relationship with the Shift model.
     *
     * @return HasMany
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }
}