const DEFAULT_APP_URL = 'http://127.0.0.1:8016/';
const DEFAULT_KINDLE_URL = 'https://read.amazon.com/kindle-library';
const HEARTBEAT_ALARM = 'captainslog-browsing-heartbeat';
const MOBILE_HISTORY_ALARM = 'captainslog-mobile-history';
const KINDLE_CHECK_ALARM = 'captainslog-kindle-session-check';
const KINDLE_SYNC_TIMEOUT_PREFIX = 'captainslog-kindle-sync-timeout-';
const DEBUG_MESSAGE_LIMIT = 1000;
let debugWriteQueue = Promise.resolve();

function debugLog(level, event, details = {}) {
  const entry = {at: new Date().toISOString(), level, event, details};
  console[level === 'error' ? 'error' : 'log'](`[Captain's Log] ${event}`, details);
  debugWriteQueue = debugWriteQueue.then(async () => {
    const stored = await chrome.storage.local.get(['debugMessages']);
    const messages = Array.isArray(stored.debugMessages) ? stored.debugMessages : [];
    messages.push(entry);
    await chrome.storage.local.set({debugMessages: messages.slice(-DEBUG_MESSAGE_LIMIT)});
  }).catch(error => {
    console.error('[Captain\'s Log] Could not store debug message', error);
  });
  return debugWriteQueue;
}

const randomKey = () => `${crypto.randomUUID().replaceAll('-', '')}${crypto.randomUUID().replaceAll('-', '')}`;
const normalizeAppUrl = value => {
  const url = new URL(value || DEFAULT_APP_URL);
  if (!['http:', 'https:'].includes(url.protocol)) throw new Error('Use an http:// or https:// app URL.');
  url.hash = '';
  url.search = '';
  if (!url.pathname.endsWith('/')) url.pathname += '/';
  return url.toString();
};
const normalizeKindleUrl = value => {
  const url = new URL(value || DEFAULT_KINDLE_URL);
  if (url.protocol !== 'https:' || !/^read\.amazon\./i.test(url.hostname)) throw new Error('Use a Kindle Cloud Reader URL beginning with https://read.amazon.');
  return url.toString();
};

async function settings() {
  const keys = ['appUrl', 'pairingKey', 'clientId', 'connectionStatus', 'lastSentAt', 'lastDomain', 'lastError', 'lastMobileHistorySyncAt', 'lastMobileHistoryImportCount', 'lastMobileHistoryRejectedCount', 'mobileHistoryLastError', 'kindleEnabled', 'kindleUrl', 'kindleStatus', 'kindleLastSyncAt', 'kindleLastTitle', 'kindleLastProgress', 'kindleLastError', 'kindleLastFingerprint'];
  const saved = await chrome.storage.local.get(keys);
  const updates = {};
  if (!saved.appUrl) updates.appUrl = DEFAULT_APP_URL;
  if (!saved.pairingKey) updates.pairingKey = randomKey();
  if (!saved.clientId) updates.clientId = crypto.randomUUID().replaceAll('-', '');
  if (!saved.kindleUrl) updates.kindleUrl = DEFAULT_KINDLE_URL;
  if (typeof saved.kindleEnabled !== 'boolean') updates.kindleEnabled = false;
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
      headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CaptainsLog-Key': config.pairingKey},
      body: JSON.stringify({url: `${browsingUrl.protocol}//${browsingUrl.hostname}`, observed_at: new Date().toISOString(), client_id: config.clientId})
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(body.message || `Captain's Log returned ${response.status}.`);
    await chrome.storage.local.set({connectionStatus: 'connected', lastSentAt: new Date().toISOString(), lastDomain: body.domain || browsingUrl.hostname, lastError: null});
  } catch (error) {
    await chrome.storage.local.set({connectionStatus: 'error', lastError: error.message || String(error)});
  }
}

async function sha256(value) {
  const bytes = new TextEncoder().encode(value);
  const digest = await crypto.subtle.digest('SHA-256', bytes);
  return [...new Uint8Array(digest)].map(byte => byte.toString(16).padStart(2, '0')).join('');
}

function isTrackableHostname(hostname) {
  const host = String(hostname || '').replace(/^\[|\]$/g, '');
  if (!host || host.includes(':')) return false;
  if (/^\d{1,3}(?:\.\d{1,3}){3}$/.test(host)) return false;
  return true;
}

async function remoteHistoryVisits(startTime, endTime, appOrigin) {
  const pageSize = 10000;
  const seenUrls = new Set();
  const visits = [];
  const counts = {pages: 0, visits: 0, local: 0, remote: 0, unknown: 0};
  let searchEndTime = endTime;
  while (searchEndTime >= startTime) {
    const pages = await chrome.history.search({text: '', startTime, endTime: searchEndTime, maxResults: pageSize});
    await debugLog('info', 'history-page-read', {startTime: new Date(startTime).toISOString(), endTime: new Date(searchEndTime).toISOString(), pages: pages.length});
    if (!pages.length) break;
    const unseenPages = pages.filter(page => page.url && !seenUrls.has(page.url));
    unseenPages.forEach(page => seenUrls.add(page.url));
    counts.pages += unseenPages.length;
    for (let offset = 0; offset < unseenPages.length; offset += 25) {
      const group = unseenPages.slice(offset, offset + 25);
      const details = await Promise.all(group.map(async page => {
        let url;
        try {
          url = new URL(page.url);
        } catch (_error) {
          return [];
        }
        if (!['http:', 'https:'].includes(url.protocol) || url.origin === appOrigin || !isTrackableHostname(url.hostname)) return [];
        const pageVisits = await chrome.history.getVisits({url: page.url});
        counts.visits += pageVisits.length;
        counts.local += pageVisits.filter(visit => visit.isLocal === true).length;
        counts.remote += pageVisits.filter(visit => visit.isLocal === false).length;
        counts.unknown += pageVisits.filter(visit => typeof visit.isLocal !== 'boolean').length;
        return Promise.all(pageVisits
          .filter(visit => visit.isLocal === false && visit.visitTime >= startTime && visit.visitTime <= endTime)
          .map(async visit => ({
            url: `${url.protocol}//${url.hostname}`,
            visited_at: new Date(visit.visitTime).toISOString(),
            visit_key: await sha256(`${page.url}\n${visit.visitTime}\n${visit.visitId}`)
          })));
      }));
      visits.push(...details.flat());
    }
    if (pages.length < pageSize || !unseenPages.length) break;
    const oldestVisitTime = Math.min(...pages.map(page => page.lastVisitTime).filter(Number.isFinite));
    if (!Number.isFinite(oldestVisitTime) || oldestVisitTime >= searchEndTime) break;
    searchEndTime = oldestVisitTime - 1;
  }
  await debugLog('info', 'history-scan-complete', {...counts, remoteVisitsSelected: visits.length});
  return visits.sort((left, right) => right.visited_at.localeCompare(left.visited_at));
}

async function syncMobileHistory(fullScan = false) {
  const running = await chrome.storage.session.get(['mobileHistorySyncRunning']);
  if (running.mobileHistorySyncRunning) {
    await debugLog('info', 'mobile-sync-skipped', {reason: 'another scan is already running', fullScan});
    return;
  }
  await chrome.storage.session.set({mobileHistorySyncRunning: true});
  await debugLog('info', 'mobile-sync-started', {fullScan});
  try {
    const config = await settings();
    if (!config.pairingKey) throw new Error('Connect the extension to Captain\'s Log before syncing mobile history.');
    const appUrl = normalizeAppUrl(config.appUrl);
    const endTime = Date.now();
    const initialStart = endTime - (90 * 24 * 60 * 60 * 1000);
    const startTime = fullScan
      ? 0
      : (config.lastMobileHistorySyncAt
        ? Math.max(initialStart, Date.parse(config.lastMobileHistorySyncAt) - (24 * 60 * 60 * 1000))
        : initialStart);
    await debugLog('info', 'mobile-sync-window', {fullScan, start: new Date(startTime).toISOString(), end: new Date(endTime).toISOString(), appOrigin: new URL(appUrl).origin});
    const visits = await remoteHistoryVisits(startTime, endTime, new URL(appUrl).origin);
    let imported = 0;
    let rejected = 0;
    const batches = visits.length ? Array.from({length: Math.ceil(visits.length / 500)}, (_value, index) => visits.slice(index * 500, (index + 1) * 500)) : [[]];
    for (const [index, batch] of batches.entries()) {
      await debugLog('info', 'mobile-api-request', {batch: index + 1, batches: batches.length, visits: batch.length});
      const response = await fetch(new URL('api/sensors/browser/mobile-history', appUrl), {
        method: 'POST',
        headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CaptainsLog-Key': config.pairingKey},
        body: JSON.stringify({visits: batch})
      });
      const body = await response.json().catch(() => ({}));
      await debugLog(response.ok ? 'info' : 'error', 'mobile-api-response', {batch: index + 1, status: response.status, ok: response.ok, imported: body.imported, duplicates: body.duplicates, rejected: body.rejected, message: body.message});
      if (!response.ok) throw new Error(body.message || `Mobile history import returned ${response.status}.`);
      imported += Number(body.imported || 0);
      rejected += Number(body.rejected || 0);
    }
    await chrome.storage.local.set({
      lastMobileHistorySyncAt: new Date(endTime).toISOString(),
      lastMobileHistoryImportCount: imported,
      lastMobileHistoryRejectedCount: rejected,
      mobileHistoryLastError: null,
      connectionStatus: 'connected',
      lastError: null
    });
    await debugLog('info', 'mobile-sync-complete', {fullScan, visitsFound: visits.length, imported, rejected});
  } catch (error) {
    await chrome.storage.local.set({mobileHistoryLastError: error.message || String(error)});
    await debugLog('error', 'mobile-sync-failed', {fullScan, message: error.message || String(error), stack: error.stack || null});
  } finally {
    await chrome.storage.session.remove(['mobileHistorySyncRunning']);
  }
}

async function setKindleStatus(status, detail = {}) {
  await chrome.storage.local.set({kindleStatus: status, ...detail});
  const warning = status === 'expired' || status === 'error' || status === 'permission-required';
  await chrome.action.setBadgeText({text: warning ? '!' : ''});
  if (warning) await chrome.action.setBadgeBackgroundColor({color: '#dc2626'});
}

async function checkKindleSession() {
  const config = await settings();
  if (!config.kindleEnabled) return {ok: false, disabled: true};
  const permitted = await chrome.permissions.contains({permissions: ['cookies']});
  if (!permitted) {
    await setKindleStatus('permission-required', {kindleLastError: 'Kindle cookie access has not been approved. Enable Kindle tracking again to grant it.'});
    return {ok: false, permissionRequired: true};
  }
  try {
    const cookies = await chrome.cookies.getAll({url: normalizeKindleUrl(config.kindleUrl)});
    const authenticated = cookies.some(cookie => /^(at-|sess-at-)/i.test(cookie.name));
    if (!authenticated) {
      await setKindleStatus('expired', {kindleLastError: 'The Kindle session is missing or expired. Open Kindle and sign in again, then resync.'});
      return {ok: false, expired: true};
    }
    await setKindleStatus(config.kindleLastSyncAt ? 'connected' : 'ready', {kindleLastError: null});
    return {ok: true};
  } catch (error) {
    await setKindleStatus('error', {kindleLastError: error.message || String(error)});
    return {ok: false, error: error.message || String(error)};
  }
}

async function connectKindle() {
  const config = await settings();
  await setKindleStatus('waiting', {kindleLastError: null});
  await chrome.tabs.create({url: normalizeKindleUrl(config.kindleUrl)});
  return {ok: true};
}

async function closeKindleSyncTab(tabId) {
  if (!tabId) return;
  await chrome.alarms.clear(`${KINDLE_SYNC_TIMEOUT_PREFIX}${tabId}`);
  await chrome.tabs.remove(tabId).catch(() => {});
  const current = await chrome.storage.session.get(['kindleSyncTabId']);
  if (current.kindleSyncTabId === tabId) await chrome.storage.session.remove(['kindleSyncTabId']);
}

async function syncKindleInBackground() {
  const config = await settings();
  if (!config.kindleEnabled) return {ok: false, disabled: true};
  try {
    const base = new URL(normalizeKindleUrl(config.kindleUrl));
    const libraryUrl = new URL('/kindle-library/search', base.origin);
    libraryUrl.search = new URLSearchParams({libraryType: 'BOOKS', paginationToken: '0', sortType: 'recency', querySize: '50'}).toString();
    await setKindleStatus('syncing', {kindleLastError: null});
    const response = await fetch(libraryUrl, {
      method: 'GET',
      credentials: 'include',
      headers: {'Accept': 'application/json'}
    });
    const responseText = await response.text();
    if (response.status === 401 || response.status === 403 || response.redirected || /\/ap\/signin/i.test(response.url) || /^\s*</.test(responseText)) {
      await setKindleStatus('expired', {kindleLastError: 'Kindle did not accept the saved session. Open Kindle, sign in again, then resync.'});
      return {ok: false, expired: true};
    }
    if (!response.ok) throw new Error(`Kindle library returned ${response.status}.`);
    let data;
    try {
      data = JSON.parse(responseText);
    } catch (_error) {
      throw new Error('Kindle returned an unexpected library response. Resync may be required.');
    }
    const books = Array.isArray(data.itemsList) ? data.itemsList : (Array.isArray(data.items) ? data.items : []);
    const book = books[0];
    if (!book) {
      await setKindleStatus('ready', {kindleLastError: null});
      return {ok: true, empty: true};
    }

    const readerPath = book.webReaderUrl || book.readerUrl || book.readUrl;
    if (!readerPath) {
      if (book.percentageRead !== null && book.percentageRead !== undefined) {
        return sendKindleProgress({title: book.title, author: Array.isArray(book.authors) ? book.authors.join(', ') : (book.author || null), asin: book.asin || null, percentage_read: Number(book.percentageRead), location: null});
      }
      throw new Error('Kindle did not provide a reader URL for the most recent book.');
    }

    const readerUrl = new URL(readerPath, base.origin);
    if (readerUrl.origin !== base.origin) throw new Error('Kindle returned a reader URL on an unexpected domain.');
    const previous = await chrome.storage.session.get(['kindleSyncTabId']);
    await closeKindleSyncTab(previous.kindleSyncTabId);
    const tab = await chrome.tabs.create({url: readerUrl.toString(), active: false});
    await chrome.storage.session.set({kindleSyncTabId: tab.id});
    await chrome.alarms.create(`${KINDLE_SYNC_TIMEOUT_PREFIX}${tab.id}`, {delayInMinutes: 2});
    return {ok: true, loading: true};
  } catch (error) {
    await setKindleStatus('error', {kindleLastError: error.message || String(error)});
    return {ok: false, error: error.message || String(error)};
  }
}

async function sendKindleProgress(progress) {
  const config = await settings();
  if (!config.kindleEnabled) return {ok: false, disabled: true};
  const fingerprint = JSON.stringify([progress.asin || '', progress.title, progress.percentage_read ?? '', progress.location || '']);
  if (fingerprint === config.kindleLastFingerprint) return {ok: true, unchanged: true};
  try {
    const response = await fetch(new URL('api/sensors/kindle/progress', normalizeAppUrl(config.appUrl)), {
      method: 'POST',
      headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CaptainsLog-Key': config.pairingKey},
      body: JSON.stringify({...progress, observed_at: new Date().toISOString(), client_id: config.clientId})
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(body.message || `Captain's Log returned ${response.status}.`);
    await setKindleStatus('connected', {
      kindleLastSyncAt: new Date().toISOString(),
      kindleLastTitle: body.title || progress.title,
      kindleLastProgress: body.percentage_read ?? progress.percentage_read ?? progress.location,
      kindleLastFingerprint: fingerprint,
      kindleLastError: null
    });
    return {ok: true};
  } catch (error) {
    await setKindleStatus('error', {kindleLastError: error.message || String(error)});
    return {ok: false, error: error.message || String(error)};
  }
}

async function connectToApp() {
  const config = await settings();
  const pairingKey = randomKey();
  await chrome.storage.local.set({pairingKey, connectionStatus: 'pairing', lastError: null, lastMobileHistorySyncAt: null, mobileHistoryLastError: null});
  const pairingUrl = new URL(`sensors/browser/pair/${encodeURIComponent(pairingKey)}`, normalizeAppUrl(config.appUrl));
  await chrome.tabs.create({url: pairingUrl.toString()});
}

async function installAlarms() {
  await chrome.alarms.create(HEARTBEAT_ALARM, {periodInMinutes: 1});
  await chrome.alarms.create(MOBILE_HISTORY_ALARM, {periodInMinutes: 5});
  await chrome.alarms.create(KINDLE_CHECK_ALARM, {periodInMinutes: 60});
}

chrome.runtime.onInstalled.addListener(async details => {
  await settings();
  await installAlarms();
  if (details.reason === 'install') await chrome.runtime.openOptionsPage();
});
chrome.runtime.onStartup.addListener(async () => {
  await settings();
  await installAlarms();
  await sendActiveBrowsing();
  await syncMobileHistory();
  await syncKindleInBackground();
});
chrome.alarms.onAlarm.addListener(alarm => {
  if (alarm.name === HEARTBEAT_ALARM) sendActiveBrowsing();
  if (alarm.name === MOBILE_HISTORY_ALARM) syncMobileHistory();
  if (alarm.name === KINDLE_CHECK_ALARM) syncKindleInBackground();
  if (alarm.name.startsWith(KINDLE_SYNC_TIMEOUT_PREFIX)) {
    const tabId = Number(alarm.name.slice(KINDLE_SYNC_TIMEOUT_PREFIX.length));
    closeKindleSyncTab(tabId).then(() => setKindleStatus('error', {kindleLastError: 'Kindle opened the recent book but no reading position was detected. Use Open / resync Kindle to check the reader.'}));
  }
});
chrome.tabs.onActivated.addListener(() => sendActiveBrowsing());
chrome.tabs.onUpdated.addListener((_tabId, changeInfo, tab) => {
  if (tab.active && (changeInfo.url || changeInfo.status === 'complete')) sendActiveBrowsing();
  if (changeInfo.url && /amazon\.[^/]+\/ap\/signin/i.test(changeInfo.url)) settings().then(config => {
    if (config.kindleEnabled) setKindleStatus('expired', {kindleLastError: 'Kindle redirected to sign in. Sign in and use Resync Kindle.'});
  });
});
chrome.windows.onFocusChanged.addListener(windowId => {
  if (windowId !== chrome.windows.WINDOW_ID_NONE) sendActiveBrowsing();
});
chrome.idle.onStateChanged.addListener(state => {
  if (state === 'active') sendActiveBrowsing();
});
chrome.cookies?.onChanged.addListener(change => {
  if (change.removed && /^(at-|sess-at-)/i.test(change.cookie.name) && /amazon\./i.test(change.cookie.domain)) checkKindleSession();
});
chrome.action.onClicked.addListener(() => chrome.runtime.openOptionsPage());
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
  debugLog('info', 'runtime-message', {type: message?.type || null, sender: sender?.url || sender?.id || 'unknown'});
  let operation;
  if (message?.type === 'connect') operation = connectToApp();
  else if (message?.type === 'send-now') operation = sendActiveBrowsing();
  else if (message?.type === 'mobile-history-sync-now') operation = syncMobileHistory();
  else if (message?.type === 'mobile-history-sync-past') operation = syncMobileHistory(true);
  else if (message?.type === 'kindle-connect') operation = connectKindle();
  else if (message?.type === 'kindle-check') operation = checkKindleSession();
  else if (message?.type === 'kindle-sync-now') operation = syncKindleInBackground();
  else if (message?.type === 'kindle-progress') operation = sendKindleProgress(message.progress || {}).then(async result => {
    const sync = await chrome.storage.session.get(['kindleSyncTabId']);
    if (result?.ok && sender.tab?.id === sync.kindleSyncTabId) await closeKindleSyncTab(sender.tab.id);
    return result;
  });
  else if (message?.type === 'kindle-session-expired') operation = setKindleStatus('expired', {kindleLastError: 'Kindle is asking you to sign in. Sign in and use Resync Kindle.'});
  else return false;
  operation.then(result => sendResponse(result || {ok: true})).catch(error => sendResponse({ok: false, error: error.message}));
  return true;
});

settings().then(installAlarms);
