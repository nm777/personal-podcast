<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LibraryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'media_file_id',
        'title',
        'description',
        'source_type',
        'source_url',
        'is_duplicate',
        'duplicate_detected_at',
        'processing_status',
        'processing_started_at',
        'processing_completed_at',
        'processing_error',
    ];

    protected $casts = [
        'is_duplicate' => 'boolean',
        'duplicate_detected_at' => 'datetime',
        'processing_started_at' => 'datetime',
        'processing_completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function mediaFile()
    {
        return $this->belongsTo(MediaFile::class);
    }

    public function isProcessing()
    {
        return $this->processing_status === 'processing';
    }

    public function isPending()
    {
        return $this->processing_status === 'pending';
    }

    public function hasCompleted()
    {
        return $this->processing_status === 'completed';
    }

    public function hasFailed()
    {
        return $this->processing_status === 'failed';
    }

    public function getProcessingStatusTextAttribute()
    {
        return match ($this->processing_status) {
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
            default => 'Unknown',
        };
    }
}
