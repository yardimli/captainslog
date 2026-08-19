const DEFAULT_APP_URL = 'http://127.0.0.1:8016/';
const appUrl = document.getElementById('app-url');
const statusDot = document.getElementById('status-dot');
const statusLabel = document.getElementById('status-label');
const statusDetail = document.getElementById('status-detail');
const kindleEnabled = document.getElementById('kindle-enabled');
const kindleFields = document.getElementById('kindle-settings-fields');
const kindleUrl = document.getElementById('kindle-url');
const kindleStatusDot = document.getElementById('kindle-status-dot');
const kindleStatusLabel = document.getElementById('kindle-status-label');
const kindleStatusDetail = document.getElementById('kindle-status-detail');

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
  const data = await chrome.storage.local.get(['appUrl', 'connectionStatus', 'lastSentAt', 'lastDomain', 'lastError', 'kindleEnabled', 'kindleUrl', 'kindleStatus', 'kindleLastSyncAt', 'kindleLastTitle', 'kindleLastProgress', 'kindleLastError']);
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
  kindleEnabled.checked = data.kindleEnabled === true;
  kindleFields.hidden = !kindleEnabled.checked;
  kindleUrl.value = data.kindleUrl || 'https://read.amazon.com/kindle-library';
  const kindleState = data.kindleStatus || 'unconnected';
  kindleStatusDot.dataset.state = kindleState;
  if (kindleState === 'connected') {
    kindleStatusLabel.textContent = 'Kindle is syncing';
    const progress = data.kindleLastProgress === null || data.kindleLastProgress === undefined ? '' : ` · ${data.kindleLastProgress}${typeof data.kindleLastProgress === 'number' ? '%' : ''}`;
    kindleStatusDetail.textContent = `${data.kindleLastTitle || 'Last book'}${progress} · ${formatTime(data.kindleLastSyncAt)}`;
  } else if (kindleState === 'ready') {
    kindleStatusLabel.textContent = 'Kindle session found';
    kindleStatusDetail.textContent = 'Open a book in Kindle Cloud Reader to send its current reading progress.';
  } else if (kindleState === 'syncing') {
    kindleStatusLabel.textContent = 'Syncing Kindle';
    kindleStatusDetail.textContent = 'Checking the recent library and loading its first book without changing your active tab.';
  } else if (kindleState === 'waiting') {
    kindleStatusLabel.textContent = 'Waiting for Kindle';
    kindleStatusDetail.textContent = 'Sign in if asked, then open a book. Progress is detected from the reader page.';
  } else if (['expired', 'error', 'permission-required'].includes(kindleState)) {
    kindleStatusLabel.textContent = 'Resync required';
    kindleStatusDetail.textContent = data.kindleLastError || 'The Kindle session is no longer available.';
  } else {
    kindleStatusLabel.textContent = 'Not connected';
    kindleStatusDetail.textContent = 'Open Kindle, sign in, and begin reading to create the first update.';
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

kindleEnabled.addEventListener('change', async () => {
  if (kindleEnabled.checked) {
    const granted = await chrome.permissions.request({permissions: ['cookies']});
    if (!granted) {
      kindleEnabled.checked = false;
      await chrome.storage.local.set({kindleEnabled: false, kindleStatus: 'permission-required', kindleLastError: 'Cookie access was not approved.'});
      await render();
      return;
    }
    await chrome.storage.local.set({kindleEnabled: true, kindleUrl: kindleUrl.value || 'https://read.amazon.com/kindle-library', kindleStatus: 'waiting', kindleLastError: null});
    await chrome.runtime.sendMessage({type: 'kindle-sync-now'});
  } else {
    await chrome.storage.local.set({kindleEnabled: false, kindleStatus: 'disabled', kindleLastError: null});
  }
  await render();
});

document.getElementById('kindle-connect').addEventListener('click', async () => {
  try {
    const url = new URL(kindleUrl.value);
    if (url.protocol !== 'https:' || !/^read\.amazon\./i.test(url.hostname)) throw new Error('Use a Kindle Cloud Reader URL beginning with https://read.amazon.');
    await chrome.storage.local.set({kindleUrl: url.toString(), kindleLastFingerprint: null});
    await chrome.runtime.sendMessage({type: 'kindle-connect'});
    await render();
  } catch (error) {
    kindleStatusLabel.textContent = 'Invalid Kindle URL';
    kindleStatusDetail.textContent = error.message;
  }
});

document.getElementById('kindle-sync').addEventListener('click', async () => {
  await chrome.storage.local.set({kindleUrl: kindleUrl.value, kindleLastFingerprint: null});
  await chrome.runtime.sendMessage({type: 'kindle-sync-now'});
  window.setTimeout(render, 200);
});

document.getElementById('kindle-check').addEventListener('click', async () => {
  await chrome.storage.local.set({kindleUrl: kindleUrl.value});
  await chrome.runtime.sendMessage({type: 'kindle-check'});
  window.setTimeout(render, 200);
});

chrome.storage.onChanged.addListener(() => render());
render();
