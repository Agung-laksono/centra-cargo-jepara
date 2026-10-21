import 'package:flutter/material.dart';
import '../services/cargo_service.dart';
import '../theme/app_theme.dart';
import '../models/cargo_models.dart';

class TrackingScreen extends StatefulWidget {
  const TrackingScreen({super.key});

  @override
  State<TrackingScreen> createState() => _TrackingScreenState();
}

class _TrackingScreenState extends State<TrackingScreen> {
  final _searchController = TextEditingController(text: 'GFC-2610-001');
  Shipment? _searchedShipment;
  List<TrackingTimelineItem> _timeline = [];

  @override
  void initState() {
    super.initState();
    _performSearch();
  }

  void _performSearch() {
    final query = _searchController.text.trim();
    if (query.isEmpty) return;

    final found = CargoService().findShipmentByResi(query);
    setState(() {
      _searchedShipment = found;
      if (found != null) {
        _timeline = CargoService().getTimelineForResi(found);
      } else {
        _timeline = [];
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Lacak Resi Kargo Mebel'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Search Box Barcode & Resi
          Container(
            padding: const EdgeInsets.all(4),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: Colors.grey.shade300),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.04),
                  blurRadius: 10,
                  offset: const Offset(0, 4),
                )
              ],
            ),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _searchController,
                    decoration: const InputDecoration(
                      hintText: 'Masukkan No. Resi (cth: GFC-2610-001)',
                      border: InputBorder.none,
                      enabledBorder: InputBorder.none,
                      focusedBorder: InputBorder.none,
                      prefixIcon: Icon(Icons.search_rounded, color: AppTheme.primary),
                    ),
                    onSubmitted: (_) => _performSearch(),
                  ),
                ),
                ElevatedButton(
                  onPressed: _performSearch,
                  style: ElevatedButton.styleFrom(
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                  ),
                  child: const Text('Lacak'),
                ),
                const SizedBox(width: 4),
              ],
            ),
          ),

          const SizedBox(height: 20),

          if (_searchedShipment == null)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 40),
              child: Center(
                child: Column(
                  children: [
                    Icon(Icons.inventory_2_outlined, size: 64, color: Colors.grey.shade400),
                    const SizedBox(height: 12),
                    Text(
                      'Resi "${_searchController.text}" tidak ditemukan.',
                      style: TextStyle(color: Colors.grey.shade600, fontSize: 14),
                    ),
                  ],
                ),
              ),
            )
          else ...[
            // Kartu Ringkasan Resi
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          _searchedShipment!.resi,
                          style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16, color: AppTheme.primary),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: Colors.green.shade50,
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            _searchedShipment!.status.replaceAll('_', ' '),
                            style: TextStyle(color: Colors.green.shade800, fontWeight: FontWeight.bold, fontSize: 11),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    const Divider(height: 1),
                    const SizedBox(height: 12),
                    _buildRowDetail('Pengirim:', _searchedShipment!.senderName),
                    const SizedBox(height: 4),
                    _buildRowDetail('Penerima:', '${_searchedShipment!.recipientName} (${_searchedShipment!.destinationCity})'),
                    const SizedBox(height: 4),
                    _buildRowDetail('Total Koli:', '${_searchedShipment!.totalKoli} Koli Mebel'),
                    const SizedBox(height: 4),
                    _buildRowDetail('Pembayaran:', _searchedShipment!.paymentType),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 24),

            const Text(
              'Timeline Riwayat Perjalanan',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 12),

            ..._timeline.asMap().entries.map((entry) {
              final index = entry.key;
              final item = entry.value;
              final isLast = index == _timeline.length - 1;

              return Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Column(
                    children: [
                      Container(
                        width: 20,
                        height: 20,
                        decoration: BoxDecoration(
                          color: item.isCompleted ? AppTheme.primary : Colors.grey.shade300,
                          shape: BoxShape.circle,
                        ),
                        child: item.isCompleted
                            ? const Icon(Icons.check, size: 13, color: Colors.white)
                            : null,
                      ),
                      if (!isLast)
                        Container(
                          width: 2,
                          height: 54,
                          color: item.isCompleted ? AppTheme.primary.withOpacity(0.5) : Colors.grey.shade300,
                        ),
                    ],
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.only(bottom: 24),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            item.status,
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 13,
                              color: item.isCompleted ? AppTheme.textMain : Colors.grey.shade500,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            item.location,
                            style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            item.timestamp,
                            style: TextStyle(fontSize: 11, color: Colors.grey.shade400),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              );
            }),
          ],
        ],
      ),
    );
  }

  Widget _buildRowDetail(String label, String value) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 90,
          child: Text(label, style: TextStyle(fontSize: 12, color: Colors.grey.shade500)),
        ),
        Expanded(
          child: Text(value, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
        ),
      ],
    );
  }
}
