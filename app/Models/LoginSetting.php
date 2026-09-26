<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginSetting extends Model
{
    public const DEFAULT_HEADLINE = 'Cemilan enak, dibuat segar setiap hari.';
    public const DEFAULT_DESCRIPTION = 'Gabin fla yang lembut dan banana roll yang renyah dari dapur Nyemil Bebs, siap menemani waktu santaimu.';

    protected $fillable = [
        'logo_path',
        'headline',
        'description',
        'updated_by',
    ];

    /**
     * Pengaturan yang berlaku; belum pernah disimpan = nilai bawaan.
     */
    public static function current(): self
    {
        return static::latest('id')->first() ?? new static([
            'headline'    => self::DEFAULT_HEADLINE,
            'description' => self::DEFAULT_DESCRIPTION,
        ]);
    }

    public function logoUrl(): string
    {
        return $this->logo_path ? asset('storage/'.$this->logo_path) : asset('logo.png');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
