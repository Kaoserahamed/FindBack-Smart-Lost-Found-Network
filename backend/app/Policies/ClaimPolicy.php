<?php

namespace App\Policies;

use App\Models\Claim;
use App\Models\CommunityMembership;
use App\Models\User;

class ClaimPolicy
{
    /**
     * Determine whether the user can review (approve/reject) the claim.
     *
     * A claim may be reviewed by:
     *   - the reporter of the found item, or
     *   - an active moderator of the report's community.
     *
     * The claimant can never review their own claim.
     */
    public function review(User $user, Claim $claim): bool
    {
        // The claimant may not review their own claim.
        if ($claim->claimant_id === $user->id) {
            return false;
        }

        $report = $claim->report;

        if (! $report) {
            return false;
        }

        // The reporter of the found item may review claims against it.
        if ($report->user_id === $user->id) {
            return true;
        }

        // Otherwise, an active moderator of that community may review.
        return CommunityMembership::query()
            ->where('community_id', $report->community_id)
            ->where('user_id', $user->id)
            ->where('role', 'moderator')
            ->where('status', 'active')
            ->exists();
    }
}
