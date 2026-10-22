<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImageUploadService
{
    /**
     * Upload image to S3 (or local storage in dev)
     * Returns the public URL
     */
    public static function upload(UploadedFile $file, string $folder = 'uploads'): string
    {
        $path = $file->store($folder, 'public');

        return Storage::disk('public')->url($path);
    }

    /**
     * Upload restaurant logo
     */
    public static function uploadRestaurantLogo(UploadedFile $file): string
    {
        $filename = 'restaurant-logos/'.uniqid().'.'.$file->getClientOriginalExtension();

        return self::uploadToDisk($file, $filename);
    }

    /**
     * Upload menu item image
     */
    public static function uploadMenuItemImage(UploadedFile $file): string
    {
        $filename = 'menu-items/'.uniqid().'.'.$file->getClientOriginalExtension();

        return self::uploadToDisk($file, $filename);
    }

    /**
     * Upload rider profile photo
     */
    public static function uploadRiderPhoto(UploadedFile $file): string
    {
        $filename = 'rider-photos/'.uniqid().'.'.$file->getClientOriginalExtension();

        return self::uploadToDisk($file, $filename);
    }

    /**
     * Upload user avatar
     */
    public static function uploadUserAvatar(UploadedFile $file): string
    {
        $filename = 'avatars/'.uniqid().'.'.$file->getClientOriginalExtension();

        return self::uploadToDisk($file, $filename);
    }

    /**
     * Delete an image by URL
     */
    public static function delete(string $url): bool
    {
        $path = str_replace(Storage::disk('public')->url(''), '', $url);
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->delete($path);
        }

        return false;
    }

    private static function uploadToDisk(UploadedFile $file, string $filename): string
    {
        $path = $file->storeAs('uploads', $filename, 'public');

        return Storage::disk('public')->url($path);
    }
}
