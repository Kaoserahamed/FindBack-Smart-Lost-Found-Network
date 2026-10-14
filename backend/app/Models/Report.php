<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'uuid',
    'user_id',
    'community_id',
    'category_id',
    'type',
    'title',
    'description',
    'color',
    'brand',
    'event_at',
    'location_name',
    'latitude',
    'longitude',
    'status',
    'expires_at',
])]
class Report extends Model
{
    /** @use HasFactory<\Database\Factories\ReportFactory> */
    use HasFactory;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Report $report) {
            if (empty($report->uuid)) {
                $report->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_at' => 'datetime',
            'expires_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * The user that filed the report.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The community the report belongs to.
     *
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * The category the report belongs to.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The photos attached to the report.
     *
     * @return HasMany<ReportPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ReportPhoto::class);
    }

    /**
     * The matches where this report is the lost item.
     *
     * @return HasMany<ItemMatch, $this>
     */
    public function lostMatches(): HasMany
    {
        return $this->hasMany(ItemMatch::class, 'lost_report_id');
    }

    /**
     * The matches where this report is the found item.
     *
     * @return HasMany<ItemMatch, $this>
     */
    public function foundMatches(): HasMany
    {
        return $this->hasMany(ItemMatch::class, 'found_report_id');
    }

    /**
     * The claims made against the report.
     *
     * @return HasMany<Claim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }

    /**
     * The audit-trail events for the report.
     *
     * @return HasMany<ItemEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ItemEvent::class);
    }
}
