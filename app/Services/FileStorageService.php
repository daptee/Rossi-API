<?php

namespace App\Services;

use Exception;
use Log;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileStorageService
{
    /**
     * Store a file using the configured filesystem disk
     *
     * @param UploadedFile $file
     * @param string $directory
     * @param string|null $filename
     * @return string The stored file path
     */
    public static function storeFile(UploadedFile $file, string $directory, ?string $filename = null): string
    {
        if (!$filename) {
            $filename = uniqid() . '_' . $file->getClientOriginalName();
        }

        $path = $directory . '/' . $filename;

        // Store the file using the configured filesystem disk
        Storage::disk(config('filesystems.default'))->putFileAs($directory, $file, $filename);

        return $path;
    }

    /**
     * Store a file with a custom name using the configured filesystem disk
     *
     * @param UploadedFile $file
     * @param string $path
     * @return string The stored file path
     */
    public static function storeFileAs(UploadedFile $file, string $path): string
    {
        Storage::disk(config('filesystems.default'))->putFileAs(
            dirname($path),
            $file,
            basename($path)
        );

        return $path;
    }

    /**
     * Delete a file using the configured filesystem disk
     *
     * @param string $path
     * @return bool
     */
    public static function deleteFile(string $path): bool
    {
        $fileDeleted = false;
        try {
            $fileDeleted = Storage::disk(config('filesystems.default'))->delete($path);
        } catch (Exception $e) {
            Log::info('fileDeleteError'. $e->getMessage());
            $fileDeleted = false;
        } finally {
            return $fileDeleted;
        }

    }

    /**
     * Check if a file exists using the configured filesystem disk
     *
     * @param string $path
     * @return bool
     */
    public static function fileExists(string $path): bool
    {
        $fileExists = false;
        try {
            $fileExists = Storage::disk(config('filesystems.default'))->exists($path);
        } catch (Exception $e) {
            Log::info('fileExistsError'. $e->getMessage());
            $fileExists = false;
        } finally {
            return $fileExists;
        }
    }

    /**
     * Get the full path for a stored file
     * Note: For URL generation, use Laravel's asset() helper or Storage::url() directly
     *
     * @param string $path
     * @return string
     */
    public static function getFilePath(string $path): string
    {
        return Storage::disk(config('filesystems.default'))->path($path);
    }

    /**
     * Generate a unique filename with timestamp and random string
     *
     * @param UploadedFile $file
     * @param string $prefix
     * @return string
     */
    public static function generateUniqueFilename(UploadedFile $file, string $prefix = ''): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = time();
        $random = Str::random(10);

        return $prefix . $timestamp . '_' . $random . '.' . $extension;
    }

    /**
     * Store multiple files in a directory
     *
     * @param array $files Array of UploadedFile objects
     * @param string $directory
     * @return array Array of stored file paths
     */
    public static function storeMultipleFiles(array $files, string $directory): array
    {
        $storedPaths = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $storedPaths[] = self::storeFile($file, $directory);
            }
        }

        return $storedPaths;
    }

    /**
     * Move a file from one location to another within the same disk
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public static function moveFile(string $from, string $to): bool
    {
        $disk = Storage::disk(config('filesystems.default'));

        if (!$disk->exists($from)) {
            return false;
        }

        $disk->move($from, $to);
        return true;
    }

    /**
     * Copy a file from one location to another within the same disk
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public static function copyFile(string $from, string $to): bool
    {
        $disk = Storage::disk(config('filesystems.default'));

        if (!$disk->exists($from)) {
            return false;
        }

        $disk->copy($from, $to);
        return true;
    }

    /**
     * Get file size in bytes
     *
     * @param string $path
     * @return int
     */
    public static function getFileSize(string $path): int
    {
        return Storage::disk(config('filesystems.default'))->size($path);
    }

    /**
     * Get file's last modified timestamp
     *
     * @param string $path
     * @return int
     */
    public static function getLastModified(string $path): int
    {
        return Storage::disk(config('filesystems.default'))->lastModified($path);
    }
}
