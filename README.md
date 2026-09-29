# VedDrive

<p align="center">
  <img src="assets/logo.svg" alt="VedDrive logo" width="88" height="88">
</p>

<h3 align="center">Your space. Your pace. Everywhere.</h3>

<p align="center">
  A personal cloud drive for keeping files, folders, and memories together — with a clean interface that works across devices.
</p>

<p align="center">
  <a href="https://veddrive.vedhant.in">Visit VedDrive</a>
  ·
  <a href="https://github.com/vedhant-khajuria">GitHub</a>
  ·
  <a href="mailto:hello@vedhant.in">Contact</a>
</p>

---

## At a glance

| | |
|---|---|
| **Frontend** | HTML, CSS, and vanilla JavaScript |
| **Backend** | PHP 8+ |
| **Database** | MySQL / MariaDB |
| **File storage** | Encrypted, chunked uploads sent to a configured Telegram channel |
| **Deployment** | Apache hosting, including cPanel |

## Features

- **A drive that feels at home.** Responsive interface with overview, grid and list views, and a mobile-friendly navigation.
- **Keep files organized.** Create folders, search and filter your library, sort files, and mark favorites.
- **Move at your own pace.** Upload multiple files with progress, then download, restore, or move files to Trash.
- **Your account, your settings.** Sign in with email and password, or enable Google sign-in. Update your profile and password in Settings.
- **Room to share.** Optional registration, per-user storage quotas, account controls, and an administrator console.
- **Chunked storage.** Files are split into configurable chunks for storage and streamed back through the app for download.

## How storage works

VedDrive keeps file and folder metadata in MySQL. File contents are encrypted and uploaded in chunks to the Telegram channel configured for the deployment. When a user downloads a file, VedDrive streams its chunks back through `download.php` and reassembles the original file.

The deployment needs a Telegram bot and a channel where that bot can post. PHP's upload and server limits must be at least as large as the configured chunk size. The default configuration uses 8 MiB chunks and allows files up to 2 GiB; hosting and Telegram limits may require different values.

## Deploy

VedDrive is designed for PHP hosting with MySQL or MariaDB and Apache. For the full cPanel walkthrough, see **[SETUP.md](SETUP.md)**.

1. Create a database and import [`schema.sql`](schema.sql).
2. On the server, copy `private/config.example.php` to `private/config.php` and set the database, Telegram bot and channel, and setup key values.
3. Keep `private/` outside the public document root when your hosting layout allows it. The included `.htaccess` also blocks direct web access to that directory.
4. Optionally set a Google OAuth client ID and configure the authorized JavaScript origin in Google Cloud Console.
5. Visit `setup.php` on the deployed site, provide the setup key, and create the administrator password. Remove `setup.php` after setup.

For a GitHub copy, use the example configuration only. Add real credentials to `private/config.php` on the server; never commit that file or publish bot tokens, setup keys, or OAuth secrets.

## Project layout

```text
├── index.php             Application entry point
├── index.html            Redirect for static-host fallbacks
├── app.js                Client-side interactions and uploads
├── style.css             Responsive visual design
├── api.php               Authentication, library, upload, and admin API
├── download.php          Chunked file download endpoint
├── schema.sql            MySQL database schema
├── setup.php             First-run administrator setup
├── private/
│   ├── bootstrap.php     Shared server bootstrap
│   └── config.example.php Safe configuration template
├── assets/               Logo and static assets
└── SETUP.md              Detailed deployment instructions
```

## Security notes

- `private/config.php` contains deployment secrets and should never be committed.
- `setup.php` is for first-time setup; delete it from the server after creating the administrator account.
- Keep the Telegram bot and storage channel private and restrict access to trusted administrators.
- Use HTTPS for sign-in, uploads, and downloads.

## Credits

Designed and built by **Vedhant Khajuria**.

