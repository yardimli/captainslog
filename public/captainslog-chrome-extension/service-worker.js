const DEFAULT_APP_URL = 'http://127.0.0.1:8016/';
const HEARTBEAT_ALARM = 'captainslog-browsing-heartbeat';

const randomKey = () => `${crypto.randomUUID().replaceAll('-', '')}${crypto.randomUUID().replaceAll('-', '')}`;
const normalizeAppUrl = value => {
  const url = new URL(value || DEFAULT_APP_URL);
  if (!['http:', 'https:'].includes(url.protocol)) throw new Error('Use an http:// or https:// app URL.');
  url.hash = '';
  url.search = '';
  if (!url.pathname.endsWith('/')) url.pathname += '/';
  return url.toString();
};

async function settings() {
  const saved = await chrome.storage.local.get(['appUrl', 'pairingKey', 'clientId', 'connectionStatus', 'lastSentAt', 'lastDomain', 'lastError']);
  const updates = {};
  if (!saved.appUrl) updates.appUrl = DEFAULT_APP_URL;
  if (!saved.pairingKey) updates.pairingKey = randomKey();
  if (!saved.clientId) updates.clientId = crypto.randomUUID().replaceAll('-', '');
  if (Object.keys(updates).length) await chrome.storage.local.set(updates);
  return {...saved, ...updates};
}

async function activeWebTab() {
  if (await chrome.idle.queryState(180) !== 'active') return null;
  const [tab] = await chrome.tabs.query({active: true, lastFocusedWindow: true});
  if (!tab?.url) return null;
  const url = new URL(tab.url);
  return ['http:', 'https:'].includes(url.protocol) ? tab : null;
}

async function sendActiveBrowsing() {
  try {
    const config = await settings();
    const appUrl = normalizeAppUrl(config.appUrl);
    const tab = await activeWebTab();
    if (!tab) return;
    const browsingUrl = new URL(tab.url);
    if (browsingUrl.origin === new URL(appUrl).origin) return;

    const response = await fetch(new URL('api/sensors/browser/activity', appUrl), {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CaptainsLog-Key': config.pairingKey
      },
      body: JSON.stringify({
        url: browsingUrl.origin,
        observed_at: new Date().toISOString(),
        client_id: config.clientId
      })
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(body.message || `Captain's Log returned ${response.status}.`);
    await chrome.storage.local.set({
      connectionStatus: 'connected',
      lastSentAt: new Date().toISOString(),
      lastDomain: body.domain || browsingUrl.hostname,
      lastError: null
    });
  } catch (error) {
    await chrome.storage.local.set({connectionStatus: 'error', lastError: error.message || String(error)});
  }
}

async function connectToApp() {
  const config = await settings();
  const pairingKey = randomKey();
  await chrome.storage.local.set({pairingKey, connectionStatus: 'pairing', lastError: null});
  const pairingUrl = new URL(`sensors/browser/pair/${encodeURIComponent(pairingKey)}`, normalizeAppUrl(config.appUrl));
  await chrome.tabs.create({url: pairingUrl.toString()});
}

chrome.runtime.onInstalled.addListener(async details => {
  await settings();
  await chrome.alarms.create(HEARTBEAT_ALARM, {periodInMinutes: 1});
  if (details.reason === 'install') await chrome.runtime.openOptionsPage();
});

chrome.runtime.onStartup.addListener(async () => {
  await settings();
  await chrome.alarms.create(HEARTBEAT_ALARM, {periodInMinutes: 1});
  await sendActiveBrowsing();
});

chrome.alarms.onAlarm.addListener(alarm => {
  if (alarm.name === HEARTBEAT_ALARM) sendActiveBrowsing();
});
chrome.tabs.onActivated.addListener(() => sendActiveBrowsing());
chrome.tabs.onUpdated.addListener((_tabId, changeInfo, tab) => {
  if (tab.active && (changeInfo.url || changeInfo.status === 'complete')) sendActiveBrowsing();
});
chrome.windows.onFocusChanged.addListener(windowId => {
  if (windowId !== chrome.windows.WINDOW_ID_NONE) sendActiveBrowsing();
});
chrome.idle.onStateChanged.addListener(state => {
  if (state === 'active') sendActiveBrowsing();
});
chrome.action.onClicked.addListener(() => chrome.runtime.openOptionsPage());
chrome.runtime.onMessage.addListener((message, _sender, sendResponse) => {
  if (message?.type === 'connect') connectToApp().then(() => sendResponse({ok: true})).catch(error => sendResponse({ok: false, error: error.message}));
  else if (message?.type === 'send-now') sendActiveBrowsing().then(() => sendResponse({ok: true})).catch(error => sendResponse({ok: false, error: error.message}));
  else return false;
  return true;
});

settings().then(() => chrome.alarms.create(HEARTBEAT_ALARM, {periodInMinutes: 1}));
