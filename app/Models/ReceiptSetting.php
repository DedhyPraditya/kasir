<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiptSetting extends Model
{
    public const DEFAULT_STORE_NAME = 'NYEMIL BEBS';
    public const DEFAULT_HEADER = "Purnama Town House Blok H/1\nTelp: +62 823-9943-0312";
    public const DEFAULT_FOOTER = "Terima Kasih atas Kunjungan Anda!\n~ Nyemil Bebs ~";

    protected $fillable = [
        'store_name',
        'header_text',
        'footer_text',
        'updated_by',
    ];

    /**
     * Pengaturan struk yang berlaku; belum pernah disimpan = nilai bawaan.
     */
    public static function current(): self
    {
        return static::latest('id')->first() ?? new static([
            'store_name'  => self::DEFAULT_STORE_NAME,
            'header_text' => self::DEFAULT_HEADER,
            'footer_text' => self::DEFAULT_FOOTER,
        ]);
    }

    /** @return array<int, string> */
    public function headerLines(): array
    {
        return self::lines($this->header_text);
    }

    /** @return array<int, string> */
    public function footerLines(): array
    {
        return self::lines($this->footer_text);
    }

    private static function lines(?string $text): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', (string) $text)),
            fn ($line) => $line !== ''
        ));
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
