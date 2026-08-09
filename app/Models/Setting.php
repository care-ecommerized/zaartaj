<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * A row in the tiny key/value `settings` store.
 *
 * The whole table is memoised under one cache key so reads are a single array
 * lookup; every write forgets it. DB access is guarded so an unmigrated table
 * simply yields defaults rather than breaking a render.
 */
class Setting extends Model
{
    /**
     * The string key is the primary key, not an autoincrement id.
     */
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['key', 'value'];

    /**
     * The cache key the whole [key => value] map lives under.
     */
    private const CACHE_KEY = 'app.settings';

    /**
     * The entire settings map, memoised forever until a write forgets it.
     *
     * @return array<string, string|null>
     */
    public static function map(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            try {
                return static::query()->pluck('value', 'key')->all();
            } catch (Throwable) {
                return [];
            }
        });
    }

    /**
     * The stored value for a key, or the default when it is absent.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::map()[$key] ?? $default;
    }

    /**
     * The stored values for many keys, falling back per key to `$defaults`.
     *
     * @param  list<string>  $keys
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public static function many(array $keys, array $defaults = []): array
    {
        $map = static::map();

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $map[$key] ?? ($defaults[$key] ?? null);
        }

        return $result;
    }

    /**
     * Upsert a single key, then forget the cached map.
     */
    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        static::flush();
    }

    /**
     * Upsert many keys in one transaction, forgetting the cache once.
     *
     * @param  array<string, mixed>  $pairs
     */
    public static function setMany(array $pairs): void
    {
        DB::transaction(function () use ($pairs): void {
            foreach ($pairs as $key => $value) {
                static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        });

        static::flush();
    }

    /**
     * Forget the cached map so the next read reloads it from the table.
     */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
