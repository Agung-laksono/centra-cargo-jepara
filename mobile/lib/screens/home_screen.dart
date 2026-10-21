import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../services/cargo_service.dart';
import '../theme/app_theme.dart';
import '../models/cargo_models.dart';

class HomeScreen extends StatefulWidget {
  final Function(int) onNavigateTab;

  const HomeScreen({super.key, required this.onNavigateTab});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final _searchResiController = TextEditingController();
  int _activeSubTab = 0; // 0: Operasional, 1: Aktivitas, 2: Keuangan

  @override
  Widget build(BuildContext context) {
    final cargoService = CargoService();
    final shipments = cargoService.shipments;
    final currency = NumberFormat.currency(locale: 'id_ID', symbol: 'Rp ', decimalDigits: 0);

    return Scaffold(
      backgroundColor: const Color(0xFFFBFBFD),
      body: Stack(
        children: [
          // Ambient Radial Glow di bagian atas (persis seperti .ambient di web)
          Positioned(
            top: -120,
            left: 0,
            right: 0,
            child: Center(
              child: Container(
                width: 450,
                height: 380,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: RadialGradient(
                    colors: [
                      AppTheme.primary.withValues(alpha: 0.14),
                      AppTheme.primaryLight.withValues(alpha: 0.05),
                      Colors.transparent,
                    ],
                  ),
                ),
              ),
            ),
          ),

          SafeArea(
            child: ListView(
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
              children: [
                // Top Navbar (Persis seperti Nav Web)
                Row(
                  children: [
                    Container(
                      width: 42,
                      height: 42,
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          colors: [AppTheme.primary, AppTheme.primaryDark],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        borderRadius: BorderRadius.circular(12),
                        boxShadow: [
                          BoxShadow(
                            color: AppTheme.primary.withValues(alpha: 0.28),
                            blurRadius: 10,
                            offset: const Offset(0, 4),
                          )
                        ],
                      ),
                      child: const Icon(Icons.local_shipping_rounded, color: Colors.white, size: 22),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Jepara Cargo',
                            style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w800,
                              color: AppTheme.textHeadline,
                              letterSpacing: -0.4,
                            ),
                          ),
                          Text(
                            'Sistem Ekspedisi & Kargo Terpadu',
                            style: TextStyle(
                              fontSize: 11,
                              color: Colors.grey.shade600,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                        ],
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
                      decoration: BoxDecoration(
                        color: AppTheme.primary,
                        borderRadius: BorderRadius.circular(98),
                        boxShadow: [
                          BoxShadow(
                            color: AppTheme.primary.withValues(alpha: 0.3),
                            blurRadius: 10,
                            offset: const Offset(0, 4),
                          )
                        ],
                      ),
                      child: const Row(
                        children: [
                          Icon(Icons.hub_rounded, size: 14, color: Colors.white),
                          SizedBox(width: 6),
                          Text(
                            'Hub Jepara',
                            style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),

                const SizedBox(height: 28),

                // Hero Headline Khas Web
                RichText(
                  text: const TextSpan(
                    style: TextStyle(
                      fontFamily: 'Plus Jakarta Sans',
                      fontSize: 30,
                      fontWeight: FontWeight.w800,
                      height: 1.15,
                      letterSpacing: -1.0,
                      color: AppTheme.textHeadline,
                    ),
                    children: [
                      TextSpan(text: 'Lacak Furniture\n'),
                      TextSpan(
                        text: 'Anda Disini.',
                        style: TextStyle(
                          color: AppTheme.primary,
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 16),

                // Floating Hero Tracking Card (Persis .tracking-card di Web)
                Container(
                  padding: const EdgeInsets.all(6),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(22),
                    border: Border.all(color: Colors.black.withValues(alpha: 0.07)),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.06),
                        blurRadius: 24,
                        offset: const Offset(0, 10),
                      ),
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.02),
                        blurRadius: 6,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Row(
                    children: [
                      const SizedBox(width: 12),
                      const Icon(Icons.search_rounded, color: AppTheme.primary, size: 22),
                      const SizedBox(width: 10),
                      Expanded(
                        child: TextField(
                          controller: _searchResiController,
                          style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
                          decoration: InputDecoration(
                            hintText: 'Cari nomor resi kargo mebel...',
                            hintStyle: TextStyle(color: Colors.grey.shade400, fontSize: 13),
                            border: InputBorder.none,
                            enabledBorder: InputBorder.none,
                            focusedBorder: InputBorder.none,
                            contentPadding: EdgeInsets.zero,
                          ),
                          onSubmitted: (v) => widget.onNavigateTab(4),
                        ),
                      ),
                      InkWell(
                        onTap: () => widget.onNavigateTab(4),
                        borderRadius: BorderRadius.circular(98),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
                          decoration: BoxDecoration(
                            color: AppTheme.primary,
                            borderRadius: BorderRadius.circular(98),
                            boxShadow: [
                              BoxShadow(
                                color: AppTheme.primary.withValues(alpha: 0.35),
                                blurRadius: 10,
                                offset: const Offset(0, 3),
                              )
                            ],
                          ),
                          child: const Row(
                            children: [
                              Text(
                                'Lacak',
                                style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold),
                              ),
                              SizedBox(width: 4),
                              Icon(Icons.arrow_forward_rounded, color: Colors.white, size: 16),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 28),

                // Sub-Tabs Operasional / Aktivitas / Keuangan (Persis Dashboard.vue)
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      _buildPillTab('Operasional', Icons.dashboard_outlined, 0),
                      const SizedBox(width: 8),
                      _buildPillTab('Aktivitas Terkini', Icons.history_rounded, 1),
                      const SizedBox(width: 8),
                      _buildPillTab('Keuangan & COD', Icons.account_balance_wallet_outlined, 2),
                    ],
                  ),
                ),

                const SizedBox(height: 18),

                // Grid Statistik Operasional (Persis props.stats di Dashboard.vue)
                if (_activeSubTab == 0) ...[
                  Row(
                    children: [
                      Expanded(
                        child: _buildMetricCard(
                          title: 'Menunggu Pickup',
                          value: '3 Toko',
                          subtext: '4 Koli Siap',
                          icon: Icons.storefront_rounded,
                          color: Colors.orange,
                          onTap: () => widget.onNavigateTab(2),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _buildMetricCard(
                          title: 'Di Gudang Hub',
                          value: '64 Koli',
                          subtext: 'Siap Muat Truk',
                          icon: Icons.warehouse_rounded,
                          color: AppTheme.primary,
                          onTap: () => widget.onNavigateTab(3),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: _buildMetricCard(
                          title: 'Dalam Perjalanan',
                          value: '2 Truk',
                          subtext: 'Lintas Pantura',
                          icon: Icons.local_shipping_rounded,
                          color: Colors.blue,
                          onTap: () => widget.onNavigateTab(3),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _buildMetricCard(
                          title: 'Terkirim Bulan Ini',
                          value: '248 Resi',
                          subtext: '100% On-Time',
                          icon: Icons.check_circle_outline_rounded,
                          color: Colors.green,
                          onTap: () => widget.onNavigateTab(4),
                        ),
                      ),
                    ],
                  ),
                ] else if (_activeSubTab == 2) ...[
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Color(0xFF1E293B), Color(0xFF0F172A)],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Total Kas Masuk & COD Berjalan', style: TextStyle(color: Colors.white70, fontSize: 13)),
                        const SizedBox(height: 6),
                        Text(
                          currency.format(15450000),
                          style: const TextStyle(color: Colors.amber, fontSize: 26, fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 14),
                        const Divider(color: Colors.white24),
                        const SizedBox(height: 10),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Uang COD di Supir:', style: TextStyle(color: Colors.white70, fontSize: 12)),
                            Text(currency.format(3500000), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
                          ],
                        ),
                      ],
                    ),
                  ),
                ] else ...[
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: Colors.black.withValues(alpha: 0.06)),
                    ),
                    child: Column(
                      children: [
                        _buildActivityRow('Resi GFC-2610-001 dimuat ke Truk Fuso K 8921 QC', '10 menit lalu'),
                        const Divider(height: 18),
                        _buildActivityRow('Driver Ahmad Fauzi konfirmasi pickup UD Maju Mebel', '35 menit lalu'),
                        const Divider(height: 18),
                        _buildActivityRow('Resi baru diterbitkan oleh Sentra Rotan Senenan', '1 jam lalu'),
                      ],
                    ),
                  ),
                ],

                const SizedBox(height: 26),

                // Tombol Aksi Cepat Buat Resi Baru (Tombol Lebar Elegan)
                InkWell(
                  onTap: () => widget.onNavigateTab(1),
                  borderRadius: BorderRadius.circular(20),
                  child: Container(
                    padding: const EdgeInsets.all(18),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: AppTheme.primary.withValues(alpha: 0.2)),
                      boxShadow: [
                        BoxShadow(
                          color: AppTheme.primary.withValues(alpha: 0.06),
                          blurRadius: 16,
                          offset: const Offset(0, 6),
                        )
                      ],
                    ),
                    child: Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: AppTheme.primary.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: const Icon(Icons.post_add_rounded, color: AppTheme.primary, size: 26),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Buat Resi Kargo Baru',
                                style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: AppTheme.textHeadline),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                'Multi-koli mebel, hitung kubikasi M³ & cetak resi',
                                style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                              ),
                            ],
                          ),
                        ),
                        const Icon(Icons.chevron_right_rounded, color: AppTheme.primary, size: 24),
                      ],
                    ),
                  ),
                ),

                const SizedBox(height: 28),

                // Daftar Pengiriman Terkini
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Pengiriman Terkini',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: AppTheme.textHeadline),
                    ),
                    TextButton(
                      onPressed: () => widget.onNavigateTab(4),
                      child: const Text('Semua Resi', style: TextStyle(fontWeight: FontWeight.bold, color: AppTheme.primary)),
                    ),
                  ],
                ),

                const SizedBox(height: 6),

                ...shipments.map((s) => _buildCleanShipmentCard(s, currency)),
                const SizedBox(height: 80),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPillTab(String title, IconData icon, int index) {
    final isSelected = _activeSubTab == index;
    return InkWell(
      onTap: () => setState(() => _activeSubTab = index),
      borderRadius: BorderRadius.circular(98),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
        decoration: BoxDecoration(
          color: isSelected ? AppTheme.primary : Colors.white,
          borderRadius: BorderRadius.circular(98),
          border: Border.all(
            color: isSelected ? AppTheme.primary : Colors.black.withValues(alpha: 0.08),
          ),
          boxShadow: isSelected
              ? [
                  BoxShadow(
                    color: AppTheme.primary.withValues(alpha: 0.25),
                    blurRadius: 8,
                    offset: const Offset(0, 3),
                  )
                ]
              : null,
        ),
        child: Row(
          children: [
            Icon(icon, size: 16, color: isSelected ? Colors.white : Colors.grey.shade700),
            const SizedBox(width: 6),
            Text(
              title,
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: isSelected ? Colors.white : Colors.grey.shade800,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildMetricCard({
    required String title,
    required String value,
    required String subtext,
    required IconData icon,
    required Color color,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: Colors.black.withValues(alpha: 0.06)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.03),
              blurRadius: 12,
              offset: const Offset(0, 4),
            )
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, color: color, size: 20),
            ),
            const SizedBox(height: 12),
            Text(
              value,
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: AppTheme.textHeadline),
            ),
            const SizedBox(height: 2),
            Text(
              title,
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Colors.grey.shade600),
            ),
            const SizedBox(height: 4),
            Text(
              subtext,
              style: TextStyle(fontSize: 11, color: color, fontWeight: FontWeight.bold),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildCleanShipmentCard(Shipment s, NumberFormat currency) {
    Color statusColor;
    String statusLabel;

    switch (s.status) {
      case 'MENUNGGU_PICKUP':
        statusColor = Colors.orange;
        statusLabel = 'Menunggu Pickup';
        break;
      case 'DI_GUDANG':
        statusColor = AppTheme.primary;
        statusLabel = 'Di Gudang Hub';
        break;
      case 'DALAM_PENGIRIMAN':
        statusColor = Colors.blue;
        statusLabel = 'Dalam Perjalanan';
        break;
      default:
        statusColor = Colors.green;
        statusLabel = 'Selesai';
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Colors.black.withValues(alpha: 0.06)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.025),
            blurRadius: 10,
            offset: const Offset(0, 3),
          )
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                s.resi,
                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, letterSpacing: 0.5, color: AppTheme.primaryDark),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: statusColor.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(98),
                ),
                child: Text(
                  statusLabel,
                  style: TextStyle(color: statusColor, fontSize: 11, fontWeight: FontWeight.bold),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            '${s.senderName} ➔ ${s.destinationCity}',
            style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppTheme.textHeadline),
          ),
          const SizedBox(height: 4),
          Text(
            s.destinationAddress,
            style: TextStyle(fontSize: 11, color: Colors.grey.shade500),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 10),
          const Divider(height: 1),
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                '${s.totalKoli} Koli Mebel • ${s.paymentType}',
                style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Colors.grey.shade700),
              ),
              Text(
                currency.format(s.totalPrice),
                style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: AppTheme.primary),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildActivityRow(String text, String time) {
    return Row(
      children: [
        Container(
          width: 8,
          height: 8,
          decoration: const BoxDecoration(
            color: AppTheme.primary,
            shape: BoxShape.circle,
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w500, color: AppTheme.textHeadline),
          ),
        ),
        Text(
          time,
          style: TextStyle(fontSize: 11, color: Colors.grey.shade400),
        ),
      ],
    );
  }
}
