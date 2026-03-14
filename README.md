# Notemod-selfhosted (Modified Notemod) v1.3.0

This is a fork of **[Notemod (upstream)](https://github.com/orayemre/Notemod)** (MIT License), extended into a **self-hosted note platform that works even on shared hosting**.  
It runs **without a database** and supports not only text, but also **image** and **file** copy/paste workflows, cleanup from the management UI, backups, and Web UI authentication. It also works with the Windows Clipboard Sync app to make clipboard usage between iPhone and Windows PCs much more convenient.

Verified shared hosting environments: Xserver, Sakura Internet, XREA, InfinityFree  
Tested PHP: 8.3.21 (**PHP 8.1 or later is required**)

> **Single source of data:** `notemod-data/data.json`

---

## Highlights of this release (v1.3.0)

In v1.3.0, Notemod-selfhosted moves beyond the previous **text-centered clipboard workflow** and now supports **image and file copy/paste workflows** as well.

- Added support for **image copy/paste workflows**
- Added support for **file copy/paste workflows**
- Added a **media / file management page** (`media_files.php`)
- Added **upload / download / cleanup** support for images and files
- Added **selective deletion and bulk deletion** for images / files
- Added a **Clipboard Sync settings page**
- Added a **backup settings page** and a **log settings page**
- Continued support for **Web UI authentication** and **PWA support**

With this update, Notemod-selfhosted has become a **self-hosted environment that can handle text, images, and files together**.

---

## Development goal

The goal is to make clipboard sharing between iPhone and Windows PC feel a little closer to the comfort of clipboard sharing between iPhone and Mac,  
while doing so **without depending on external services**.

Typical use cases:

- Send text copied on a Windows PC to Notemod-selfhosted (can be automated with the Windows app ClipboardSync)
- Retrieve the latest text from an iPhone shortcut or app integration
- Save and retrieve images
- Save and retrieve PDFs and other files
- Manage media and backups from the Web admin UI
- Access from a web browser to make text and file exchange across different devices easier

---

## Main features

### 1. Self-hosted Notemod core
- Run the Notemod UI on your own server
- Data is stored in `notemod-data/data.json`
- No database required

### 2. Text write / read APIs
- Add notes via `api/api.php`
- Get the latest note via `api/read_api.php`
- `latest_note` excludes the Logs category
- `pretty=2` can return only the body text

### 3. Image / file copy-paste support
- Image upload
- General file upload
- Get the latest image / latest file
- Detect the latest clip type
- Manage images / files from the web management page

### 4. Cleanup API
- Perform destructive operations with an admin token
- Supports deletion with backup creation attached (or backup-only operation)
- Bulk deletion of log files / backup files
- Image / file cleanup functions

### 5. Web UI authentication
- Protect the UI with login-based auth even in environments where Basic auth is not available
- Protect settings pages and management pages

### 6. Backup / restore
- Change settings from `bak_settings.php`
- Create a backup immediately
- Keep the latest *n* backups and remove older ones
- Restore `data.json` from the backup list

### 7. Log settings
- Change settings from `log_settings.php`
- Enable / disable access logs
- Enable / disable saving access logs into the Notemod Logs category
- Configure maximum number of access log lines
- Enable / disable notification for access from a first-time IP address
- Configure the email address for IP access notifications

### 8. PWA support
- Supports Add to Home Screen on iPhone / Android
- Can be launched like an app

---

## Who this is for

- People who want their own private clip / note platform
- People who do not want to rely on external cloud clipboard sync services
- People who want something lightweight that runs on shared hosting
- People who want to build their own bridge between iPhone and Windows
- People who want to handle not only text, but also images and files

---

## Recommended directory structure

```text
public_html/
├─ index.php                 # Notemod UI
├─ logger.php                # Access log + append to Logs category
├─ notemod_sync.php          # Sync endpoint (save/load)
├─ setup_auth.php            # Initial Web UI setup
├─ login.php / logout.php    # Login / logout
├─ auth_common.php           # Shared auth helpers
├─ account.php               # Account / admin menu
├─ clipboard_sync.php        # Clipboard Sync settings page
├─ bak_settings.php          # Backup settings page
├─ log_settings.php          # Log settings page
├─ media_files.php           # Image / file management page
├─ api/
│  ├─ api.php                # Add notes / receive images / receive files
│  ├─ read_api.php           # Read API
│  └─ cleanup_api.php        # Destructive operations (admin)
├─ config/
│  ├─ config.php             # Shared config (do not commit)
│  └─ config.api.php         # API config (do not commit)
├─ notemod-data/
│  ├─ data.json              # Single source of data
│  └─ .htaccess
├─ pwa/
│  ├─ icon-192.png
│  └─ icon-512.png
├─ manifest.php
├─ service-worker.js / sw.php / sw-register.js
└─ robots.txt
```

---

## Usage

### 1. Upload to your server
Upload the full contents of this repository to your server's public directory (for example, `public_html/`).

> `config/` and `api/` are expected to exist at the same level as `index.php`.  
> If you change the structure, you will need to adjust the PHP paths accordingly.

### 2. Create the config files
After uploading the repository, **access `index.php` in your browser and the required files can be created automatically**.

#### Shared config
Copy or rename `config/config.sample.php` to `config/config.php`, then adjust it as needed.

Main items:
- `SECRET`
- `TIMEZONE`
- `DEBUG`
- `LOGGER_FILE_ENABLED`
- `LOGGER_NOTEMOD_ENABLED`
- `INITIAL_SNAPSHOT`

#### API config
Copy or rename `config/config.api.sample.php` to `config/config.api.php`, then adjust it as needed.

Main items:
- `EXPECTED_TOKEN`
- `ADMIN_TOKEN`
- `DATA_JSON`
- `DEFAULT_COLOR`
- `CLEANUP_BACKUP_ENABLED`
- `CLEANUP_BACKUP_SUFFIX`

### 3. Change the SECRET / TOKEN values
Do not leave the sample values as-is. Always replace them with long random values.

Example:
- `openssl rand -hex 32`

### 4. First launch
Open the public URL in your browser and proceed with the initial setup.

Depending on your environment and settings, the following files / directories may be created on first launch.

- `notemod-data/data.json`
- `notemod-data/.htaccess`
- `api/.htaccess`
- `config/config.php` / `config/config.api.php` (when using the Web UI setup)
- Log-related files (depending on settings)

---

## Security (important)

### If Basic authentication is available
At minimum, **Basic authentication is strongly recommended** for the `api/` directory.

Why:
- `robots.txt` is not access control
- `/api/` on a public server is reachable from outside
- Long-lived tokens can become brute-force targets
- Basic auth adds a wall in front of the PHP token check

### If Basic authentication is not available
Use **Web UI authentication + token-based operation**.

- Initial setup / admin: `setup_auth.php`
- After login: move to `account.php` and other settings pages

---

## Media / file management (major feature in v1.3.0)

### Management page
`media_files.php`

This page allows you to:

- View the image list
- View the file list
- Upload images by drag-and-drop
- Upload files by drag-and-drop
- Download files
- Delete selected items using checkboxes
- Select all / clear all
- Bulk delete images / bulk delete files
- Check server upload / download related settings

### Storage locations
- Images: `notemod-data/USER_NAME/images/`
- Files: `notemod-data/USER_NAME/files/`

*The exact handling of `USER_NAME` depends on the implementation and authentication state.*

### JSON management model
The image side and file side are managed a little differently.

#### Image side
- `image_latest.json`
  - Stores information about the latest image
- List display is based on scanning the folder directly
- Images are assumed to be identifiable by thumbnail

#### File side
- `file.json`
  - File history log
- `file_index.json`
  - Current file list
- `file_latest.json`
  - Latest file information

The reason `file_index.json` is used on the file side is to **display the original uploaded file names instead of only the stored server-side names**.

### About latest protection
In the current implementation of `cleanup_api.php`, cleanup operations **protect the actual latest item**.  
Because of this, a “delete all” operation may still leave the latest image or latest file in place.

---

## Backup settings

### Management page
`bak_settings.php`

Main operations:
- Enable / disable the backup function
- Configure how many backups to keep
- Create a backup immediately
- Keep the latest *n* backups and delete older ones
- Restore `data.json` from the backup list

Features:
- Automatically backs up the current `data.json` before restore
- Uses `TIMEZONE` for date/time display and file naming

---

## Log settings

### Management page
`log_settings.php`

Main settings:
- Enable / disable access logs
- Enable / disable appending logs to the Notemod Logs category
- Save log-related settings
- Enable / disable notifications for access from a first-time IP address
- Configure the email address for IP access notifications

---

## Clipboard Sync settings

### Management page
`clipboard_sync.php`

This page allows you to confirm the API URLs and tokens used to integrate with external clients such as the Windows app ClipboardSync.  
It also provides a download link for ClipboardSync and a download link for the iOS Shortcut used for iPhone integration.

Main uses:
- Configure the Windows app
- Copy URLs into an iPhone shortcut (used during initial shortcut setup)
- Confirm the `/api/` directory URL
- Confirm the URLs for `api.php`, `read_api.php`, and `cleanup_api.php`

---

## API overview

### `api/api.php`
Mainly the write-side endpoint.

Examples of supported operations:
- Add note
- Upload image
- Upload file
- Handle WebP images
- Update `image_latest.json` / `file.json` / `file_index.json` / `file_latest.json`

### `api/read_api.php`
The read-side endpoint.

Examples of supported operations:
- `latest_note`
- `latest_image`
- `latest_file`
- `latest_clip_type`

### `api/cleanup_api.php`
The admin-side destructive-operation endpoint.

Examples of supported operations:
- Delete categories
- Delete logs
- Delete backups
- Clean up images / files
- Selective deletion / bulk deletion
- Rebuild `file_index.json`
- Repair `file_latest.json`

---

## API usage examples

### Add a note
```text
/api/api.php?token=EXPECTED_TOKEN&category=INBOX&text=Hello
```

### Get the latest note (excluding Logs, body only)
```text
/api/read_api.php?token=EXPECTED_TOKEN&action=latest_note&pretty=2
```

### Get the latest image
```text
/api/read_api.php?token=EXPECTED_TOKEN&action=latest_image
```

### Get the latest file
```text
/api/read_api.php?token=EXPECTED_TOKEN&action=latest_file
```

### Get the latest clip type
```text
/api/read_api.php?token=EXPECTED_TOKEN&action=latest_clip_type
```

### Bulk delete log files (POST)
```bash
curl -X POST "https://USER:PASS@YOUR_SITE/api/cleanup_api.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "token=ADMIN_TOKEN" \
  --data-urlencode "purge_log=1" \
  --data-urlencode "confirm=YES"
```

### Bulk delete backup files (POST)
```bash
curl -X POST "https://USER:PASS@YOUR_SITE/api/cleanup_api.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "token=ADMIN_TOKEN" \
  --data-urlencode "purge_bak=1" \
  --data-urlencode "confirm=YES"
```

---

## External links

For more detailed usage examples, including API calls, iPhone Shortcuts, and Windows app integration, see:

- [StayHomeLab YouTube ch](https://www.youtube.com/@StayHomeLab)
- [Website](https://stayhomelab.net/notemod-selfhosted)
- [ClipboardSender](https://github.com/StayHomeLabNet/ClipboardSender)

---

## Requirements

- **PHP 8.1 or later**
- Apache recommended (because `.htaccess` is used)
- Writable locations from PHP
  - `notemod-data/`
  - `config/` (during initial setup)
  - `logs/` or the configured log directory (optional)

---

## About `robots.txt`

Recommended:

```text
User-agent: *
Disallow: /
```

Notes:
- `robots.txt` is not access control
- Use protection such as Basic auth / Web UI auth / `.htaccess` together with it

---

## Role split between the README and the web manual

- **README**: Overview of the whole project, installation, and guidance to the main features
- **Web manual**: How to use each page, settings details, operational procedures, and checkpoints for troubleshooting

Rather than trying to explain every operation inside the README alone, it is recommended to keep detailed operating instructions in the web manual.

---

## License

MIT License.

This project is based on **Notemod (MIT) by Oray Emre Gündüz**.  
As required by the MIT License, **keep the copyright notice and license text**.

---

## Credits

- Notemod (upstream): https://github.com/orayemre/Notemod
