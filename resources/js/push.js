import axios from 'axios';
import { route } from 'ziggy-js';

function b64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = atob(base64);
  const out = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; ++i) out[i] = raw.charCodeAt(i);
  return out;
}

export async function initPush() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

  const perm = await Notification.requestPermission();
  if (perm !== 'granted') return;

  const reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });

  let sub = await reg.pushManager.getSubscription();
  if (!sub) {
    const vapid = import.meta.env.VITE_VAPID_PUBLIC_KEY;
    sub = await reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: b64ToUint8Array(vapid),
    });
  }

  await axios.post(route('push.subscribe'), sub.toJSON(), { withCredentials: true });
}

export async function unsubscribePush() {
  const reg = await navigator.serviceWorker.getRegistration();
  if (!reg) return;
  const sub = await reg.pushManager.getSubscription();
  if (!sub) return;
  await axios.post(route('push.unsubscribe'), { endpoint: sub.endpoint });
  await sub.unsubscribe();
}
