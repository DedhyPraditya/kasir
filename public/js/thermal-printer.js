/**
 * Cetak struk langsung ke printer thermal 58 mm dari browser (Chrome/Edge desktop)
 * lewat Web Serial API + perintah ESC/POS, tanpa dialog print browser.
 *
 * Printer harus terlihat sebagai port COM di Windows:
 * - Printer Bluetooth: pair dulu di Pengaturan Bluetooth Windows.
 * - Printer USB: yang memakai chip serial (CH340/CP210x/PL2303) muncul sebagai COM.
 *
 * Koneksi: printer dipilih sekali (Pengaturan Printer). Setelah itu setiap halaman
 * membuka port otomatis di latar belakang, menjaga koneksi tetap terbuka, dan
 * menyambung ulang sendiri bila printer mati/Bluetooth lepas.
 *
 * Tombol memakai: onclick="ThermalPrinter.printFromButton(this)" data-receipt="{json}"
 * (data dari Order::receiptData()). Status koneksi dikirim lewat event
 * `thermal:status` di window (detail: { state, message }).
 */
(function () {
    'use strict';

    const COLS = 32;                 // Lebar karakter font A pada kertas 58 mm
    const TITLE_COLS = 16;           // Lebar karakter judul (ukuran dobel)
    const DEFAULT_BAUD = 9600;
    const RECONNECT_MS = 5000;
    const PORT_KEY = 'thermalPrinterPort';
    const BAUD_KEY = 'thermalPrinterBaud';
    // Baris kosong setelah footer agar teks terakhir melewati gerigi sobek.
    // Nilainya dari Pengaturan Printer di server (r.feed / r.cut), sama untuk web & mobile.
    const DEFAULT_FEED = 4;
    const MAX_FEED = 8;

    const ESC = 0x1b;
    const GS = 0x1d;

    const supported = () => 'serial' in navigator;

    const store = {
        get(key) { try { return localStorage.getItem(key); } catch (e) { return null; } },
        set(key, value) { try { localStorage.setItem(key, value); } catch (e) { /* abaikan */ } },
        remove(key) { try { localStorage.removeItem(key); } catch (e) { /* abaikan */ } },
    };

    // ---------- Format struk ----------

    const rupiah = (n) => Math.round(n).toLocaleString('id-ID');

    // Printer thermal hanya paham ASCII; ganti karakter lain agar tidak jadi sampah.
    const ascii = (text) => String(text ?? '')
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^\x20-\x7e]/g, '?');

    const wrap = (text, width = COLS) => {
        const words = ascii(text).split(' ');
        const lines = [];
        let line = '';
        for (const word of words) {
            if (!line.length) {
                line = word;
            } else if ((line + ' ' + word).length <= width) {
                line += ' ' + word;
            } else {
                lines.push(line);
                line = word;
            }
            while (line.length > width) {
                lines.push(line.slice(0, width));
                line = line.slice(width);
            }
        }
        if (line.length || !lines.length) lines.push(line);
        return lines;
    };

    const leftRight = (left, right, width = COLS) => {
        left = ascii(left);
        right = ascii(right);
        const space = width - left.length - right.length;
        if (space >= 1) return left + ' '.repeat(space) + right;
        return wrap(left, width).join('\n') + '\n' + ' '.repeat(Math.max(0, width - right.length)) + right;
    };

    // Baris info transaksi: label di kiri, nilai rata kanan. Nilai yang terlalu
    // panjang turun ke baris berikutnya, tetap rata kanan.
    const field = (label, value, width = COLS) => {
        label = ascii(label);
        value = ascii(value);
        if (label.length + 1 + value.length <= width) return [leftRight(label, value, width)];
        return [label, ...wrap(value, width).map((v) => ' '.repeat(Math.max(0, width - v.length)) + v)];
    };

    function buildReceipt(r) {
        const out = [];
        const push = (...bytes) => out.push(...bytes);
        const text = (s) => { for (const ch of ascii(s)) out.push(ch.charCodeAt(0)); };
        const line = (s = '') => { text(s); push(0x0a); };
        const lines = (s, width) => wrap(s, width).forEach((l) => line(l));
        const align = (n) => push(ESC, 0x61, n);           // 0 kiri, 1 tengah
        const bold = (on) => push(ESC, 0x45, on ? 1 : 0);
        const size = (n) => push(GS, 0x21, n);              // 0x00 normal, 0x11 dobel
        const divider = () => line('-'.repeat(COLS));

        push(ESC, 0x40); // reset printer

        // Header toko (dari Pengaturan Printer)
        align(1);
        if (r.store) {
            bold(true); size(0x11); lines(r.store, TITLE_COLS); size(0x00); bold(false);
        }
        (r.header || []).forEach((h) => lines(h));
        line();

        // Info transaksi
        align(0);
        const info = (label, value) => field(label, value).forEach((l) => line(l));
        info('No', r.invoice);
        info('Tgl', r.date);
        if (r.kasir) info('Kasir', r.kasir);
        info('Pelanggan', r.customer || 'Umum');
        divider();

        // Item
        for (const item of r.items || []) {
            bold(true); lines(item.name); bold(false);
            line(leftRight(item.qty + ' x ' + rupiah(item.price), rupiah(item.subtotal)));
            for (const t of item.toppings || []) {
                lines('+ ' + t.name + ' (' + rupiah(t.price) + ')');
            }
        }
        divider();

        // Total & pembayaran
        bold(true); line(leftRight('TOTAL', 'Rp ' + rupiah(r.total))); bold(false);
        line(leftRight('Metode', String(r.method || '').toUpperCase()));
        if (r.paid !== null && r.paid !== undefined) {
            line(leftRight('Tunai', 'Rp ' + rupiah(r.paid)));
            line(leftRight('Kembali', 'Rp ' + rupiah(r.change)));
        }
        line();

        // Footer (dari Pengaturan Printer)
        align(1);
        if (r.reprint) line('** CETAK ULANG **');
        (r.footer || []).forEach((f) => lines(f));
        align(0);

        const feed = Number.isInteger(r.feed) ? Math.min(MAX_FEED, Math.max(0, r.feed)) : DEFAULT_FEED;
        for (let i = 0; i < feed; i++) push(0x0a);
        // Potong otomatis hanya untuk printer ber-cutter; pada printer tanpa cutter
        // perintah ini justru mendorong kertas jauh ke posisi pisau (sisa kosong panjang).
        if (r.cut === true) push(GS, 0x56, 0x42, 0x00);

        return new Uint8Array(out);
    }

    // ---------- Koneksi ----------

    let port = null;          // port yang sedang terbuka
    let connecting = null;    // promise koneksi yang sedang berjalan
    let reconnectTimer = null;
    let status = { state: supported() ? 'idle' : 'unsupported', message: '' };

    const portKey = (p) => JSON.stringify(p.getInfo ? p.getInfo() : {});
    const hasSavedPrinter = () => store.get(PORT_KEY) !== null;

    function baudRate() {
        return parseInt(store.get(BAUD_KEY), 10) || DEFAULT_BAUD;
    }

    function setStatusState(state, message) {
        status = { state, message: message || '' };
        window.dispatchEvent(new CustomEvent('thermal:status', { detail: status }));
    }

    async function savedPort() {
        const ports = await navigator.serial.getPorts();
        if (!ports.length) return null;
        const saved = store.get(PORT_KEY);
        return ports.find((p) => portKey(p) === saved) || null;
    }

    async function closePort() {
        const p = port;
        port = null;
        if (p) {
            try { await p.close(); } catch (e) { /* abaikan */ }
        }
    }

    // Buka port tersimpan tanpa interaksi pengguna. Aman dipanggil berulang.
    function connect() {
        if (!supported()) return Promise.resolve(false);
        if (port && port.writable) return Promise.resolve(true);
        if (connecting) return connecting;

        connecting = (async () => {
            const p = await savedPort();
            if (!p) {
                setStatusState('none', 'Belum ada printer dipilih.');
                return false;
            }
            setStatusState('connecting', 'Menghubungkan printer...');
            try {
                if (!p.writable) await p.open({ baudRate: baudRate() });
                port = p;
                setStatusState('connected', 'Printer terhubung.');
                return true;
            } catch (e) {
                const busy = e && e.name === 'InvalidStateError';
                setStatusState('disconnected', busy
                    ? 'Printer sedang dipakai tab/aplikasi lain.'
                    : 'Printer tidak terjangkau. Pastikan printer menyala.');
                scheduleReconnect();
                return false;
            } finally {
                connecting = null;
            }
        })();
        return connecting;
    }

    function scheduleReconnect() {
        if (reconnectTimer || !hasSavedPrinter()) return;
        reconnectTimer = setTimeout(async () => {
            reconnectTimer = null;
            if (!(port && port.writable)) await connect();
        }, RECONNECT_MS);
    }

    // Pilih printer (butuh klik pengguna); pilihan tersimpan permanen di browser ini.
    async function choosePrinter() {
        if (!supported()) throw new Error('unsupported');
        const p = await navigator.serial.requestPort();
        await closePort();
        store.set(PORT_KEY, portKey(p));
        await connect();
        return p;
    }

    async function forgetPrinter() {
        const p = supported() ? await savedPort() : null;
        await closePort();
        store.remove(PORT_KEY);
        if (p && p.forget) {
            try { await p.forget(); } catch (e) { /* abaikan */ }
        }
        setStatusState('none', 'Belum ada printer dipilih.');
    }

    async function setBaudRate(value) {
        store.set(BAUD_KEY, String(parseInt(value, 10) || DEFAULT_BAUD));
        await closePort();
        await connect();
    }

    async function write(bytes) {
        const writer = port.writable.getWriter();
        try {
            // Kirim bertahap agar buffer printer Bluetooth tidak meluap.
            for (let i = 0; i < bytes.length; i += 256) {
                await writer.write(bytes.slice(i, i + 256));
            }
        } finally {
            writer.releaseLock();
        }
    }

    async function print(receipt) {
        if (!supported()) throw new Error('unsupported');
        if (!hasSavedPrinter() || !(await savedPort())) {
            await choosePrinter();
        }
        const bytes = buildReceipt(receipt);
        try {
            if (!(await connect())) throw new Error('not-connected');
            await write(bytes);
        } catch (e) {
            // Koneksi basi (printer sempat mati / Bluetooth lepas): buka ulang sekali.
            await closePort();
            if (!(await connect())) throw new Error('not-connected');
            await write(bytes);
        }
    }

    if (supported()) {
        navigator.serial.addEventListener('connect', () => connect());
        navigator.serial.addEventListener('disconnect', (event) => {
            if (event.target === port) {
                port = null;
                setStatusState('disconnected', 'Printer terputus. Menyambung ulang...');
                scheduleReconnect();
            }
        });
        // Buka koneksi otomatis begitu halaman siap, tanpa perlu klik apa pun.
        if (hasSavedPrinter()) connect();
        else setStatusState('none', 'Belum ada printer dipilih.');
    }

    // ---------- Integrasi tombol ----------

    function setButtonStatus(button, message, type) {
        const box = button.closest('[data-thermal]')?.querySelector('[data-thermal-status]');
        if (!box) return;
        box.textContent = message;
        box.className = 'small mt-2 text-' + (type || 'muted');
        box.hidden = !message;
    }

    async function printFromButton(button) {
        if (!supported()) {
            setButtonStatus(button, 'Browser ini tidak mendukung cetak thermal langsung. Pakai Chrome/Edge di PC, atau klik "Browser".', 'danger');
            return;
        }
        const label = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mencetak...';
        setButtonStatus(button, '', 'muted');
        try {
            await print(JSON.parse(button.dataset.receipt));
            setButtonStatus(button, 'Struk terkirim ke printer.', 'success');
        } catch (e) {
            if (e && e.name === 'NotFoundError') {
                setButtonStatus(button, 'Printer belum dipilih.', 'warning');
            } else {
                console.error(e);
                setButtonStatus(button, status.message || 'Gagal mencetak. Cek printer di menu Pengaturan Printer.', 'danger');
            }
        } finally {
            button.disabled = false;
            button.innerHTML = label;
        }
    }

    window.ThermalPrinter = {
        supported,
        status: () => status,
        baudRate,
        setBaudRate,
        connect,
        choosePrinter,
        forgetPrinter,
        print,
        buildReceipt,
        printFromButton,
    };
})();
