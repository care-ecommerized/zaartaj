<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    /**
     * The customer-supplied fields. `user_id` is deliberately absent — an address
     * is always created through the owning user's relation, never mass-assigned.
     *
     * @var list<string>
     */
    protected $fillable = [
        'label',
        'recipient_name',
        'phone',
        'address_line',
        'city',
        'state',
        'postcode',
        'country',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A single-line rendering of the address for compact display.
     */
    public function oneLine(): string
    {
        $parts = array_filter([
            $this->address_line,
            $this->city,
            $this->state,
            $this->postcode,
            $this->country,
        ]);

        return implode(', ', $parts);
    }
}
