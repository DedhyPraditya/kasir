<?php

namespace App\Services;

use App\Models\QrisSetting;
use InvalidArgumentException;

class QrisService
{
    /**
     * Ambil payload QRIS statis yang sedang aktif (hasil upload terbaru di DB),
     * fallback ke config('qris.static_payload') jika belum pernah ada yang di-upload.
     */
    public function getActivePayload(): ?string
    {
        $payload = QrisSetting::query()->latest('id')->value('payload');

        return $payload ?: config('qris.static_payload');
    }

    /**
     * Pastikan sebuah string adalah payload EMV QRIS statis yang valid & checksum-nya benar.
     * Dipakai saat admin upload/tempel QRIS baru, sebelum disimpan sebagai payload aktif.
     */
    public function assertValidStaticPayload(string $payload): void
    {
        $payload = trim($payload);

        if ($payload === '') {
            throw new InvalidArgumentException('Kode QRIS tidak terbaca.');
        }

        $tags = $this->parse($payload);
        $tagIds = array_column($tags, 'id');

        if (! in_array('00', $tagIds, true) || ! in_array('01', $tagIds, true)
            || ! in_array('53', $tagIds, true) || ! in_array('63', $tagIds, true)) {
            throw new InvalidArgumentException('Bukan format kode QRIS yang dikenali.');
        }

        $crcValue = collect($tags)->firstWhere('id', '63')['value'];
        $expectedCrc = $this->crc16(substr($payload, 0, -4));

        if (strtoupper($crcValue) !== $expectedCrc) {
            throw new InvalidArgumentException('Checksum QRIS tidak valid, kode kemungkinan rusak atau salah baca.');
        }
    }

    /**
     * Konversi payload EMV QRIS statis menjadi payload dynamic dengan nominal tertentu.
     * Mengubah Point of Initiation Method (tag 01) dari "11" (statis) ke "12" (dynamic),
     * menyisipkan tag 54 (Transaction Amount), lalu menghitung ulang CRC16 (tag 63).
     */
    public function generateDynamicPayload(string $staticPayload, int|float $amount): string
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal QRIS harus lebih dari 0.');
        }

        $tags = $this->parse($staticPayload);

        $tagIds = array_column($tags, 'id');
        if (! in_array('01', $tagIds, true) || ! in_array('53', $tagIds, true)) {
            throw new InvalidArgumentException('Payload QRIS statis tidak valid.');
        }

        $rebuilt = '';

        foreach ($tags as ['id' => $id, 'value' => $value]) {
            if ($id === '63') {
                continue; // CRC dihitung ulang di akhir
            }

            if ($id === '01') {
                $value = '12';
            }

            $rebuilt .= $this->encode($id, $value);

            if ($id === '53') {
                $rebuilt .= $this->encode('54', $this->formatAmount($amount));
            }
        }

        $rebuilt .= '6304';

        return $rebuilt.$this->crc16($rebuilt);
    }

    /**
     * @return list<array{id: string, value: string}> urutan sesuai payload asli
     */
    private function parse(string $payload): array
    {
        $tags = [];
        $i = 0;
        $length = strlen($payload);

        while ($i < $length) {
            if ($i + 4 > $length) {
                throw new InvalidArgumentException('Payload QRIS statis tidak valid.');
            }

            $id = substr($payload, $i, 2);
            $valueLength = (int) substr($payload, $i + 2, 2);
            $value = substr($payload, $i + 4, $valueLength);

            if (strlen($value) !== $valueLength) {
                throw new InvalidArgumentException('Payload QRIS statis tidak valid.');
            }

            $tags[] = ['id' => $id, 'value' => $value];
            $i += 4 + $valueLength;
        }

        return $tags;
    }

    private function encode(string $id, string $value): string
    {
        return $id.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    private function formatAmount(int|float $amount): string
    {
        if ((float) $amount === floor((float) $amount)) {
            return (string) (int) $amount;
        }

        return number_format((float) $amount, 2, '.', '');
    }

    private function crc16(string $data): string
    {
        $crc = 0xFFFF;

        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $crc ^= (ord($data[$i]) << 8);

            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 0x8000)
                    ? (($crc << 1) ^ 0x1021) & 0xFFFF
                    : ($crc << 1) & 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
