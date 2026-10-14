<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lost_report_id', 'found_report_id', 'score', 'reasons', 'status', 'algorithm_version'])]
class ItemMatch extends Model
{
    /**
     * The table associated with the model.
     * Named "ItemMatch" to avoid the reserved PHP keyword "match".
     *
     * @var string
     */
    protected $table = 'matches';

    /** @use HasFactory<\Database\Factories\ItemMatchFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'reasons' => 'array',
        ];
    }

    /**
     * The lost report in the match.
     *
     * @return BelongsTo<Report, $this>
     */
    public function lostReport(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'lost_report_id');
    }

    /**
     * The found report in the match.
     *
     * @return BelongsTo<Report, $this>
     */
    public function foundReport(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'found_report_id');
    }
}
