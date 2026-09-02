const DEFAULT_APP_URL = 'http://127.0.0.1:8016/';
const appUrl = document.getElementById('app-url');
const statusDot = document.getElementById('status-dot');
const statusLabel = document.getElementById('status-label');
const statusDetail = document.getElementById('status-detail');
const mobileHistoryDot = document.getElementById('mobile-history-dot');
const mobileHistoryDetail = document.getElementById('mobile-history-detail');
const kindleEnabled = document.getElementById('kindle-enabled');
const kindleFields = document.getElementById('kindle-settings-fields');
const kindleUrl = document.getElementById('kindle-url');
const kindleStatusDot = document.getElementById('kindle-status-dot');
const kindleStatusLabel = document.getElementById('kindle-status-label');
const kindleStatusDetail = document.getElementById('kindle-status-detail');
const debugHistoryState = document.getElementById('debug-history-state');
const debugHistorySummary = document.getElementById('debug-history-summary');
const debugHistoryList = document.getElementById('debug-history-list');
const debugHistoryFilter = document.getElementById('debug-history-filter');
const debugHistoryPageLabel = document.getElementById('debug-history-page');
const debugMessageList = document.getElementById('debug-message-list');
const DEBUG_HISTORY_PAGE_SIZE = 100;
let debugHistoryEntries = [];
let debugHistoryPage = 0;

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

function historyLocality(visit) {
  if (visit.isLocal === false) return 'remote';
  if (visit.isLocal === true) return 'local';
  return 'unknown';
}

function filteredDebugHistory() {
  const filter = debugHistoryFilter.value;
  return filter === 'all' ? debugHistoryEntries : debugHistoryEntries.filter(entry => entry.locality === filter);
}

function renderDebugHistory() {
  const entries = filteredDebugHistory();
  const pageCount = Math.ceil(entries.length / DEBUG_HISTORY_PAGE_SIZE);
  debugHistoryPage = Math.max(0, Math.min(debugHistoryPage, Math.max(0, pageCount - 1)));
  const visible = entries.slice(debugHistoryPage * DEBUG_HISTORY_PAGE_SIZE, (debugHistoryPage + 1) * DEBUG_HISTORY_PAGE_SIZE);
  const rows = visible.map(entry => {
    const row = document.createElement('div');
    row.className = 'debug-history-row';
    const head = document.createElement('div');
    head.className = 'debug-history-row-head';
    const time = document.createElement('time');
    time.textContent = new Date(entry.visitTime).toLocaleString();
    const locality = document.createElement('span');
    locality.className = 'debug-history-locality';
    locality.dataset.locality = entry.locality;
    locality.textContent = `isLocal: ${entry.isLocal === undefined ? 'undefined' : String(entry.isLocal)}`;
    head.append(time, locality);
    const url = document.createElement('div');
    url.className = 'debug-history-url';
    url.textContent = entry.url;
    const meta = document.createElement('div');
    meta.className = 'debug-history-meta';
    meta.textContent = `${entry.transition || 'unknown transition'}${entry.title ? ` · ${entry.title}` : ''}`;
    row.append(head, url, meta);
    return row;
  });
  debugHistoryList.replaceChildren(...rows);
  if (!rows.length) debugHistoryList.textContent = 'No visits match this filter.';
  debugHistoryPageLabel.textContent = `Page ${pageCount ? debugHistoryPage + 1 : 0} of ${pageCount} · ${entries.length} visits`;
  document.getElementById('debug-history-previous').disabled = debugHistoryPage <= 0;
  document.getElementById('debug-history-next').disabled = debugHistoryPage >= pageCount - 1;
}

async function readAllDebugHistory() {
  debugHistoryEntries = [];
  debugHistoryPage = 0;
  debugHistoryState.textContent = 'Reading…';
  debugHistorySummary.textContent = 'Querying Chrome history pages and individual visit records…';
  const pageSize = 10000;
  const startTime = 0;
  let endTime = Date.now();
  const seenUrls = new Set();
  while (endTime >= startTime) {
    const pages = await chrome.history.search({text: '', startTime, endTime, maxResults: pageSize});
    if (!pages.length) break;
    const unseenPages = pages.filter(page => page.url && !seenUrls.has(page.url));
    unseenPages.forEach(page => seenUrls.add(page.url));
    for (let offset = 0; offset < unseenPages.length; offset += 25) {
      const group = unseenPages.slice(offset, offset + 25);
      const visitsByPage = await Promise.all(group.map(async page => {
        const visits = await chrome.history.getVisits({url: page.url});
        return visits.map(visit => ({
          url: page.url,
          title: page.title || '',
          visitTime: visit.visitTime,
          transition: visit.transition,
          isLocal: visit.isLocal,
          locality: historyLocality(visit)
        }));
      }));
      debugHistoryEntries.push(...visitsByPage.flat());
      debugHistoryState.textContent = `${debugHistoryEntries.length.toLocaleString()} visits read`;
    }
    if (pages.length < pageSize || !unseenPages.length) break;
    const oldestVisitTime = Math.min(...pages.map(page => page.lastVisitTime).filter(Number.isFinite));
    if (!Number.isFinite(oldestVisitTime) || oldestVisitTime >= endTime) break;
    endTime = oldestVisitTime - 1;
  }
  debugHistoryEntries.sort((left, right) => Number(right.visitTime || 0) - Number(left.visitTime || 0));
  const local = debugHistoryEntries.filter(entry => entry.locality === 'local').length;
  const remote = debugHistoryEntries.filter(entry => entry.locality === 'remote').length;
  const unknown = debugHistoryEntries.filter(entry => entry.locality === 'unknown').length;
  debugHistoryState.textContent = 'Scan complete';
  debugHistorySummary.textContent = `${debugHistoryEntries.length.toLocaleString()} total visits across ${seenUrls.size.toLocaleString()} URLs · ${remote.toLocaleString()} synced (isLocal: false) · ${local.toLocaleString()} desktop (isLocal: true) · ${unknown.toLocaleString()} unknown`;
  renderDebugHistory();
}

async function renderDebugMessages() {
  const data = await chrome.storage.local.get(['debugMessages']);
  const messages = Array.isArray(data.debugMessages) ? [...data.debugMessages].reverse() : [];
  debugMessageList.textContent = messages.length
    ? messages.map(message => `${message.at} [${String(message.level || 'info').toUpperCase()}] ${message.event}\n${JSON.stringify(message.details || {}, null, 2)}`).join('\n\n')
    : 'No debug messages recorded yet.';
}

async function render() {
  const data = await chrome.storage.local.get(['appUrl', 'connectionStatus', 'lastSentAt', 'lastDomain', 'lastLogDate', 'lastError', 'lastMobileHistorySyncAt', 'lastMobileHistoryImportCount', 'lastMobileHistoryRejectedCount', 'mobileHistoryLastError', 'kindleEnabled', 'kindleUrl', 'kindleStatus', 'kindleLastSyncAt', 'kindleLastTitle', 'kindleLastProgress', 'kindleLastError']);
  appUrl.value = data.appUrl || DEFAULT_APP_URL;
  const state = data.connectionStatus || 'unpaired';
  statusDot.dataset.state = state;
  if (state === 'connected') {
    statusLabel.textContent = 'Connected';
    const recordedDate = data.lastLogDate ? ` · recorded on ${data.lastLogDate}` : '';
    statusDetail.textContent = data.lastDomain ? `Last sent ${data.lastDomain} · ${formatTime(data.lastSentAt)}${recordedDate}` : 'Connected and waiting for a browsed site.';
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
  mobileHistoryDot.dataset.state = data.mobileHistoryLastError ? 'error' : (data.lastMobileHistorySyncAt ? 'connected' : 'waiting');
  if (data.mobileHistoryLastError) {
    mobileHistoryDetail.textContent = data.mobileHistoryLastError;
  } else if (data.lastMobileHistorySyncAt) {
    const count = Number(data.lastMobileHistoryImportCount || 0);
    const rejected = Number(data.lastMobileHistoryRejectedCount || 0);
    mobileHistoryDetail.textContent = `Last scan ${formatTime(data.lastMobileHistorySyncAt)} · ${count} new ${count === 1 ? 'visit' : 'visits'}${rejected ? ` · ${rejected} skipped` : ''}`;
  } else {
    mobileHistoryDetail.textContent = 'Waiting for the first synchronized history scan.';
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

document.getElementById('mobile-history-sync').addEventListener('click', async () => {
  mobileHistoryDetail.textContent = 'Scanning recent synchronized Chrome history…';
  await chrome.runtime.sendMessage({type: 'mobile-history-sync-now'});
  await render();
});

document.getElementById('mobile-history-sync-past').addEventListener('click', async () => {
  mobileHistoryDetail.textContent = 'Scanning all synchronized history Chrome still has…';
  await chrome.runtime.sendMessage({type: 'mobile-history-sync-past'});
  await render();
});

document.querySelectorAll('[data-options-tab]').forEach(button => button.addEventListener('click', async () => {
  const selected = button.dataset.optionsTab;
  document.querySelectorAll('[data-options-tab]').forEach(tab => {
    const active = tab.dataset.optionsTab === selected;
    tab.setAttribute('aria-selected', String(active));
    tab.classList.toggle('secondary', !active);
  });
  document.querySelectorAll('[data-options-tab-panel]').forEach(panel => { panel.hidden = panel.dataset.optionsTabPanel !== selected; });
  if (selected === 'debug') await renderDebugMessages();
}));

document.getElementById('debug-refresh-history').addEventListener('click', async () => {
  try {
    await readAllDebugHistory();
  } catch (error) {
    debugHistoryState.textContent = 'Scan failed';
    debugHistorySummary.textContent = error.message || String(error);
  }
});
document.getElementById('debug-refresh-messages').addEventListener('click', renderDebugMessages);
document.getElementById('debug-clear-messages').addEventListener('click', async () => {
  await chrome.storage.local.set({debugMessages: []});
  await renderDebugMessages();
});
document.getElementById('debug-copy-report').addEventListener('click', async () => {
  const data = await chrome.storage.local.get(['debugMessages', 'connectionStatus', 'lastMobileHistorySyncAt', 'lastMobileHistoryImportCount', 'lastMobileHistoryRejectedCount', 'mobileHistoryLastError']);
  const report = {
    generatedAt: new Date().toISOString(),
    status: {
      connectionStatus: data.connectionStatus,
      lastMobileHistorySyncAt: data.lastMobileHistorySyncAt,
      lastMobileHistoryImportCount: data.lastMobileHistoryImportCount,
      lastMobileHistoryRejectedCount: data.lastMobileHistoryRejectedCount,
      mobileHistoryLastError: data.mobileHistoryLastError
    },
    historySummary: debugHistorySummary.textContent,
    history: debugHistoryEntries,
    messages: data.debugMessages || []
  };
  await navigator.clipboard.writeText(JSON.stringify(report, null, 2));
  debugHistoryState.textContent = 'Report copied';
});
debugHistoryFilter.addEventListener('change', () => { debugHistoryPage = 0; renderDebugHistory(); });
document.getElementById('debug-history-previous').addEventListener('click', () => { debugHistoryPage--; renderDebugHistory(); });
document.getElementById('debug-history-next').addEventListener('click', () => { debugHistoryPage++; renderDebugHistory(); });

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

chrome.storage.onChanged.addListener(changes => {
  render();
  if (changes.debugMessages && !document.getElementById('debug-tab-panel').hidden) renderDebugMessages();
});
render();
