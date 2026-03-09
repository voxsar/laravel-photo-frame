<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrameOutput extends Model
{
    use HasFactory;

    protected $fillable = [
        'photo_frame_id',
        'original_filename',
        'fill_path',
        'contain_path',
    ];

    public function photoFrame(): BelongsTo
    {
        return $this->belongsTo(PhotoFrame::class);
    }
}
