<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Facades\Storage;

class FileUrlService
{
    /**
     * Generate URL untuk asset
     *
     * @param Asset $asset
     * @return string
     */
    public static function generateAssetUrl(Asset $asset): string
    {
        // Jika asset eksternal, gunakan URL eksternal
        if ($asset->is_external) {
            return $asset->file_url;
        }

        // Jika file_path tidak ada, gunakan URL default
        if (!$asset->file_path) {
            return self::getDefaultImageUrl();
        }

        // Cek apakah file ada di storage
        if (!Storage::disk('public')->exists($asset->file_path)) {
            return self::getDefaultImageUrl();
        }

        // Gunakan endpoint API untuk serve image (lebih aman dan konsisten)
        return url('/api/assets/' . $asset->id . '/serve');
    }

    /**
     * Generate URL untuk file berdasarkan path
     *
     * @param string $filePath
     * @return string
     */
    public static function generateFileUrl(string $filePath): string
    {
        // Jika path kosong, gunakan URL default
        if (empty($filePath)) {
            return self::getDefaultImageUrl();
        }

        // Cek apakah file ada di storage
        if (!Storage::disk('public')->exists($filePath)) {
            return self::getDefaultImageUrl();
        }

        // Generate URL menggunakan route API
        return url('/api/files/' . urlencode($filePath));
    }

    /**
     * Generate URL untuk file menggunakan storage URL (fallback)
     *
     * @param string $filePath
     * @return string
     */
    public static function generateStorageUrl(string $filePath): string
    {
        // Jika path kosong, gunakan URL default
        if (empty($filePath)) {
            return self::getDefaultImageUrl();
        }

        // Cek apakah file ada di storage
        if (!Storage::disk('public')->exists($filePath)) {
            return self::getDefaultImageUrl();
        }

        // Generate URL menggunakan Storage facade
        return Storage::url($filePath);
    }

    /**
     * Get default image URL
     *
     * @return string
     */
    public static function getDefaultImageUrl(): string
    {
        return 'https://via.placeholder.com/400x300?text=Image+Not+Found';
    }

    /**
     * Validate file path untuk keamanan
     *
     * @param string $filePath
     * @return bool
     */
    public static function isValidPath(string $filePath): bool
    {
        // Cek path traversal
        if (str_contains($filePath, '..') || str_contains($filePath, '//')) {
            return false;
        }

        // Cek apakah path dimulai dengan folder yang diizinkan
        $allowedFolders = ['trip', 'boat', 'cabin', 'gallery', 'blog', 'assets'];
        $pathParts = explode('/', $filePath);

        if (empty($pathParts) || !in_array($pathParts[0], $allowedFolders)) {
            return false;
        }

        return true;
    }

    /**
     * Get file info untuk debugging
     *
     * @param string $filePath
     * @return array
     */
    public static function getFileInfo(string $filePath): array
    {
        $info = [
            'path' => $filePath,
            'exists' => false,
            'size' => 0,
            'mime_type' => null,
            'url' => null,
            'error' => null
        ];

        try {
            if (Storage::disk('public')->exists($filePath)) {
                $info['exists'] = true;
                $info['size'] = Storage::disk('public')->size($filePath);
                $info['mime_type'] = Storage::disk('public')->mimeType($filePath);
                $info['url'] = self::generateFileUrl($filePath);
            } else {
                $info['error'] = 'File tidak ditemukan di storage';
            }
        } catch (\Exception $e) {
            $info['error'] = $e->getMessage();
        }

        return $info;
    }
}
