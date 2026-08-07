<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QrisSetting extends Model
{
    protected $fillable = [
        'payload',
        'image_path',
        'updated_by',
    ];

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
