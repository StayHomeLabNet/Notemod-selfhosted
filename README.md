# Notemod-selfhosted v1.4.0

This is a fork based on **[Notemod (original)](https://github.com/orayemre/Notemod)** (MIT License), expanded into a **self-hosted note platform that can run even on shared hosting**, for the purpose of improving clipboard use between iPhone and Windows PCs in particular by integrating with the Windows app **[ClipboardSync](https://github.com/StayHomeLabNet/ClipboardSync)**.  
It runs with **no database required**, and supports not only text but also **images** and **files**, including copy-and-paste integration, organization from the management screen, backups, and Web UI authentication.

Shared hosting environments confirmed to work: Xserver, Sakura Internet, XREA, InfinityFree  
Tested PHP version: 8.3.21 (**PHP 8.1 or later is required**)

> **Per-user data source:** `notemod-data/<USER_NAME>/data.json`

---

## Highlights of This Version (v1.4.0)

In v1.4.0, building on the **per-user directory structure** and the separation of **`USERNAME` / `DIR_USER`** introduced in v1.3.1, operation based on **`config/<USER_NAME>/...` / `notemod-data/<USER_NAME>/...` / `logs/<USER_NAME>/...`** has been further reinforced. In addition, reference targets in `cleanup_api.php` and surrounding management functions have been shifted toward the per-user structure, old assumptions based on variable log directory settings such as `LOGGER_LOGS_DIRNAME` have been cleaned up, and operation has been aligned around **`logs/<USER_NAME>/`** as the standard.

Also, in `media_files.php`, in addition to **clicking an image thumbnail to copy the image** and **clicking the filename to copy the image URL**, the **input values for width / height can now be reflected in the image retrieval URL**, making it easier to use images externally and share them with size parameters.

- Organized based on a **per-user directory structure**
- Supports `config/<USER_NAME>/...` / `notemod-data/<USER_NAME>/...` / `logs/<USER_NAME>/...`
- Continued operation with **separated `USERNAME` and `DIR_USER`**
- Organized log operation around **`logs/<USER_NAME>/`**
- **Unified `cleanup_api.php` configuration references to the per-user structure**
- **Cleaned up assumptions based on `LOGGER_LOGS_DIRNAME`**
- **Click image thumbnail to copy image**
- **Click image filename to copy image URL**
- **Apply width / height input values to image URLs**
- Supports **upload / download / organization of images and files**
- Continues to include **Web UI authentication** and **PWA support**

With this update, Notemod-selfhosted has become not only a **self-hosted environment that is easy to organize with per-user directories**, but also a version that can handle **image, file, and log management in a more consistent structure**.

---

## Development Purpose

To make clipboard integration between iPhone and Windows PCs a little closer to the comfort of clipboard sharing between iPhone and Mac.  
The goal is to achieve this **without relying on external services**.

Typical use cases:

- Send text copied on a Windows PC to Notemod-selfhosted (can be automated with the Windows app ClipboardSync)
- Retrieve the latest text from iPhone using Shortcuts or app integration
- Save and retrieve images
- Save and retrieve PDFs and other files
- Organize media and backups from the Web management screen
- Access via a Web browser to make text and file exchange between different devices easier

---

## Main Features

### 1. Self-hosted operation of the Notemod app itself
- You can run the Notemod UI on your server
- Data is stored in `notemod-data/<USER_NAME>/data.json`
- No database is required

### 2. Text write / read API
- Add notes with `api/api.php`
- Retrieve the latest note with `api/read_api.php`
- `latest_note` excludes the Logs category
- Supports operation that returns only the body with `pretty=2`

### 3. Image / file copy-and-paste support
- Image upload
- General file upload
- Retrieve latest image / latest file
- Determine the latest clip type
- Organize images / files from `media_files.php`
- Click an image thumbnail in `media_files.php` to copy the image
- Click an image filename in `media_files.php` to copy the image URL
- Supports retrieving image URLs with width / height parameters

### 4. Cleanup API
- Execute destructive operations with an admin token
- Supports deletion with backup creation (backup creation only is also possible)
- Bulk deletion of log files / backup files
- Image / file organization functions
- Supports log deletion based on `logs/<USER_NAME>/`

### 5. Web UI authentication
- Management by login is possible even in environments where Basic authentication cannot be used
- `USERNAME` (display / login name) and `DIR_USER` (storage directory name) can be operated separately
- Protects settings screens and various management screens

### 6. Backup / restore
- Change settings from `bak_settings.php`
- Create a backup immediately
- Keep the latest n backups and delete older ones
- Restore `data.json` from the backup list

### 7. Log settings
- Change settings from `log_settings.php`
- Enable / disable access logs
- Enable / disable saving access logs into a Notemod category
- Set the maximum number of log lines
- Enable / disable first-IP access notifications
- Set the email address for IP access notifications

### 8. PWA support
- Supports adding to the home screen on iPhone / Android
- Can be launched like an app

---

## Who This Is For

- People who want their own clipboard / note platform
- People who do not want to depend on external cloud clipboard sync
- People who want something lightweight that runs on shared hosting
- People who want to build their own bridge between iPhone and Windows
- People who want to handle not only text but also images and files
- People who want to organize and operate with per-user directories

---

## Recommended Directory Structure

```text
public_html/
├─ index.php
├─ logger.php
├─ notemod_sync.php
├─ setup_auth.php
├─ login.php / logout.php
├─ auth_common.php
├─ account.php
├─ clipboard_sync.php
├─ bak_settings.php
├─ log_settings.php
├─ media_files.php
├─ api/
│  ├─ api.php
│  ├─ read_api.php
│  ├─ image_api.php
│  └─ cleanup_api.php
├─ config/
│  └─ USER_NAME/
│     ├─ auth.php
│     ├─ config.php
│     └─ config.api.php
├─ logs/
│  └─ USER_NAME/
│     ├─ .htaccess
│     └─ ...log files...
├─ notemod-data/
│  └─ USER_NAME/
│     ├─ .htaccess
│     ├─ data.json
│     ├─ _known_ips.json
│     ├─ images/
│     └─ files/
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
Upload the entire repository to your server’s public directory (for example, `public_html/`).

> `config/` and `api/` are assumed to be at the same level as `index.php`.  
> If you change the structure, you will need to adjust the paths in the PHP code.

### 2. Create the configuration files
Upload the entire repository to the server, and they will be **created automatically by accessing `index.php`**. After initial setup, the various settings below will be created automatically, so you can start using it right away.

#### Common settings / API settings / authentication settings
In v1.4.0 as well, configuration files are assumed to be created automatically in the **per-user directory** during initial setup.

Creation locations:
- `config/<USER_NAME>/auth.php`
- `config/<USER_NAME>/config.php`
- `config/<USER_NAME>/config.api.php`

Main items:
- `USERNAME`
- `DIR_USER`
- `SECRET`
- `TIMEZONE`
- `DEBUG`
- `EXPECTED_TOKEN`
- `ADMIN_TOKEN`
- `DATA_JSON`
- `DEFAULT_COLOR`
- `CLEANUP_BACKUP_ENABLED`
- `CLEANUP_BACKUP_SUFFIX`

### 3. Change the SECRET / TOKEN
Do not use the sample values as they are. Be sure to change them to long random values.

Example:
- `openssl rand -hex 32`

### 4. First launch
Access the public URL in your browser and proceed with the initial setup.

Depending on the environment and settings, files / directories such as the following will be generated at first launch:

- `config/<USER_NAME>/auth.php`
- `config/<USER_NAME>/config.php`
- `config/<USER_NAME>/config.api.php`
- `notemod-data/<USER_NAME>/data.json`
- `notemod-data/<USER_NAME>/.htaccess`
- `notemod-data/<USER_NAME>/_known_ips.json`
- `notemod-data/<USER_NAME>/images/`
- `notemod-data/<USER_NAME>/files/`
- `logs/<USER_NAME>/`
- `api/.htaccess`
- Log-related files (depending on the settings)

---

## About USERNAME and DIR_USER (v1.4.0)

In v1.4.0 as well, **`USERNAME` as the display / login name** and **`DIR_USER` as the storage directory name** are handled separately.

- `USERNAME`
  - Login name / display name
  - Can be changed from `account.php`
- `DIR_USER`
  - Storage directory name
  - Handled in lowercase
  - Not changed after initial creation

Main storage locations:
- `config/<DIR_USER>/auth.php`
- `config/<DIR_USER>/config.php`
- `config/<DIR_USER>/config.api.php`
- `notemod-data/<DIR_USER>/data.json`
- `notemod-data/<DIR_USER>/images/`
- `notemod-data/<DIR_USER>/files/`
- `logs/<DIR_USER>/`

In `account.php`, operation assumes that even if the login name is changed, the storage directory name does not change.

---

## Security (Important)

### If Basic authentication can be used
Basic authentication is strongly recommended at least for the `api/` directory.

Reason:
- `robots.txt` is not access control
- `/api/` on a public server is reachable from outside
- Long-term tokens can become targets for brute-force attacks
- Basic authentication creates a barrier before PHP token authentication

### If Basic authentication cannot be used
Use **Web UI authentication + token operation**.

- Initial setup / management: `setup_auth.php`
- After login: move to each settings screen from `account.php`, etc.

---

## Media / File Management

### Management screen
`media_files.php`

The following operations are available on this screen:

- Display image list
- Display file list
- Drag-and-drop image upload
- Drag-and-drop file upload
- Download
- Click image thumbnail to copy image
- Click image filename to copy image URL
- Retrieve image URLs with specified width / height
- Delete selected items with checkboxes
- Select all / deselect all
- Delete all images / delete all files
- Check server upload / download related settings

### Storage locations
- Images: `notemod-data/<USER_NAME>/images/`
- Files: `notemod-data/<USER_NAME>/files/`

* `<USER_NAME>` is the storage directory name.

### How JSON is managed
Management differs slightly between images and files.

#### Image side
- `image_latest.json`
  - Holds information on the latest image
- List display is based on directory scanning
- Assumes images can be identified by thumbnails

#### File side
- `file.json`
  - File history log
- `file_index.json`
  - Current file list
- `file_latest.json`
  - Latest file information

The reason `file_index.json` is used on the file side is to display the original filename rather than the stored filename.

### About latest protection
In cleanup-related processing in `cleanup_api.php`, the current implementation protects the actual file referenced by latest.  
Therefore, even in “delete all,” the latest actual file may remain.

---

## Backup Settings

### Management screen
`bak_settings.php`

Main operations:
- Enable / disable backup feature
- Set retention count
- Create a backup immediately
- Keep the latest n backups and delete older ones
- Restore `data.json` from the backup list

Features:
- Automatically backs up the current `data.json` before restoring
- Uses `TIMEZONE` for date/time display and filename handling

---

## Log Settings

### Management screen
`log_settings.php`

Main settings:
- Enable / disable access logs
- Enable / disable appending to the Logs category in Notemod
- Save log-related settings
- Enable / disable first-IP access notifications
- Set the email address for IP access notifications

In v1.4.0, log-related operation has been organized on the assumption of **`logs/<USER_NAME>/`**.

---

## ClipboardSync Settings

### Management screen
`clipboard_sync.php`

On this screen, you can check the API URLs and tokens used for integration with the external client (the Windows app ClipboardSync).  
It also provides a download link for ClipboardSync and a download link for the iOS Shortcut used to integrate with iPhone.

Main uses:
- Settings for the Windows app
- Copying URLs into iPhone Shortcuts (used for initial setup of the shortcut)
- Checking the `/api/` URL
- Checking the URLs for `api.php`, `read_api.php`, and `cleanup_api.php`
- Checking settings corresponding to the current `DIR_USER`

---

## API Overview

### `api/api.php`
This is mainly the write-side endpoint.

Supported examples:
- Add notes
- Upload images
- Upload files
- Handle WebP images
- Update `image_latest.json`, `file.json`, `file_index.json`, and `file_latest.json`

### `api/read_api.php`
This is the read-side endpoint.

Supported examples:
- `latest_note`
- `latest_image`
- `latest_file`
- `latest_clip_type`

### `api/image_api.php`
This is the image delivery endpoint.

Supported examples:
- Direct display of saved images
- Image copy support from `media_files.php`
- External integration using image URLs
- Retrieval with size parameters via `w` / `h`

### `api/cleanup_api.php`
This is the admin-only destructive-operation endpoint.

Supported examples:
- Delete categories
- Delete logs
- Delete backups
- Organize images / files
- Delete selected items / delete all
- Rebuild `file_index.json`
- Fix `file_latest.json`
- Delete logs targeting `logs/<USER_NAME>/`

---

## API Usage Examples

### Add a note
```text
/api/api.php?token=EXPECTED_TOKEN&category=INBOX&text=Hello
```

### Retrieve the latest note (excluding Logs, body only)
```text
/api/read_api.php?token=EXPECTED_TOKEN&action=latest_note&pretty=2
```

### Retrieve the latest image
```text
/api/read_api.php?token=EXPECTED_TOKEN&action=latest_image
```

### Retrieve the latest file
```text
/api/read_api.php?token=EXPECTED_TOKEN&action=latest_file
```

### Directly display an image (`image_api.php`)
```text
/api/image_api.php?user=<DIR_USER>&file=IMAGE_FILE_NAME.png
```

### Retrieve an image with width specified
```text
/api/image_api.php?user=<DIR_USER>&file=IMAGE_FILE_NAME.png&w=300
```

### Retrieve an image with height specified
```text
/api/image_api.php?user=<DIR_USER>&file=IMAGE_FILE_NAME.png&h=300
```

### Retrieve the latest clip type
```text
/api/read_api.php?token=EXPECTED_TOKEN&action=latest_clip_type
```

### Bulk delete log files (POST)
```bash
curl -X POST "https://USER:PASS@YOUR_SITE/api/cleanup_api.php"   -H "Content-Type: application/x-www-form-urlencoded"   --data-urlencode "token=ADMIN_TOKEN"   --data-urlencode "purge_log=1"   --data-urlencode "confirm=YES"
```

### Bulk delete backup files (POST)
```bash
curl -X POST "https://USER:PASS@YOUR_SITE/api/cleanup_api.php"   -H "Content-Type: application/x-www-form-urlencoded"   --data-urlencode "token=ADMIN_TOKEN"   --data-urlencode "purge_bak=1"   --data-urlencode "confirm=YES"
```

---

## External Integration

For specific usage methods (how to call the API, iPhone Shortcuts, Windows app integration, etc.), please also refer to the following:

- [StayHomeLab YouTube ch](https://www.youtube.com/@StayHomeLab)
- [Website](https://stayhomelab.net/notemod-selfhosted)
- [ClipboardSync](https://github.com/StayHomeLabNet/ClipboardSync)

---

## Requirements

- **PHP 8.1 or later**
- Apache recommended (because `.htaccess` is used)
- Locations writable by PHP:
  - `notemod-data/<USER_NAME>/`
  - `config/<USER_NAME>/` (during initial setup)
  - `logs/<USER_NAME>/`

---

## About `robots.txt`

It is automatically created by `setup_auth.php`.

Recommended:

```text
User-agent: *
Disallow: /
```

Notes:
- `robots.txt` is not access control
- Use it together with Basic authentication / Web UI authentication / `.htaccess` protection, etc.

---

## Division of Roles Between README and the Web Manual

- **README**: overall project overview, introduction, and main feature guide
- **Web manual**: how to use each screen, setting items, operation procedures, and troubleshooting checkpoints

Rather than trying to explain every operation in the README alone, it is recommended to separate detailed operational instructions into the Web manual.

---

## License

MIT License.

This project is based on **Notemod (MIT)** by Oray Emre Gunduz.  
As required by the MIT License, please retain the copyright notice and the license text.

---

## Credits

- Notemod (original): https://github.com/orayemre/Notemod
