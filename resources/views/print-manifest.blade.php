<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Jalan Manifest #{{ $manifest->id }} - {{ $manifest->driver?->name ?? 'Armada' }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f1f5f9;
            color: #0f172a;
            font-size: 12px;
        }
        .page {
            max-width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 16mm 18mm;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            position: relative;
        }

        /* Toolbar Screen Only */
        .toolbar {
            max-width: 210mm;
            margin: 0 auto 15px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #0f172a;
            padding: 10px 18px;
            border-radius: 12px;
            color: #ffffff;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.15s;
        }
        .btn-primary { background: #ea580c; color: white; }
        .btn-primary:hover { background: #c2410c; }
        .btn-secondary { background: #334155; color: white; }
        .btn-secondary:hover { background: #475569; }

        /* Document Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 14px;
            margin-bottom: 14px;
        }
        .company-name {
            font-size: 22px;
            font-weight: 900;
            letter-spacing: -0.5px;
            color: #0f172a;
            text-transform: uppercase;
            margin: 0 0 2px 0;
        }
        .company-sub {
            font-size: 11px;
            color: #64748b;
            font-weight: 600;
            margin: 0 0 8px 0;
        }
        .doc-title-badge {
            display: inline-block;
            background: #0f172a;
            color: #ffffff;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        /* QR Handshake Block */
        .qr-card {
            border: 2px dashed #0f172a;
            padding: 8px 12px;
            border-radius: 10px;
            text-align: center;
            background: #f8fafc;
            width: 155px;
            flex-shrink: 0;
        }
        .qr-card img {
            width: 115px;
            height: 115px;
            display: block;
            margin: 0 auto 4px auto;
        }
        .qr-label {
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #ea580c;
        }
        .qr-sub {
            font-size: 8px;
            color: #64748b;
            font-weight: 600;
        }
        .qr-token {
            font-size: 10px;
            font-family: monospace;
            font-weight: 900;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 1px 4px;
            margin-top: 3px;
            color: #0f172a;
        }

        /* Meta Grid */
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 16px;
        }
        .meta-row {
            display: flex;
            margin-bottom: 4px;
        }
        .meta-row:last-child { margin-bottom: 0; }
        .meta-key {
            width: 120px;
            color: #64748b;
            font-weight: 600;
            font-size: 11px;
        }
        .meta-val {
            font-weight: 800;
            color: #0f172a;
            font-size: 11px;
            flex: 1;
        }

        /* Table */
        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        table.items-table th {
            background: #0f172a;
            color: #ffffff;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 800;
            letter-spacing: 0.5px;
            padding: 8px 6px;
            border: 1px solid #0f172a;
            text-align: left;
        }
        table.items-table td {
            padding: 6px;
            border: 1px solid #cbd5e1;
            font-size: 10.5px;
            vertical-align: top;
        }
        table.items-table tr:nth-child(even) td {
            background: #f8fafc;
        }
        .barcode-pill {
            display: inline-block;
            background: #e2e8f0;
            font-family: monospace;
            font-size: 9px;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 3px;
            margin: 1px 2px 1px 0;
            color: #0f172a;
        }

        /* Total Section */
        .summary-box {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 24px;
        }
        .summary-card {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px 16px;
            display: flex;
            gap: 20px;
        }
        .summary-item { text-align: right; }
        .summary-label { font-size: 9px; font-weight: 700; color: #64748b; text-transform: uppercase; }
        .summary-val { font-size: 15px; font-weight: 900; color: #ea580c; }

        /* Signatures */
        .signature-section {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-top: 30px;
            page-break-inside: avoid;
        }
        .sig-box {
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 8px;
            text-align: center;
        }
        .sig-title {
            font-size: 10px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 50px;
        }
        .sig-name {
            font-weight: 800;
            font-size: 11px;
            border-top: 1px solid #0f172a;
            padding-top: 4px;
            display: inline-block;
            min-width: 120px;
        }

        .footer-note {
            margin-top: 20px;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            display: flex;
            justify-content: space-between;
        }

        /* PRINT MEDIA QUERIES */
        @media print {
            body { background: #ffffff; padding: 0; }
            .toolbar { display: none !important; }
            .page {
                box-shadow: none;
                padding: 10mm 12mm;
                max-width: 100%;
                min-height: auto;
            }
            @page {
                size: A4 portrait;
                margin: 8mm;
            }
        }
    </style>
</head>
<body>

    <!-- Toolbar Atas (Layar Saja) -->
    <div class="toolbar">
        <div>
            <strong style="font-size: 13px;">Surat Jalan / Surat Tugas #{{ $manifest->id }}</strong>
            <span style="color: #94a3b8; font-size: 11px; margin-left: 8px;">{{ $manifest->manifest_type === 'PICKUP' ? 'Tugas Penjemputan Toko' : 'Manifest Pengiriman Lintas' }}</span>
        </div>
        <div style="display: flex; gap: 8px;">
            <button onclick="window.print()" class="btn btn-primary">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Cetak Dokumen (Print)
            </button>
            <a href="{{ $manifest->manifest_type === 'PICKUP' ? '/supir/pickup?token='.$manifest->magic_link_token : '/supir/lintas?token='.$manifest->magic_link_token }}" target="_blank" class="btn btn-secondary">
                Layar Supir
            </a>
            <button onclick="window.close()" class="btn btn-secondary">
                Tutup
            </button>
        </div>
    </div>

    <!-- Lembar Cetak Dokumen -->
    <div class="page">
        <!-- Header -->
        <div class="header">
            <div>
                <h1 class="company-name">{{ $manifest->expedition?->name ?? 'JEPARA CARGO LOGISTICS' }}</h1>
                <p class="company-sub">Spesialis Ekspedisi & Logistik Pengiriman Mebel Jepara • Jawa - Bali - Sumatera</p>
                <div class="doc-title-badge">
                    {{ $manifest->manifest_type === 'PICKUP' ? 'SURAT TUGAS PENJEMPUTAN (PICKUP TOKO)' : 'SURAT JALAN PENGIRIMAN (MANIFEST LINTAS)' }}
                </div>
            </div>

            <!-- QR Code Handshake -->
            <div class="qr-card">
                @php
                    $magicUrl = url(($manifest->manifest_type === 'PICKUP' ? '/supir/pickup' : '/supir/lintas') . '?token=' . $manifest->magic_link_token);
                    $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&margin=6&data=" . urlencode($manifest->magic_link_token);
                @endphp
                <img src="{{ $qrCodeUrl }}" alt="QR Handshake Manifest" />
                <div class="qr-label">SCAN QR HANDSHAKE</div>
                <div class="qr-sub">Buka App Supir &rarr; Scan QR ini</div>
                <div class="qr-token">#{{ $manifest->id }}</div>
            </div>
        </div>

        <!-- Meta Grid Data -->
        <div class="meta-grid">
            <div>
                <div class="meta-row">
                    <span class="meta-key">No. Manifest</span>
                    <span class="meta-val">MNF-{{ str_pad($manifest->id, 5, '0', STR_PAD_LEFT) }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-key">Tanggal Pembuatan</span>
                    <span class="meta-val">{{ $manifest->created_at->format('d F Y, H:i') }} WIB</span>
                </div>
                <div class="meta-row">
                    <span class="meta-key">Gudang Asal</span>
                    <span class="meta-val">{{ $manifest->originWarehouse?->name ?? 'Gudang Pusat Jepara' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-key">Gudang Tujuan</span>
                    <span class="meta-val">{{ $manifest->destinationWarehouse?->name ?? ($manifest->manifest_type === 'PICKUP' ? 'Gudang Sortasi Jepara' : 'Lintas / Multi-Drop') }}</span>
                </div>
            </div>

            <div>
                <div class="meta-row">
                    <span class="meta-key">Supir Armada</span>
                    <span class="meta-val">
                        @if ($manifest->driver)
                            <strong style="color: #ea580c;">{{ $manifest->driver->name }}</strong>
                        @elseif ($manifest->vendor_name)
                            <strong>Vendor: {{ $manifest->vendor_name }}</strong>
                        @else
                            <span style="color: #ea580c; font-weight: 800; background: #fff7ed; padding: 2px 6px; border-radius: 4px; border: 1px dashed #fdba74;">[ Menunggu Scan Handshake di Gudang ]</span>
                        @endif
                    </span>
                </div>
                <div class="meta-row">
                    <span class="meta-key">Armada / No. Plat</span>
                    <span class="meta-val">
                        @if ($manifest->driver)
                            {{ $manifest->driver->vehicle_name }}
                        @elseif ($manifest->vendor_resi)
                            Resi Vendor: {{ $manifest->vendor_resi }}
                        @else
                            <span style="color: #64748b; font-style: italic;">(Tercatat otomatis saat supir scan QR)</span>
                        @endif
                    </span>
                </div>
                <div class="meta-row">
                    <span class="meta-key">No. HP / WA Supir</span>
                    <span class="meta-val">{{ $manifest->driver?->phone_number ?? '-' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-key">Status Muatan</span>
                    <span class="meta-val">{{ $manifest->status }}</span>
                </div>
            </div>
        </div>

        <!-- Tabel Daftar Muatan -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 25px; text-align: center;">No</th>
                    <th style="width: 110px;">No. Resi</th>
                    <th style="width: 140px;">Pengirim (Toko/Mebel)</th>
                    <th>Penerima & Alamat Tujuan</th>
                    <th style="width: 50px; text-align: center;">Koli</th>
                    <th>Rincian Koli & Barang</th>
                    <th style="width: 45px; text-align: center;">Paraf</th>
                </tr>
            </thead>
            <tbody>
                @forelse($shipments as $index => $item)
                    @php
                        $shipment = $item['shipment'];
                        $kolis = $item['kolis'];
                        
                        $city = is_array($shipment?->destination_city) ? ($shipment->destination_city['name'] ?? '') : $shipment?->destination_city;
                        $dist = is_array($shipment?->destination_district) ? ($shipment->destination_district['name'] ?? '') : $shipment?->destination_district;
                    @endphp
                    <tr>
                        <td style="text-align: center; font-weight: bold;">{{ $index + 1 }}</td>
                        <td>
                            <strong style="font-family: monospace; font-size: 11px;">{{ $shipment?->tracking_number ?? '-' }}</strong>
                            <div style="font-size: 9px; color: #64748b;">{{ $shipment?->service_type ?? 'Reguler' }}</div>
                        </td>
                        <td>
                            <strong>{{ $shipment?->sender?->business_name ?? ($shipment?->sender?->owner_name ?? 'Pengirim Jepara') }}</strong>
                            <div style="font-size: 9px; color: #64748b;">{{ $shipment?->sender?->phone ?? '-' }}</div>
                            <div style="font-size: 9px; color: #475569;">{{ Str::limit($shipment?->sender?->address ?? '', 40) }}</div>
                        </td>
                        <td>
                            <strong>{{ $shipment?->recipient_name ?? '-' }}</strong>
                            <div style="font-size: 9px; color: #64748b;">{{ $shipment?->recipient_phone ?? '-' }}</div>
                            <div style="font-size: 9.5px; color: #0f172a; line-height: 1.25;">
                                {{ $shipment?->recipient_address ?? '-' }}
                                @if($city || $dist)
                                    <br><span style="font-weight: bold; color: #ea580c;">{{ $dist ? 'Kec. '.$dist.', ' : '' }}{{ $city }}</span>
                                @endif
                            </div>
                        </td>
                        <td style="text-align: center; font-weight: 900; font-size: 13px; color: #ea580c;">
                            {{ $item['total_koli'] }}
                        </td>
                        <td>
                            <div style="margin-bottom: 3px;">
                                @foreach($kolis as $k)
                                    <span class="barcode-pill">{{ $k->barcode_id ?: ('KLI-'.$k->id) }}</span>
                                @endforeach
                            </div>
                            @if($shipment?->items && $shipment->items->count())
                                <div style="font-size: 9px; color: #475569; font-style: italic;">
                                    Barang: {{ $shipment->items->pluck('name')->join(', ') }}
                                </div>
                            @endif
                        </td>
                        <td style="text-align: center; vertical-align: middle;">
                            <div style="width: 24px; height: 24px; border: 1px dashed #94a3b8; border-radius: 4px; margin: 0 auto;"></div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px; color: #94a3b8;">
                            Belum ada barang/koli yang dimuat ke dalam manifest ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Ringkasan Total -->
        <div class="summary-box">
            <div class="summary-card">
                <div class="summary-item">
                    <div class="summary-label">Total Resi</div>
                    <div class="summary-val">{{ count($shipments) }} Resi</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Total Koli Fisik</div>
                    <div class="summary-val">{{ $manifest->kolis->count() }} Koli</div>
                </div>
            </div>
        </div>

        <!-- Kolom Tanda Tangan -->
        <div class="signature-section">
            <div class="sig-box">
                <div class="sig-title">Diserahkan Oleh (Gudang)</div>
                <div class="sig-name">( {{ auth()->user()->name ?? 'Petugas Gudang' }} )</div>
            </div>
            <div class="sig-box">
                <div class="sig-title">Diterima Oleh (Supir Armada)</div>
                <div class="sig-name">( {{ $manifest->driver?->name ?? '............................................' }} )</div>
            </div>
            <div class="sig-box">
                <div class="sig-title">Verifikasi Keberangkatan</div>
                <div class="sig-name">Tgl: ____ / ____ / 2026</div>
            </div>
        </div>

        <!-- Catatan Kaki -->
        <div class="footer-note">
            <span>Dicetak otomatis oleh Sistem Jepara Cargo Logistics pada {{ now()->format('d/m/Y H:i:s') }}</span>
            <span>Halaman 1 / 1 • Simpan bukti surat jalan ini selama perjalanan ekspedisi</span>
        </div>
    </div>

    <script>
        // Auto-print saat halaman selesai dimuat (jika dibuka untuk cetak)
        window.addEventListener('load', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('autoprint') === '1' || urlParams.get('autoprint') === 'true') {
                setTimeout(() => {
                    window.print();
                }, 400);
            }
        });
    </script>
</body>
</html>
