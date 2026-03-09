<?php

namespace App\Services;

use App\Models\FrameOutput;
use App\Models\PhotoFrame;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class PhotoFrameService
{
    /**
     * Process an uploaded image with the active photo frame.
     * Produces two outputs: fill (cover) and contain.
     *
     * @param  string  $uploadedImagePath  Absolute path of the uploaded image.
     * @param  string  $originalFilename   Original file name as supplied by the user.
     * @return FrameOutput
     *
     * @throws \RuntimeException
     */
    public function process(string $uploadedImagePath, string $originalFilename): FrameOutput
    {
        $frame = PhotoFrame::where('is_active', true)->first();

        if (! $frame) {
            throw new \RuntimeException('No active photo frame found. Please configure one in the admin panel.');
        }

        if (empty($frame->frame_path)) {
            throw new \RuntimeException('Active photo frame has no image configured.');
        }

        // Download the frame from DigitalOcean Spaces to a local temp file.
        $frameTmp = tempnam(sys_get_temp_dir(), 'frame_') . '.png';
        file_put_contents($frameTmp, Storage::disk('spaces')->get($frame->frame_path));

        $frameImg = Image::read($frameTmp);
        $frameWidth  = $frameImg->width();
        $frameHeight = $frameImg->height();

        // Build the output record first to get its auto-incremented ID.
        $output = FrameOutput::create([
            'photo_frame_id'   => $frame->id,
            'original_filename' => $originalFilename,
        ]);

        $baseName   = pathinfo($originalFilename, PATHINFO_FILENAME);
        $ext        = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION) ?: 'jpg');
        $filePrefix = $output->id . '_' . $baseName;

        // Produce the fill output.
        $fillPath = 'outputs/' . $filePrefix . '_fill.' . $ext;
        $fillResult = $this->applyFill(
            $uploadedImagePath,
            $frameTmp,
            $frameWidth,
            $frameHeight,
            $frame->anchor_point ?? 'center',
            $ext
        );
        Storage::disk('spaces')->put($fillPath, $fillResult, 'public');

        // Produce the contain output.
        $containPath = 'outputs/' . $filePrefix . '_contain.' . $ext;
        $containResult = $this->applyContain(
            $uploadedImagePath,
            $frameTmp,
            $frameWidth,
            $frameHeight,
            $ext
        );
        Storage::disk('spaces')->put($containPath, $containResult, 'public');

        @unlink($frameTmp);

        $output->update([
            'fill_path'    => $fillPath,
            'contain_path' => $containPath,
        ]);

        return $output;
    }

    /**
     * Create a fill (cover) composite: the photo fills the frame canvas,
     * cropped according to the anchor point, then the frame is overlaid on top.
     */
    private function applyFill(
        string $photoPath,
        string $framePath,
        int    $frameWidth,
        int    $frameHeight,
        string $anchorPoint,
        string $ext
    ): string {
        $photo = Image::read($photoPath);

        // Scale the photo so that its smallest dimension covers the frame, then crop.
        $photo->cover($frameWidth, $frameHeight, $this->mapAnchor($anchorPoint));

        // Overlay the frame on top.
        $frame = Image::read($framePath);
        $photo->place($frame, 'top-left', 0, 0);

        return (string) $photo->encodeByExtension($ext);
    }

    /**
     * Create a contain composite: the photo is scaled so that it fits entirely
     * within the frame canvas (letterboxed), then the frame is overlaid on top.
     */
    private function applyContain(
        string $photoPath,
        string $framePath,
        int    $frameWidth,
        int    $frameHeight,
        string $ext
    ): string {
        // Start with a transparent (or white) canvas the size of the frame.
        $canvas = Image::create($frameWidth, $frameHeight);

        $photo = Image::read($photoPath);

        // Scale the photo down if it is larger than the frame. Never upscale.
        $photoWidth  = $photo->width();
        $photoHeight = $photo->height();

        if ($photoWidth > $frameWidth || $photoHeight > $frameHeight) {
            $photo->scaleDown($frameWidth, $frameHeight);
        }

        // Center the (possibly resized) photo on the canvas.
        $offsetX = (int) (($frameWidth  - $photo->width())  / 2);
        $offsetY = (int) (($frameHeight - $photo->height()) / 2);

        $canvas->place($photo, 'top-left', $offsetX, $offsetY);

        // Overlay the frame.
        $frame = Image::read($framePath);
        $canvas->place($frame, 'top-left', 0, 0);

        return (string) $canvas->encodeByExtension($ext);
    }

    /**
     * Map anchor point string to Intervention Image v3 position string.
     */
    private function mapAnchor(string $anchor): string
    {
        $map = [
            'top-left'     => 'top-left',
            'top'          => 'top',
            'top-right'    => 'top-right',
            'left'         => 'left',
            'center'       => 'center',
            'right'        => 'right',
            'bottom-left'  => 'bottom-left',
            'bottom'       => 'bottom',
            'bottom-right' => 'bottom-right',
        ];

        return $map[$anchor] ?? 'center';
    }
}
