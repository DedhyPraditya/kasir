<?php

namespace App\Console\Commands;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FixOrderTimestamps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:fix-timestamps {--dry-run : Simulasi tanpa menyimpan perubahan ke database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Koreksi timestamp created_at transaksi lama yang tertumpuk saat sync offline berdasarkan tanggal pada nomor invoice';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('[DRY RUN] Menjalankan simulasi perbaikan timestamp...');
        } else {
            $this->info('Memulai perbaikan timestamp transaksi...');
        }

        $orders = Order::withoutGlobalScopes()
            ->where('invoice_number', 'like', 'INV-20%')
            ->get();

        $fixedCount = 0;

        foreach ($orders as $order) {
            // A. Format baru presisi waktu: INV-YYYYMMDD-HHmmss (misal: INV-20261108-013506)
            if (preg_match('/^INV-(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})$/', $order->invoice_number, $matches)) {
                $targetTimestamp = Carbon::create(
                    (int) $matches[1],
                    (int) $matches[2],
                    (int) $matches[3],
                    (int) $matches[4],
                    (int) $matches[5],
                    (int) $matches[6]
                );

                if (! $order->created_at || $order->created_at->toDateTimeString() !== $targetTimestamp->toDateTimeString()) {
                    $this->info(sprintf(
                        '[FORMAT PRESISI] %s: %s -> %s',
                        $order->invoice_number,
                        $order->created_at ? $order->created_at->toDateTimeString() : 'NULL',
                        $targetTimestamp->toDateTimeString()
                    ));

                    if (! $isDryRun) {
                        $order->created_at = $targetTimestamp;
                        $order->updated_at = $targetTimestamp;
                        $order->saveQuietly();
                    }

                    $fixedCount++;
                }
                continue;
            }

            // B. Khusus transaksi lama tengah malam beda timezone (PR9E & 85R2)
            if (in_array($order->invoice_number, ['INV-20261108-PR9E', 'INV-20261108-85R2'])) {
                if ($order->created_at && $order->created_at->format('Y-m-d') === '2026-11-08' && $order->created_at->hour === 23) {
                    $corrected = $order->created_at->copy()->subDay();
                    $this->warn(sprintf(
                        '[KOREKSI 7 NOV] %s: %s -> %s',
                        $order->invoice_number,
                        $order->created_at->toDateTimeString(),
                        $corrected->toDateTimeString()
                    ));

                    if (! $isDryRun) {
                        $order->created_at = $corrected;
                        $order->updated_at = $corrected;
                        $order->saveQuietly();
                    }

                    $fixedCount++;
                }
                continue;
            }

            // C. Format lama dengan 4 karakter acak: INV-YYYYMMDD-XXXX
            if (preg_match('/^INV-(\d{4})(\d{2})(\d{2})-/i', $order->invoice_number, $matches)) {
                $invoiceDate = "{$matches[1]}-{$matches[2]}-{$matches[3]}";
                $currentDate = $order->created_at ? $order->created_at->format('Y-m-d') : null;

                if ($invoiceDate !== $currentDate) {
                    $timePart = $order->created_at ? $order->created_at->format('H:i:s') : '12:00:00';
                    $newTimestamp = Carbon::parse("{$invoiceDate} {$timePart}");

                    $this->line(sprintf(
                        '[%s] %s: %s -> %s',
                        $order->id,
                        $order->invoice_number,
                        $order->created_at ? $order->created_at->toDateTimeString() : 'NULL',
                        $newTimestamp->toDateTimeString()
                    ));

                    if (! $isDryRun) {
                        $order->created_at = $newTimestamp;
                        $order->updated_at = $newTimestamp;
                        $order->saveQuietly();
                    }

                    $fixedCount++;
                }
            }
        }

        if ($fixedCount === 0) {
            $this->info('Semua transaksi sudah sesuai tanggal invoice. Tidak ada yang perlu dikoreksi.');
        } else {
            $this->info(sprintf(
                'Selesai. Total %d transaksi %s.',
                $fixedCount,
                $isDryRun ? 'terdeteksi perlu dikoreksi' : 'berhasil dikoreksi'
            ));
        }

        return Command::SUCCESS;
    }
}

