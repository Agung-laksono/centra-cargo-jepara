import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../services/cargo_service.dart';
import '../theme/app_theme.dart';
import '../models/cargo_models.dart';

class ManifestScreen extends StatelessWidget {
  const ManifestScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final manifests = CargoService().manifests;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Surat Jalan & Manifest Truk'),
        actions: [
          IconButton(
            icon: const Icon(Icons.add_box_outlined),
            tooltip: 'Buat Manifest Baru',
            onPressed: () {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Form pembuatan manifest baru dibuka')),
              );
            },
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Manifest Perjalanan Aktif',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: AppTheme.secondary.withOpacity(0.08),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  '${manifests.length} Surat Jalan',
                  style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppTheme.secondary),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          ...manifests.map((m) => _buildManifestCard(context, m)),
        ],
      ),
    );
  }

  Widget _buildManifestCard(BuildContext context, ManifestRecord m) {
    final isDeparted = m.status == 'ON_THE_WAY';

    return Card(
      margin: const EdgeInsets.only(bottom: 14),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    const Icon(Icons.description_outlined, size: 18, color: AppTheme.primary),
                    const SizedBox(width: 8),
                    Text(
                      m.manifestNumber,
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                    ),
                  ],
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: isDeparted ? Colors.blue.shade50 : Colors.grey.shade100,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    isDeparted ? 'Truk Sedang Jalan' : 'Draft / Siap Muat',
                    style: TextStyle(
                      color: isDeparted ? Colors.blue.shade800 : Colors.grey.shade800,
                      fontWeight: FontWeight.bold,
                      fontSize: 11,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.grey.shade50,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Column(
                children: [
                  Row(
                    children: [
                      const Icon(Icons.route_outlined, size: 16, color: AppTheme.primary),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          m.route,
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      const Icon(Icons.person_outline_rounded, size: 16, color: Colors.grey),
                      const SizedBox(width: 8),
                      Text('Driver: ${m.driverName}', style: const TextStyle(fontSize: 12)),
                      const Spacer(),
                      Text(m.vehiclePlate, style: TextStyle(fontSize: 12, color: Colors.grey.shade700, fontWeight: FontWeight.w600)),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  '${m.totalShipments} Resi • ${m.totalKoli} Koli Mebel',
                  style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13, color: AppTheme.primaryDark),
                ),
                Text(
                  DateFormat('dd MMM yyyy, HH:mm').format(m.departTime),
                  style: TextStyle(fontSize: 11, color: Colors.grey.shade500),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
