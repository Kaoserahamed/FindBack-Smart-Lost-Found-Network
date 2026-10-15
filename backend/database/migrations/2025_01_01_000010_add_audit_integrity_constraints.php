<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Integrity constraints added following the security audit.
 *
 * These are database-level guarantees that back up the application-level state
 * machine, so a bug (or a future code change) cannot violate them:
 *
 *  1. A claimant may hold only ONE live claim per report, which stops a user
 *     from stacking duplicate claims (previously unbounded).
 *  2. A report may have only ONE approved/completed claim, which guarantees a
 *     single item can never be handed to two different people.
 *  3. A composite index matching the matching engine's candidate query.
 *
 * Constraints 1 and 2 are PostgreSQL partial unique indexes so that terminal
 * states (rejected) stay excluded and legitimate retries remain possible.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. One live claim per claimant per report. Mirrors ACTIVE_STATUSES in
        //    ManageClaimAction; partial so a rejected claimant may re-apply.
        DB::statement(
            "CREATE UNIQUE INDEX claims_one_active_per_claimant
             ON claims (report_id, claimant_id)
             WHERE status IN ('submitted', 'under_review', 'approved')"
        );

        // 2. At most one winning claim per report.
        DB::statement(
            "CREATE UNIQUE INDEX claims_one_winner_per_report
             ON claims (report_id)
             WHERE status IN ('approved', 'completed')"
        );

        // 3. Composite index for the matching engine's candidate lookup.
        Schema::table('reports', function (Blueprint $table) {
            $table->index(
                ['community_id', 'category_id', 'type', 'status'],
                'reports_matching_candidate_index'
            );
        });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS claims_one_winner_per_report');
        DB::statement('DROP INDEX IF EXISTS claims_one_active_per_claimant');

        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex('reports_matching_candidate_index');
        });
    }
};
