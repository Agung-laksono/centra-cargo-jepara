class KoliItem {
  final String code;
  final String description;
  final double? length;
  final double? width;
  final double? height;
  final double? weight;
  bool isScanned;

  KoliItem({
    required this.code,
    required this.description,
    this.length,
    this.width,
    this.height,
    this.weight,
    this.isScanned = false,
  });

  double get volumeM3 {
    if (length == null || width == null || height == null) return 0.0;
    return (length! * width! * height!) / 1000000;
  }
}

class Shipment {
  final String resi;
  final String senderName;
  final String senderPhone;
  final String senderCity;
  final String recipientName;
  final String recipientPhone;
  final String destinationCity;
  final String destinationAddress;
  final int totalKoli;
  final double totalPrice;
  final String paymentType; // 'NON_COD', 'COD'
  final String status; // 'MENUNGGU_PICKUP', 'DI_GUDANG', 'DALAM_PENGIRIMAN', 'SELESAI'
  final DateTime createdAt;
  final List<KoliItem> kolis;

  Shipment({
    required this.resi,
    required this.senderName,
    required this.senderPhone,
    required this.senderCity,
    required this.recipientName,
    required this.recipientPhone,
    required this.destinationCity,
    required this.destinationAddress,
    required this.totalKoli,
    required this.totalPrice,
    required this.paymentType,
    required this.status,
    required this.createdAt,
    required this.kolis,
  });
}

class PickupTask {
  final int id;
  final String senderName;
  final String phone;
  final String address;
  final String? mapsUrl;
  final int totalKoli;
  final List<String> koliBarcodes;
  final String itemName;
  bool isCompleted;

  PickupTask({
    required this.id,
    required this.senderName,
    required this.phone,
    required this.address,
    this.mapsUrl,
    required this.totalKoli,
    required this.koliBarcodes,
    required this.itemName,
    this.isCompleted = false,
  });
}

class ManifestRecord {
  final String manifestNumber;
  final String route;
  final String driverName;
  final String vehiclePlate;
  final int totalShipments;
  final int totalKoli;
  final String status; // 'DRAFT', 'ON_THE_WAY', 'ARRIVED'
  final DateTime departTime;

  ManifestRecord({
    required this.manifestNumber,
    required this.route,
    required this.driverName,
    required this.vehiclePlate,
    required this.totalShipments,
    required this.totalKoli,
    required this.status,
    required this.departTime,
  });
}

class TrackingTimelineItem {
  final String status;
  final String location;
  final String timestamp;
  final bool isCompleted;

  TrackingTimelineItem({
    required this.status,
    required this.location,
    required this.timestamp,
    required this.isCompleted,
  });
}
