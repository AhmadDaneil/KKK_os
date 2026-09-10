<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArtworkReviewAction extends Model
{
    protected $fillable = [
        'design_job_id',
        'artwork_version_id',
        'action',
        'customer_comment',
        'actor_user_id',
        'acted_at',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];

    public function designJob()
    {
        return $this->belongsTo(DesignJob::class);
    }

    public function artworkVersion()
    {
        return $this->belongsTo(ArtworkVersion::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
