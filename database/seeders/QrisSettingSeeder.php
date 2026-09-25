<?php

namespace Database\Seeders;

use App\Models\QrisSetting;
use App\Services\QrisService;
use Illuminate\Database\Seeder;

class QrisSettingSeeder extends Seeder
{
    public function run(): void
    {
        $payload = config('qris.static_payload');

        if (empty($payload)) {
            $this->command->warn('QRIS_STATIC_PAYLOAD belum diset di .env — seeder dilewati.');
            return;
        }

        try {
            app(QrisService::class)->assertValidStaticPayload($payload);
        } catch (\InvalidArgumentException $e) {
            $this->command->error('Payload QRIS tidak valid: ' . $e->getMessage());
            return;
        }

        // Hanya insert jika belum ada payload yang sama persis
        $exists = QrisSetting::where('payload', $payload)->exists();

        if ($exists) {
            $this->command->info('Payload QRIS sudah ada di database — dilewati.');
            return;
        }

        QrisSetting::create([
            'payload'    => $payload,
            'image_path' => null,
            'updated_by' => null,
        ]);

        $this->command->info('Payload QRIS dari .env berhasil disimpan ke database.');
    }
}
