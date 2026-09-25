<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArtworkVersion extends Model
{
    protected $fillable = [
        'design_job_id', 'version_number', 'storage_disk', 'storage_path',
        'original_filename', 'mime_type', 'file_size_bytes', 'checksum_sha256',
        'source_files', 'preview_storage_path', 'preview_files', 'internal_note', 'created_by_user_id',
    ];

    protected $casts = [
        'source_files' => 'array',
        'preview_files' => 'array',
    ];

    public function designJob()
    {
        return $this->belongsTo(DesignJob::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reviewActions()
    {
        return $this->hasMany(ArtworkReviewAction::class);
    }

    public function printJobs()
    {
        return $this->hasMany(PrintJob::class);
    }
}
