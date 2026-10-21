<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Koli;
use App\Models\Sender;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Services\TrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CargoApiController extends Controller
{
    /**
     * Ringkasan Dashboard & Metrik Operasional
     */
    public function dashboard()
    {
        $today = now()->startOfDay();

        $menungguPickup = Shipment::where('current_status', 'MENUNGGU_BARANG')->count();
        $diGudang = Shipment::where('current_status', 'DI_GUDANG')->count();
        $dalamPerjalanan = Shipment::where('current_status', 'DALAM_PENGIRIMAN')->count();
        $terkirimBulanIni = Shipment::where('current_status', 'SELESAI')
            ->whereMonth('created_at', now()->month)
            ->count();

        $totalKoliHariIni = Koli::whereDate('created_at', today())->count();
        $activeResiCount = Shipment::where('current_status', '!=', 'SELESAI')->count();

        $recentShipments = Shipment::with(['sender', 'kolis'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'stats' => [
                    'menunggu_pickup' => $menungguPickup,
                    'di_gudang' => $diGudang,
                    'dalam_perjalanan' => $dalamPerjalanan,
                    'terkirim_bulan_ini' => $terkirimBulanIni,
                    'total_koli_hari_ini' => $totalKoliHariIni,
                    'resi_aktif' => $activeResiCount,
                    'uang_cod_di_supir' => 3500000,
                    'omset_bulan_ini' => 15450000,
                ],
                'recent_shipments' => $recentShipments,
            ],
        ]);
    }

    /**
     * Daftar Resi Pengiriman
     */
    public function getShipments(Request $request)
    {
        $query = Shipment::with(['sender', 'kolis', 'items'])->orderBy('created_at', 'desc');

        if ($request->has('search') && ! empty($request->search)) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('tracking_number', 'like', "%{$s}%")
                    ->orWhere('recipient_name', 'like', "%{$s}%")
                    ->orWhere('recipient_phone', 'like', "%{$s}%");
            });
        }

        if ($request->has('status') && ! empty($request->status) && $request->status !== 'Semua') {
            $query->where('current_status', $request->status);
        }

        $shipments = $query->paginate($request->get('limit', 20));

        return response()->json([
            'status' => 'success',
            'data' => $shipments,
        ]);
    }

    /**
     * Buat Resi Baru (REST API)
     */
    public function storeShipment(Request $request, TrackingService $trackingService)
    {
        $validated = $request->validate([
            'sender_id' => 'required|exists:senders,id',
            'metode_pickup' => 'required|string', // DROP_OFF atau PICKUP
            'recipient_name' => 'required|string',
            'recipient_phone' => 'required|string',
            'destination_province' => 'nullable',
            'destination_city' => 'nullable',
            'destination_district' => 'nullable',
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

        $shipment = DB::transaction(function () use ($validated, $trackingService) {
            $trackingNumber = $trackingService->generateTrackingNumber();

            $totalCod = 0;
            if ($validated['payment_type'] === 'COD') {
                $goodsValue = (float) ($validated['goods_value'] ?? 0);
                $freightFee = (float) $validated['freight_fee'];
                $totalCod = $goodsValue + $freightFee;
            }

            $shipment = Shipment::create([
                'tracking_number' => $trackingNumber,
                'sender_id' => $validated['sender_id'],
                'recipient_name' => $validated['recipient_name'],
                'recipient_phone' => $validated['recipient_phone'],
                'recipient_address' => $validated['recipient_address'] ?? '',
                'destination_province' => is_array($validated['destination_province']) ? $validated['destination_province'] : ['name' => $validated['destination_province'] ?? 'Jawa'],
                'destination_city' => is_array($validated['destination_city']) ? $validated['destination_city'] : ['name' => $validated['destination_city'] ?? 'Kota'],
                'destination_district' => is_array($validated['destination_district']) ? $validated['destination_district'] : ['name' => $validated['destination_district'] ?? 'Kecamatan'],
                'destination_village' => $validated['destination_village'] ?? null,
                'payment_type' => $validated['payment_type'],
                'goods_value' => $validated['goods_value'] ?? 0,
                'freight_fee' => $validated['freight_fee'],
                'total_cod' => $totalCod,
                'payment_status' => 'UNPAID',
                'current_status' => 'MENUNGGU_BARANG',
            ]);

            $totalKoli = 0;
            foreach ($validated['items'] as $item) {
                $totalKoli += (int) $item['koli_per_item'];
            }

            $koliStatus = $validated['metode_pickup'] === 'PICKUP' ? 'MENUNGGU_PICKUP' : 'MENUNGGU_DROPOFF';
            $currentKoliNumber = 1;

            foreach ($validated['items'] as $item) {
                $itemName = $item['name'];
                $itemUnit = $item['unit'] ?? 'Pcs';
                $itemQty = (int) $item['qty'];
                $koliPerItem = (int) $item['koli_per_item'];

                ShipmentItem::create([
                    'shipment_id' => $shipment->id,
                    'name' => $itemName,
                    'unit' => $itemUnit,
                    'qty' => $itemQty,
                    'koli_per_item' => $koliPerItem,
                ]);

                for ($i = 1; $i <= $koliPerItem; $i++) {
                    $description = $itemQty.' '.$itemName;
                    if ($itemUnit) {
                        $description .= ' ('.$itemUnit.')';
                    }
                    if ($koliPerItem > 1) {
                        $description .= " (Bagian $i/$koliPerItem)";
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

            $logMsg = $validated['metode_pickup'] === 'PICKUP'
                ? 'Resi dibuat via Mobile App. Menunggu armada pick-up.'
                : 'Resi dibuat via Mobile App. Menunggu pengirim mengantar barang ke gudang (Drop-off).';

            $trackingService->logStatus($shipment->id, 'MENUNGGU_BARANG', $logMsg);

            return $shipment->load(['kolis', 'sender', 'items']);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Resi berhasil dibuat!',
            'data' => $shipment,
        ], 201);
    }

    /**
     * Master Data Pengirim (Senders)
     */
    public function getSenders()
    {
        $senders = Sender::orderBy('business_name', 'asc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $senders,
        ]);
    }

    /**
     * Riwayat Pelanggan / Penerima (Recipients)
     */
    public function getRecipients()
    {
        $recipients = Shipment::select('recipient_name', 'recipient_phone', 'recipient_address', 'destination_city', 'destination_district', 'destination_village', 'destination_province')
            ->whereNotNull('recipient_name')
            ->groupBy('recipient_phone', 'recipient_name', 'recipient_address', 'destination_city', 'destination_district', 'destination_village', 'destination_province')
            ->orderBy('recipient_name', 'asc')
            ->limit(50)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $recipients,
        ]);
    }

    /**
     * Lacak Resi Publik / Internal
     */
    public function trackResi($resi)
    {
        $cleanResi = trim(strtoupper($resi));
        $shipment = Shipment::with(['sender', 'kolis', 'items', 'trackingLogs'])
            ->where('tracking_number', $cleanResi)
            ->first();

        if (! $shipment) {
            return response()->json([
                'status' => 'error',
                'message' => "Nomor resi {$cleanResi} tidak ditemukan.",
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $shipment,
        ]);
    }
}
