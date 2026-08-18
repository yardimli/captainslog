# Captain's Log Browsing Sensor

1. Open `chrome://extensions` in Chrome.
2. Enable **Developer mode**.
3. Select **Load unpacked** and choose this `captainslog-chrome-extension` folder.
4. Open the extension settings. The default Captain's Log URL is `http://127.0.0.1:8016/`.
5. Select **Connect to Captain's Log** and sign in if asked. Opening the pairing URL links the extension's random secret to the signed-in user.

The extension sends only the active HTTP(S) hostname when the active tab changes and once per minute while Chrome is active. Page paths, titles, query strings, and ports never leave the extension. Captain's Log stores the complete hostname—including subdomains such as `docs.github.com` or `mail.google.com`—and elapsed time in hourly browsing blocks. A session is considered finished after three minutes without a heartbeat.
