<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MediaFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'file_path',
        'file_hash',
        'mime_type',
        'filesize',
        'duration',
        'source_url',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function libraryItems()
    {
        return $this->hasMany(LibraryItem::class);
    }

    public function getPublicUrlAttribute(): string
    {
        return Storage::url($this->file_path);
    }

    /**
     * Find a media file by source URL.
     */
    public static function findBySourceUrl(string $sourceUrl): ?static
    {
        return static::where('source_url', $sourceUrl)->first();
    }

    /**
     * Find a media file by file hash.
     */
    public static function findByHash(string $fileHash): ?static
    {
        return static::where('file_hash', $fileHash)->first();
    }

    /**
     * Find a media file by file hash for a specific user.
     */
    public static function findByHashForUser(string $fileHash, int $userId): ?static
    {
        return static::where('file_hash', $fileHash)->where('user_id', $userId)->first();
    }

    /**
     * Check if a file is a duplicate by calculating its hash.
     */
    public static function isDuplicate(string $filePath): ?static
    {
        // Try to get file content from storage first
        try {
            $content = Storage::disk('public')->get($filePath);
            if ($content === false) {
                // Fallback to real file system if storage doesn't have it
                if (! file_exists($filePath)) {
                    return null;
                }
                $fileHash = hash_file('sha256', $filePath);
            } else {
                $fileHash = hash('sha256', $content);
            }
        } catch (\Exception $e) {
            // Fallback to real file system
            if (! file_exists($filePath)) {
                return null;
            }
            $fileHash = hash_file('sha256', $filePath);
        }

        return static::findByHash($fileHash);
    }
}
