<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Label Resi - {{ $shipment->tracking_number }}</title>
    <style id="page-style">
        @page { size: 100mm 150mm; margin: 4mm; }
    </style>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background: #e5e7eb; padding: 20px; color: #000; margin: 0; }
        .print-container { background: #fff; margin: 0 auto; padding-bottom: 20px; }
        
        .label-koli { 
            border: 2px solid #000; 
            padding: 0; 
            background: #fff;
            position: relative;
            overflow: hidden; /* Mencegah konten luber */
        }
        
        /* Layout Sections */
        .header-section { display: flex; border-bottom: 2px solid #000; }
        .header-left { padding: 8px 10px; width: 65%; border-right: 2px solid #000; display: flex; flex-direction: column; justify-content: center; }
        .header-right { padding: 6px; width: 35%; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        
        .logo-text { font-size: 20px; font-weight: 900; letter-spacing: -1px; text-transform: uppercase; line-height: 1.1; }
        
        .barcode-section { padding: 8px 10px; border-bottom: 2px solid #000; text-align: center; }
        .barcode-text { font-size: 20px; font-weight: bold; font-family: monospace; letter-spacing: 2px; margin-top: 2px; }
        
        .address-section { display: flex; border-bottom: 2px solid #000; }
        .address-box { padding: 8px 10px; flex: 1; }
        .address-box:first-child { border-right: 2px solid #000; }
        
        .section-title { font-size: 10px; font-weight: bold; margin-bottom: 2px; text-transform: uppercase; border-bottom: 1px solid #000; padding-bottom: 2px; display: inline-block; }
        
        .person-name { font-weight: bold; font-size: 14px; margin-bottom: 2px; }
        .person-phone { font-size: 12px; margin-bottom: 4px; font-weight: bold; }
        .person-address { font-size: 11px; line-height: 1.3; }
        
        .info-section { display: flex; border-bottom: 2px solid #000; }
        .info-box { padding: 8px 10px; flex: 1; border-right: 2px solid #000; text-align: center; }
        .info-box:last-child { border-right: none; }
        
        .info-label { font-size: 9px; text-transform: uppercase; }
        .info-value { font-size: 14px; font-weight: bold; margin-top: 2px; }
        
        .footer-section { padding: 8px 10px; text-align: center; font-size: 12px; display: flex; justify-content: space-between; align-items: center; }
        
        .payment-badge { font-size: 16px; font-weight: 900; border: 3px solid #000; padding: 2px 10px; text-transform: uppercase; background: #000; color: #fff; }
        
        /* Item Table (Menampilkan semua barang) */
        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0 6px 0;
        }
        .item-table td {
            font-size: 11px;
            padding: 1px 0;
            line-height: 1.1;
            vertical-align: top;
        }
        .item-table td:last-child {
            text-align: right;
            white-space: nowrap;
            width: 35%;
        }

        /* Driver-Friendly Destination Address Layout */
        .dest-address { margin-top: 8px; }
        .dest-street { font-size: 14px; font-weight: bold; line-height: 1.3; }
        .dest-region-group { display: flex; flex-direction: column; gap: 4px; margin-top: 6px; }
        .dest-region-box { width: 100%; font-size: 11px; font-weight: 900; text-transform: uppercase; border: 2px solid #000; border-radius: 4px; padding: 4px; text-align: center; letter-spacing: 0.5px; line-height: 1.1; }
        .dest-city { font-size: 16px; font-weight: 900; background: #000; color: #fff; padding: 4px; text-align: center; border-radius: 4px; margin-top: 6px; letter-spacing: 1px; text-transform: uppercase; }
        .dest-prov { font-size: 12px; font-weight: bold; text-align: center; margin-top: 2px; text-transform: uppercase; }

        /* Mode Thermal (10x15) - Deep Compression */
        body.mode-thermal .print-container { width: 100%; max-width: 92mm; margin: 0 auto; }
        body.mode-thermal .label-koli { 
            margin-top: 4mm;
            height: 142mm; /* Sisa ruang setelah dikurangi margin page 4mm (atas-bawah) */
            display: flex;
            flex-direction: column;
            margin-bottom: 20px;
            overflow: hidden;
        }
        body.mode-thermal .address-section { flex: 1; } /* Sisa ruang diberikan untuk alamat & daftar barang */
        
        /* Kompresi Font & Spasi Ekstra untuk Thermal */
        body.mode-thermal .header-left, body.mode-thermal .header-right, body.mode-thermal .barcode-section, body.mode-thermal .address-box, body.mode-thermal .info-box, body.mode-thermal .footer-section { padding: 4px 6px; }
        body.mode-thermal .logo-text { font-size: 16px; }
        body.mode-thermal .barcode-text { font-size: 18px; margin-top: 1px; }
        body.mode-thermal .section-title { font-size: 9px; margin-bottom: 2px; padding-bottom: 1px; }
        body.mode-thermal .person-name { font-size: 12px; margin-bottom: 1px; }
        body.mode-thermal .person-phone { font-size: 11px; margin-bottom: 2px; }
        
        body.mode-thermal .dest-street { font-size: 11px; }
        body.mode-thermal .dest-region-group { margin-top: 4px; gap: 2px; }
        body.mode-thermal .dest-region-box { font-size: 9px; padding: 2px; border-width: 1.5px; }
        body.mode-thermal .dest-city { font-size: 14px; margin-top: 4px; padding: 2px; }
        body.mode-thermal .dest-prov { font-size: 9px; }
        
        body.mode-thermal .info-label { font-size: 8px; }
        body.mode-thermal .info-value { font-size: 12px; margin-top: 1px; }
        body.mode-thermal .footer-section { font-size: 10px; }
        body.mode-thermal .payment-badge { font-size: 13px; padding: 2px 6px; }
        body.mode-thermal .item-table { margin: 2px 0 4px 0; }
        body.mode-thermal .item-table td { font-size: 9px; }
        body.mode-thermal .address-box > div:last-child { margin-top: 5px !important; } /* Kurangi spasi sebelum daftar barang */
        
        /* Mode A4 (2 Label Atas-Bawah) */
        body.mode-a4 .print-container { 
            width: 100%;
            max-width: 190mm; /* Lebar A4 minus margin */
            display: flex; 
            flex-direction: column;
            gap: 10mm;
            background: transparent;
        }
        body.mode-a4 .label-koli { 
            width: 100%;
            height: 140mm; /* 2 buah = 280mm (cukup di A4) */
            margin-bottom: 0;
            background: #fff;
        }
        body.mode-a4 .item-list { -webkit-line-clamp: 6; } /* Bisa muat lebih banyak list */

        /* Print media queries */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            body { background: #fff; padding: 0; margin: 0; }
            .print-container { padding: 0 !important; margin: 0 auto !important; box-shadow: none; width: 100%; max-width: 92mm; }
            .action-buttons { display: none !important; }
            
            body.mode-thermal .label-koli {
                margin: 4mm auto 0 auto;
                page-break-after: always;
                border: 2px solid #000; /* Biarkan border terlihat jelas */
            }
            body.mode-thermal .label-koli:last-child {
                page-break-after: auto;
            }
            body.mode-a4 .print-container { gap: 5mm; max-width: 190mm; }
            body.mode-a4 .label-koli { page-break-after: auto; page-break-inside: avoid; }
            body.mode-a4 .label-koli:nth-child(2n) { page-break-after: always; } /* Paksa halaman baru setiap 2 label */
        }
    </style>
</head>
@php
    function maskPhone($phone) {
        $len = strlen($phone);
        if ($len <= 7) return $phone;
        return substr($phone, 0, 4) . str_repeat('*', $len - 7) . substr($phone, -3);
    }
@endphp
<body class="mode-thermal">
    <!-- Action Bar (Screen Only) -->
    <div class="action-buttons" style="background: #0f172a; color: #fff; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.3); padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 15px;">
            <span style="font-weight: bold; font-size: 16px; letter-spacing: 0.5px;">Label Resi</span>
            <select id="paper-mode" onchange="changePaperMode(this.value)" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #475569; background: #1e293b; color: white; outline: none; cursor: pointer; font-size: 14px;">
                <option value="thermal">Mode Thermal (10x15cm)</option>
                <option value="a4">Mode A4 (2 Resi Susun Bawah)</option>
            </select>
        </div>
        <div style="display: flex; gap: 10px;">
            <button class="btn-back" onclick="window.close()" style="background: #334155; color: white; padding: 8px 16px; border: 1px solid #475569; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.2s;">
                <svg xmlns="http://www.w3.org/2000/svg" style="height: 16px; width: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Tutup
            </button>
            <button class="btn-print" onclick="window.print()" style="background: #4f46e5; color: white; padding: 8px 16px; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); transition: all 0.2s;">
                <svg xmlns="http://www.w3.org/2000/svg" style="height: 16px; width: 16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak Semua Label
            </button>
        </div>
    </div>

    <div class="print-container">
        @foreach($shipment->kolis as $koli)
        <div class="label-koli">
            <!-- Header Section -->
            <div class="header-section">
                <div class="header-left">
                    <div class="logo-text">{{ strtoupper($companyName) }}</div>
                    <div style="font-size: 11px; margin-top: 5px;">{{ $companyTagline }}</div>
                </div>
                <div class="header-right" style="display: flex; flex-direction: column; align-items: center;">
                    <div id="qrcode-{{ $koli->id }}"></div>
                    <div style="font-size: 8px; font-weight: bold; margin-top: 2px;">QR RESI</div>
                </div>
            </div>

            <!-- Barcode Section -->
            <div class="barcode-section" style="text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; border-bottom: 2px dashed #000; padding: 4px 0; width: 100%; overflow: hidden;">
                <div style="font-size: 14px; font-weight: 900; margin-bottom: 2px; letter-spacing: 1px;">NO. RESI: {{ $shipment->tracking_number }}</div>
                <div style="font-size: 9px; font-weight: bold; position: relative; z-index: 10; margin-bottom: -2px;">BARCODE KOLI</div>
                <div style="width: 100%; display: flex; justify-content: center; position: relative; z-index: 1;">
                    <svg id="barcode-{{ $koli->id }}" style="max-width: 100%; height: auto;"></svg>
                </div>
            </div>

            <!-- Address Section -->
            <div class="address-section">
                <!-- Penerima -->
                <div class="address-box">
                    <div class="section-title">PENERIMA</div>
                    <div class="person-name">{{ $shipment->recipient_name }}</div>
                    <div class="person-phone">{{ maskPhone($shipment->recipient_phone) }}</div>
                    
                    @php
                        $dist = is_array($shipment->destination_district) ? ($shipment->destination_district['name'] ?? '') : $shipment->destination_district;
                        $city = is_array($shipment->destination_city) ? ($shipment->destination_city['name'] ?? '') : $shipment->destination_city;
                        $prov = is_array($shipment->destination_province) ? ($shipment->destination_province['name'] ?? '') : $shipment->destination_province;
                    @endphp
                    
                    <div class="person-address dest-address">
                        <div class="dest-street">{{ $shipment->recipient_address }}</div>
                        <div class="dest-region-group">
                            <div class="dest-region-box">KEL. {{ $shipment->destination_village ?? '-' }}</div>
                            <div class="dest-region-box">KEC. {{ $dist }}</div>
                        </div>
                        <div class="dest-city">{{ $city }}</div>
                        <div class="dest-prov">{{ $prov }}</div>
                    </div>
                </div>

                <!-- Pengirim -->
                <div class="address-box">
                    <div class="section-title">PENGIRIM</div>
                    <div class="person-name">{{ $shipment->sender?->business_name ?? 'Pengirim Jepara' }}</div>
                    <div class="person-phone">{{ maskPhone($shipment->sender?->phone ?? '-') }}</div>
                    
                    <div style="margin-top: 15px;">
                        <div class="section-title">DAFTAR BARANG (KESELURUHAN)</div>
                        <table class="item-table">
                            @foreach($shipment->items as $item)
                            <tr>
                                <td>{{ $loop->iteration }}. {{ $item->name }}</td>
                                <td>{{ $item->qty }} {{ $item->unit ?? 'pcs' }}</td>
                            </tr>
                            @endforeach
                        </table>
                        
                        <div class="section-title">ISI LABEL INI</div>
                        <div style="font-weight: bold; font-size: 13px; background: #f3f4f6; padding: 4px; display: inline-block; border-radius: 4px;">{{ $koli->description }}</div>
                    </div>
                </div>
            </div>

            <!-- Info Section -->
            <div class="info-section">
                <div class="info-box">
                    <div class="info-label">Tipe Layanan</div>
                    <div class="info-value">REGULER</div>
                </div>
                <div class="info-box">
                    <div class="info-label">Pembayaran</div>
                    <div class="info-value">{{ strtoupper($shipment->payment_type) }}</div>
                </div>
                <div class="info-box">
                    <div class="info-label">Koli Ke</div>
                    <div class="info-value">{{ $koli->koli_number }} / {{ $koli->total_koli }}</div>
                </div>
            </div>

            <!-- Footer / Payment Section -->
            <div class="footer-section">
                <div>
                    <strong>Tgl Kirim:</strong> {{ $shipment->created_at->format('d/m/Y') }}
                </div>
                @if($shipment->goods_value > 0)
                <div class="payment-badge">
                    TAGIHAN: RP {{ number_format($shipment->goods_value, 0, ',', '.') }}
                </div>
                @else
                <div style="font-weight: bold; font-size: 18px;">
                    NON-COD
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.5/JsBarcode.all.min.js"></script>
    <script>
        // Generate QR Codes & Barcodes
        @foreach($shipment->kolis as $koli)
        // QR Code Resi
        new QRCode(document.getElementById("qrcode-{{ $koli->id }}"), {
            text: "{{ $shipment->tracking_number }}",
            width: 65,
            height: 65,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.M
        });

        // Barcode 1D Koli
        JsBarcode("#barcode-{{ $koli->id }}", "{{ $koli->barcode_id }}", {
            format: "CODE128",
            width: 1.5,
            height: 25,
            displayValue: true,
            fontSize: 12,
            fontWeight: "bold",
            background: "transparent",
            marginTop: 0,
            marginBottom: 10,
            marginLeft: 15,
            marginRight: 15
        });
        @endforeach

        // Mode Kertas Dinamis
        function changePaperMode(mode) {
            let pageStyle = document.getElementById('page-style');
            
            if (mode === 'a4') {
                document.body.classList.remove('mode-thermal');
                document.body.classList.add('mode-a4');
                pageStyle.innerHTML = '@page { size: A4 portrait; margin: 5mm; }';
            } else {
                document.body.classList.remove('mode-a4');
                document.body.classList.add('mode-thermal');
                pageStyle.innerHTML = '@page { size: 100mm 150mm; margin: 4mm; }';
            }
        }

        // Otomatis muncul dialog print saat tab dibuka
        window.onload = function() {
            setTimeout(() => { window.print(); }, 800); // Waktu ekstra untuk render QR
        }

        // Hapus window.close() agar tidak pernah menutup aplikasi secara tidak sengaja
        window.onafterprint = function() {
            if (window.self !== window.top) {
                // Jika dicetak dari PC (lewat Iframe yang tersembunyi)
                window.parent.document.body.removeChild(window.frameElement);
            }
        };

        function goBack() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = '/';
            }
        }
    </script>
</body>
</html>
