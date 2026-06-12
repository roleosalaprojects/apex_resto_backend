<?php

namespace App\Http\Controllers\API\v1\second_screen;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaStreamController extends Controller
{
    /**
     * Stream video file with proper byte-range support
     */
    public function stream(Request $request, string $filename)
    {
        $path = public_path('media/advertisements/' . $filename);

        if (!file_exists($path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $fileSize = filesize($path);
        $mimeType = $this->getMimeType($filename);

        // Check if this is a range request
        $range = $request->header('Range');

        if ($range) {
            return $this->streamRangeResponse($path, $fileSize, $mimeType, $range);
        }

        // Full file response
        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Content-Length' => $fileSize,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Handle range request for video streaming
     */
    private function streamRangeResponse(string $path, int $fileSize, string $mimeType, string $range): StreamedResponse
    {
        // Parse range header (e.g., "bytes=0-1023")
        preg_match('/bytes=(\d+)-(\d*)/', $range, $matches);

        $start = intval($matches[1]);
        $end = isset($matches[2]) && $matches[2] !== '' ? intval($matches[2]) : $fileSize - 1;

        // Ensure valid range
        if ($start > $end || $start >= $fileSize) {
            return response()->stream(function () {}, 416, [
                'Content-Range' => "bytes */$fileSize",
            ]);
        }

        $end = min($end, $fileSize - 1);
        $length = $end - $start + 1;

        $response = new StreamedResponse(function () use ($path, $start, $length) {
            $handle = fopen($path, 'rb');
            fseek($handle, $start);

            $chunkSize = 8192; // 8KB chunks
            $remaining = $length;

            while ($remaining > 0 && !feof($handle)) {
                $readSize = min($chunkSize, $remaining);
                echo fread($handle, $readSize);
                $remaining -= $readSize;
                flush();
            }

            fclose($handle);
        }, 206);

        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Content-Length', $length);
        $response->headers->set('Content-Range', "bytes $start-$end/$fileSize");
        $response->headers->set('Accept-Ranges', 'bytes');
        $response->headers->set('Cache-Control', 'public, max-age=86400');

        return $response;
    }

    /**
     * Get MIME type based on file extension
     */
    private function getMimeType(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/octet-stream',
        };
    }
}
