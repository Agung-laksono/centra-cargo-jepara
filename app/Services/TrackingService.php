<?php

namespace App\Services;

use App\Models\TrackingLog;
use App\Models\Shipment;
use Illuminate\Support\Str;

class TrackingService
{
    /**
     * Generate Nomor Resi Unik (Contoh: JPR-2610-001)
     */
    public function generateTrackingNumber(?int $expeditionId = null): string
    {
        $prefix = null;

        // 1. Cek dari Ekspedisi tenant user aktif atau parameter
        $targetExpeditionId = $expeditionId ?? (auth()->check() ? auth()->user()->expedition_id : null);
        if ($targetExpeditionId) {
            $expedition = \App\Models\Expedition::find($targetExpeditionId);
            if ($expedition && !empty($expedition->receipt_code)) {
                $prefix = strtoupper(trim($expedition->receipt_code)) . '-';
            }
        }

        // 2. Fallback ke Pengaturan Sistem
        if (!$prefix) {
            $setting = \App\Models\Setting::where('key', 'company_receipt_code')->first();
            if ($setting && !empty($setting->value)) {
                $prefix = strtoupper(trim($setting->value)) . '-';
            }
        }

        // 3. Fallback default
        if (!$prefix) {
            $prefix = 'GFC-';
        }
        
        // Generate random 4 uppercase alphanumeric string
        do {
            $randomStr = strtoupper(Str::random(4));
            $newNumber = $prefix . $randomStr;
            // Check for collision
            $exists = Shipment::where('tracking_number', $newNumber)->exists();
        } while ($exists);

        return $newNumber;
    }

    /**
     * Catat riwayat pergerakan resi/koli (Re-usable logic)
     */
    public function logStatus(int $shipmentId, string $status, string $locationDesc, ?int $koliId = null): void
    {
        TrackingLog::create([
            'shipment_id' => $shipmentId,
            'koli_id' => $koliId,
            'status' => $status,
            'location_desc' => $locationDesc,
        ]);
        
        // Jika koliId null, artinya ini update status general untuk seluruh Resi
        if (!$koliId) {
            Shipment::where('id', $shipmentId)->update(['current_status' => $status]);
        }
    }
}
