# maimai Queue List

A web-based queue manager for the **maimai-gw-queue** arcade. Players can join and manage the live queue, while the app tracks the two cabinets and the current turn.

The app includes a player terminal, a live display for the arcade, and an operator panel for managing the queue, play modes, operating hours, and timers. Optional location checking can restrict queue registration to players near the venue.

## Website

[Open the maimai Queue List](https://mdx-gw-queue.freehosting.dev/)

## Love Letter

The player terminal links to a multi-device implementation of the current 21-card Love Letter edition. It supports 2–6 players, the full character effects and favor scoring, private hands scoped to each player device, and encrypted server-side saved sessions. A creator recovery key lets the session creator resume or delete their game; an authenticated admin can delete any saved session from the admin page.

### Local multiplayer test

Install PHP and Git, clone the repository, and run the PHP development server from the repository directory:

```sh
php -S 0.0.0.0:8000 -t .
```

On the same computer, open `http://127.0.0.1:8000/?view=love-letter`. To test across devices, connect both devices to the same trusted Wi-Fi network, find the computer’s LAN address with `hostname -I`, then open `http://<LAN-IP>:8000/?view=love-letter` on each device. Create a two-player session on the first device, share its 8-character invite code, and join from the second. The creator starts once both seats have joined. Stop the local server with Ctrl+C. The PHP development server is for local testing only; do not expose it to the public internet.

Runtime state is written to `arcade_state.json`, `arcade_config.json`, `.arcade_secret.key`, and `love_letter_sessions.json`. These files are ignored by Git and should remain private. The session creator should save the displayed recovery key somewhere safe.

Made by Rikuven.
