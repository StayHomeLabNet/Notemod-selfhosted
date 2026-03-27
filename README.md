# Notemod-selfhosted v1.4.2

This is a fork based on **[Notemod (original)](https://github.com/orayemre/Notemod)** (MIT License), expanded into a **self-hosted memo platform that works even on shared hosting**.  
No database is required, and it uses **`notemod-data/<DIR_USER>/data.json`** as the single data source.

Verified shared hosting: Xserver, Sakura Internet, XREA, InfinityFree  
Tested PHP: 8.3.21 (**PHP 8.1 or later is required**)

> **Single data source:** `notemod-data/<DIR_USER>/data.json`

---

## Particularly important points in this version

- **Per-user configuration files**
  - `config/<DIR_USER>/config.php`
  - `config/<DIR_USER>/config.api.php`
- **Main data**
  - `notemod-data/<DIR_USER>/data.json`
- **Encryption**
  - `DATA_ENCRYPTION_ENABLED`
  - `DATA_ENCRYPTION_KEY`
  - `data.json` can be encrypted with **AES-256-CBC + HMAC**
- **Session retention period**
  - `SESSION_COOKIE_LIFETIME`
  - Can be changed from `log_settings.php`
- **Backup naming**
  - Plaintext: `data.json.bak-YYYYMMDD-HHMMSS`
  - Encrypted: `data.enc.json.bak-YYYYMMDD-HHMMSS`

---

## Main items organized and fixed in v1.4.2

### 1. Countermeasures against type corruption around sync and import
- Reviewed sync snapshot creation in `index.php` and fixed it so that `categories` / `notes` / `categoryOrder` / `noteOrder` are **sent as arrays**
- Improved `save` in `notemod_sync.php` so that it passes through **`nm_sync_normalize_snapshot()`**, normalizing stringified arrays and `null` / bool values before saving
- Reviewed the handling of `selectedLanguage` / `hasSelectedLanguage` / `sidebarState` / `thizaState` / `tema` during import, and fixed it so that **double-stringification is less likely to occur**
- Added support for importing `.txt` / `.json`

### 2. Save-format fixes for API / logger / cleanup
- `api/api.php`
- `logger.php`
- `api/cleanup_api.php`

In these files, the paths that **re-applied `json_encode()` to `categories` / `notes` and turned them into strings again** were fixed, and the code was reorganized so they are **always saved to `data.json` as arrays**

### 3. Organization of per-user configuration references for read API / cleanup API
- APIs were unified on the assumption that they refer to **`config/<DIR_USER>/config.api.php`**
- Improved consistency between the actual path of `DATA_JSON` obtained from `config.api.php` and the target `DIR_USER`
- Fixed the latest-related metadata references in `read_api.php` so that they are aligned with the **same user directory as the actual `DATA_JSON`**

### 4. Improved safety of the sync button
- Added a guard on the save side to stop “dangerous empty saves”
- Check login state before save
- Added diff checking
- Added automatic backup before save
- Reviewed the sync button so that it **does not clear localStorage first** when clicked

### 5. Added session settings
- Save `SESSION_COOKIE_LIFETIME` in `config/<DIR_USER>/config.php`
- From `log_settings.php`, you can select:
  - Until browser is closed
  - 1 day
  - 7 days
  - 30 days
- The server-side `session.gc_maxlifetime` can also be checked on screen

### 6. Centralized encryption settings in setup_auth
- In `setup_auth.php`, handle:
  - Automatic generation of `DATA_ENCRYPTION_KEY`
  - ON/OFF of `DATA_ENCRYPTION_ENABLED`
  - Backup immediately before switching
- The actual value of `DATA_ENCRYPTION_KEY` is not shown in the UI; only **“Configured”** is displayed

---

## Directory structure

```text
/index.php
/setup_auth.php
/login.php
/logout.php
/auth_common.php
/data_crypto.php
/logger.php
/log_settings.php
/bak_settings.php
/media_files.php
/clipboard_sync.php
/notemod_sync.php
/api/
  api.php
  read_api.php
  cleanup_api.php
  image_api.php
/config/<DIR_USER>/
  config.php
  config.api.php
/notemod-data/<DIR_USER>/
  data.json
  images/
  files/
/logs/<DIR_USER>/
```

---

## Initial setup

### 1. Upload to the server
Upload the entire repository to the public folder.

### 2. First access
Access `setup_auth.php` / `index.php` and perform the initial setup.

Main files that are automatically generated as needed:

- `config/<DIR_USER>/config.php`
- `config/<DIR_USER>/config.api.php`
- `notemod-data/<DIR_USER>/data.json`
- `notemod-data/<DIR_USER>/.htaccess`
- `logs/<DIR_USER>/.htaccess`
- `api/.htaccess`

---

## Configuration files

### Common settings
`config/<DIR_USER>/config.php`

Main keys:

- `SECRET`
- `TIMEZONE`
- `DEBUG`
- `LOGGER_FILE_ENABLED`
- `LOGGER_NOTEMOD_ENABLED`
- `LOGGER_FILE_MAX_LINES`
- `LOGGER_NOTEMOD_MAX_LINES`
- `IP_ALERT_ENABLED`
- `IP_ALERT_TO`
- `IP_ALERT_FROM`
- `IP_ALERT_SUBJECT`
- `IP_ALERT_IGNORE_BOTS`
- `IP_ALERT_IGNORE_IPS`
- `IP_ALERT_STORE`
- `SESSION_COOKIE_LIFETIME`
- `DATA_ENCRYPTION_ENABLED`
- `DATA_ENCRYPTION_KEY`

### API settings
`config/<DIR_USER>/config.api.php`

Main keys:

- `EXPECTED_TOKEN`
- `ADMIN_TOKEN`
- `DATA_JSON`
- `DEFAULT_COLOR`
- `CLEANUP_BACKUP_ENABLED`
- `CLEANUP_BACKUP_SUFFIX`
- `CLEANUP_BACKUP_KEEP`

---

## Security

### Basic authentication is strongly recommended
If possible, set Basic authentication on `api/`.

### Web UI authentication
If Basic authentication cannot be used, you can still secure the system to a certain extent by operating it with Web UI authentication using `setup_auth.php` and `login.php` / `logout.php`.

### `data.json` encryption
- When `DATA_ENCRYPTION_ENABLED` is `true`, `data.json` is stored encrypted
- Export is **always plaintext JSON**
- If you lose the encryption key, you will not be able to decrypt the data

---

## API overview

### `api/api.php`
- Add text
- Upload images
- Upload files
- Automatically create categories if necessary
- Update `note_latest.json`

### `api/read_api.php`
- Read-only
- `latest_note`
- `latest_clip_type`
- `latest_image`
- `latest_file`

> When calling the API, it is **recommended to include `user=<DIR_USER>`**

Example for `latest_note`:

- Get as JSON  
  `...?token=...&user=USER_NAME&action=latest_note&pretty=1`
- Get body only  
  `...?token=...&user=USER_NAME&action=latest_note&pretty=2`

### `api/cleanup_api.php`
- Delete by category
- `dry_run`
- Delete backups
- Delete logs
- Bulk delete images / files

### `api/image_api.php`
- Serve images
- Simple resize
- Cache control

---

## Backups

### Automatic backups
Backups are created at the following times.

- Immediately before switching encryption settings
- Before sync save
- Before destructive cleanup operations (depending on settings)

### Naming rules
- Plaintext: `data.json.bak-YYYYMMDD-HHMMSS`
- Encrypted: `data.enc.json.bak-YYYYMMDD-HHMMSS`

### Restore
You can restore from `bak_settings.php`.  
When restoring an encrypted backup, the corresponding `DATA_ENCRYPTION_KEY` is required.

---

## Log / session settings

Items handled in `log_settings.php`:

- File log ON/OFF
- Notemod Logs category log ON/OFF
- `SESSION_COOKIE_LIFETIME`
- Display check for `session.gc_maxlifetime`

Description:
> This is the browser-side retention period. Depending on the server-side settings, the login may expire earlier.

---

## Integration

- [StayHomeLab YouTube ch](https://www.youtube.com/@StayHomeLab)
- [Website](https://stayhomelab.net/notemod-selfhosted)
- [ClipboardSync](https://github.com/StayHomeLabNet/ClipboardSync)

---

## Notes

- APIs and cleanup are assumed to **always refer to `config/<DIR_USER>/config.api.php`**
- Do not revert to the old `/config/config.api.php` assumption
- Even when handling broken old-format `data.json`, the current code is designed to normalize it as much as possible before saving
