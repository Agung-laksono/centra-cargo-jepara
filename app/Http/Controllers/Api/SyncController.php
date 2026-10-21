<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Koli;
use App\Models\Manifest;
use App\Models\Shipment;
use App\Services\TrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SyncController extends Controller
{
    public function __construct(protected TrackingService $trackingService) {}

    /**
     * Menerima antrean sinkronisasi dari supir (DriverLintas)
     */
    public function driverSync(Request $request)
    {
        try {
            $action = $request->input('action');
            $data = $request->input('data');

            Log::info('Driver Sync Received: '.$action, $data ?? []);

            DB::beginTransaction();

            if ($action === 'update_route_order') {
                if (! empty($data['order']) && is_array($data['order'])) {
                    foreach ($data['order'] as $index => $shipmentId) {
                        Shipment::where('id', $shipmentId)->update(['route_order' => $index + 1]);
                    }
                }
            } elseif ($action === 'lapor_kendala') {
                $shipmentId = $data['id'] ?? null;
                $alasan = $data['alasan'] ?? 'Kendala pengantaran';

                if ($shipmentId) {
                    $shipment = Shipment::find($shipmentId);
                    if ($shipment) {
                        $shipment->update([
                            'current_status' => 'Kendala',
                        ]);
                        $this->trackingService->logStatus($shipmentId, 'Kendala', 'Kendala pengantaran: '.$alasan);
                    }
                }
            } elseif ($action === 'selesaikan_antaran') {
                $shipmentId = $data['id'] ?? null;
                $filename = null;

                if (! empty($data['fotoBase64'])) {
                    try {
                        $image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data['fotoBase64']));
                        $filename = 'pod_'.$shipmentId.'_'.time().'.png';
                        Storage::disk('public')->put('pod/'.$filename, $image);
                    } catch (\Exception $e) {
                        Log::warning('Failed to save POD image: '.$e->getMessage());
                    }
                }

                if ($shipmentId) {
                    $shipment = Shipment::find($shipmentId);
                    if ($shipment) {
                        $shipment->update([
                            'current_status' => 'Selesai',
                            'payment_status' => $shipment->payment_type === 'COD' ? 'PAID' : $shipment->payment_status,
                        ]);

                        // Update seluruh koli shipment ini menjadi TERKIRIM
                        $shipment->kolis()->update([
                            'physical_status' => Koli::STATUS_TERKIRIM,
                        ]);

                        $this->trackingService->logStatus(
                            $shipmentId,
                            'Selesai',
                            'Barang telah diterima oleh penerima ('.$shipment->recipient_name.').'
                        );
                    }
                }
            }

            DB::commit();

            return response()->json(['status' => 'success', 'message' => 'Synced']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Driver Sync Error: '.$e->getMessage());

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Menerima antrean sinkronisasi dari Petugas Gudang (Inbound)
     */
    public function inboundSync(Request $request)
    {
        try {
            $action = $request->input('action');

            if ($action === 'inbound_scan') {
                $koliIds = $request->input('koli_ids', []);
                Log::info('Inbound Sync Received. Koli IDs: '.implode(',', $koliIds));

                if (! empty($koliIds)) {
                    DB::beginTransaction();

                    // 1. Update status fisik setiap koli yang discan
                    Koli::whereIn('id', $koliIds)->update([
                        'physical_status' => Koli::STATUS_DI_GUDANG,
                    ]);

                    // 2. Cek shipment-shipment yang terpengaruh
                    $shipmentIds = Koli::whereIn('id', $koliIds)->pluck('shipment_id')->unique();

                    foreach ($shipmentIds as $shipmentId) {
                        $shipment = Shipment::with('kolis')->find($shipmentId);
                        if ($shipment) {
                            $allInWarehouse = $shipment->kolis->every(function ($k) {
                                return in_array($k->physical_status, [
                                    Koli::STATUS_DI_GUDANG,
                                    Koli::STATUS_DALAM_PERJALANAN,
                                    Koli::STATUS_TERKIRIM,
                                ]);
                            });

                            $newStatus = $allInWarehouse ? 'Di Gudang' : 'Sebagian di Gudang';
                            $shipment->update(['current_status' => $newStatus]);

                            $this->trackingService->logStatus(
                                $shipmentId,
                                $newStatus,
                                'Barang telah tiba dan diverifikasi masuk Gudang Utama.'
                            );
                        }
                    }

                    DB::commit();
                }
            }

            return response()->json(['status' => 'success', 'message' => 'Synced']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Inbound Sync Error: '.$e->getMessage());

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Menerima antrean sinkronisasi dari Supir Penjemputan (DriverPickup)
     */
    public function pickupSync(Request $request)
    {
        try {
            $action = $request->input('action');
            $data = $request->input('data', []);

            Log::info('Pickup Sync Received: '.$action, $data);

            if ($action === 'selesaikan_pickup') {
                $token = $data['token'] ?? null;
                $senderId = $data['sender_id'] ?? null;
                $scannedBarcodes = $data['scanned_barcodes'] ?? [];
                $isForce = (bool) ($data['force'] ?? false);
                $forceReason = $data['force_reason'] ?? null;

                if (! $token || ! $senderId) {
                    return response()->json(['status' => 'error', 'message' => 'Token & Sender ID wajib diisi'], 422);
                }

                $manifest = Manifest::where('magic_link_token', $token)->first();
                if (! $manifest) {
                    return response()->json(['status' => 'error', 'message' => 'Manifest tidak ditemukan'], 404);
                }

                DB::beginTransaction();

                // Temukan koli yang terkait manifest ini dan milik sender_id
                $kolisQuery = $manifest->kolis()->whereHas('shipment', function ($q) use ($senderId) {
                    $q->where('sender_id', $senderId);
                });
                $koliIds = $kolisQuery->pluck('kolis.id');

                // Update physical_status koli menjadi DI_ANGKUT
                Koli::whereIn('id', $koliIds)->update([
                    'physical_status' => Koli::STATUS_DI_ANGKUT,
                ]);

                // Temukan shipment yang berelasi
                $shipmentIds = Koli::whereIn('id', $koliIds)->pluck('shipment_id')->unique();
                Shipment::whereIn('id', $shipmentIds)->update([
                    'current_status' => 'Diterima',
                ]);

                // Log audit ke manifest
                $scannedCount = count($scannedBarcodes);
                $totalKoli = $koliIds->count();
                $driverName = $manifest->driver ? $manifest->driver->name : 'Armada Penjemputan';

                $notes = $isForce
                    ? "[PAKSA SELESAI] Ter-scan: {$scannedCount}/{$totalKoli} koli. Alasan: ".($forceReason ?? 'Tidak disebutkan')
                    : "[SELESAI NORMAL] Semua {$scannedCount} koli ter-scan dan diverifikasi.";

                $manifest->update([
                    'is_force_completed' => $isForce,
                    'scanned_koli_count' => $scannedCount,
                    'pickup_notes' => ($manifest->pickup_notes ? $manifest->pickup_notes."\n" : '').now()->format('d/m/Y H:i').' — Sender #'.$senderId.': '.$notes,
                ]);

                foreach ($shipmentIds as $shipmentId) {
                    $this->trackingService->logStatus(
                        $shipmentId,
                        'Diterima',
                        "Barang telah dijemput dan dimuat oleh supir penjemputan ({$driverName})."
                    );
                }

                DB::commit();
            }

            return response()->json(['status' => 'success', 'message' => 'Synced']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Pickup Sync Error: '.$e->getMessage());

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
