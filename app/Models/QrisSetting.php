<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class QrisSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'store_id',
        'payload',
        'image_path',
        'updated_by',
    ];

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
