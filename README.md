# Notemod-selfhosted (v1.2.3)

A self-hosted, API-driven fork of **[Notemod](https://github.com/orayemre/Notemod)** (MIT License), designed to run on simple shared hosting (no DB required) and integrate with external tools (iPhone Shortcuts, Windows apps, scripts, etc.).

Upload the repository to your web server and set a secret key + tokens in two config files to start using it.

Tested on shared hosts: InfinityFree, Xserver, SAKURA Internet, XREA  
Tested with PHP 8.3.21 (and **requires PHP 8.1+**)

> **Single source of truth:** `notemod-data/data.json`

---

## Purpose

Make clipboard synchronization between an iPhone and a Windows PC as seamless as possible—close to the comfort level between an iPhone and a Mac—**without relying on external services**.

- Text copied on Windows is instantly sent to Notemod-selfhosted (**via ClipboardSync**)
- Running an iPhone Shortcut fetches that text so it can be pasted on iPhone
- Text copied on the iPhone is instantly sent to Notemod-selfhosted when an iPhone shortcut is run
- On a Windows PC, pressing a hotkey (e.g., Ctrl + Shift + R) allows you to paste it immediately (**via ClipboardSync**)

---
## Highlights in v1.2.3

### 1) Clipboard Sync added to the Settings (gear) menu
- Added **Clipboard Sync** entry to the Settings (gear) menu
- **ClipboardSync (Windows app) + iPhone Shortcuts setup has been greatly simplified**
  - ClipboardSync app configuration is now mostly **copy & paste**
  - iPhone Shortcut can be **downloaded** (no need to build it from scratch)
  - Initial Shortcut setup is also mostly **copy & paste**

### 2) Backup settings enhanced (Restore + “Backup now”)
- In the Settings (gear) → backup settings page (`bak_settings.php`), added:
  - **Restore feature**
  - **“Create backup now” button**
    - Copies the current `notemod-data/data.json` and saves it as a backup file (timestamped filename)

### 3) Navigation links placed at both top and bottom on settings pages
- On settings-related pages, common navigation links (e.g., “Back”, etc.) are placed **both at the top and bottom** to improve usability.

### 4) Settings pages inherit Notemod’s language setting
- Custom settings pages support **Japanese / English only**
- Since Notemod itself supports additional languages, the settings pages now **inherit the Notemod language setting**
  - If `selectedLanguage === "JA"` → Japanese
  - Otherwise (including non-JA/non-EN languages) → **English**
- Implemented using **localStorage** to keep it lightweight

### 5) TIMEZONE applied to backups as well
- Previously, `TIMEZONE` in `config/config.php` was applied mainly to logs
- Now the same **TIMEZONE** is applied to:
  - **backup filename timestamps**
  - **backup date/time display**
- This ensures backup timestamps follow the configured timezone rather than the server’s default timezone

### 6) Log line limit (up to x lines)
- Added an option to cap:
  - **monthly raw logs**
  - **Notemod Logs note**
  to a maximum of **x lines**, preventing logs from growing indefinitely

### 7) `read_api.php` performance improvement
- Improved the processing in `api/read_api.php` to speed up read operations.

## What’s new in v1.1.6

- Added log settings and backup settings to the Web UI.
- Added an email notification feature for first-time IP access (a notification is sent by email when access is made from a new IP address).
- With this update, **config.php** and **config.api.php** can now be changed through the Web UI.
  - For modifications that may require changing source code, such as altering data file paths, please edit **config.php** and **config.api.php** directly.
- Add backup settings and log settings to the settings icon (gear).

## What’s new in v1.1.0

- **Web UI Initial setup** added
- **Web UI Authentication** (for servers where Basic Auth cannot be used)
- **Settings (gear) icon** added to the Notemod UI (links to Account/Auth pages)
- Added support for automatic creation of **config.php** and **config.api.php** by `setup_auth.php`
- `cleanup_api.php` supports **bulk delete of log files** and **backup files**
- **PHP 8.1+ required**
- **PWA support** (Add to Home Screen / app-like usage)

---

## Quick Start

### 1) Deploy to your server
Upload the repository into your public web directory (e.g. `public_html/`).

> Keep `config/` and `api/` at the same level as `index.php`.  
> If you change the directory structure, you must update PHP paths accordingly.

### 2) Create configuration files (IMPORTANT)

#### Configuration files auto-generation
In v1.1.0, it is automatically generated during the initial setup in the Web UI.
To configure the time zone, enable or disable logging, and enable or disable automatic creation of backup files, please edit the configuration file below.

#### Common settings (config.php)
Rename `config/config.sample.php` to `config/config.php`, then configure:
- Replace `CHANGE_ME_SECRET` with a long random string (16+ characters recommended)
- Set `TIMEZONE` if needed

#### API settings (config.api.php)
Rename `config/config.api.sample.php` to `config/config.api.php`, then configure:
- Replace `CHANGE_ME_EXPECTED_TOKEN` with your token
- Replace `CHANGE_ME_ADMIN_TOKEN` with a stronger token (recommended) used for cleanup operations

### 3) Generate SECRET / TOKEN values
You can use a password generator website, for example:
https://passwords-generator.org/

If you prefer not to rely on third-party sites, generate locally, e.g.:
- `openssl rand -hex 32`

> Note: The term “SECRET” may be deprecated in the future.

### 4) First-time initialization (one time only)
Open your site in a browser to launch **Notemod-selfhosted**.  
Set the display language and create the first category.

This will auto-generate the following if missing:
- `notemod-data/data.json` (initial snapshot)
- `notemod-data/.htaccess` (blocks direct access)
- (optional, depending on config) `logs/` and `logs/.htaccess`
- `api/.htaccess` (by default API access is allowed; authentication is recommended)

![](Notemod-selfhosted.png)

---

## Security (IMPORTANT)

### Basic Auth is strongly recommended (when available)
Notemod-selfhosted handles personal information such as “clipboard contents” and “personal notes.”
Even if you have `.htaccess` or `robots.txt` files in place, since the server is publicly accessible, it is strongly recommended to implement Basic Authentication for at least the `api/` directory.

**Why**
- `robots.txt` is not access control (only a request to crawlers)
- `/api/` endpoints are reachable from the internet on a public server
- Long-lived tokens can be targeted (brute force, etc.)
- Basic Auth adds a strong outer layer before PHP token checks

### If Basic Auth cannot be used: Web UI Authentication (v1.1.0)
Some shared hosts do not provide Basic Auth.  
In that case, enable **Web UI Authentication** and operate with **Web UI auth + API tokens**.

- Setup/management page: `setup_auth.php`
- Not logged in: sensitive values are masked / not editable
- Logged in: account/auth management available

---

## Integrations / Guides
Detailed usage (API calls / iPhone Shortcuts / ClipboardSender, etc.):

- [StayHomeLab YouTube ch](https://www.youtube.com/@StayHomeLab)  
- [Website](https://stayhomelab.net/notemod-selfhosted)  
- [ClipboardSender](https://github.com/StayHomeLabNet/ClipboardSender)

---

## What this project adds (summary)

- **Server-side sync endpoint** (`notemod_sync.php`)
  - Token auth, `save` / `load`
  - Initializes `data.json` from an initial snapshot when missing
  - Auto-creates protective `.htaccess` in `notemod-data/` and `config/`
  - Creates `api/.htaccess` if it doesn’t exist
- **Write API** (`api/api.php`)
  - Add notes into any category (auto-creates category if missing)
- **Read API** (`api/read_api.php`)
  - Read-only access
  - `latest_note` always excludes the **Logs** category
  - `pretty=2` returns *plain text* for Shortcuts / CLI usage
- **Cleanup API** (`api/cleanup_api.php`) 
  - Destructive delete-by-category (POST only)
  - `dry_run` support
  - Optional backup creation (configurable)
  - **Bulk delete backup files** (`purge_bak=1`) (v1.1.0)
  - **Bulk delete log files** (`purge_log=1`) (v1.1.0)
- **Access logger** (`logger.php`)
  - Optional file log to `/logs*/access-YYYY-MM.log`
  - Optional Notemod **Logs** category logging (monthly note)
  - Timezone from config
  - Auto-creates log folder + `.htaccess` when enabled
- **Toolbar additions** (`index.php`)
  - Custom Copy / Paste buttons (disables original sag-tik buttons)
  - Copy behavior: copy selection if selected, otherwise copy full note
- **Web UI Authentication** (v1.1.0)
  - Login-protect Notemod without Basic Auth
  - Adds **settings gear icon** in UI
- **PWA support** (v1.1.0)
  - Add to Home Screen on iPhone/Android (HTTPS recommended/required)

---

## Requirements

- **PHP 8.1+ required**
- Apache recommended (for `.htaccess`)
- Writable directories (by PHP):
  - `notemod-data/`
  - (optional) `logs/` or your configured log directory

---

## Directory layout (recommended)

```text
public_html/
├─ index.php
├─ notemod_sync.php
├─ logger.php
├─ auth_common.php
├─ setup_auth.php
├─ login.php
├─ logout.php
├─ account.php
├─ bak_settings.php
├─ clipboard_sync.php
├─ log_settings.php
├─ api/
│  ├─ api.php
│  ├─ read_api.php
│  └─ cleanup_api.php
├─ config/
│  ├─ config.php             # Create on server (secrets/config). Do not commit to Git.
│  ├─ config.sample.php      # Sample
│  ├─ config.api.php         # Create on server (tokens). Do not commit to Git.
│  └─ config.api.sample.php  # Sample
├─ notemod-data/
│  └─ data.json              # Single source of truth
└─ pwa/
   └─ (PWA-related files)
```

---

## Configuration

Create these files on the server (not committed):

### `config/config.php`

```php
<?php
return [
    'SECRET' => 'CHANGE_ME_SECRET',
    'TIMEZONE' => 'Asia/Tokyo',
    'DEBUG' => false,

    'LOGGER_FILE_ENABLED' => true,
    'LOGGER_NOTEMOD_ENABLED' => true,

    // 'LOGGER_LOGS_DIRNAME' => 'logs',

    'INITIAL_SNAPSHOT' => '{"categories":null,"hasSelectedLanguage":null,"notes":null,"selectedLanguage":null}',
];
```

### `config/config.api.php`

```php
<?php
return [
    'EXPECTED_TOKEN' => 'CHANGE_ME_EXPECTED_TOKEN',
    'ADMIN_TOKEN'    => 'CHANGE_ME_ADMIN_TOKEN',

    'DATA_JSON' => dirname(__DIR__) . '/notemod-data/data.json',
    'DEFAULT_COLOR' => '3478bd',

    'CLEANUP_BACKUP_ENABLED' => true,
    'CLEANUP_BACKUP_SUFFIX'  => '.bak-',
];
```

---

## API usage examples

### Add a note (GET or POST)
```text
/api/api.php?token=EXPECTED_TOKEN&category=INBOX&text=Hello
```

### Get latest note (excluding Logs) — plain text
```text
/api/read_api.php?token=EXPECTED_TOKEN&action=latest_note&pretty=2
```

### Cleanup (danger)
- POST only
- `dry_run=1` to preview
- `confirm=YES` required when not dry_run

#### Purge log files (POST) — v1.1.0
```bash
curl -X POST "https://USER:PASS@YOUR_SITE/api/cleanup_api.php"   -H "Content-Type: application/x-www-form-urlencoded"   --data-urlencode "token=ADMIN_TOKEN"   --data-urlencode "purge_log=1"   --data-urlencode "confirm=YES"
```

#### Purge backup files (POST) — v1.1.0
```bash
curl -X POST "https://USER:PASS@YOUR_SITE/api/cleanup_api.php"   -H "Content-Type: application/x-www-form-urlencoded"   --data-urlencode "token=ADMIN_TOKEN"   --data-urlencode "purge_bak=1"   --data-urlencode "confirm=YES"
```

---

## ClipboardSender (Windows app) integration

This fork is designed to accept notes from external clients (optional):

https://github.com/StayHomeLabNet/ClipboardSender  
![](ClipboardSender.png)

Typical flow:
1. ClipboardSender watches your clipboard
2. When enabled, it sends clipboard text to `api/api.php`
3. Notemod UI updates immediately

Recommended payload:
- `token`
- `category` (e.g. `INBOX` or `CLIPBOARD`)
- `title` (optional)
- `text` (clipboard content)

---

## About `robots.txt`

This repository includes `robots.txt` to discourage indexing.

**Recommended**
```text
User-agent: *
Disallow: /
```

**Important**
- `robots.txt` is not access control  
  → Use **Basic Auth** and/or **Web UI Authentication** and/or `.htaccess` rules.

---

## License

MIT License.

This project is based on **Notemod** by **Oray Emre Gündüz** (MIT).  
Keep the upstream copyright notice and the MIT license text.

---

## Credits

- Original Notemod: https://github.com/orayemre/Notemod
