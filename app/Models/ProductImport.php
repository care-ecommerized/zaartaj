<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ProductImport extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'original_filename',
        'disk',
        'path',
        'status',
        'rows_read',
        'products_created',
        'products_updated',
        'images_queued',
        'failures',
        'error',
        'started_at',
        'finished_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ProductImportRow::class)->orderBy('line_number');
    }

    /**
     * Absolute path to the uploaded CSV, for the streaming reader.
     */
    public function absolutePath(): string
    {
        return Storage::disk($this->disk)->path($this->path);
    }

    /**
     * Record a rejected row without aborting the rest of the import.
     *
     * @param  array<string, mixed>  $context
     */
    public function recordFailure(int $lineNumber, string $message, ?string $handle = null, array $context = []): void
    {
        $this->rows()->create([
            'line_number' => $lineNumber,
            'handle' => $handle,
            'message' => $message,
            'context' => $context ?: null,
        ]);

        $this->increment('failures');
    }
}
