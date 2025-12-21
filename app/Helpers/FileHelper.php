<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * File Helper
 * 
 * Handles file uploads and storage operations.
 */
class FileHelper
{
    /**
     * Upload a file to storage.
     *
     * @param UploadedFile|null $file
     * @param string $directory
     * @param string $disk
     * @return string|null Returns the file path relative to storage/app/public or null if no file
     */
    public function upload(?UploadedFile $file, string $directory = 'customers', string $disk = 'public'): ?string
    {
        if (!$file || !$file->isValid()) {
            return null;
        }

        // Generate unique filename
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;
        
        // Store file
        $path = $file->storeAs($directory, $filename, $disk);

        // Return path relative to storage/app/public
        return $path;
    }

    /**
     * Delete a file from storage.
     *
     * @param string $path
     * @param string $disk
     * @return bool
     */
    public function delete(string $path, string $disk = 'public'): bool
    {
        if (Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->delete($path);
        }

        return false;
    }
}
