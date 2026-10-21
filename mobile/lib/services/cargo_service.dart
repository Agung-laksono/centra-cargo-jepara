import '../models/cargo_models.dart';

class CargoService {
  // Singleton pattern
  static final CargoService _instance = CargoService._internal();
  factory CargoService() => _instance;
  CargoService._internal();

  // In-Memory Data Storage (Syncable with Laravel Backend)
  final List<Shipment> _shipments = [
    Shipment(
      resi: 'GFC-2610-001',
      senderName: 'UD Maju Mebel Tahunan',
      senderPhone: '081234567890',
      senderCity: 'Jepara',
      recipientName: 'Bpk. Budi Santoso',
      recipientPhone: '085712345678',
      destinationCity: 'Jakarta Selatan',
      destinationAddress: 'Jl. Kemang Raya No. 18, Kebayoran Baru',
      totalKoli: 4,
      totalPrice: 1850000,
      paymentType: 'COD',
      status: 'DALAM_PENGIRIMAN',
      createdAt: DateTime.now().subtract(const Duration(hours: 14)),
      kolis: [
        KoliItem(code: 'GFC-2610-001-1', description: 'Meja Makan Jati Ukir (1 Pcs)', length: 200, width: 100, height: 75),
        KoliItem(code: 'GFC-2610-001-2', description: 'Kursi Makan Jati (Set 1 - 3 Kursi)', length: 60, width: 60, height: 110),
        KoliItem(code: 'GFC-2610-001-3', description: 'Kursi Makan Jati (Set 2 - 3 Kursi)', length: 60, width: 60, height: 110),
        KoliItem(code: 'GFC-2610-001-4', description: 'Kaca Meja Tebal 10mm (Packing Kayu)', length: 205, width: 105, height: 15),
      ],
    ),
    Shipment(
      resi: 'GFC-2610-002',
      senderName: 'Karya Jati Abadi',
      senderPhone: '082198765432',
      senderCity: 'Jepara',
      recipientName: 'Ibu Ratna Dewi',
      recipientPhone: '081398761234',
      destinationCity: 'Bandung',
      destinationAddress: 'Komplek Dago Asri Blok C-12, Coblong',
      totalKoli: 2,
      totalPrice: 1200000,
      paymentType: 'NON_COD',
      status: 'DI_GUDANG',
      createdAt: DateTime.now().subtract(const Duration(hours: 5)),
      kolis: [
        KoliItem(code: 'GFC-2610-002-1', description: 'Lemari Pakaian Pintu 3 (Bodi)', length: 160, width: 60, height: 210),
        KoliItem(code: 'GFC-2610-002-2', description: 'Lemari Pakaian Pintu 3 (Pintu & Kaca)', length: 160, width: 60, height: 20),
      ],
    ),
    Shipment(
      resi: 'GFC-2610-003',
      senderName: 'Sentra Rotan & Jati Senenan',
      senderPhone: '081122334455',
      senderCity: 'Jepara',
      recipientName: 'Toko Mebel Sejahtera',
      recipientPhone: '087811223344',
      destinationCity: 'Surabaya',
      destinationAddress: 'Jl. Basuki Rahmat No. 45, Tegalsari',
      totalKoli: 3,
      totalPrice: 2400000,
      paymentType: 'NON_COD',
      status: 'MENUNGGU_PICKUP',
      createdAt: DateTime.now().subtract(const Duration(minutes: 45)),
      kolis: [
        KoliItem(code: 'GFC-2610-003-1', description: 'Buffet TV Minimalis Jati', length: 180, width: 45, height: 60),
        KoliItem(code: 'GFC-2610-003-2', description: 'Nakas Tempat Tidur (2 Pcs)', length: 50, width: 45, height: 50),
        KoliItem(code: 'GFC-2610-003-3', description: 'Headboard Dipan 180x200', length: 190, width: 15, height: 120),
      ],
    ),
  ];

  final List<PickupTask> _pickupTasks = [
    PickupTask(
      id: 1,
      senderName: 'UD Maju Mebel (Jepara)',
      phone: '081234567890',
      address: 'Jl. Raya Tahunan No. 12, Tahunan, Jepara',
      mapsUrl: 'https://maps.google.com/?q=Tahunan+Jepara',
      totalKoli: 4,
      koliBarcodes: ['GFC-2610-001-1', 'GFC-2610-001-2', 'GFC-2610-001-3', 'GFC-2610-001-4'],
      itemName: 'Set Meja Makan Jati Ukir (4 Koli)',
      isCompleted: false,
    ),
    PickupTask(
      id: 2,
      senderName: 'Karya Jati Abadi',
      phone: '082198765432',
      address: 'Desa Mantingan RT 02/01, Tahunan, Jepara',
      mapsUrl: 'https://maps.google.com/?q=Mantingan+Jepara',
      totalKoli: 2,
      koliBarcodes: ['GFC-2610-002-1', 'GFC-2610-002-2'],
      itemName: 'Lemari Pakaian Pintu 3 Minimalis (2 Koli)',
      isCompleted: true,
    ),
    PickupTask(
      id: 3,
      senderName: 'Sentra Rotan & Jati Senenan',
      phone: '081122334455',
      address: 'Jl. Pemuda No. 88, Senenan, Jepara',
      mapsUrl: 'https://maps.google.com/?q=Senenan+Jepara',
      totalKoli: 3,
      koliBarcodes: ['GFC-2610-003-1', 'GFC-2610-003-2', 'GFC-2610-003-3'],
      itemName: 'Buffet TV & Dipan Jati (3 Koli)',
      isCompleted: false,
    ),
  ];

  final List<ManifestRecord> _manifests = [
    ManifestRecord(
      manifestNumber: 'MNF-JPR-JKT-091',
      route: 'Jepara Hub ➔ Jakarta Selatan Hub',
      driverName: 'Slamet Raharjo',
      vehiclePlate: 'K 8921 QC (Truk Fuso)',
      totalShipments: 18,
      totalKoli: 64,
      status: 'ON_THE_WAY',
      departTime: DateTime.now().subtract(const Duration(hours: 8)),
    ),
    ManifestRecord(
      manifestNumber: 'MNF-JPR-BDG-045',
      route: 'Jepara Hub ➔ Bandung & Cimahi',
      driverName: 'Agus Setiawan',
      vehiclePlate: 'K 9102 BL (Colt Diesel)',
      totalShipments: 12,
      totalKoli: 38,
      status: 'DRAFT',
      departTime: DateTime.now().add(const Duration(hours: 3)),
    ),
  ];

  // Getters
  List<Shipment> get shipments => List.unmodifiable(_shipments);
  List<PickupTask> get pickupTasks => List.unmodifiable(_pickupTasks);
  List<ManifestRecord> get manifests => List.unmodifiable(_manifests);

  void addShipment(Shipment shipment) {
    _shipments.insert(0, shipment);
  }

  void togglePickupTask(int id) {
    final index = _pickupTasks.indexWhere((task) => task.id == id);
    if (index != -1) {
      _pickupTasks[index].isCompleted = !_pickupTasks[index].isCompleted;
    }
  }

  Shipment? findShipmentByResi(String resi) {
    final clean = resi.trim().toUpperCase();
    try {
      return _shipments.firstWhere(
        (s) => s.resi.toUpperCase() == clean,
      );
    } catch (_) {
      return null;
    }
  }

  List<TrackingTimelineItem> getTimelineForResi(Shipment shipment) {
    return [
      TrackingTimelineItem(
        status: 'Order Dibuat & Tiket Terbit',
        location: 'Sistem Kargo Jepara (${shipment.senderName})',
        timestamp: '11 Okt 2026, 09:15 WIB',
        isCompleted: true,
      ),
      TrackingTimelineItem(
        status: 'Barang Selesai Di-pickup Driver',
        location: 'Bengkel Mebel Pengrajin (Tahunan, Jepara)',
        timestamp: '11 Okt 2026, 11:30 WIB',
        isCompleted: shipment.status != 'MENUNGGU_PICKUP',
      ),
      TrackingTimelineItem(
        status: 'Masuk Gudang & Sortir Muatan Hub Jepara',
        location: 'Gudang Pusat Jepara (Tahunan - Batealit)',
        timestamp: '11 Okt 2026, 14:00 WIB',
        isCompleted: shipment.status == 'DI_GUDANG' || shipment.status == 'DALAM_PENGIRIMAN' || shipment.status == 'SELESAI',
      ),
      TrackingTimelineItem(
        status: 'Muat Truk & Berangkat Menuju Kota Tujuan',
        location: 'Lintas Pantura Jalur Utama',
        timestamp: '11 Okt 2026, 20:00 WIB',
        isCompleted: shipment.status == 'DALAM_PENGIRIMAN' || shipment.status == 'SELESAI',
      ),
      TrackingTimelineItem(
        status: 'Barang Diterima Pemesan (Selesai)',
        location: '${shipment.recipientName} (${shipment.destinationCity})',
        timestamp: shipment.status == 'SELESAI' ? '12 Okt 2026, 14:20 WIB' : 'Estimasi Besok Siang',
        isCompleted: shipment.status == 'SELESAI',
      ),
    ];
  }
}
