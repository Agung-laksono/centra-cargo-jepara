import 'package:flutter/material.dart';
import '../services/cargo_service.dart';
import '../theme/app_theme.dart';
import '../models/cargo_models.dart';

class DriverPickupScreen extends StatefulWidget {
  const DriverPickupScreen({super.key});

  @override
  State<DriverPickupScreen> createState() => _DriverPickupScreenState();
}

class _DriverPickupScreenState extends State<DriverPickupScreen> {
  final CargoService _cargoService = CargoService();

  @override
  Widget build(BuildContext context) {
    final tasks = _cargoService.pickupTasks;
    final completedCount = tasks.where((t) => t.isCompleted).length;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Tugas Penjemputan Mebel'),
        actions: [
          IconButton(
            icon: const Icon(Icons.qr_code_scanner_rounded),
            tooltip: 'Scan Barcode Koli',
            onPressed: () => _showScanModal(context),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Banner Status Armada Driver
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: Colors.grey.shade200),
            ),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 24,
                  backgroundColor: AppTheme.accentOrange.withOpacity(0.15),
                  child: const Icon(Icons.local_shipping_rounded, color: AppTheme.accentOrange),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Rute Pickup: Tahunan & Batealit',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Driver: Ahmad Fauzi • K 7823 QC (Pickup Grandmax)',
                        style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  decoration: BoxDecoration(
                    color: AppTheme.primary.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    '$completedCount / ${tasks.length} Selesai',
                    style: const TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold, fontSize: 11),
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 18),
          const Text(
            'Daftar Toko & Pengrajin Mebel',
            style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 10),

          ...tasks.map((task) => _buildPickupCard(task)),
        ],
      ),
    );
  }

  Widget _buildPickupCard(PickupTask task) {
    return Card(
      margin: const EdgeInsets.only(bottom: 14),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    task.senderName,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: task.isCompleted ? Colors.green.shade50 : Colors.amber.shade50,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    task.isCompleted ? 'Sudah Diambil' : 'Menunggu',
                    style: TextStyle(
                      color: task.isCompleted ? Colors.green.shade700 : Colors.amber.shade900,
                      fontWeight: FontWeight.bold,
                      fontSize: 11,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Row(
              children: [
                const Icon(Icons.location_on_outlined, size: 16, color: Colors.grey),
                const SizedBox(width: 4),
                Expanded(
                  child: Text(
                    task.address,
                    style: TextStyle(fontSize: 12, color: Colors.grey.shade700),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: Colors.grey.shade50,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.grey.shade200),
              ),
              child: Row(
                children: [
                  const Icon(Icons.inventory_2_rounded, size: 18, color: AppTheme.primary),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      '${task.itemName} (${task.totalKoli} Koli)',
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                OutlinedButton.icon(
                  onPressed: () {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(content: Text('Membuka Maps ke: ${task.address}')),
                    );
                  },
                  icon: const Icon(Icons.navigation_outlined, size: 16),
                  label: const Text('Rute Maps'),
                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                  ),
                ),
                const Spacer(),
                ElevatedButton.icon(
                  onPressed: () {
                    setState(() {
                      _cargoService.togglePickupTask(task.id);
                    });
                  },
                  icon: Icon(
                    task.isCompleted ? Icons.undo_rounded : Icons.check_circle_outline_rounded,
                    size: 16,
                  ),
                  label: Text(task.isCompleted ? 'Batal' : 'Konfirmasi Ambil'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: task.isCompleted ? Colors.grey.shade700 : AppTheme.primary,
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  void _showScanModal(BuildContext context) {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: AppTheme.primary.withOpacity(0.1),
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.camera_alt_rounded, size: 36, color: AppTheme.primary),
            ),
            const SizedBox(height: 16),
            const Text(
              'Scanner Barcode Koli Fisik',
              style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            Text(
              'Arahkan kamera ke stiker barcode label pada mebel (contoh: GFC-2610-001-1)',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 13, color: Colors.grey.shade600),
            ),
            const SizedBox(height: 24),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () {
                  Navigator.pop(ctx);
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('✓ Barcode Koli GFC-2610-001-1 Berhasil Di-scan! (Audio Beep OK)'),
                      backgroundColor: AppTheme.accentGreen,
                    ),
                  );
                },
                icon: const Icon(Icons.check_rounded),
                label: const Text('Simulasi Scan Barcode Berhasil'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
