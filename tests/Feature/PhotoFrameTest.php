<?php

namespace Tests\Feature;

use App\Models\FrameOutput;
use App\Models\PhotoFrame;
use App\Services\PhotoFrameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoFrameTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function active_frame_endpoint_returns_null_when_no_active_frame(): void
    {
        $response = $this->getJson('/api/active-frame');

        $response->assertOk()
            ->assertJson(['frame' => null]);
    }

    /** @test */
    public function active_frame_endpoint_returns_active_frame(): void
    {
        Storage::fake('spaces');

        PhotoFrame::create([
            'name'         => 'Test Frame',
            'frame_path'   => 'frames/test.png',
            'is_active'    => true,
            'anchor_point' => 'center',
        ]);

        $response = $this->getJson('/api/active-frame');

        $response->assertOk()
            ->assertJsonStructure([
                'frame' => ['id', 'name', 'anchor_point', 'frame_url'],
            ])
            ->assertJsonPath('frame.name', 'Test Frame')
            ->assertJsonPath('frame.anchor_point', 'center');
    }

    /** @test */
    public function process_image_requires_an_image_file(): void
    {
        $response = $this->postJson('/api/process-image', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);
    }

    /** @test */
    public function process_image_returns_422_when_no_active_frame(): void
    {
        Storage::fake('spaces');

        $response = $this->postJson('/api/process-image', [
            'image' => UploadedFile::fake()->image('photo.jpg', 200, 200),
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error', fn ($v) => str_contains($v, 'active'));
    }

    /** @test */
    public function process_image_stores_outputs_and_returns_urls(): void
    {
        Storage::fake('spaces');

        // Create a real 100×100 PNG frame in fake storage.
        $framePng = $this->createPng(100, 100);
        Storage::disk('spaces')->put('frames/frame.png', $framePng);

        PhotoFrame::create([
            'name'         => 'My Frame',
            'frame_path'   => 'frames/frame.png',
            'is_active'    => true,
            'anchor_point' => 'center',
        ]);

        $response = $this->postJson('/api/process-image', [
            'image' => UploadedFile::fake()->image('DSCE123.jpg', 200, 200),
        ]);

        $response->assertOk()
            ->assertJsonStructure(['id', 'original_filename', 'fill_url', 'contain_url']);

        $output = FrameOutput::first();
        $this->assertNotNull($output);
        $this->assertEquals('DSCE123.jpg', $output->original_filename);

        // Paths should follow the naming convention: {id}_{basename}_{mode}.{ext}
        $this->assertStringStartsWith("{$output->id}_DSCE123_fill", basename($output->fill_path));
        $this->assertStringStartsWith("{$output->id}_DSCE123_contain", basename($output->contain_path));

        Storage::disk('spaces')->assertExists($output->fill_path);
        Storage::disk('spaces')->assertExists($output->contain_path);
    }

    /** @test */
    public function photo_frame_model_anchor_points_returns_expected_keys(): void
    {
        $keys = array_keys(PhotoFrame::anchorPoints());

        $this->assertContains('center', $keys);
        $this->assertContains('top-left', $keys);
        $this->assertContains('bottom-right', $keys);
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    /**
     * Create a minimal valid PNG binary for a given size.
     */
    private function createPng(int $width, int $height): string
    {
        $im = imagecreatetruecolor($width, $height);
        imagesavealpha($im, true);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $transparent);
        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        imagedestroy($im);
        return $png;
    }
}
