<?php

namespace Tests\Unit;

use App\Services\QrisService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class QrisServiceTest extends TestCase
{
    private const STATIC_PAYLOAD = '00020101021126760024ID.CO.SPEEDCASH.MERCHANT01189360081530002105700215ID10250021057040303UKE51440014ID.CO.QRIS.WWW0215ID10254315212240303UKE5204526253033605802ID5910DYFHAASHOP6005MAROS61059056262330509S3871109301091263389110703A016304108B';

    public function test_generate_dynamic_payload_changes_point_of_initiation_to_dynamic(): void
    {
        $service = new QrisService;

        $dynamic = $service->generateDynamicPayload(self::STATIC_PAYLOAD, 15000);

        // Tag 01 harus "12" (dynamic), bukan "11" (statis).
        $this->assertStringContainsString('010212', substr($dynamic, 0, 12));
    }

    public function test_generate_dynamic_payload_embeds_amount_tag(): void
    {
        $service = new QrisService;

        $dynamic = $service->generateDynamicPayload(self::STATIC_PAYLOAD, 15000);

        $this->assertStringContainsString('540515000', $dynamic);
    }

    public function test_generate_dynamic_payload_produces_correct_crc(): void
    {
        $service = new QrisService;

        $dynamic = $service->generateDynamicPayload(self::STATIC_PAYLOAD, 15000);

        // Nilai referensi ini sudah diverifikasi manual pakai implementasi CRC16-CCITT terpisah.
        $this->assertSame('4F0A', substr($dynamic, -4));
    }

    public function test_generate_dynamic_payload_rejects_zero_or_negative_amount(): void
    {
        $service = new QrisService;

        $this->expectException(InvalidArgumentException::class);

        $service->generateDynamicPayload(self::STATIC_PAYLOAD, 0);
    }

    public function test_generate_dynamic_payload_rejects_malformed_payload(): void
    {
        $service = new QrisService;

        $this->expectException(InvalidArgumentException::class);

        $service->generateDynamicPayload('000201broken', 15000);
    }

    public function test_assert_valid_static_payload_passes_for_known_good_payload(): void
    {
        $service = new QrisService;

        $service->assertValidStaticPayload(self::STATIC_PAYLOAD);

        $this->addToAssertionCount(1); // Tidak melempar exception = valid.
    }

    public function test_assert_valid_static_payload_rejects_empty_string(): void
    {
        $service = new QrisService;

        $this->expectException(InvalidArgumentException::class);

        $service->assertValidStaticPayload('');
    }

    public function test_assert_valid_static_payload_rejects_wrong_checksum(): void
    {
        $service = new QrisService;

        // Ganti 1 karakter di CRC supaya checksum jadi salah, tapi struktur tag tetap utuh.
        $tampered = substr(self::STATIC_PAYLOAD, 0, -1).'0';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Checksum QRIS tidak valid');

        $service->assertValidStaticPayload($tampered);
    }

    public function test_assert_valid_static_payload_rejects_incomplete_payload(): void
    {
        $service = new QrisService;

        $this->expectException(InvalidArgumentException::class);

        $service->assertValidStaticPayload('00020101021226');
    }
}
