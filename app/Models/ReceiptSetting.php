<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ReceiptSetting extends Model
{
    use BelongsToTenant;

    public const DEFAULT_STORE_NAME = 'NYEMIL BEBS';
    public const DEFAULT_HEADER = "Purnama Town House Blok H/1\nTelp: +62 823-9943-0312";
    public const DEFAULT_FOOTER = "Terima Kasih atas Kunjungan Anda!\n~ Nyemil Bebs ~";

    /** Baris kosong setelah footer agar tulisan terakhir melewati gerigi sobek. */
    public const DEFAULT_FEED_LINES = 4;
    public const MAX_FEED_LINES = 8;

    protected $fillable = [
        'store_id',
        'store_name',
        'header_text',
        'footer_text',
        'feed_lines',
        'auto_cut',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'feed_lines' => 'integer',
            'auto_cut'   => 'boolean',
        ];
    }

    /**
     * Pengaturan struk yang berlaku; belum pernah disimpan = nilai bawaan.
     */
    public static function current(): self
    {
        return static::latest('id')->first() ?? static::defaults();
    }

    /**
     * Nilai bawaan: toko default memakai teks lama; toko lain memakai nama, alamat,
     * dan telepon dari data tokonya (jangan mencetak alamat toko lain di struk).
     */
    private static function defaults(): self
    {
        $user = auth()->user();
        $store = $user instanceof User && $user->tenantId() ? Store::find($user->tenantId()) : null;

        $values = [
            'store_name'  => self::DEFAULT_STORE_NAME,
            'header_text' => self::DEFAULT_HEADER,
            'footer_text' => self::DEFAULT_FOOTER,
            'feed_lines'  => self::DEFAULT_FEED_LINES,
            'auto_cut'    => false,
        ];

        if ($store && $store->id !== Store::defaultId()) {
            $values['store_name'] = mb_substr($store->name, 0, 40);
            $values['header_text'] = implode("\n", array_filter([
                $store->address,
                $store->phone ? 'Telp: '.$store->phone : null,
            ]));
            $values['footer_text'] = 'Terima Kasih atas Kunjungan Anda!';
        }

        return new static($values);
    }

    /**
     * Pengaturan cetak yang dipakai printer web (thermal-printer.js) dan aplikasi mobile.
     */
    public function printOptions(): array
    {
        return [
            'store'  => $this->store_name,
            'header' => $this->headerLines(),
            'footer' => $this->footerLines(),
            'feed'   => (int) ($this->feed_lines ?? self::DEFAULT_FEED_LINES),
            'cut'    => (bool) $this->auto_cut,
        ];
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
