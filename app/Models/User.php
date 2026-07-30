<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * Note `is_admin` is deliberately absent: registration and profile updates both
     * mass-assign from request input, and an admin flag in this list would let anyone
     * grant themselves the dashboard. Promote through a console command or a seeder.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function productImports(): HasMany
    {
        return $this->hasMany(ProductImport::class);
    }

    /**
     * The orders this customer has placed, most recent first.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest('placed_at');
    }

    /**
     * The customer's saved delivery addresses.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * The address the customer marked as their default, if any.
     */
    public function defaultAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('is_default', true);
    }

    /**
     * Claim any guest orders placed with this user's email address.
     *
     * Design-safe: only ever called after email verification (so the address is
     * proven to belong to this account), and gated on a verified email by the
     * caller. Backfills user_id on matching guest orders. Returns the number of
     * orders linked.
     */
    public function linkGuestOrders(): int
    {
        if (! $this->hasVerifiedEmail() || $this->email === null) {
            return 0;
        }

        return Order::query()
            ->whereNull('user_id')
            ->where('customer_email', $this->email)
            ->update(['user_id' => $this->id]);
    }
}
