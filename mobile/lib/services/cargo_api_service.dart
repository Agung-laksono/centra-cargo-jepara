import 'dart:convert';
import 'package:http/http.dart' as http;
import '../models/cargo_models.dart';

class CargoApiService {
  static const String baseUrl = 'http://127.0.0.1:8000/api/cargo';

  // Singleton
  static final CargoApiService _instance = CargoApiService._internal();
  factory CargoApiService() => _instance;
  CargoApiService._internal();

  /// Mengambil metrik dashboard & pengiriman terbaru dari database Laravel
  Future<Map<String, dynamic>> fetchDashboard() async {
    try {
      final res = await http.get(Uri.parse('$baseUrl/dashboard')).timeout(const Duration(seconds: 4));
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        return body['data'] ?? {};
      }
    } catch (e) {
      // Fallback
    }
    return {};
  }

  /// Mengambil daftar resi langsung dari database
  Future<List<Shipment>> fetchShipments({String? search, String? status}) async {
    try {
      String url = '$baseUrl/shipments?';
      if (search != null && search.isNotEmpty) url += 'search=$search&';
      if (status != null && status != 'Semua') url += 'status=$status&';

      final res = await http.get(Uri.parse(url)).timeout(const Duration(seconds: 4));
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        final list = (body['data']?['data'] as List?) ?? [];
        return list.map((json) {
          final sender = json['sender'];
          final kolis = (json['kolis'] as List?) ?? [];
          return Shipment(
            resi: json['tracking_number'] ?? 'GFC-NEW',
            senderName: sender != null ? (sender['business_name'] ?? 'UD Mebel') : 'Pengrajin Jepara',
            senderPhone: sender != null ? (sender['phone'] ?? '') : '',
            senderCity: sender != null ? (sender['city'] ?? 'Jepara') : 'Jepara',
            recipientName: json['recipient_name'] ?? '',
            recipientPhone: json['recipient_phone'] ?? '',
            destinationCity: _extractCity(json['destination_city']),
            destinationAddress: json['recipient_address'] ?? '',
            totalKoli: kolis.isNotEmpty ? kolis.length : 1,
            totalPrice: (json['total_cod'] as num?)?.toDouble() ?? ((json['freight_fee'] as num?)?.toDouble() ?? 0.0),
            paymentType: json['payment_type'] ?? 'COD',
            status: json['current_status'] ?? 'MENUNGGU_BARANG',
            createdAt: DateTime.tryParse(json['created_at'] ?? '') ?? DateTime.now(),
            kolis: kolis.map((k) => KoliItem(
              code: k['barcode_id'] ?? '',
              description: k['description'] ?? '',
            )).toList(),
          );
        }).toList();
      }
    } catch (e) {
      // Error handling
    }
    return [];
  }

  /// Kirim Resi Baru ke Database Laravel via API
  Future<bool> createShipment({
    required int senderId,
    required String metodePickup,
    required String recipientName,
    required String recipientPhone,
    required String destinationCity,
    required String recipientAddress,
    required String paymentType,
    required double freightFee,
    required double goodsValue,
    required List<Map<String, dynamic>> items,
  }) async {
    try {
      final res = await http.post(
        Uri.parse('$baseUrl/shipments'),
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
        body: jsonEncode({
          'sender_id': senderId,
          'metode_pickup': metodePickup,
          'recipient_name': recipientName,
          'recipient_phone': recipientPhone,
          'destination_city': {'name': destinationCity},
          'recipient_address': recipientAddress,
          'payment_type': paymentType,
          'freight_fee': freightFee,
          'goods_value': goodsValue,
          'items': items,
        }),
      ).timeout(const Duration(seconds: 6));

      return res.statusCode == 200 || res.statusCode == 201;
    } catch (e) {
      return false;
    }
  }

  /// Master Toko Pengirim
  Future<List<Map<String, dynamic>>> fetchSenders() async {
    try {
      final res = await http.get(Uri.parse('$baseUrl/senders')).timeout(const Duration(seconds: 4));
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        return ((body['data'] as List?) ?? []).cast<Map<String, dynamic>>();
      }
    } catch (_) {}
    return [];
  }

  /// Riwayat Penerima Pelanggan
  Future<List<Map<String, dynamic>>> fetchRecipients() async {
    try {
      final res = await http.get(Uri.parse('$baseUrl/recipients')).timeout(const Duration(seconds: 4));
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        return ((body['data'] as List?) ?? []).cast<Map<String, dynamic>>();
      }
    } catch (_) {}
    return [];
  }

  /// Smart Search Wilayah Indonesia
  Future<List<Map<String, dynamic>>> searchWilayah(String query) async {
    final clean = query.trim();
    if (clean.length < 3) return [];
    
    // Coba via 127.0.0.1 (adb reverse) lalu fallback ke IP LAN
    final urls = [
      'http://127.0.0.1:8000/api/wilayah/search?q=${Uri.encodeComponent(clean)}',
      'http://192.168.18.113:8000/api/wilayah/search?q=${Uri.encodeComponent(clean)}',
    ];

    for (var url in urls) {
      try {
        final res = await http.get(Uri.parse(url)).timeout(const Duration(seconds: 5));
        if (res.statusCode == 200) {
          final decoded = jsonDecode(res.body);
          if (decoded is List) {
            return decoded.map((e) => Map<String, dynamic>.from(e)).toList();
          }
        }
      } catch (_) {}
    }
    return [];
  }

  /// Lacak Resi Real-Time
  Future<Map<String, dynamic>?> trackResi(String resi) async {
    try {
      final res = await http.get(Uri.parse('$baseUrl/track/$resi')).timeout(const Duration(seconds: 4));
      if (res.statusCode == 200) {
        final body = jsonDecode(res.body);
        return body['data'];
      }
    } catch (_) {}
    return null;
  }

  static String _extractCity(dynamic cityField) {
    if (cityField is Map) return cityField['name'] ?? 'Kota';
    if (cityField is String) return cityField;
    return 'Jawa';
  }
}
