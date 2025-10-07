<?php

use App\Services\PhotoStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear cache before each test
    Cache::flush();

    // Fake the S3 storage
    Storage::fake('s3');

    $this->service = new PhotoStorageService();
});

describe('PhotoStorageService', function () {
    test('uploadStrangerPhoto uploads to S3 and returns path', function () {
        $base64Photo = base64_encode(UploadedFile::fake()->image('stranger.jpg')->get());
        $deviceId = 'DEVICE001';

        $path = $this->service->uploadStrangerPhoto($base64Photo, $deviceId);

        expect($path)->toStartWith('strangers/photos/')
            ->and($path)->toContain($deviceId);

        // Verify file was uploaded
        Storage::disk('s3')->assertExists($path);
    });

    test('uploadStrangerPhoto handles different image types', function () {
        $pngPhoto = base64_encode(UploadedFile::fake()->image('stranger.png')->get());
        $deviceId = 'DEVICE002';

        $path = $this->service->uploadStrangerPhoto($pngPhoto, $deviceId);

        expect($path)->toEndWith('.png');
        Storage::disk('s3')->assertExists($path);
    });

    test('getSignedUrl returns temporary URL', function () {
        // Upload a file first
        $base64Photo = base64_encode(UploadedFile::fake()->image('stranger.jpg')->get());
        $path = $this->service->uploadStrangerPhoto($base64Photo, 'DEVICE001');

        $url = $this->service->getSignedUrl($path);

        expect($url)->toBeString()
            ->and($url)->not->toBeEmpty();
    });

    test('getSignedUrl caches URL for 30 minutes', function () {
        // Upload a file first
        $base64Photo = base64_encode(UploadedFile::fake()->image('stranger.jpg')->get());
        $path = $this->service->uploadStrangerPhoto($base64Photo, 'DEVICE001');

        // First call - should cache
        $url1 = $this->service->getSignedUrl($path);

        // Second call - should return cached
        $url2 = $this->service->getSignedUrl($path);

        expect($url1)->toBe($url2);

        // Verify cache exists
        $cacheKey = 'signed_url:' . md5($path);
        expect(Cache::has($cacheKey))->toBeTrue();
    });

    test('getSignedUrl cache expires after 30 minutes', function () {
        // Skip if not using Redis cache
        if (! config('cache.default') === 'redis') {
            $this->markTestSkipped('This test requires Redis cache');
        }

        // Upload a file first
        $base64Photo = base64_encode(UploadedFile::fake()->image('stranger.jpg')->get());
        $path = $this->service->uploadStrangerPhoto($base64Photo, 'DEVICE001');

        // Get signed URL
        $this->service->getSignedUrl($path);

        // Verify cache TTL is 30 minutes (1800 seconds)
        $cacheKey = 'signed_url:' . md5($path);
        $ttl = Cache::getStore()->getRedis()->ttl(
            config('cache.prefix') . ':' . $cacheKey
        );

        expect($ttl)->toBeGreaterThan(1790) // Allow some variance
            ->and($ttl)->toBeLessThanOrEqual(1800);
    })->skip();

    test('deletePhoto removes file from S3', function () {
        // Upload a file first
        $base64Photo = base64_encode(UploadedFile::fake()->image('stranger.jpg')->get());
        $path = $this->service->uploadStrangerPhoto($base64Photo, 'DEVICE001');

        // Verify file exists
        Storage::disk('s3')->assertExists($path);

        // Delete the file
        $result = $this->service->deletePhoto($path);

        expect($result)->toBeTrue();
        Storage::disk('s3')->assertMissing($path);
    });

    test('deletePhoto removes cached signed URL', function () {
        // Upload a file first
        $base64Photo = base64_encode(UploadedFile::fake()->image('stranger.jpg')->get());
        $path = $this->service->uploadStrangerPhoto($base64Photo, 'DEVICE001');

        // Get signed URL to cache it
        $this->service->getSignedUrl($path);

        $cacheKey = 'signed_url:' . md5($path);
        expect(Cache::has($cacheKey))->toBeTrue();

        // Delete the file
        $this->service->deletePhoto($path);

        // Verify cache was cleared
        expect(Cache::has($cacheKey))->toBeFalse();
    });

    test('deletePhoto returns false for non-existent file', function () {
        $result = $this->service->deletePhoto('strangers/photos/nonexistent.jpg');

        expect($result)->toBeFalse();
    });

    test('uploadStrangerPhoto throws exception for invalid base64', function () {
        $this->service->uploadStrangerPhoto('invalid-base64', 'DEVICE001');
    })->throws(\InvalidArgumentException::class);
});
