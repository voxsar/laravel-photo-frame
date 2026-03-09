<?php

namespace App\Http\Controllers;

use App\Models\FrameOutput;
use App\Services\PhotoFrameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PhotoFrameController extends Controller
{
    public function __construct(private PhotoFrameService $service) {}

    /**
     * Process an uploaded image with the active photo frame.
     */
    public function process(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpeg,png,gif,webp', 'max:20480'],
        ]);

        $file = $request->file('image');
        $originalFilename = $file->getClientOriginalName();
        $tmpPath = $file->getRealPath();

        try {
            $output = $this->service->process($tmpPath, $originalFilename);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'id'               => $output->id,
            'original_filename' => $output->original_filename,
            'fill_url'         => Storage::disk('spaces')->url($output->fill_path),
            'contain_url'      => Storage::disk('spaces')->url($output->contain_path),
            'fill_download_url' => url("/api/frame-outputs/{$output->id}/download/fill"),
            'contain_download_url' => url("/api/frame-outputs/{$output->id}/download/contain"),
        ]);
    }

    /**
     * Force-download one of the generated output variants.
     */
    public function download(FrameOutput $frameOutput, string $variant): StreamedResponse
    {
        if (! in_array($variant, ['fill', 'contain'], true)) {
            abort(404);
        }

        $path = $variant === 'fill' ? $frameOutput->fill_path : $frameOutput->contain_path;

        if (! $path || ! Storage::disk('spaces')->exists($path)) {
            abort(404);
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg';
        $baseName = pathinfo($frameOutput->original_filename, PATHINFO_FILENAME) ?: 'photo';
        $downloadName = $baseName . '_' . $variant . '.' . $extension;
        $mimeType = Storage::disk('spaces')->mimeType($path) ?: 'application/octet-stream';

        return response()->streamDownload(function () use ($path) {
            $stream = Storage::disk('spaces')->readStream($path);

            if ($stream === false) {
                abort(404);
            }

            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, $downloadName, [
            'Content-Type' => $mimeType,
        ]);
    }

    /**
     * Return the active photo frame info for the frontend.
     */
    public function activeFrame(): JsonResponse
    {
        $frame = \App\Models\PhotoFrame::where('is_active', true)->first();

        if (! $frame) {
            return response()->json(['frame' => null]);
        }

        return response()->json([
            'frame' => [
                'id'           => $frame->id,
                'name'         => $frame->name,
                'anchor_point' => $frame->anchor_point,
                'frame_url'    => $frame->frame_path
                    ? Storage::disk('spaces')->url($frame->frame_path)
                    : null,
            ],
        ]);
    }
}
