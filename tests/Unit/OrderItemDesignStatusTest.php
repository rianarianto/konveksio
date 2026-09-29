<?php

namespace Tests\Unit;

use App\Models\OrderItem;
use App\Observers\OrderItemObserver;
use Tests\TestCase;

class OrderItemDesignStatusTest extends TestCase
{
    public function test_jasa_without_sablon_is_auto_approved(): void
    {
        $item = new OrderItem();
        $item->production_category = 'jasa';
        $item->size_and_request_details = [
            'sablon_jenis' => 'Tanpa Sablon/Bordir',
        ];

        $details = $item->size_and_request_details ?? [];
        $sablonJenis = $details['sablon_jenis'] ?? null;
        $hasSablon = !empty($sablonJenis) && $sablonJenis !== 'Tanpa Sablon/Bordir';

        if (in_array($item->production_category, ['jasa', 'non_produksi']) && !$hasSablon) {
            $item->design_status = 'approved';
        }

        $this->assertEquals('approved', $item->design_status);
    }

    public function test_jasa_with_sablon_remains_pending(): void
    {
        $item = new OrderItem();
        $item->production_category = 'jasa';
        $item->design_status = 'pending';
        $item->size_and_request_details = [
            'sablon_jenis' => 'Bordir Komputer',
        ];

        $details = $item->size_and_request_details ?? [];
        $sablonJenis = $details['sablon_jenis'] ?? null;
        $hasSablon = !empty($sablonJenis) && $sablonJenis !== 'Tanpa Sablon/Bordir';

        if (in_array($item->production_category, ['jasa', 'non_produksi']) && !$hasSablon) {
            $item->design_status = 'approved';
        }

        $this->assertEquals('pending', $item->design_status);
    }

    public function test_non_produksi_without_sablon_is_auto_approved(): void
    {
        $item = new OrderItem();
        $item->production_category = 'non_produksi';
        $item->size_and_request_details = [
            'sablon_jenis' => null,
        ];

        $details = $item->size_and_request_details ?? [];
        $sablonJenis = $details['sablon_jenis'] ?? null;
        $hasSablon = !empty($sablonJenis) && $sablonJenis !== 'Tanpa Sablon/Bordir';

        if (in_array($item->production_category, ['jasa', 'non_produksi']) && !$hasSablon) {
            $item->design_status = 'approved';
        }

        $this->assertEquals('approved', $item->design_status);
    }

    public function test_produksi_category_is_not_auto_approved(): void
    {
        $item = new OrderItem();
        $item->production_category = 'produksi';
        $item->design_status = 'pending';
        $item->size_and_request_details = [
            'sablon_jenis' => 'Tanpa Sablon/Bordir',
        ];

        $details = $item->size_and_request_details ?? [];
        $sablonJenis = $details['sablon_jenis'] ?? null;
        $hasSablon = !empty($sablonJenis) && $sablonJenis !== 'Tanpa Sablon/Bordir';

        if (in_array($item->production_category, ['jasa', 'non_produksi']) && !$hasSablon) {
            $item->design_status = 'approved';
        }

        $this->assertEquals('pending', $item->design_status);
    }
}
