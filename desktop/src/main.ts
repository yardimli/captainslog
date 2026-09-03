import { invoke } from '@tauri-apps/api/core';
import { listen } from '@tauri-apps/api/event';
import { openUrl } from '@tauri-apps/plugin-opener';
import './styles.css';

type ActivitySnapshot = {
  application: string;
  executable: string;
  window_title: string;
  process_id: number;
  idle_seconds: number;
  captured_at_unix_ms: number;
  supported: boolean;
};

type KindleProgress = {
  title: string;
  author: string | null;
  asin: string | null;
  percentage_read: number | null;
  location: string | null;
};

type KindleStatusReport = { status: string; message: string | null };

const appName = document.querySelector<HTMLElement>('#application-name')!;
const windowTitle = document.querySelector<HTMLElement>('#window-title')!;
const idleTime = document.querySelector<HTMLElement>('#idle-time')!;
const platformBadge = document.querySelector<HTMLElement>('#platform-badge')!;
const list = document.querySelector<HTMLOListElement>('#activity-list')!;
const appUrl = document.querySelector<HTMLInputElement>('#app-url')!;
const pairingKey = document.querySelector<HTMLInputElement>('#pairing-key')!;
const connectionStatus = document.querySelector<HTMLElement>('#connection-status')!;
const kindleUrl = document.querySelector<HTMLInputElement>('#kindle-url')!;
const kindleStatus = document.querySelector<HTMLElement>('#kindle-status')!;
const samples: ActivitySnapshot[] = [];
const clientId = localStorage.getItem('totalLogDesktop.clientId') || crypto.randomUUID().replaceAll('-', '');
let lastSyncAt = 0;
let syncRunning = false;
let lastAttemptedIdentity = '';
let kindleUploadRunning = false;

const randomKey = () => `${crypto.randomUUID().replaceAll('-', '')}${crypto.randomUUID().replaceAll('-', '')}`;
const seconds = (value: number) => value < 60 ? `${value}s` : `${Math.floor(value / 60)}m ${value % 60}s`;

function normalizedBaseUrl(): URL {
  const url = new URL(appUrl.value.trim());
  if (!['http:', 'https:'].includes(url.protocol)) throw new Error('Use an http:// or https:// URL.');
  url.hash = '';
  url.search = '';
  if (!url.pathname.endsWith('/')) url.pathname += '/';
  return url;
}

function saveSettings() {
  localStorage.setItem('totalLogDesktop.appUrl', normalizedBaseUrl().toString());
  localStorage.setItem('totalLogDesktop.pairingKey', pairingKey.value);
}

function normalizedKindleUrl(): URL {
  const url = new URL(kindleUrl.value.trim());
  if (url.protocol !== 'https:' || !/^read\.amazon\./i.test(url.hostname)) {
    throw new Error('Use a Kindle Cloud Reader URL beginning with https://read.amazon.');
  }
  return url;
}

function kindleProgressLabel(progress: KindleProgress): string {
  if (progress.percentage_read !== null) return `${progress.percentage_read}% read`;
  return progress.location || 'position found';
}

async function uploadKindleProgress(progress: KindleProgress) {
  if (kindleUploadRunning) return;
  if (localStorage.getItem('totalLogDesktop.syncEnabled') !== 'true') {
    kindleStatus.textContent = 'Kindle is signed in. Pair the desktop app before sending reading progress.';
    return;
  }
  const fingerprint = JSON.stringify([progress.asin || '', progress.title, progress.percentage_read ?? '', progress.location || '']);
  if (fingerprint === localStorage.getItem('totalLogDesktop.kindleLastFingerprint')) {
    kindleStatus.textContent = `${progress.title} · ${kindleProgressLabel(progress)} · no change`;
    return;
  }
  kindleUploadRunning = true;
  kindleStatus.textContent = `Sending ${progress.title}…`;
  try {
    await invoke('send_kindle_progress', {payload: {
      app_url: normalizedBaseUrl().toString(), pairing_key: pairingKey.value, client_id: clientId,
      progress, observed_at: new Date().toISOString(),
    }});
    localStorage.setItem('totalLogDesktop.kindleLastFingerprint', fingerprint);
    localStorage.setItem('totalLogDesktop.kindleLastTitle', progress.title);
    localStorage.setItem('totalLogDesktop.kindleLastProgress', kindleProgressLabel(progress));
    localStorage.setItem('totalLogDesktop.kindleLastSyncAt', new Date().toISOString());
    kindleStatus.textContent = `${progress.title} · ${kindleProgressLabel(progress)} · synced ${new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})}`;
  } catch (error) {
    kindleStatus.textContent = error instanceof Error ? error.message : String(error);
  } finally {
    kindleUploadRunning = false;
  }
}

async function syncKindle() {
  if (localStorage.getItem('totalLogDesktop.kindleEnabled') !== 'true') return;
  try {
    kindleStatus.textContent = 'Checking your most recently read Kindle book…';
    await invoke('sync_kindle', {kindleUrl: normalizedKindleUrl().toString()});
  } catch (error) {
    kindleStatus.textContent = error instanceof Error ? error.message : String(error);
  }
}

function render(snapshot: ActivitySnapshot) {
  appName.textContent = snapshot.application || 'Unknown application';
  windowTitle.textContent = snapshot.window_title || snapshot.executable || 'No window title available';
  idleTime.textContent = seconds(snapshot.idle_seconds);
  platformBadge.textContent = snapshot.supported ? (snapshot.idle_seconds >= 180 ? 'Away' : 'Tracking') : 'Windows only';
  platformBadge.classList.toggle('away', snapshot.idle_seconds >= 180);

  const previous = samples.at(0);
  if (!previous || previous.process_id !== snapshot.process_id || previous.window_title !== snapshot.window_title) {
    samples.unshift(snapshot);
    samples.splice(8);
    list.replaceChildren(...samples.map(item => {
      const row = document.createElement('li');
      const date = new Date(item.captured_at_unix_ms);
      row.innerHTML = `<span><strong></strong><small></small></span><time></time>`;
      row.querySelector('strong')!.textContent = item.application;
      row.querySelector('small')!.textContent = item.window_title || item.executable;
      row.querySelector('time')!.textContent = date.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
      return row;
    }));
  }
  syncActivity(snapshot);
}

async function syncActivity(snapshot: ActivitySnapshot) {
  if (syncRunning || localStorage.getItem('totalLogDesktop.syncEnabled') !== 'true') return;
  if (!snapshot.supported || snapshot.idle_seconds >= 180 || snapshot.process_id === 0 || !snapshot.application) return;
  if (snapshot.application.toLowerCase() === 'total-log-desktop') return;
  const processName = snapshot.executable.split(/[\\/]/).at(-1) || snapshot.application;
  const identity = `${snapshot.application}\n${processName}`;
  if (identity === lastAttemptedIdentity && Date.now() - lastSyncAt < 30_000) return;
  syncRunning = true;
  lastSyncAt = Date.now();
  lastAttemptedIdentity = identity;
  try {
    await invoke('send_activity', {payload: {
      app_url: normalizedBaseUrl().toString(), pairing_key: pairingKey.value, client_id: clientId,
      application: snapshot.application, process_name: processName, observed_at: new Date().toISOString(),
    }});
    connectionStatus.textContent = `Connected · last update ${new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})}`;
  } catch (error) {
    connectionStatus.textContent = error instanceof Error ? error.message : String(error);
  } finally {
    syncRunning = false;
  }
}

appUrl.value = localStorage.getItem('totalLogDesktop.appUrl') || appUrl.value;
pairingKey.value = localStorage.getItem('totalLogDesktop.pairingKey') || randomKey();
kindleUrl.value = localStorage.getItem('totalLogDesktop.kindleUrl') || kindleUrl.value;
localStorage.setItem('totalLogDesktop.clientId', clientId);
saveSettings();

const previousKindleTitle = localStorage.getItem('totalLogDesktop.kindleLastTitle');
const previousKindleProgress = localStorage.getItem('totalLogDesktop.kindleLastProgress');
const previousKindleSync = localStorage.getItem('totalLogDesktop.kindleLastSyncAt');
if (previousKindleTitle && previousKindleSync) {
  kindleStatus.textContent = `${previousKindleTitle}${previousKindleProgress ? ` · ${previousKindleProgress}` : ''} · last synced ${new Date(previousKindleSync).toLocaleString()}`;
}

document.querySelector('#reveal-key')?.addEventListener('click', event => {
  pairingKey.type = pairingKey.type === 'password' ? 'text' : 'password';
  (event.currentTarget as HTMLButtonElement).textContent = pairingKey.type === 'password' ? 'Show' : 'Hide';
});

document.querySelector('#new-key-button')?.addEventListener('click', () => {
  pairingKey.value = randomKey();
  localStorage.setItem('totalLogDesktop.syncEnabled', 'false');
  saveSettings();
  connectionStatus.textContent = 'A new local key was generated. Pair it before syncing.';
});

document.querySelector('#pair-button')?.addEventListener('click', async () => {
  try {
    saveSettings();
    if (!/^[A-Za-z0-9_-]{32,128}$/.test(pairingKey.value)) throw new Error('The pairing key must be 32–128 URL-safe characters.');
    const url = new URL(`sensors/desktop/pair/${encodeURIComponent(pairingKey.value)}`, normalizedBaseUrl());
    await openUrl(url.toString());
    localStorage.setItem('totalLogDesktop.syncEnabled', 'true');
    connectionStatus.textContent = 'Finish signing in and approve pairing in your browser.';
  } catch (error) {
    connectionStatus.textContent = error instanceof Error ? error.message : String(error);
  }
});

appUrl.addEventListener('change', () => {
  try { saveSettings(); } catch (error) { connectionStatus.textContent = error instanceof Error ? error.message : String(error); }
});
pairingKey.addEventListener('change', saveSettings);

document.querySelector('#kindle-connect')?.addEventListener('click', async () => {
  try {
    const url = normalizedKindleUrl();
    localStorage.setItem('totalLogDesktop.kindleEnabled', 'true');
    localStorage.setItem('totalLogDesktop.kindleUrl', url.toString());
    kindleStatus.textContent = 'Sign in to Amazon in the Kindle window. Total Log never receives your password or cookies.';
    await invoke('open_kindle', {kindleUrl: url.toString()});
  } catch (error) {
    kindleStatus.textContent = error instanceof Error ? error.message : String(error);
  }
});

document.querySelector('#kindle-sync')?.addEventListener('click', async () => {
  localStorage.setItem('totalLogDesktop.kindleEnabled', 'true');
  localStorage.setItem('totalLogDesktop.kindleUrl', kindleUrl.value);
  localStorage.removeItem('totalLogDesktop.kindleLastFingerprint');
  await syncKindle();
});

kindleUrl.addEventListener('change', () => {
  try {
    localStorage.setItem('totalLogDesktop.kindleUrl', normalizedKindleUrl().toString());
  } catch (error) {
    kindleStatus.textContent = error instanceof Error ? error.message : String(error);
  }
});

async function bootstrap() {
  await Promise.all([
    listen<ActivitySnapshot>('activity-update', event => render(event.payload)),
    listen<KindleProgress>('kindle-progress', event => uploadKindleProgress(event.payload)),
    listen<KindleStatusReport>('kindle-status', event => {
      const labels: Record<string, string> = {
        syncing: 'Checking your most recently read Kindle book…',
        ready: 'Kindle is connected, but no recent book was found.',
        expired: 'Kindle sign-in expired. Open Kindle and sign in again.',
      };
      kindleStatus.textContent = event.payload.message || labels[event.payload.status] || event.payload.status;
    }),
  ]);
  render(await invoke<ActivitySnapshot>('current_activity'));
  if (localStorage.getItem('totalLogDesktop.kindleEnabled') === 'true') {
    window.setTimeout(syncKindle, 5000);
  }
}

window.setInterval(syncKindle, 60 * 60 * 1000);

bootstrap().catch(error => {
  platformBadge.textContent = 'Error';
  windowTitle.textContent = error instanceof Error ? error.message : String(error);
});
