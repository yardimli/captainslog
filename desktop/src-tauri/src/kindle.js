(() => {
  if (window.top !== window || !/^read\.amazon\./i.test(location.hostname) || window.__totalLogKindleInstalled) return;
  window.__totalLogKindleInstalled = true;

  const invoke = (command, args = {}) => window.__TAURI__?.core?.invoke(command, args)
    || Promise.reject(new Error('The Total Log desktop bridge is not ready.'));
  const reportStatus = (status, message = null) => invoke('kindle_status_observed', {report: {status, message}}).catch(() => {});
  const sleep = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));
  const text = element => (element?.textContent || element?.getAttribute?.('aria-label') || '').replace(/\s+/g, ' ').trim();

  function bookDetails(item) {
    const title = text(item.querySelector('[id^="title-"] p, [id^="title-"]'))
      || text(item.querySelector('[id^="coverContainer-"] [aria-hidden="true"]'))
      || text(item.querySelector('img[alt]'));
    const author = text(item.querySelector('[id^="author-"] p, [id^="author-"]')) || null;
    const asin = item.id?.match(/^library-item-option-([A-Z0-9]+)$/i)?.[1]?.toUpperCase()
      || item.querySelector('[id^="title-"]')?.id?.match(/^title-([A-Z0-9]+)$/i)?.[1]?.toUpperCase()
      || null;
    return {title, author, asin};
  }

  async function captureFirstLibraryBook() {
    await reportStatus('syncing', 'Waiting for the first book in your Kindle library…');
    for (let attempt = 0; attempt < 40; attempt += 1) {
      const item = document.querySelector('li[role="listitem"]');
      if (item) {
        const book = bookDetails(item);
        if (book.title) {
          await invoke('kindle_book_observed', {book});
          return;
        }
      }
      await sleep(500);
    }
    await reportStatus('error', 'The Kindle library loaded, but no book title was found in its first list item.');
  }

  setTimeout(() => {
    invoke('kindle_manual_sync_ready').then(requested => {
      if (requested && /kindle-library/i.test(location.pathname)) captureFirstLibraryBook();
    }).catch(() => {});
  }, 500);
})();
