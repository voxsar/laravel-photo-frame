<?php

namespace Database\Seeders;

use App\Models\PhotoFrame;
use Illuminate\Database\Seeder;

class PhotoFrameSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (PhotoFrame::count() === 0) {
            PhotoFrame::create([
                'name'         => 'Default Frame',
                'frame_path'   => null,
                'is_active'    => false,
                'anchor_point' => 'center',
            ]);

            $this->command->info('Default photo frame placeholder created. Upload a frame image via the admin panel.');
        }
    }
}
