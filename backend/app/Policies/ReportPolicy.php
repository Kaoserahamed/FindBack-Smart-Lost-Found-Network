<?php

namespace App\Policies;

use App\Models\CommunityMembership;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    /**
     * Determine whether the user may view the report's detail page and matches.
     *
     * A report is visible to:
     *   - its author, or
     *   - an active member of the report's community.
     *
     * This is the object-level authorization gate that was previously missing
     * from the report detail route, which allowed any authenticated user to
     * open any report regardless of community.
     */
    public function view(User $user, Report $report): bool
    {
        if ($report->user_id === $user->id) {
            return true;
        }

        return CommunityMembership::query()
            ->where('community_id', $report->community_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }
}
