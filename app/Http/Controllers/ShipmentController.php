<?php

namespace App\Http\Controllers;

use App\Models\Koli;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Services\TrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShipmentController extends Controller
{
    public function store(Request $request, TrackingService $trackingService)
    {
        $validated = $request->validate([
            'sender_id' => 'required|exists:senders,id',
            'metode_pickup' => 'required|string', // DROP_OFF atau PICKUP
            'recipient_name' => 'required|string',
            'recipient_phone' => 'required|string',
            'destination_province' => 'required|array',
            'destination_city' => 'required|array',
            'destination_district' => 'required|array',
            'destination_village' => 'nullable|string',
            'recipient_address' => 'nullable|string',
            'payment_type' => 'required|in:COD,PREPAID',
            'goods_value' => 'nullable|numeric',
            'freight_fee' => 'required|numeric',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string',
            'items.*.unit' => 'nullable|string',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.koli_per_item' => 'required|integer|min:1',
        ]);

        // Gunakan DB Transaction agar jika gagal di tengah jalan, data di-rollback
        $trackingNumber = DB::transaction(function () use ($validated, $trackingService) {

            // 1. Generate Nomor Resi
            $trackingNumber = $trackingService->generateTrackingNumber();

            // 2. Hitung Total COD (Jika metode COD)
            $totalCod = 0;
            if ($validated['payment_type'] === 'COD') {
                $goodsValue = (float) ($validated['goods_value'] ?? 0);
                $freightFee = (float) $validated['freight_fee'];
                $totalCod = $goodsValue + $freightFee;
            }

            // 3. Simpan Data Resi (Shipment)
            $shipment = Shipment::create([
                'tracking_number' => $trackingNumber,
                'sender_id' => $validated['sender_id'],
                'recipient_name' => $validated['recipient_name'],
                'recipient_phone' => $validated['recipient_phone'],
                'recipient_address' => $validated['recipient_address'],
                'destination_province' => $validated['destination_province'],
                'destination_city' => $validated['destination_city'],
                'destination_district' => $validated['destination_district'],
                'destination_village' => $validated['destination_village'] ?? null,
                'payment_type' => $validated['payment_type'],
                'goods_value' => $validated['goods_value'] ?? 0,
                'freight_fee' => $validated['freight_fee'],
                'total_cod' => $totalCod,
                'payment_status' => 'UNPAID',
                'current_status' => 'MENUNGGU_BARANG',
            ]);

            // 4. Simpan Data Koli berdasarkan Item (Banyak Item, Tiap Item Banyak Koli)
            $totalKoli = 0;
            foreach ($validated['items'] as $item) {
                $totalKoli += (int) $item['koli_per_item'];
            }

            $koliStatus = $validated['metode_pickup'] === 'PICKUP' ? 'MENUNGGU_PICKUP' : 'MENUNGGU_DROPOFF';
            $currentKoliNumber = 1;

            foreach ($validated['items'] as $item) {
                $itemName = $item['name'];
                $itemUnit = $item['unit'] ?? '';
                $itemQty = (int) $item['qty']; // Jumlah Barang (Misal: 4)
                $koliPerItem = (int) $item['koli_per_item']; // Koli per Barang (Misal: 5)

                // Simpan ke Master Data Barang (Shipment Items)
                ShipmentItem::create([
                    'shipment_id' => $shipment->id,
                    'name' => $itemName,
                    'unit' => $itemUnit,
                    'qty' => $itemQty,
                    'koli_per_item' => $koliPerItem, // Sekarang ini bertindak sebagai 'total koli untuk baris ini'
                ]);

                // Buat record koli sejumlah koliPerItem
                for ($i = 1; $i <= $koliPerItem; $i++) {
                    // Susun deskripsi spesifik
                    $description = $itemQty.' '.$itemName; // Misal: 10 Mimbar
                    if ($itemUnit) {
                        $description .= ' ('.$itemUnit.')'; // Misal: 10 Mimbar (Pcs)
                    }
                    if ($koliPerItem > 1) {
                        $description .= " (Bagian $i/$koliPerItem)"; // Tambahkan informasi bagian jika dibongkar lebih dari 1 koli
                    }

                    do {
                        $randomSuffix = strtoupper(Str::random(3));
                        $barcodeId = $trackingNumber.'-'.$randomSuffix;
                    } while (Koli::where('barcode_id', $barcodeId)->exists());

                    Koli::create([
                        'shipment_id' => $shipment->id,
                        'koli_number' => $currentKoliNumber,
                        'total_koli' => $totalKoli,
                        'barcode_id' => $barcodeId,
                        'description' => $description,
                        'physical_status' => $koliStatus,
                    ]);

                    $currentKoliNumber++;
                }
            }

            // 5. Catat Log Status (Menggunakan Service Re-usable)
            $logMsg = $validated['metode_pickup'] === 'PICKUP'
                ? 'Resi dibuat. Menunggu armada pick-up.'
                : 'Resi dibuat. Menunggu pengirim mengantar barang ke gudang (Drop-off).';

            $trackingService->logStatus($shipment->id, 'MENUNGGU_BARANG', $logMsg);

            return $trackingNumber;
        });

        // Redirect kembali dengan pesan sukses
        return redirect()->route('buat-resi')->with([
            'success' => "Resi {$trackingNumber} telah berhasil dibuat!",
            'print_url' => route('shipments.print', ['trackingNumber' => $trackingNumber]),
            'tracking_number' => $trackingNumber,
        ]);
    }

    public function print(string $trackingNumber)
    {
        $shipment = Shipment::withoutGlobalScope('expedition')
            ->with([
                'sender' => function ($q) {
                    $q->withoutGlobalScope('expedition');
                },
                'kolis',
                'items',
            ])
            ->where('tracking_number', $trackingNumber)
            ->firstOrFail();

        $settingName = Setting::where('key', 'company_name')->first();
        $companyName = $settingName ? $settingName->value : 'GALANG FURNITURE CARGO';

        $settingTagline = Setting::where('key', 'company_tagline')->first();
        $companyTagline = $settingTagline ? $settingTagline->value : 'Layanan Pengiriman Cargo Terpercaya';

        return view('print-label', compact('shipment', 'companyName', 'companyTagline'));
    }
}
