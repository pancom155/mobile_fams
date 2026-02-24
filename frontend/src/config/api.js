import { Capacitor } from '@capacitor/core';

// 10.0.2.2 is the special alias to your host loopback interface (127.0.0.1)
// on the Android Emulator.
const isAndroid = Capacitor.getPlatform() === 'android';

export const API_BASE_URL = isAndroid 
  ? 'http://127.0.0.1:8001' 
  : 'http://127.0.0.1:8001';
