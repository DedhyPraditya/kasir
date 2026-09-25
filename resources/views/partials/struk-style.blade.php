{{-- Style struk thermal 58 mm. Dipakai POS (struk baru) dan Laporan (cetak ulang). --}}
    <style>
        @media print {
            @page {
                size: 58mm auto;
                margin: 0;
            }
            html, body {
                width: 58mm !important;
                min-width: 58mm !important;
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
            }
            /* Lepaskan layout dashboard/flex agar titik tengah dihitung dari kertas. */
            body > .d-flex,
            .main-content,
            .main-content main {
                display: block !important;
                width: 58mm !important;
                min-width: 58mm !important;
                height: auto !important;
                min-height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
            }
            body * {
                visibility: hidden;
            }
            #print-area, #print-area * {
                visibility: visible;
            }
            #print-area {
                /* Printer 58 mm umumnya hanya menyediakan area cetak efektif ±48 mm. */
                box-sizing: border-box !important;
                position: static !important;
                width: 48mm !important;
                max-width: 48mm !important;
                min-width: 0 !important;
                padding: 2mm 0 !important;
                margin: 0 auto !important;
                color: black !important;
                font-family: "Arial Black", Arial, Helvetica, sans-serif !important;
                font-size: 13px !important;
                font-weight: 900 !important;
                line-height: 1.3 !important;
                overflow: visible !important;
            }
            /* Hilangkan warna abu-abu Bootstrap dan cetak dengan hitam pekat. */
            #print-area, #print-area * {
                color: #000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            #print-area [style*="font-size"] {
                font-size: 12px !important;
            }
            #print-area h4 {
                font-size: 15px !important;
                font-weight: 700 !important;
            }
            .modal-backdrop {
                display: none !important;
            }
            .modal, .modal-dialog {
                display: block !important;
                position: static !important;
                top: 0 !important;
                left: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 58mm !important;
                max-width: 58mm !important;
                height: auto !important;
                transform: none !important;
            }
            .d-print-none {
                display: none !important;
            }
            .modal-content {
                border: none !important;
                box-shadow: none !important;
                background: white !important;
                display: block !important;
                width: 58mm !important;
                max-width: 58mm !important;
                height: auto !important;
            }
            #print-area .d-flex {
                width: 100% !important;
                min-width: 0 !important;
            }
            #print-area .d-flex > * {
                min-width: 0 !important;
            }
            #print-area .d-flex > :last-child:not(:only-child) {
                flex-shrink: 0 !important;
                margin-left: 2mm !important;
                text-align: right !important;
            }
        }
        
        @import url('https://fonts.googleapis.com/css2?family=Courier+Prime:ital,wght@0,400;0,700;1,400;1,700&display=swap');

        .struk-font {
            font-family: 'Courier Prime', 'Consolas', 'Courier New', Courier, monospace !important;
            letter-spacing: 0.3px;
        }
    </style>
