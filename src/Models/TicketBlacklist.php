<?php

namespace webdophp\WebkassaIntegration\Models;

use Illuminate\Database\Eloquent\Model;

class TicketBlacklist extends Model
{
    protected $fillable = [
        'number',
        'comment',
    ];


    /**
     * @param string $number
     * @return bool
     */
    public static function isBlacklisted(string $number): bool
    {
        return cache()->remember(
            "ticket_blacklist_{$number}",
            3600, // кеш 1 час
            fn() => self::where('number', $number)->exists()
        );
    }

}
