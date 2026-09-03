(() => {
  let timer = null;
  let lastFingerprint = '';

  const visible = element => {
    const style = getComputedStyle(element);
    const rect = element.getBoundingClientRect();
    return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
  };
  const cleanTitle = value => (value || '').replace(/\s*[|–—-]\s*(Kindle Cloud Reader|Kindle)$/i, '').replace(/^Kindle Cloud Reader\s*[|–—-]\s*/i, '').trim();
  const firstText = selectors => {
    for (const selector of selectors) {
      const element = document.querySelector(selector);
      const value = element?.getAttribute('content') || element?.textContent || element?.getAttribute('aria-label');
      if (value?.trim() && (!element.getBoundingClientRect || visible(element))) return value.trim();
    }
    return '';
  };
  const pageText = () => (document.body?.innerText || '').slice(0, 150000);

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

  async function inspect() {
    const {kindleEnabled} = await chrome.storage.local.get(['kindleEnabled']);
    if (!kindleEnabled) return;
    const text = pageText();
    if (/sign\s*in\s*(?:to|with).*amazon|amazon\s+sign-in/i.test(text) && !/sign\s*out/i.test(text)) {
      chrome.runtime.sendMessage({type: 'kindle-session-expired'});
      return;
    }
    const read = percentage();
    const locationText = readingLocation();
    if (read === null && !locationText) return;
    const title = cleanTitle(firstText([
      '[data-testid*="book-title" i]', '[class*="bookTitle"]', '[class*="book-title"]',
      '[aria-label^="Book title" i]', 'meta[property="og:title"]', 'h1'
    ]) || document.title);
    if (!title || /^kindle cloud reader$/i.test(title)) return;
    const author = firstText(['[data-testid*="author" i]', '[class*="author" i]', '[aria-label^="Author" i]']).replace(/^by\s+/i, '');
    const progress = {title, author: author || null, asin: asin(), percentage_read: read, location: locationText};
    const fingerprint = JSON.stringify(progress);
    if (fingerprint === lastFingerprint) return;
    lastFingerprint = fingerprint;
    chrome.runtime.sendMessage({type: 'kindle-progress', progress});
  }

  function schedule() {
    clearTimeout(timer);
    timer = setTimeout(inspect, 1200);
  }

  new MutationObserver(schedule).observe(document.documentElement, {subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: ['aria-valuenow', 'aria-label', 'title']});
  window.addEventListener('hashchange', schedule);
  window.addEventListener('popstate', schedule);
  schedule();
})();
