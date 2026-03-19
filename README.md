# Notemod-selfhosted v1.3.1

This is a fork based on **[Notemod (upstream)](https://github.com/orayemre/Notemod)** (MIT License), expanded into a **self-hosted note platform that can run even on shared hosting**.  
It works **without a database**, and lets you handle not only text, but also **images** and **files** for copy-and-paste workflows, organization from the admin screens, backups, and Web UI authentication in one place. It also works with the Windows Clipboard Sync app, making clipboard use between iPhone and Windows PC especially convenient.

Confirmed to work on these shared hosting services: Xserver, Sakura Internet, XREA, InfinityFree  
Tested PHP version: 8.3.21 (**PHP 8.1 or later is required**)

> **Single source of truth:** `notemod-data/data.json`

---

## Highlights in This Version (v1.3.1)

In v1.3.1, building on the **image / file support** introduced in v1.3.0, the **login screen and custom settings menu pages now share a unified UI**, **`image_api.php` has been added**, and **`media_files.php` now allows images to be copied by clicking on image thumbnails**, making daily operation even easier.

- Supports **copy-and-paste workflows for images**
- Supports **copy-and-paste workflows for files**
- Added a **media / file management page** (`media_files.php`)
- Supports **uploading / downloading / organizing images and files**
- Supports **selected deletion and delete-all for images / files**
- Improved `media_files.php` so you can **copy images by clicking image thumbnails**
- Added **`image_api.php`**
- Unified the UI of **`login.php` and the custom settings menu related pages**
- Target pages: `login.php` / `media_files.php` / `clipboard_sync.php` / `bak_settings.php` / `log_settings.php` / `setup_auth.php` / `account.php`
- Unified the appearance of the **Clipboard Sync settings page / backup settings page / log settings page / initial setup / account settings**
- Continues to include **Web UI authentication** and **PWA support**

With this update, Notemod-selfhosted is not only a **self-hosted environment that can handle text, images, and files together**, but also provides a **more consistent and easier-to-use UI from login through the settings pages**.

---

## Development Goal

The goal is to make clipboard sharing between iPhone and Windows PC feel as close as possible to the convenience of iPhone and Mac,  
while achieving it **without depending on external services**.

Typical use cases:

- Send text copied on a Windows PC to Notemod-selfhosted (can be automated with the Windows app ClipboardSync)
- Retrieve the latest text using iPhone Shortcuts or app integrations
- Save and retrieve images
- Save and retrieve PDFs and other files
- Organize media and backups from the Web admin screens
- Access from a web browser to make exchanging text and files between different devices easier

---

## Main Features

### 1. Self-hosted operation of the Notemod core
- Run the Notemod UI on your own server
- Data is stored in `notemod-data/data.json`
- No database required

### 2. Text write / read APIs
- Add notes via `api/api.php`
- Retrieve the latest note via `api/read_api.php`
- `latest_note` excludes the Logs category
- Supports returning only the body with `pretty=2`

### 3. Image / file copy-and-paste support
- Image upload
- General file upload
- Retrieve latest image / latest file
- Determine the latest clip type
- Organize images / files from `media_files.php`
- Copy images by clicking image thumbnails in `media_files.php`

### 4. Cleanup API
- Execute destructive operations with the admin token
- Supports deletion with backup creation (and backup creation only)
- Bulk deletion of log files / backup files
- Image / file cleanup features

### 5. Web UI authentication
- Allows management with a login system even in environments where Basic Auth cannot be used
- Protects settings pages and various admin pages
- Unified UI for `login.php` and each custom settings menu page

### 6. Backup / restore
- Change settings from `bak_settings.php`
- Back up now
- Keep the latest n backups and delete older backups
- Restore `data.json` from the backup list

### 7. Log settings
- Change settings from `log_settings.php`
- Enable / disable access logs
- Enable / disable saving access logs into a Notemod category
- Set the maximum number of access log lines
- Enable / disable notifications on first-time access from a new IP
- Set the email address for IP access notifications

### 8. PWA support
- Supports adding to the home screen on iPhone / Android
- Can be launched like an app

---

## Who This Is For

- People who want their own personal clip / note platform
- People who do not want to depend on external cloud clipboard sync services
- People who want something lightweight that runs on shared hosting
- People who want to build their own bridge between iPhone and Windows
- People who want to handle not only text, but also images and files

---

## Recommended Directory Structure

```text
public_html/
├─ index.php                 # Notemod UI
├─ logger.php                # Access log + append to Logs category
├─ notemod_sync.php          # Sync endpoint (save/load)
├─ setup_auth.php            # Web UI initial setup
├─ login.php / logout.php    # Login / logout
├─ auth_common.php           # Shared authentication logic
├─ account.php               # Account / admin menu
├─ clipboard_sync.php        # Clipboard Sync settings page
├─ bak_settings.php          # Backup settings page
├─ log_settings.php          # Log settings page
├─ media_files.php           # Image / file management page
├─ api/
│  ├─ api.php                # Add note / receive image / file
│  ├─ read_api.php           # Read API
│  ├─ image_api.php          # Image delivery API
│  └─ cleanup_api.php        # Destructive operations (admin)
├─ config/
│  ├─ config.php             # Shared settings (do not commit)
│  └─ config.api.php         # API settings (do not commit)
├─ notemod-data/
│  ├─ data.json              # Single source of truth
│  └─ .htaccess
├─ pwa/
│  ├─ icon-192.png
│  └─ icon-512.png
├─ manifest.php
├─ service-worker.js / sw.php / sw-register.js
└─ robots.txt
```

---

## How to Use

### 1. Upload to the server
Upload the entire repository to your server’s public folder (for example, `public_html/`).

> `config/` and `api/` are assumed to be at the same level as `index.php`.  
> If you change the structure, you will need to adjust the PHP paths.

### 2. Create the configuration files
Upload the repository to the server, then **access `index.php` to generate them automatically**.

#### Shared settings
Copy or rename `config/config.sample.php` to `config/config.php`, then change it as needed.

Main items:
- `SECRET`
- `TIMEZONE`
- `DEBUG`
- `LOGGER_FILE_ENABLED`
- `LOGGER_NOTEMOD_ENABLED`
- `INITIAL_SNAPSHOT`

#### API settings
Copy or rename `config/config.api.sample.php` to `config/config.api.php`, then change it as needed.

Main items:
- `EXPECTED_TOKEN`
- `ADMIN_TOKEN`
- `DATA_JSON`
- `DEFAULT_COLOR`
- `CLEANUP_BACKUP_ENABLED`
- `CLEANUP_BACKUP_SUFFIX`

### 3. Change SECRET / TOKEN values
Do not use the sample values as-is. Always change them to long random values.

Example:
- `openssl rand -hex 32`

### 4. First launch
Access the public URL in your browser and complete the initial setup.

Depending on the environment and settings, files / directories like the following may be generated on first launch.

- `notemod-data/data.json`
- `notemod-data/.htaccess`
- `api/.htaccess`
- `config/config.php` / `config/config.api.php` (when using Web UI setup)
- Log-related files (depending on settings)

---

## Security (Important)

### If Basic Auth can be used
At minimum, **Basic Auth is strongly recommended** for the `api/` directory.

Reasons:
- `robots.txt` is not access control
- `/api/` on a public server is reachable from outside
- Long-lived tokens may become targets for brute-force attacks
- Basic Auth adds a barrier before PHP token authentication

### If Basic Auth cannot be used
Use **Web UI authentication + token-based operation**.

- Initial setup / admin: `setup_auth.php`
- After login: move to pages such as `account.php`

---

## Media / File Management (Still Important in v1.3.1)

### Management page
`media_files.php`

You can perform the following actions on this page:

- View image list
- View file list
- Drag-and-drop image upload
- Drag-and-drop file upload
- Download
- Copy images by clicking image thumbnails
- Selected deletion with checkboxes
- Select all / clear all
- Delete all images / delete all files
- Check server upload / download related settings

### Storage locations
- Images: `notemod-data/USER_NAME/images/`
- Files: `notemod-data/USER_NAME/files/`

*The handling of `USER_NAME` depends on the implementation and authentication state.*

### JSON management concept
Images and files are managed slightly differently.

#### Image side
- `image_latest.json`
  - Stores information about the latest image
- List display is based on folder scanning
- Images are assumed to be identifiable by thumbnails

#### File side
- `file.json`
  - File history log
- `file_index.json`
  - List of currently existing files
- `file_latest.json`
  - Latest file information

The reason the file side uses `file_index.json` is to **display the original filenames in the list instead of only the saved filenames**.

### latest protection
In cleanup-related processing by `cleanup_api.php`, the current implementation **protects the actual latest entity**.  
Because of this, even “delete all” may still leave the latest actual file/image behind.

---

## Backup Settings

### Management page
`bak_settings.php`

Main actions:
- Enable / disable backup function
- Set retention count
- Back up now
- Keep the latest n items and delete older backups
- Restore `data.json` from the backup list

Features:
- Automatically backs up the current `data.json` before restore
- Uses `TIMEZONE` for date/time display and filename handling

---

## Log Settings

### Management page
`log_settings.php`

Main settings:
- Enable / disable access log
- Enable / disable appending to the Logs category in Notemod
- Save log-related settings
- Enable / disable notification on first-time access from a new IP
- Set the email address for IP access notifications

---

## Unified UI (v1.3.1)

In v1.3.1, the UI of the login page and the custom settings menu related pages has been unified.  
This makes it easier to perform settings changes and admin tasks continuously without a major shift in appearance or operation from page to page.

### Pages included in the unified UI
- `login.php` (login page)
- `media_files.php` (media / files in the custom settings menu)
- `clipboard_sync.php` (clipboard sync in the custom settings menu)
- `bak_settings.php` (backup settings in the custom settings menu)
- `log_settings.php` (log settings in the custom settings menu)
- `setup_auth.php` (initial setup / authentication settings in the custom settings menu)
- `account.php` (account settings in the custom settings menu)

---

## Clipboard Sync Settings

### Management page
`clipboard_sync.php`

On this page, you can check API URLs and tokens for integration with external clients (the Windows app ClipboardSync).
It provides the ClipboardSync download link and the iOS Shortcut download link for iPhone integration.
It also uses the same unified UI as `login.php`, `media_files.php`, `bak_settings.php`, `log_settings.php`, `setup_auth.php`, and `account.php`, keeping the overall appearance and feel of the settings menu consistent.

Main uses:
- Windows app setup
- Copying URLs into iPhone Shortcuts (used during shortcut initial setup)
- Checking the `/api/` URL
- Checking the URLs of `api.php` / `read_api.php` / `cleanup_api.php`

---

## API Overview

### `api/api.php`
This is mainly the write-side endpoint.

Examples of supported operations:
- Add notes
- Upload images
- Upload files
- Handle WebP images
- Update `image_latest.json` / `file.json` / `file_index.json` / `file_latest.json`

### `api/read_api.php`
This is the read-side endpoint.

Examples of supported operations:
- `latest_note`
- `latest_image`
- `latest_file`
- `latest_clip_type`

### `api/image_api.php`
This is the image delivery endpoint.

Examples of supported operations:
- Direct display of saved images
- Assist image copying from `media_files.php`
- External integration using image URLs

### `api/cleanup_api.php`
This is the admin endpoint for destructive operations.

Examples of supported operations:
- Delete categories
- Delete logs
- Delete backups
- Organize images / files
- Selected deletion / delete all
- Rebuild `file_index.json`
- Correct `file_latest.json`

---

## API Usage Examples

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

### Direct image display (`image_api.php`)
```text
/api/image_api.php?user=USER_NAME&file=IMAGE_FILE_NAME.png
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

## External Integrations

For specific usage examples (API calls / iPhone Shortcuts / Windows app integration, etc.), please also refer to:

- [StayHomeLab YouTube ch](https://www.youtube.com/@StayHomeLab)
- [Website](https://stayhomelab.net/notemod-selfhosted)
- [ClipboardSender](https://github.com/StayHomeLabNet/ClipboardSender)

---

## Requirements

- **PHP 8.1 or later**
- Apache recommended (because `.htaccess` is used)
- Writable locations from PHP:
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
- Use it together with Basic Auth / Web UI authentication / `.htaccess` protection

---

## Division of Roles Between README and Web Manual

- **README**: overall project overview, installation, and guidance for the main features
- **Web manual**: how to use each screen, settings items, operational procedures, and checkpoints when something goes wrong

Instead of trying to explain every single operation in the README alone, it is recommended to move detailed usage instructions into the web manual.

---

## License

MIT License.

This project is based on **Notemod by Oray Emre Gunduz (MIT)**.  
As required by the MIT license, please **retain the copyright notice and the license text**.

---

## Credits

- Notemod (upstream): https://github.com/orayemre/Notemod
