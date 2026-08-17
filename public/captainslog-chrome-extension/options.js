const DEFAULT_APP_URL = 'http://127.0.0.1:8016/';
const appUrl = document.getElementById('app-url');
const statusDot = document.getElementById('status-dot');
const statusLabel = document.getElementById('status-label');
const statusDetail = document.getElementById('status-detail');

function normalize(value) {
  const url = new URL(value || DEFAULT_APP_URL);
  if (!['http:', 'https:'].includes(url.protocol)) throw new Error('Use an http:// or https:// URL.');
  url.hash = '';
  url.search = '';
  if (!url.pathname.endsWith('/')) url.pathname += '/';
  return url.toString();
}

function formatTime(value) {
  return value ? new Date(value).toLocaleString() : 'No browsing updates sent yet.';
}

async function render() {
  const data = await chrome.storage.local.get(['appUrl', 'connectionStatus', 'lastSentAt', 'lastDomain', 'lastError']);
  appUrl.value = data.appUrl || DEFAULT_APP_URL;
  const state = data.connectionStatus || 'unpaired';
  statusDot.dataset.state = state;
  if (state === 'connected') {
    statusLabel.textContent = 'Connected';
    statusDetail.textContent = data.lastDomain ? `Last sent ${data.lastDomain} · ${formatTime(data.lastSentAt)}` : 'Connected and waiting for a browsed site.';
  } else if (state === 'error') {
    statusLabel.textContent = 'Needs attention';
    statusDetail.textContent = data.lastError || 'Could not reach Captain\'s Log.';
  } else if (state === 'pairing') {
    statusLabel.textContent = 'Pairing';
    statusDetail.textContent = 'Finish signing in and approve the pairing in the opened Captain\'s Log tab.';
  } else {
    statusLabel.textContent = 'Not connected';
    statusDetail.textContent = 'Save the app URL, then connect this extension to your signed-in account.';
  }
}

document.getElementById('save-url').addEventListener('click', async () => {
  try {
    const value = normalize(appUrl.value);
    await chrome.storage.local.set({appUrl: value, connectionStatus: 'unpaired', lastError: null});
    appUrl.value = value;
    await render();
  } catch (error) {
    statusDot.dataset.state = 'error';
    statusLabel.textContent = 'Invalid app URL';
    statusDetail.textContent = error.message;
  }
});

document.getElementById('connect').addEventListener('click', async () => {
  try {
    const value = normalize(appUrl.value);
    await chrome.storage.local.set({appUrl: value});
    await chrome.runtime.sendMessage({type: 'connect'});
    await render();
  } catch (error) {
    statusDetail.textContent = error.message;
  }
});

document.getElementById('send-now').addEventListener('click', async () => {
  await chrome.runtime.sendMessage({type: 'send-now'});
  window.setTimeout(render, 300);
});

chrome.storage.onChanged.addListener(() => render());
render();
