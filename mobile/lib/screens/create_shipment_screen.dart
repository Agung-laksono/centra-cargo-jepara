import 'dart:async';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../services/cargo_service.dart';
import '../services/cargo_api_service.dart';
import '../theme/app_theme.dart';
import '../models/cargo_models.dart';

class CreateShipmentScreen extends StatefulWidget {
  final VoidCallback? onSuccess;

  const CreateShipmentScreen({super.key, this.onSuccess});

  @override
  State<CreateShipmentScreen> createState() => _CreateShipmentScreenState();
}

class _CreateShipmentScreenState extends State<CreateShipmentScreen> {
  final CargoApiService _apiService = CargoApiService();

  int _activeTab = 0; // 0: Form Input, 1: Daftar Resi
  int _currentStep = 1; // 1: Pengirim, 2: Penerima, 3: Barang, 4: Keuangan

  // ==========================================
  // DATA MASTER & DROPDOWN AUTOCOMPLETE
  // ==========================================
  List<Map<String, dynamic>> _sendersList = [];
  List<Map<String, dynamic>> _filteredSenders = [];
  bool _showSenderDropdown = false;
  int? _selectedSenderId;

  List<Map<String, dynamic>> _recipientsHistory = [];
  List<Map<String, dynamic>> _filteredRecipients = [];
  bool _showRecipientDropdown = false;

  List<Map<String, dynamic>> _wilayahResults = [];
  bool _showWilayahDropdown = false;
  bool _isSearchingWilayah = false;
  Timer? _wilayahDebounceTimer;

  // LANGKAH 1: PENGIRIM
  final _searchSenderController = TextEditingController();
  final _senderPhoneController = TextEditingController();
  String _metodePickup = 'PICKUP'; // 'DROP_OFF' / 'PICKUP'

  // LANGKAH 2: PENERIMA
  final _recipientNameController = TextEditingController();
  final _recipientPhoneController = TextEditingController();
  final _searchWilayahController = TextEditingController();
  final _recipientAddressController = TextEditingController();

  // LANGKAH 3: ITEMS BARANG
  final List<Map<String, dynamic>> _items = [
    {
      'name': 'Lemari 4 Pintu Jati',
      'unit': 'Set',
      'qty': 1,
      'koli_per_item': 3,
    },
  ];
  final List<String> _unitOptions = ['Pcs', 'Set', 'Koli', 'Dus', 'Unit'];

  // LANGKAH 4: KEUANGAN (BORONGAN)
  String _paymentType = 'COD';
  final _goodsValueController = TextEditingController(text: '2.500.000');
  final _freightFeeController = TextEditingController(text: '450.000');

  @override
  void initState() {
    super.initState();
    _loadMasterData();
  }

  Future<void> _loadMasterData() async {
    final senders = await _apiService.fetchSenders();
    final recipients = await _apiService.fetchRecipients();
    if (mounted) {
      setState(() {
        _sendersList = senders;
        _recipientsHistory = recipients;
        if (_sendersList.isNotEmpty) {
          _selectSender(_sendersList.first);
        }
      });
    }
  }

  void _onSearchSenderChanged(String q) {
    final query = q.trim().toLowerCase();
    setState(() {
      if (query.isEmpty) {
        _filteredSenders = _sendersList;
      } else {
        _filteredSenders = _sendersList.where((s) {
          final name = (s['business_name'] ?? '').toString().toLowerCase();
          final phone = (s['phone'] ?? '').toString().toLowerCase();
          return name.contains(query) || phone.contains(query);
        }).toList();
      }
      _showSenderDropdown = _filteredSenders.isNotEmpty;
    });
  }

  void _selectSender(Map<String, dynamic> sender) {
    setState(() {
      _selectedSenderId = sender['id'];
      _searchSenderController.text = sender['business_name'] ?? '';
      _senderPhoneController.text = sender['phone'] ?? '';
      _showSenderDropdown = false;
    });
  }

  void _onSearchRecipientChanged(String q) {
    final query = q.trim().toLowerCase();
    setState(() {
      if (query.isEmpty) {
        _filteredRecipients = _recipientsHistory;
      } else {
        _filteredRecipients = _recipientsHistory.where((r) {
          final name = (r['recipient_name'] ?? '').toString().toLowerCase();
          final phone = (r['recipient_phone'] ?? '').toString().toLowerCase();
          return name.contains(query) || phone.contains(query);
        }).toList();
      }
      _showRecipientDropdown = _filteredRecipients.isNotEmpty;
    });
  }

  void _selectRecipient(Map<String, dynamic> r) {
    setState(() {
      _recipientNameController.text = r['recipient_name'] ?? '';
      _recipientPhoneController.text = r['recipient_phone'] ?? '';
      _recipientAddressController.text = r['recipient_address'] ?? '';
      final city = r['destination_city'];
      if (city is Map) {
        _searchWilayahController.text = city['name'] ?? '';
      } else if (city is String) {
        _searchWilayahController.text = city;
      }
      _showRecipientDropdown = false;
    });
  }

  @override
  void dispose() {
    _wilayahDebounceTimer?.cancel();
    super.dispose();
  }

  void _onSearchWilayahChanged(String query) {
    _wilayahDebounceTimer?.cancel();
    final clean = query.trim();

    if (clean.length < 3) {
      setState(() {
        _wilayahResults = [];
        _showWilayahDropdown = false;
        _isSearchingWilayah = false;
      });
      return;
    }

    setState(() => _isSearchingWilayah = true);

    _wilayahDebounceTimer = Timer(const Duration(milliseconds: 350), () async {
      final results = await _apiService.searchWilayah(clean);
      if (mounted) {
        setState(() {
          _isSearchingWilayah = false;
          _wilayahResults = results;
          _showWilayahDropdown = true; // tampilkan dropdown hasil (atau kosong jika tidak ada)
        });
      }
    });
  }

  void _selectWilayah(Map<String, dynamic> w) {
    setState(() {
      _searchWilayahController.text = '${w['village_name']}, ${w['district_name']}, ${w['city_name']}, ${w['province_name']}';
      _showWilayahDropdown = false;
    });
  }

  void _tambahItem() {
    setState(() {
      _items.add({
        'name': '',
        'unit': 'Pcs',
        'qty': 1,
        'koli_per_item': 1,
      });
    });
  }

  void _hapusItem(int index) {
    if (_items.length <= 1) return;
    setState(() {
      _items.removeAt(index);
    });
  }

  Future<void> _simpanResi() async {
    if (_recipientNameController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Nama lengkap penerima wajib diisi!'), backgroundColor: Colors.red),
      );
      return;
    }

    final cleanFreight = double.tryParse(_freightFeeController.text.replaceAll('.', '').replaceAll(',', '')) ?? 450000;
    final cleanGoods = double.tryParse(_goodsValueController.text.replaceAll('.', '').replaceAll(',', '')) ?? 2500000;

    final success = await _apiService.createShipment(
      senderId: _selectedSenderId ?? 1,
      metodePickup: _metodePickup,
      recipientName: _recipientNameController.text.trim(),
      recipientPhone: _recipientPhoneController.text.trim(),
      destinationCity: _searchWilayahController.text.trim().isEmpty ? 'Jakarta Selatan' : _searchWilayahController.text.trim(),
      recipientAddress: _recipientAddressController.text.trim(),
      paymentType: _paymentType,
      freightFee: cleanFreight,
      goodsValue: cleanGoods,
      items: _items,
    );

    if (!mounted) return;

    if (success) {
      showDialog(
        context: context,
        builder: (ctx) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          title: const Row(
            children: [
              Icon(Icons.check_circle_rounded, color: AppTheme.accentGreen, size: 28),
              SizedBox(width: 10),
              Text('Resi Berhasil Terbit!'),
            ],
          ),
          content: Text(
            'Resi berhasil dibuat dan disimpan langsung ke database backend Laravel.\n\nTujuan: ${_searchWilayahController.text}',
          ),
          actions: [
            ElevatedButton(
              onPressed: () {
                Navigator.pop(ctx);
                widget.onSuccess?.call();
              },
              child: const Text('Selesai'),
            ),
          ],
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Gagal mengirim ke server. Mohon periksa form Anda!'), backgroundColor: Colors.red),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final currency = NumberFormat.currency(locale: 'id_ID', symbol: 'Rp ', decimalDigits: 0);

    return Scaffold(
      backgroundColor: const Color(0xFFFBFBFD),
      appBar: AppBar(
        title: const Text('Buat Resi'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(48),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
            child: Container(
              height: 40,
              decoration: BoxDecoration(
                color: Colors.grey.shade200,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: _buildHeaderTab('Form Input', Icons.add_box_rounded, 0),
                  ),
                  Expanded(
                    child: _buildHeaderTab('Daftar Resi', Icons.list_alt_rounded, 1),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
      body: _activeTab == 0 ? _buildFormTab() : _buildListTab(currency),
    );
  }

  Widget _buildHeaderTab(String label, IconData icon, int index) {
    final isSelected = _activeTab == index;
    return InkWell(
      onTap: () => setState(() => _activeTab = index),
      borderRadius: BorderRadius.circular(10),
      child: Container(
        margin: const EdgeInsets.all(3),
        decoration: BoxDecoration(
          color: isSelected ? AppTheme.primary : Colors.transparent,
          borderRadius: BorderRadius.circular(10),
          boxShadow: isSelected
              ? [
                  BoxShadow(
                    color: AppTheme.primary.withValues(alpha: 0.25),
                    blurRadius: 6,
                    offset: const Offset(0, 2),
                  )
                ]
              : null,
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 15, color: isSelected ? Colors.white : Colors.grey.shade600),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.bold,
                color: isSelected ? Colors.white : Colors.grey.shade700,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFormTab() {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        // LANGKAH 1: PENGIRIM DENGAN AUTOCOMPLETE DROPDOWN
        _buildStepCard(
          stepNumber: 'LANGKAH 1',
          title: 'Data Pengirim',
          icon: Icons.store_mall_directory_rounded,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Cari Nama Toko / Pengirim', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
              const SizedBox(height: 6),
              TextField(
                controller: _searchSenderController,
                decoration: const InputDecoration(
                  prefixIcon: Icon(Icons.search_rounded),
                  hintText: 'Ketik nama toko mebel atau WhatsApp...',
                ),
                onChanged: _onSearchSenderChanged,
                onTap: () => _onSearchSenderChanged(_searchSenderController.text),
              ),

              // Pop-up Dropdown Pengirim
              if (_showSenderDropdown && _filteredSenders.isNotEmpty)
                Container(
                  margin: const EdgeInsets.only(top: 6),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: Colors.grey.shade300),
                    boxShadow: const [BoxShadow(color: Colors.black12, blurRadius: 10, offset: Offset(0, 4))],
                  ),
                  child: ListView.separated(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    itemCount: _filteredSenders.length > 5 ? 5 : _filteredSenders.length,
                    separatorBuilder: (_, __) => const Divider(height: 1),
                    itemBuilder: (ctx, i) {
                      final s = _filteredSenders[i];
                      return ListTile(
                        dense: true,
                        leading: const Icon(Icons.storefront_rounded, color: AppTheme.primary, size: 20),
                        title: Text(s['business_name'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                        subtitle: Text(s['phone'] ?? '', style: TextStyle(color: Colors.grey.shade600, fontSize: 11)),
                        onTap: () => _selectSender(s),
                      );
                    },
                  ),
                ),

              const SizedBox(height: 14),
              const Text('Penyerahan Barang ke Gudang', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: _buildPickupOption(
                      label: 'Diantar Pengirim',
                      isSelected: _metodePickup == 'DROP_OFF',
                      onTap: () => setState(() => _metodePickup = 'DROP_OFF'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _buildPickupOption(
                      label: 'Minta Dijemput Armada',
                      isSelected: _metodePickup == 'PICKUP',
                      onTap: () => setState(() => _metodePickup = 'PICKUP'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                '*Catatan: Resi penjemputan akan otomatis masuk ke tugas Supir Pick-up.',
                style: TextStyle(fontSize: 11, color: Colors.grey.shade500),
              ),
              if (_currentStep == 1) ...[
                const SizedBox(height: 16),
                Align(
                  alignment: Alignment.centerRight,
                  child: ElevatedButton.icon(
                    onPressed: () => setState(() => _currentStep = 2),
                    icon: const Text('Lanjut ke Penerima'),
                    label: const Icon(Icons.arrow_forward_rounded, size: 16),
                  ),
                ),
              ],
            ],
          ),
        ),

        // LANGKAH 2: PENERIMA DENGAN AUTOCOMPLETE & WILAYAH SMART LOOKUP
        if (_currentStep >= 2) ...[
          const SizedBox(height: 16),
          _buildStepCard(
            stepNumber: 'LANGKAH 2',
            title: 'Data Penerima',
            icon: Icons.person_pin_circle_rounded,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Nama Lengkap Penerima *', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                const SizedBox(height: 6),
                TextField(
                  controller: _recipientNameController,
                  decoration: const InputDecoration(
                    prefixIcon: Icon(Icons.person_outline_rounded),
                    hintText: 'Ketik nama pembeli atau pilih dari riwayat...',
                  ),
                  onChanged: _onSearchRecipientChanged,
                  onTap: () => _onSearchRecipientChanged(_recipientNameController.text),
                ),

                // Pop-up Dropdown Riwayat Penerima
                if (_showRecipientDropdown && _filteredRecipients.isNotEmpty)
                  Container(
                    margin: const EdgeInsets.only(top: 6),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: Colors.grey.shade300),
                      boxShadow: const [BoxShadow(color: Colors.black12, blurRadius: 10, offset: Offset(0, 4))],
                    ),
                    child: ListView.separated(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: _filteredRecipients.length > 5 ? 5 : _filteredRecipients.length,
                      separatorBuilder: (_, __) => const Divider(height: 1),
                      itemBuilder: (ctx, i) {
                        final r = _filteredRecipients[i];
                        return ListTile(
                          dense: true,
                          leading: const Icon(Icons.history_rounded, color: AppTheme.primary, size: 20),
                          title: Text(r['recipient_name'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                          subtitle: Text(r['recipient_phone'] ?? '', style: TextStyle(color: Colors.grey.shade600, fontSize: 11)),
                          onTap: () => _selectRecipient(r),
                        );
                      },
                    ),
                  ),

                const SizedBox(height: 12),
                TextField(
                  controller: _recipientPhoneController,
                  decoration: const InputDecoration(
                    labelText: 'No. WhatsApp Penerima *',
                    prefixIcon: Icon(Icons.phone_outlined),
                    hintText: '0812xxxxxx',
                  ),
                  keyboardType: TextInputType.phone,
                ),

                const SizedBox(height: 14),
                const Text('Cari Wilayah (Kecamatan / Kelurahan) *', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                const SizedBox(height: 6),
                TextField(
                  controller: _searchWilayahController,
                  decoration: InputDecoration(
                    prefixIcon: const Icon(Icons.map_outlined),
                    suffixIcon: _isSearchingWilayah
                        ? const SizedBox(width: 16, height: 16, child: Padding(padding: EdgeInsets.all(12), child: CircularProgressIndicator(strokeWidth: 2)))
                        : null,
                    hintText: 'Ketik nama kecamatan atau desa (cth: Tahunan)...',
                  ),
                  onChanged: _onSearchWilayahChanged,
                ),

                // Pop-up Dropdown Wilayah Smart Search
                if (_showWilayahDropdown)
                  Container(
                    margin: const EdgeInsets.only(top: 6),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: Colors.grey.shade300),
                      boxShadow: const [BoxShadow(color: Colors.black12, blurRadius: 12, offset: Offset(0, 4))],
                    ),
                    child: _wilayahResults.isEmpty
                        ? Padding(
                            padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 16),
                            child: Row(
                              children: [
                                Icon(Icons.info_outline_rounded, color: Colors.orange.shade400, size: 20),
                                const SizedBox(width: 8),
                                Expanded(
                                  child: Text(
                                    _isSearchingWilayah ? 'Mencari wilayah...' : 'Wilayah tidak ditemukan. Coba ketik nama kecamatan lain.',
                                    style: TextStyle(fontSize: 12, color: Colors.grey.shade700),
                                  ),
                                ),
                              ],
                            ),
                          )
                        : ListView.separated(
                            shrinkWrap: true,
                            physics: const NeverScrollableScrollPhysics(),
                            itemCount: _wilayahResults.length > 6 ? 6 : _wilayahResults.length,
                            separatorBuilder: (_, __) => const Divider(height: 1),
                            itemBuilder: (ctx, i) {
                              final w = _wilayahResults[i];
                              return ListTile(
                                dense: true,
                                leading: const Icon(Icons.location_on_rounded, color: Colors.redAccent, size: 20),
                                title: Text(
                                  '${w['village_name']}, ${w['district_name']}',
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                                ),
                                subtitle: Text(
                                  '${w['city_name']}, ${w['province_name']}',
                                  style: TextStyle(color: Colors.grey.shade600, fontSize: 11),
                                ),
                                onTap: () => _selectWilayah(w),
                              );
                            },
                          ),
                  ),

                const SizedBox(height: 12),
                TextField(
                  controller: _recipientAddressController,
                  decoration: const InputDecoration(
                    labelText: 'Alamat Lengkap',
                    hintText: 'Jalan, RT/RW, Patokan Rumah...',
                  ),
                  maxLines: 2,
                ),

                if (_currentStep == 2) ...[
                  const SizedBox(height: 16),
                  Align(
                    alignment: Alignment.centerRight,
                    child: ElevatedButton.icon(
                      onPressed: () => setState(() => _currentStep = 3),
                      icon: const Text('Lanjut ke Barang'),
                      label: const Icon(Icons.arrow_forward_rounded, size: 16),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],

        // LANGKAH 3: DETAIL BARANG
        if (_currentStep >= 3) ...[
          const SizedBox(height: 16),
          _buildStepCard(
            stepNumber: 'LANGKAH 3',
            title: 'Detail Barang (Items)',
            icon: Icons.inventory_2_rounded,
            child: Column(
              children: [
                ..._items.asMap().entries.map((entry) {
                  final index = entry.key;
                  final item = entry.value;

                  return Container(
                    margin: const EdgeInsets.only(bottom: 12),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.grey.shade50,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: Colors.grey.shade200),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                              decoration: BoxDecoration(
                                color: Colors.grey.shade200,
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Text('Item #${index + 1}', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 11)),
                            ),
                            const Spacer(),
                            if (_items.length > 1)
                              IconButton(
                                icon: const Icon(Icons.delete_outline_rounded, color: Colors.red, size: 18),
                                onPressed: () => _hapusItem(index),
                              ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        TextField(
                          decoration: const InputDecoration(
                            labelText: 'Nama Barang *',
                            hintText: 'Contoh: Lemari 4 Pintu / Kursi Makan',
                          ),
                          controller: TextEditingController(text: item['name']),
                          onChanged: (v) => item['name'] = v,
                        ),
                        const SizedBox(height: 10),
                        Row(
                          children: [
                            Expanded(
                              flex: 3,
                              child: DropdownButtonFormField<String>(
                                value: item['unit'],
                                decoration: const InputDecoration(labelText: 'Satuan'),
                                items: _unitOptions.map((u) => DropdownMenuItem(value: u, child: Text(u))).toList(),
                                onChanged: (val) => setState(() => item['unit'] = val),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              flex: 2,
                              child: TextField(
                                decoration: const InputDecoration(labelText: 'Jml'),
                                keyboardType: TextInputType.number,
                                controller: TextEditingController(text: item['qty'].toString()),
                                onChanged: (v) => setState(() => item['qty'] = int.tryParse(v) ?? 1),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              flex: 3,
                              child: TextField(
                                decoration: const InputDecoration(labelText: 'Total Koli'),
                                keyboardType: TextInputType.number,
                                controller: TextEditingController(text: item['koli_per_item'].toString()),
                                onChanged: (v) => setState(() => item['koli_per_item'] = int.tryParse(v) ?? 1),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  );
                }),
                const SizedBox(height: 6),
                OutlinedButton.icon(
                  onPressed: _tambahItem,
                  icon: const Icon(Icons.add_rounded, size: 16),
                  label: const Text('Tambah Barang Lagi'),
                ),
                if (_currentStep == 3) ...[
                  const SizedBox(height: 16),
                  Align(
                    alignment: Alignment.centerRight,
                    child: ElevatedButton.icon(
                      onPressed: () => setState(() => _currentStep = 4),
                      icon: const Text('Lanjut ke Keuangan'),
                      label: const Icon(Icons.arrow_forward_rounded, size: 16),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],

        // LANGKAH 4: KEUANGAN (BORONGAN)
        if (_currentStep >= 4) ...[
          const SizedBox(height: 16),
          _buildStepCard(
            stepNumber: 'LANGKAH 4',
            title: 'Keuangan (Borongan)',
            icon: Icons.account_balance_wallet_rounded,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                DropdownButtonFormField<String>(
                  value: _paymentType,
                  decoration: const InputDecoration(labelText: 'Tipe Pembayaran'),
                  items: const [
                    DropdownMenuItem(value: 'COD', child: Text('COD (Ditagih Supir)')),
                    DropdownMenuItem(value: 'PREPAID', child: Text('LUNAS (Prepaid)')),
                  ],
                  onChanged: (val) => setState(() => _paymentType = val ?? 'COD'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _goodsValueController,
                  decoration: const InputDecoration(
                    labelText: 'Tagihan / Nilai Barang (Rp) *',
                    hintText: '2.000.000',
                  ),
                  keyboardType: TextInputType.number,
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _freightFeeController,
                  decoration: const InputDecoration(
                    labelText: 'Ongkos Kirim Borongan (Rp) *',
                    hintText: '500.000',
                  ),
                  keyboardType: TextInputType.number,
                ),
                const SizedBox(height: 20),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: _simpanResi,
                    icon: const Icon(Icons.done_all_rounded),
                    label: const Text('Simpan Resi & Kirim ke Backend', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF1F2937),
                      padding: const EdgeInsets.symmetric(vertical: 16),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],

        const SizedBox(height: 80),
      ],
    );
  }

  Widget _buildStepCard({
    required String stepNumber,
    required String title,
    required IconData icon,
    required Widget child,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.grey.shade200),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0A000000),
            blurRadius: 10,
            offset: Offset(0, 4),
          )
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(
              color: AppTheme.primary.withValues(alpha: 0.08),
              borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
              border: Border(bottom: BorderSide(color: Colors.grey.shade200)),
            ),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: Colors.amber.shade200,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    stepNumber,
                    style: TextStyle(color: Colors.amber.shade900, fontWeight: FontWeight.bold, fontSize: 10),
                  ),
                ),
                const SizedBox(width: 8),
                Icon(icon, size: 16, color: AppTheme.primary),
                const SizedBox(width: 6),
                Text(
                  title,
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: child,
          ),
        ],
      ),
    );
  }

  Widget _buildPickupOption({
    required String label,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(10),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
        decoration: BoxDecoration(
          color: isSelected ? AppTheme.primary.withValues(alpha: 0.08) : Colors.grey.shade50,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: isSelected ? AppTheme.primary : Colors.grey.shade300,
            width: isSelected ? 1.5 : 1,
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              isSelected ? Icons.radio_button_checked_rounded : Icons.radio_button_off_rounded,
              size: 16,
              color: isSelected ? AppTheme.primary : Colors.grey.shade500,
            ),
            const SizedBox(width: 6),
            Flexible(
              child: Text(
                label,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: isSelected ? FontWeight.bold : FontWeight.w500,
                  color: isSelected ? AppTheme.primary : Colors.grey.shade700,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildListTab(NumberFormat currency) {
    return FutureBuilder<List<Shipment>>(
      future: _apiService.fetchShipments(),
      builder: (ctx, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        final list = snapshot.data ?? [];
        if (list.isEmpty) {
          return const Center(child: Text('Belum ada resi di server.'));
        }
        return ListView.builder(
          padding: const EdgeInsets.all(16),
          itemCount: list.length,
          itemBuilder: (ctx, i) {
            final s = list[i];
            return Card(
              margin: const EdgeInsets.only(bottom: 12),
              child: ListTile(
                contentPadding: const EdgeInsets.all(16),
                leading: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: AppTheme.primary.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(Icons.receipt_long_rounded, color: AppTheme.primary),
                ),
                title: Text(s.resi, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                subtitle: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const SizedBox(height: 4),
                    Text('${s.senderName} ➔ ${s.recipientName} (${s.destinationCity})', style: const TextStyle(fontSize: 12)),
                    const SizedBox(height: 2),
                    Text('${s.totalKoli} Koli • ${currency.format(s.totalPrice)} • ${s.paymentType}', style: TextStyle(fontSize: 11, color: Colors.grey.shade600, fontWeight: FontWeight.w600)),
                  ],
                ),
                trailing: const Icon(Icons.chevron_right_rounded),
              ),
            );
          },
        );
      },
    );
  }
}
