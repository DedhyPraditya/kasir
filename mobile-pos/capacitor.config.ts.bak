import 'dotenv/config';
import type { CapacitorConfig } from '@capacitor/cli';

const serverUrl = process.env.CAPACITOR_SERVER_URL;

if (!serverUrl || !serverUrl.startsWith('https://')) {
    throw new Error(
        'CAPACITOR_SERVER_URL harus berupa URL HTTPS production. Salin .env.example menjadi .env lalu isi domain aplikasi Laravel.',
    );
}

const config: CapacitorConfig = {
    appId: 'id.nyemilbebs.pos',
    appName: 'Nyemil Bebs POS',
    webDir: 'www',
    server: {
        url: serverUrl,
        cleartext: false,
        androidScheme: 'https',
    },
};

export default config;

