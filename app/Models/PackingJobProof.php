<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackingJobProof extends Model
{
    protected $fillable = [
        'packing_job_id',
        'uploaded_by_user_id',
        'storage_path',
        'original_name',
        'mime_type',
    ];

    public function packingJob(): BelongsTo
    {
        return $this->belongsTo(PackingJob::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
