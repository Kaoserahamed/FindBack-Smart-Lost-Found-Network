<?php

namespace Database\Seeders;

use App\Models\Community;
use App\Models\CommunityMembership;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommunityMembershipsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Enrolls the seeded test user(s) into the default SUST community as active
     * members so they can immediately create reports in the ReportCreate form
     * (which lists only communities the user is an active member of).
     */
    public function run(): void
    {
        $community = Community::where('slug', 'sust')->first();

        if (! $community) {
            $this->command?->warn('CommunityMembershipsTableSeeder: SUST community not found; skipping.');

            return;
        }

        // Enroll the default test user as an active member.
        $testUser = User::where('email', 'test@example.com')->first();

        if ($testUser) {
            CommunityMembership::updateOrCreate(
                ['community_id' => $community->id, 'user_id' => $testUser->id],
                ['role' => 'member', 'status' => 'active'],
            );
        }

        $this->command?->info('Memberships seeded: '.CommunityMembership::count().' total.');
    }
}
