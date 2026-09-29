# VedDrive deployment

## cPanel

1. Create a MySQL database and user in cPanel. Give the user **All Privileges**.
2. Open phpMyAdmin, select the new database, and import `schema.sql`.
3. Upload the cPanel ZIP into the `veddrive.vedhant.in` document root and extract it. Keep `private/` one level below the document root; its `.htaccess` blocks web access.
4. Edit `private/config.php`: replace `CPANEL_DATABASE`, `CPANEL_DB_USER`, `CHANGE_ME`, and the database host if your host uses a custom value. Set `google_client_id` to your Google OAuth web client ID to enable Google sign-in. In Google Cloud Console, allow your exact site origin under Authorized JavaScript origins. Keep OAuth and bot credentials private.
5. Visit `https://veddrive.vedhant.in/setup.php`. Enter the `setup_key` shown in `private/config.php` and choose an administrator password.
6. Delete `setup.php` in cPanel File Manager immediately after setup.
7. Sign in with `hello@vedhant.in`. Use Admin console to block members, set quotas, and pause registrations.

## GitHub

Upload the GitHub ZIP to a private repository. It intentionally contains a safe `private/config.example.php`, not your working credentials. Copy it to `private/config.php` only on the server, then fill in the cPanel database details, Telegram values, and Google client ID. Do not commit `private/config.php`.

## Notes

VedDrive stores file metadata in MySQL and sends encrypted upload chunks to the configured Telegram channel. Downloads stream chunks through `download.php`, so recovery no longer depends on a browser folder. The Bot API still imposes its own transport limits; the app's chunking allows large files to be stored in parts, while the configured PHP upload and server limits must be at least `chunk_bytes`.
