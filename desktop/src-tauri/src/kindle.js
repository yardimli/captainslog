(() => {
  if (!/^read\.amazon\./i.test(location.hostname) || window.__totalLogKindleInstalled) return;
  window.__totalLogKindleInstalled = true;
  let timer = null;

  const invoke = (command, args) => window.__TAURI__?.core?.invoke(command, args).catch(() => {});
  const reportStatus = (status, message = null) => invoke('kindle_status_observed', {report: {status, message}});
  const reportProgress = progress => invoke('kindle_progress_observed', {progress});
  const visible = element => {
    const style = getComputedStyle(element);
    const rect = element.getBoundingClientRect();
    return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
  };
  const pageText = () => (document.body?.innerText || '').slice(0, 150000);
  const firstText = selectors => {
    for (const selector of selectors) {
      const element = document.querySelector(selector);
      const value = element?.getAttribute('content') || element?.textContent || element?.getAttribute('aria-label');
      if (value?.trim() && (!element.getBoundingClientRect || visible(element))) return value.trim();
    }
    return '';
  };
  const cleanTitle = value => (value || '').replace(/\s*[|–—-]\s*(Kindle Cloud Reader|Kindle)$/i, '').replace(/^Kindle Cloud Reader\s*[|–—-]\s*/i, '').trim();

  function percentage() {
    const candidates = [...document.querySelectorAll('[role="progressbar"], [aria-valuenow], [aria-label*="%"], [title*="%"], [class*="progress" i]')].filter(visible);
    for (const element of candidates) {
      const explicit = element.getAttribute('aria-valuenow');
      const text = `${element.getAttribute('aria-label') || ''} ${element.getAttribute('title') || ''} ${element.textContent || ''}`;
      const match = text.match(/(?:progress|read|complete)?\s*[:：]?\s*(100|\d{1,2})(?:\.\d+)?\s*%/i);
      const value = explicit ?? match?.[1];
      if (value !== null && value !== undefined && Number(value) >= 0 && Number(value) <= 100) return Number(value);
    }
    const match = pageText().match(/(?:progress|read|complete)\s*[:：]?\s*(100|\d{1,2})(?:\.\d+)?\s*%/i);
    return match ? Number(match[1]) : null;
  }

  function readingLocation() {
    const match = pageText().match(/(?:location|page)\s+([\d,]+)(?:\s+of\s+[\d,]+)?/i);
    return match ? match[0].replace(/\s+/g, ' ').trim() : null;
  }

  function asin() {
    const source = `${location.href} ${[...document.querySelectorAll('a[href]')].slice(0, 100).map(link => link.href).join(' ')}`;
    return source.match(/(?:\/dp\/|[?&](?:asin|bookAsin)=)([A-Z0-9]{10})/i)?.[1]?.toUpperCase() || null;
  }

  async function inspectReader() {
    const text = pageText();
    if (/sign\s*in\s*(?:to|with).*amazon|amazon\s+sign-in/i.test(text) && !/sign\s*out/i.test(text)) {
      await reportStatus('expired', 'Kindle is asking you to sign in again.');
      return false;
    }
    const read = percentage();
    const locationText = readingLocation();
    if (read === null && !locationText) return false;
    const title = cleanTitle(firstText([
      '[data-testid*="book-title" i]', '[class*="bookTitle"]', '[class*="book-title"]',
      '[aria-label^="Book title" i]', 'meta[property="og:title"]', 'h1'
    ]) || document.title);
    if (!title || /^kindle cloud reader$/i.test(title)) return false;
    const author = firstText(['[data-testid*="author" i]', '[class*="author" i]', '[aria-label^="Author" i]']).replace(/^by\s+/i, '');
    await reportProgress({title, author: author || null, asin: asin(), percentage_read: read, location: locationText});
    return true;
  }

  async function syncRecentBook() {
    try {
      await reportStatus('syncing', 'Checking the most recently read Kindle book.');
      const libraryUrl = new URL('/kindle-library/search', location.origin);
      libraryUrl.search = new URLSearchParams({libraryType: 'BOOKS', paginationToken: '0', sortType: 'recency', querySize: '50'}).toString();
      const response = await fetch(libraryUrl, {credentials: 'include', headers: {'Accept': 'application/json'}});
      const responseText = await response.text();
      if (response.status === 401 || response.status === 403 || response.redirected || /\/ap\/signin/i.test(response.url) || /^\s*</.test(responseText)) {
        await reportStatus('expired', 'Kindle sign-in expired. Open the Kindle window and sign in again.');
        return;
      }
      if (!response.ok) throw new Error(`Kindle library returned ${response.status}.`);
      const data = JSON.parse(responseText);
      const books = Array.isArray(data.itemsList) ? data.itemsList : (Array.isArray(data.items) ? data.items : []);
      const book = books[0];
      if (!book) {
        await reportStatus('ready', 'Kindle is connected, but no recent book was found.');
        return;
      }
      const libraryPercentage = Number(book.percentageRead);
      if (String(book.percentageRead ?? '').trim() !== '' && Number.isFinite(libraryPercentage) && libraryPercentage >= 0 && libraryPercentage <= 100) {
        await reportProgress({title: book.title, author: Array.isArray(book.authors) ? book.authors.join(', ') : (book.author || null), asin: book.asin || null, percentage_read: libraryPercentage, location: null});
        return;
      }
      const readerPath = book.webReaderUrl || book.readerUrl || book.readUrl;
      if (!readerPath) throw new Error('Kindle did not provide a reader URL for the recent book.');
      const readerUrl = new URL(readerPath, location.origin);
      if (readerUrl.origin !== location.origin) throw new Error('Kindle returned a reader URL on an unexpected domain.');
      location.assign(readerUrl.toString());
    } catch (error) {
      await reportStatus('error', error?.message || String(error));
    }
  }

  window.__totalLogKindleSync = syncRecentBook;
  const scheduleInspect = () => {
    clearTimeout(timer);
    timer = setTimeout(inspectReader, 1200);
  };
  new MutationObserver(scheduleInspect).observe(document.documentElement, {subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: ['aria-valuenow', 'aria-label', 'title']});
  window.addEventListener('hashchange', scheduleInspect);
  window.addEventListener('popstate', scheduleInspect);

  setTimeout(() => {
    if (/kindle-library/i.test(location.pathname)) syncRecentBook();
    else inspectReader();
  }, 1500);
})();
