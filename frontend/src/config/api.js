import { Capacitor } from '@capacitor/core';

// --- CONFIGURATION ---
// 1. Set your computer's IP address here.
//    - To find it, run "ipconfig" in your terminal.
//    - It usually looks like 192.168.x.x
// 2. Ensure your server is started with: php artisan serve --host=0.0.0.0 --port=8001

const HOST = '192.168.9.174'; // <-- EDIT THIS LINE
const PORT = '8001';

// No need to edit below this line
const isAndroid = Capacitor.getPlatform() === 'android';
export const API_BASE_URL = isAndroid ? `http://${HOST}:${PORT}` : `http://localhost:${PORT}`;

console.log('Using API_BASE_URL:', API_BASE_URL);