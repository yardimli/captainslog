# Captain's Log Browsing Sensor

1. Open `chrome://extensions` in Chrome.
2. Enable **Developer mode**.
3. Select **Load unpacked** and choose this `captainslog-chrome-extension` folder.
4. Open the extension settings. The default Captain's Log URL is `http://127.0.0.1:8016/`.
5. Select **Connect to Captain's Log** and sign in if asked. Opening the pairing URL links the extension's random secret to the signed-in user.

The extension sends only the active HTTP(S) hostname when the active tab changes and once per minute while Chrome is active. Page paths, titles, query strings, and ports never leave the extension. Captain's Log stores the complete hostname—including subdomains such as `docs.github.com` or `mail.google.com`—and elapsed time in hourly browsing blocks. A session is considered finished after three minutes without a heartbeat.

When Chrome **History and tabs** sync is enabled, the extension also scans history synchronized from other devices every five minutes. It sends only the hostname, visit time, and a one-way deduplication hash. Captain's Log groups these visits into separate **Mobile browsing** blocks by hour and counts visits per domain. Chrome identifies them only as non-local visits, so history from multiple remote devices cannot be separated.

Use **Sync past data** in the extension settings to ignore the incremental cursor and rescan the complete history range Chrome still retains. The scan pages through the available history index and the server safely ignores visits it has already imported.

The **Debug** tab contains a paginated raw-history inspector and up to 1,000 extension diagnostic messages. The history view shows each visit's full URL, timestamp, transition, and `isLocal` value locally so sync and classification problems can be diagnosed. **Copy report** copies the current status, history snapshot, and messages as JSON; full URLs in that report should be treated as private.
