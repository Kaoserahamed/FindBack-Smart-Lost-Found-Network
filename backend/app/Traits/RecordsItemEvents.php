<?php

namespace App\Traits;

use App\Models\ItemEvent;
use App\Models\Report;

trait RecordsItemEvents
{
    /**
     * Record an audit-trail event against a report.
     *
     * Provides a single, reusable entry point for writing to the item_events
     * table so controllers and jobs stay consistent. Metadata is stored as
     * JSON; an empty metadata array is persisted as NULL.
     */
    protected function recordItemEvent(
        Report $report,
        string $action,
        ?int $userId = null,
        array $metadata = [],
    ): ItemEvent {
        return $report->events()->create([
            'user_id' => $userId,
            'action' => $action,
            'metadata' => $metadata ?: null,
        ]);
    }
}
