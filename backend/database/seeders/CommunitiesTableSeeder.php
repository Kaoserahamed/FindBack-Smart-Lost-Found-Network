<?php

namespace Database\Seeders;

use App\Models\Community;
use Illuminate\Database\Seeder;

class CommunitiesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Default university community: Shahjalal University of Science & Technology.
        Community::updateOrCreate(
            ['slug' => 'sust'],
            [
                'name' => 'Shahjalal University of Science and Technology',
                'type' => 'university',
                'description' => 'The default FindBack community for SUST students, faculty, and staff.',
                'address' => 'University Avenue, Kumargaon, Sylhet 3114, Bangladesh',
                'latitude' => 24.9186000,
                'longitude' => 91.8315000,
                'status' => 'approved',
            ]
        );

        $this->command?->info('Communities seeded: '.Community::count().' total.');
    }
}
