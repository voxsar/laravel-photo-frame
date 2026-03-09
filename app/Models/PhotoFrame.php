<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhotoFrame extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'frame_path',
        'is_active',
        'anchor_point',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Anchor point options for cover/fill mode.
     */
    public static function anchorPoints(): array
    {
        return [
            'top-left'     => 'Top Left',
            'top'          => 'Top Center',
            'top-right'    => 'Top Right',
            'left'         => 'Center Left',
            'center'       => 'Center',
            'right'        => 'Center Right',
            'bottom-left'  => 'Bottom Left',
            'bottom'       => 'Bottom Center',
            'bottom-right' => 'Bottom Right',
        ];
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(FrameOutput::class);
    }
}
