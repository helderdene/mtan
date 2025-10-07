<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PhotoStorageService
{
    /**
     * Upload a base64-encoded stranger photo to S3.
     *
     * @param  string  $base64Photo  Base64-encoded image data
     * @param  string  $deviceId  Device ID for path organization
     * @return string S3 path to the uploaded photo
     * @throws InvalidArgumentException If base64 data is invalid
     */
    public function uploadStrangerPhoto(string $base64Photo, string $deviceId): string
    {
        // Decode base64 image
        $imageData = base64_decode($base64Photo, true);

        if ($imageData === false) {
            throw new InvalidArgumentException('Invalid base64 image data');
        }

        // Detect image type from binary data
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $imageData);
        finfo_close($finfo);

        // Map MIME type to extension
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg', // Default to jpg
        };

        // Generate unique filename with device ID
        $filename = sprintf(
            '%s_%s.%s',
            $deviceId,
            Str::uuid(),
            $extension
        );

        // Upload to S3
        $path = 'strangers/photos/' . $filename;
        Storage::disk('s3')->put($path, $imageData, 'private');

        return $path;
    }

    /**
     * Get a temporary signed URL for viewing a stranger photo.
     *
     * Signed URLs are cached for 30 minutes to reduce S3 API calls.
     *
     * @param  string  $path  S3 path to the photo
     * @param  int  $expirationMinutes  URL expiration time in minutes (default: 60)
     * @return string Temporary signed URL
     */
    public function getSignedUrl(string $path, int $expirationMinutes = 60): string
    {
        $cacheKey = 'signed_url:' . md5($path);

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($path, $expirationMinutes) {
            return Storage::disk('s3')->temporaryUrl(
                $path,
                now()->addMinutes($expirationMinutes)
            );
        });
    }

    /**
     * Delete a stranger photo from S3 and clear its cached signed URL.
     *
     * @param  string  $path  S3 path to the photo
     * @return bool True if deleted successfully, false if file doesn't exist
     */
    public function deletePhoto(string $path): bool
    {
        // Check if file exists
        if (! Storage::disk('s3')->exists($path)) {
            return false;
        }

        // Delete from S3
        Storage::disk('s3')->delete($path);

        // Clear cached signed URL
        $cacheKey = 'signed_url:' . md5($path);
        Cache::forget($cacheKey);

        return true;
    }
}
