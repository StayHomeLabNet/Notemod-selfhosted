# Notemod-selfhosted (Modified Notemod) v1.4.1

This is a fork of **[Notemod (original)](https://github.com/orayemre/Notemod)** (MIT License), extended into a **self-hosted note platform that can run on shared hosting** with **no database required**.
Because it does not use a database, you can upload it to your web server, prepare the configuration files, and start using it right away.

Verified on shared hosting: Xserver, Sakura Internet, XREA, InfinityFree  
Tested PHP: 8.3.21 (**PHP 8.1 or later is required**)

> **Single source of truth:** `notemod-data/<USER_NAME>/data.json`

---

## Development Goal

To make clipboard sharing between **iPhone and Windows PC** feel a little closer to the convenience of **iPhone and Mac**, without relying on external services.

- Text copied on a Windows PC is sent immediately to Notemod-selfhosted (**Windows app: ClipboardSync**)
- Running an iPhone Shortcut lets you paste that text on the iPhone
- Text copied on the iPhone can be sent immediately to Notemod-selfhosted by running an iPhone Shortcut
- On Windows PC, running a hotkey (for example, `Ctrl + Alt + R`) lets you paste it immediately (**Windows app: ClipboardSync**)

---

## Main Changes in v1.4.1

### 1) `data.json` encryption support
- `data.json` can now be saved in encrypted form using **AES-256-CBC + HMAC**
- Encryption can be turned on or off from the **Web UI in `setup_auth.php`**
- The encryption key is stored in **`config/<USER_NAME>/config.php`**
- The actual value of **`DATA_ENCRYPTION_KEY` is never shown in the UI**
- Right before switching the encryption setting, **one automatic backup of the current `data.json`** is created
- If format conversion fails, **the setting is not changed and the system safely rolls back**
- Export always remains **plain JSON**

### 2) Encryption support applied across save, load, and backup flows
- Added `data_crypto.php` to centralize plain/encrypted save handling
- `notemod_sync.php`, `api/api.php`, `api/read_api.php`, `api/cleanup_api.php`, `bak_settings.php`, and related files can now handle encrypted data
- When restoring from backup, `data.json` is **saved again in the format that matches the current encryption setting**

### 3) Added a Media / Files management page
- Added `media_files.php` to organize **image and file listing / counts / upload / download / deletion flows**
- Images can be listed with thumbnails
- Files are listed using `file_index.json` first, and restored from history data when needed
- You can open it from `index.php` via the **Media & Files** button

### 4) Added an image delivery API
- Added `api/image_api.php`
- Can **serve image binaries** under the specified user
- Supports resize display by width and height, caching, and basic input validation

### 5) Reorganized around per-user directories
- Config: **`config/<DIR_USER>/`**
- Data: **`notemod-data/<DIR_USER>/`**
- Logs: **`logs/<DIR_USER>/`**
- Separates the visible account name `USERNAME` from the storage directory name `DIR_USER` for more reliable operation
- Even if the username changes, **the storage directory name does not change**

### 6) Improved “latest clipboard type” detection
- `note_latest.json` is now updated whenever text is added
- `read_api.php?action=latest_clip_type` can determine whether the latest sent item was **text / image / file**
- This works well with ClipboardSync’s **Receive Latest** feature

### 7) Backup / cleanup improvements
- Backup filenames now follow these formats depending on state:
  - Plain: `data.json.bak-YYYYMMDD-HHMMSS`
  - Encrypted: `data.enc.json.bak-YYYYMMDD-HHMMSS`
- `cleanup_api.php` now supports `dry_run=2` to return **only the number of items that would be deleted**
- `bak_settings.php` can create, list, restore, and delete backups

### 8) Web UI cleanup and consistency improvements
- `setup_auth.php`, `account.php`, `clipboard_sync.php`, `log_settings.php`, `bak_settings.php`, `media_files.php`, and related pages now have more consistent **JP/EN switching, Dark/Light switching, and logged-in user display**
- Added automatic `robots.txt` generation as a basic search engine guard

---

## How to Use

### 1. Upload to your server
Upload the full repository contents to your server’s public web directory (for example, `public_html/`).

> Note: `config/` and `api/` should also be placed in the same directory level as `index.php`  
> (If you change the structure, you will need to adjust PHP paths manually.)

### 2. Create configuration files (important)

#### Automatic configuration file generation
Since v1.1.0, the initial setup through the Web UI can generate the files automatically.  
In v1.4.1, they are stored per user at:

- `config/<DIR_USER>/config.php`
- `config/<DIR_USER>/config.api.php`

Edit those files for timezone, log on/off settings, automatic backup settings, and other options.

#### Common settings
Use `config.sample.php` or `config.sample.ja.php` as a reference and prepare `config/<DIR_USER>/config.php`.

Main settings include:
- `SECRET`
- `TIMEZONE`
- `DEBUG`
- `LOGGER_FILE_ENABLED`
- `LOGGER_NOTEMOD_ENABLED`
- `IP_ALERT_*`
- `DATA_ENCRYPTION_ENABLED`
- `DATA_ENCRYPTION_KEY`

#### API settings
Use `config.api.sample.php` or `config.api.sample.ja.php` as a reference and prepare `config/<DIR_USER>/config.api.php`.

Main settings include:
- `EXPECTED_TOKEN`
- `ADMIN_TOKEN`
- `DATA_JSON`
- `DEFAULT_COLOR`
- `CLEANUP_BACKUP_ENABLED`
- `CLEANUP_BACKUP_SUFFIX`
- `CLEANUP_BACKUP_KEEP`

### 3. Generate SECRET / TOKEN values
You can use a password generator website to generate your SECRET or TOKEN values, for example:  
<https://passwords-generator.org/>

- If you do not want to use a third-party site, OS-level password generation or commands like `openssl rand -hex 32` are also fine
- `DATA_ENCRYPTION_KEY` can normally be generated automatically from `setup_auth.php`

### 4. Initialize (first run only)
Open your public URL and Notemod-selfhosted will start.  
Set the display language and create the first category.

During this process, the following files will be created automatically if missing (depending on environment and settings):

- `config/<DIR_USER>/config.php`
- `config/<DIR_USER>/config.api.php`
- `notemod-data/<DIR_USER>/data.json`
- `notemod-data/<DIR_USER>/.htaccess`
- (Depending on settings) `logs/<DIR_USER>/` and `logs/<DIR_USER>/.htaccess`
- `api/.htaccess`
- `robots.txt`

---

## Security (Important)

### If Basic Authentication is available, it is strongly recommended for `api/`
Notemod-selfhosted handles personal information such as clipboard contents and private notes.  
Even if you place `.htaccess` and `robots.txt`, once it is on a public server, it is **strongly recommended** to protect at least the `api/` directory with **Basic Authentication**.

**Why this matters**
- `robots.txt` is only a request to search engines, not access control
- If `/api/` is public, it can be called from outside
- If tokens remain exposed for a long time, they may become a target for brute-force attempts
- Basic Authentication adds another barrier **before PHP token authentication**, which improves security significantly

### If Basic Authentication is not available: use Web UI authentication
Some shared hosting providers do not offer Basic Authentication.  
In that case, operate it with **Web UI authentication + token authentication**.

- Initial setup / administration: `setup_auth.php`
- While logged out: important values are masked and cannot be edited
- While logged in: account and authentication settings can be managed

### About `data.json` encryption
In v1.4.1, the stored `data.json` can be encrypted.  
However, encryption does not prevent server intrusion itself. It is an **additional layer to reduce plain-text exposure if data is leaked**.

- Prioritize **Basic Authentication / Web UI authentication / strong tokens / minimizing public exposure** first
- If you lose the encryption key, the data can no longer be decrypted, so **store it separately in a safe way**
- Export is plain JSON, so handle exported files carefully as well

---

## Usage and Integration

For concrete usage examples (API calls / iPhone Shortcuts / ClipboardSync integration, etc.), see:

- [StayHomeLab YouTube ch](https://www.youtube.com/@StayHomeLab)
- [Website](https://stayhomelab.net/notemod-selfhosted)
- [ClipboardSync](https://github.com/StayHomeLabNet/ClipboardSync)

---

## Main Features Added in This Modified Version

- **Server sync endpoint** (`notemod_sync.php`)
  - Token authentication
  - `save` / `load`
  - If `data.json` does not exist, it is generated automatically from the initial snapshot
  - Supports per-user directory structure
  - Supports reading and writing encrypted stored data

- **Write API** (`api/api.php`)
  - Add notes to any category (automatically creates the category if missing)
  - Supports image upload
  - Supports file upload
  - Updates `note_latest.json` when text is added
  - Converts received WebP images to PNG on the server side before saving

- **Read API** (`api/read_api.php`)
  - Read-only
  - `latest_note` always excludes the **Logs** category
  - `pretty=2` returns *plain text body only* (useful for iPhone Shortcuts and CLI)
  - `latest_clip_type` can determine the last sent type (text / image / file)
  - Supports reading encrypted stored data

- **Image API** (`api/image_api.php`)
  - Serves images under the specified user
  - Simple resize support by width / height
  - Cache control

- **Cleanup API** (`api/cleanup_api.php`) *(recommended to remove if unused)*
  - Delete all notes by category (POST only)
  - Supports `dry_run`
  - `dry_run=2` returns only the number of targets
  - Backup creation can be enabled or disabled by settings
  - Bulk delete backup files (`purge_bak=1`)
  - Bulk delete log files (`purge_log=1`)
  - Bulk delete images / files
  - Assists with rebuilding `file_index.json`

- **Media / Files management page** (`media_files.php`)
  - Check server-side settings
  - Show image/file counts
  - Image list (thumbnails + download)
  - File list (history / index reference)
  - Drag-and-drop upload

- **Integrated access logging** (`logger.php`)
  - File logging to `/logs/<USER_NAME>/access-YYYY-MM.log` (can be turned on/off)
  - Monthly note append into Notemod’s **Logs** category (can be turned on/off)
  - Timezone is read from config
  - Creates the log folder and `.htaccess` if missing

- **Added copy / paste buttons to the toolbar** (`index.php`)
  - Disables the original copy and paste buttons from Notemod (sag-tik)
  - If nothing is selected, copies the whole note; if text is selected, copies only the selected range

- **Web UI authentication**
  - Allows login-based operation without Basic Authentication
  - Adds a settings icon (gear) in the UI for account / authentication pages

- **PWA support**
  - Can be launched like an app from “Add to Home Screen” on iPhone / Android (HTTPS recommended and practically required)

---

## Requirements

- **PHP 8.1 or later**
- Apache recommended (because `.htaccess` is used)
- Writable locations for PHP:
  - `config/`
  - `notemod-data/`
  - (Optional) `logs/`

---

## Recommended Directory Structure

```text
public_html/
├─ index.php
├─ notemod_sync.php
├─ logger.php
├─ auth_common.php
├─ data_crypto.php
├─ setup_auth.php
├─ login.php
├─ logout.php
├─ account.php
├─ bak_settings.php
├─ clipboard_sync.php
├─ log_settings.php
├─ media_files.php
├─ manifest.php
├─ sw-register.js
├─ sw.php
├─ service-worker.js
├─ api/
│  ├─ api.php
│  ├─ read_api.php
│  ├─ image_api.php
│  └─ cleanup_api.php
├─ config/
│  └─ <DIR_USER>/
│     ├─ config.php
│     └─ config.api.php
├─ notemod-data/
│  └─ <DIR_USER>/
│     ├─ data.json
│     ├─ note_latest.json
│     ├─ image_latest.json
│     ├─ file_latest.json
│     ├─ file.json
│     ├─ file_index.json
│     ├─ images/
│     └─ files/
├─ logs/
│  └─ <DIR_USER>/
│     └─ access-YYYY-MM.log
└─ pwa/
   ├─ icon-192.png
   └─ icon-512.png
```

---

## Example Configuration Files

### `config/<DIR_USER>/config.php`

```php
<?php
return [
    'TIMEZONE' => 'Asia/Tokyo',
    'DEBUG' => false,
    'LOGGER_FILE_ENABLED' => true,
    'LOGGER_NOTEMOD_ENABLED' => true,
    'IP_ALERT_ENABLED' => true,
    'IP_ALERT_TO' => 'YOUR_EMAIL',
    'IP_ALERT_FROM' => 'notemod@localhost',
    'IP_ALERT_SUBJECT' => 'Notemod: First-time IP access',
    'IP_ALERT_IGNORE_BOTS' => true,
    'IP_ALERT_IGNORE_IPS' => [''],
    'LOGGER_FILE_MAX_LINES' => 500,
    'LOGGER_NOTEMOD_MAX_LINES' => 50,
    'SECRET' => 'CHANGE_ME_SECRET',
    'DATA_ENCRYPTION_ENABLED' => false,
    'DATA_ENCRYPTION_KEY' => 'CHANGE_ME_ENCRYPTION_KEY',
];
```

### `config/<DIR_USER>/config.api.php`

```php
<?php
return [
    'EXPECTED_TOKEN' => 'CHANGE_ME_EXPECTED_TOKEN',
    'ADMIN_TOKEN' => 'CHANGE_ME_ADMIN_TOKEN',
    'DATA_JSON' => dirname(__DIR__, 2) . '/notemod-data/<DIR_USER>/data.json',
    'DEFAULT_COLOR' => '3478bd',
    'CLEANUP_BACKUP_ENABLED' => true,
    'CLEANUP_BACKUP_SUFFIX' => '.bak-',
    'CLEANUP_BACKUP_KEEP' => 10,
];
```

---

## API Examples

### Add a note (GET/POST)

```text
/api/api.php?token=EXPECTED_TOKEN&user=YOUR_DIR_USER&category=INBOX&text=Hello
```

### Get the latest note (Logs excluded, body only)

```text
/api/read_api.php?token=EXPECTED_TOKEN&user=YOUR_DIR_USER&action=latest_note&pretty=2
```

### Get the latest clipboard type

```text
/api/read_api.php?token=EXPECTED_TOKEN&user=YOUR_DIR_USER&action=latest_clip_type
```

### Get an image

```text
/api/image_api.php?user=YOUR_DIR_USER&file=photo.png&w=300
```

### Bulk delete log files (POST)

```bash
curl -X POST "https://USER:PASS@YOUR_SITE/api/cleanup_api.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "user=YOUR_DIR_USER" \
  --data-urlencode "token=ADMIN_TOKEN" \
  --data-urlencode "purge_log=1" \
  --data-urlencode "confirm=YES"
```

### Bulk delete backup files (POST)

```bash
curl -X POST "https://USER:PASS@YOUR_SITE/api/cleanup_api.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "user=YOUR_DIR_USER" \
  --data-urlencode "token=ADMIN_TOKEN" \
  --data-urlencode "purge_bak=1" \
  --data-urlencode "confirm=YES"
```

---

## ClipboardSync (Windows App) Integration

This modified version is designed to support input from external clients (optional).

<https://github.com/StayHomeLabNet/ClipboardSync>

Typical flow:
1. ClipboardSync monitors the clipboard
2. While enabled, it sends clipboard contents to `api/api.php`
3. By using `read_api.php?action=latest_clip_type`, it can receive the right item type based on what was last sent
4. The result is reflected immediately in the Notemod UI or via a receive hotkey

---

## About `robots.txt` (Search Engine Protection)

Recommended (block all crawlers):

```text
User-agent: *
Disallow: /
```

Note:
- `robots.txt` is not access control  
  → Protect it with **Basic Authentication** or **Web UI authentication**, `.htaccess`, etc.

---

## License

MIT License.  
This project is based on **Notemod (MIT) by Oray Emre Gündüz**.  
Under the MIT license terms, please **keep the copyright notice and license text**.

---

## Credits

- Notemod (original): <https://github.com/orayemre/Notemod>
