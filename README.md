<div align="center">

# 📝 Notemod-selfhosted

## A self-hosted Notemod fork with PHP sync, APIs, encryption, backups, and media support

**Notemod-selfhosted is a database-free, self-hosted memo platform designed to run even on shared hosting.  
It stores notes in `notemod-data/<DIR_USER>/data.json` and provides Web UI sync, API access, clipboard integration, image/file handling, backups, SMTP settings, and optional AES-256-CBC + HMAC encryption.**

[English](README.md) | [日本語](README.ja.md)

<br>

[Overview](#overview) ・ [Features](#features) ・ [Requirements](#requirements) ・ [Installation](#installation) ・ [Initial Setup](#initial-setup) ・ [Directory Structure](#directory-structure) ・ [API](#api-overview) ・ [Security](#security) ・ [Backups](#backups) ・ [Links](#links) ・ [License](#license)

<br>

![version](https://img.shields.io/badge/version-1.4.7-2ea44f)
![license](https://img.shields.io/badge/license-MIT-97ca00)
![language](https://img.shields.io/badge/language-PHP-777bb4)
![database](https://img.shields.io/badge/database-not%20required-blue)
![hosting](https://img.shields.io/badge/hosting-shared%20hosting-orange)
![data](https://img.shields.io/badge/data-json-lightgrey)

<br>

![sync](https://img.shields.io/badge/sync-Web%20UI%20%2F%20API-brightgreen)
![encryption](https://img.shields.io/badge/encryption-AES--256--CBC%20%2B%20HMAC-purple)
![backup](https://img.shields.io/badge/backups-supported-blue)
![media](https://img.shields.io/badge/media-images%20%2F%20files-ff69b4)
![smtp](https://img.shields.io/badge/mail-mail%28%29%20%2F%20SMTP-yellow)
![pwa](https://img.shields.io/badge/PWA-supported-555555)

<br>

<a href="https://ko-fi.com/stayhomelabnet">
  <img src="https://img.shields.io/badge/Ko--fi-Support%20this%20project-ff5e5b?style=for-the-badge&logo=kofi&logoColor=white" alt="Support this project on Ko-fi">
</a>
<a href="https://buymeacoffee.com/stayhomelabnet">
  <img src="https://img.shields.io/badge/Buy%20Me%20a%20Coffee-Support%20this%20project-ffdd00?style=for-the-badge&logo=buymeacoffee&logoColor=000000" alt="Support this project on Buy Me a Coffee">
</a>

</div>

---

## Overview

This is a fork based on **[Notemod (upstream)](https://github.com/orayemre/Notemod)** (MIT License), extended as a **self-hosted note platform that can run on shared hosting environments**.  
No database is required, and **`notemod-data/<DIR_USER>/data.json`** is used as the single data source.

It is developed to **smoothly exchange text, images, and files between Windows PCs and iPhones** without relying on external services. It can also serve as an alternative to note services such as simplenote.com.

> **Single data source:** `notemod-data/<DIR_USER>/data.json`

---

## Features

- Database-free JSON-based storage
- Memo editing and sync save through the Web UI
- API integration through `api.php`, `read_api.php`, `cleanup_api.php`, and related endpoints
- ClipboardSync integration for sharing text, images, and files between PCs and iPhones
- Image/file storage, listing, deletion, and lock protection
- Optional data encryption with AES-256-CBC + HMAC
- Automatic backups, restore support, and pre-sync-save backups
- Notifications and password reset through `mail()` / SMTP
- Authentication security improvements such as CSRF protection, rate limiting, audit logs, and security headers

---

## Requirements

- PHP 8.1 or later
- PHP-compatible web server with writable file storage
- No database required
- Shared hosting supported

Verified shared hosting environments: Xserver, Sakura Internet, XREA, InfinityFree  
Tested PHP: 8.3.21

### Automated tests

Run the dependency-free PHP test suite and HTTP security-header smoke tests locally:

```bash
php tests/run.php
bash tests/http_smoke.sh
```

The PHP suite covers configuration defaults, storage paths, trusted-proxy IP and HTTPS detection, canonical URL validation, Unicode password length, media limits, concurrent rate-limit updates, index locking, atomic authentication-config writes, and encrypted-data integrity. The HTTP smoke test verifies the HTML, API, and unauthenticated image-response security policies against a temporary isolated storage directory.

GitHub Actions runs PHP syntax checks and both test suites on PHP 8.1 through 8.5 for every push and pull request.

---
## Especially important points in this update

- **Per-user configuration files**
  - `config/<DIR_USER>/config.php`
  - `config/<DIR_USER>/config.api.php`
  - `config/<DIR_USER>/auth.php`
- **Shared mail settings for all users**
  - `config/mail.php`
- **Main data**
  - `notemod-data/<DIR_USER>/data.json`
- **Image index**
  - `notemod-data/<DIR_USER>/image_index.json`
- **File index**
  - `notemod-data/<DIR_USER>/file_index.json`
- **Authentication email address**
  - Required in `setup_auth.php`
  - Saved as `EMAIL` in `auth.php`
- **Password reset**
  - `forgot_password.php`
  - `reset_password.php`
  - `config/<DIR_USER>/password_reset.json`
- **Encryption**
  - `DATA_ENCRYPTION_ENABLED`
  - `DATA_ENCRYPTION_KEY`
  - `data.json` can be encrypted with **AES-256-CBC + HMAC**
- **Session retention period**
  - `SESSION_COOKIE_LIFETIME`
  - Can be changed from `log_settings.php`
- **Mail sending**
  - Supports both `mail()` and SMTP
  - Centrally managed by the shared mail sending foundation in `auth_common.php`
- **Backup naming**
  - Plaintext: `data.json.bak-YYYYMMDD-HHMMSS`
  - Encrypted: `data.enc.json.bak-YYYYMMDD-HHMMSS`
- **Pre-sync-save backup settings**
  - `SYNC_PRE_SAVE_BACKUP_ENABLED`
  - `SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED`
  - Can control pre-sync-save backups in the Web UI and pruning old backups immediately before that
- **Media lock**
  - Each item in `file_index.json` / `image_index.json` stores `lock: true/false`
  - `true` means locked, `false` means unlocked
- **Authentication-related security enhancements**
  - security headers
  - CSRF protection
  - rate limiting for login / forgot password / reset password
  - audit log
- **Token exposure reduction**
  - `setup_auth.php` no longer shows API tokens in plain text
  - `clipboard_sync.php` uses masked-by-default + temporary reveal
  - `media_files.php` was changed to a server-side relay design without exposing tokens to the browser

---

## Main additions and improvements in v1.4.7

- Added `NM_STORAGE_ROOT` and updated `.gitignore` for the per-user storage layout, keeping runtime data and secrets out of version control
- Added trusted-proxy-aware client IP and HTTPS detection through `NM_TRUSTED_PROXIES`
- Replaced URLs derived from the `Host` header with validated `NM_PUBLIC_BASE_URL` and optional `NM_INTERNAL_BASE_URL` settings
- Clarified image API authentication and applied private, no-store cache controls to protected responses
- Made rate-limit state, authentication configuration, and media index updates concurrency-safe with locking and atomic writes
- Added application-level upload, image-processing, and request-size limits
- Unified Unicode-aware password length validation, security headers, CSP handling, and shared configuration defaults
- Added dependency-free PHP tests, HTTP security smoke tests, and a PHP 8.1-8.5 GitHub Actions test matrix

---

## Main additions and improvements in v1.4.6

### 1. Added support for `image_index.json`
- Previously, images did not have an index equivalent to `file_index.json`, but v1.4.6 adds **`image_index.json`**
- Incrementally updated when images are uploaded
- Regenerated after image deletion or purge
- `media_files.php` now builds the image list by prioritizing `image_index.json`

### 2. Added a `lock` flag to `file_index.json` / `image_index.json`
- Added **`lock`** to each image and file entry
- Stored as a **boolean**
  - `true` = locked
  - `false` = unlocked
- Default value for new entries is `false`

### 3. Added lock / unlock UI to `media_files.php`
- Added a **lock icon** to the right of the checkbox for each image and file row
- Each click toggles between
  - locked
  - unlocked
- The UI was adjusted to a small icon button so it blends into the existing screen

### 4. Locked media are excluded from deletion targets
- Images/files with `lock=true` are excluded from deletion targets
- Even if they are included in bulk deletion or individual deletion operations, locked items are not deleted
- Unlocked items remain deletable as before

### 5. Improved cleanup to preserve lock state
- When `api/cleanup_api.php` regenerates `file_index.json` / `image_index.json`,
  it now preserves the existing **`lock`** state if a file with the same name already exists in the old index
- This makes lock settings less likely to be lost after cleanup or purge

### 6. Strengthened authentication-related security
- Reorganized shared authentication-related security handling mainly in `auth_common.php`
- Added common security headers to HTML pages
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: SAMEORIGIN`
  - `Referrer-Policy: same-origin`
  - `Cache-Control: no-store, no-cache, must-revalidate, max-age=0`
  - `Pragma: no-cache`
- `login.php` now performs `session_regenerate_id(true)` after successful login
- Improved protected pages so security headers are also applied when returning an unauthenticated redirect

### 7. Added CSRF protection
- Added **CSRF tokens** to major form-based pages
- Targets:
  - `login.php`
  - `setup_auth.php`
  - `account.php`
  - `log_settings.php`
  - `bak_settings.php`
  - `forgot_password.php`
  - `reset_password.php`
  - token reveal in `clipboard_sync.php`
  - download / upload / cleanup / lock actions in `media_files.php`
- Requests with missing or tampered tokens are rejected

### 8. Added rate limiting to login / password reset flows
- `login.php`
- `forgot_password.php`
- `reset_password.php`

Short-time repeated attempts are now restricted

- Repeated failed login attempts are limited
- Repeated password reset requests in a short period are limited
- Repeated password reset attempts in a short period are limited
- Required buckets are cleared on successful completion

### 9. Added audit logging
- Added shared audit log handling to `auth_common.php`
- Stored in **`logs/audit.log`** under `NM_STORAGE_ROOT` (JSON Lines)
- Records events such as:
  - `login_success`
  - `login_failed`
  - `login_rate_limited`
  - `password_reset_requested`
  - `password_reset_request_rate_limited`
  - `password_reset_failed`
  - `password_reset_completed`
  - `password_reset_rate_limited`
  - `setup_auth_updated`
  - various operation events from `account` / `log_settings` / `bak_settings`
- Secret values are not recorded; only minimum necessary differences such as
  - changed flags
  - from / to
  - masked email
  are stored

### 10. Improved `setup_auth.php` so API tokens are not shown in plain text
- Changed so `EXPECTED_TOKEN` / `ADMIN_TOKEN` are not displayed in plain text as existing values
- Input fields are shown empty
- Existing tokens are indicated only through placeholders / explanatory text as “configured”
- Leaving the fields blank keeps existing values
- Values are updated only when new input is provided

### 11. Strengthened token display handling in `clipboard_sync.php`
- `EXPECTED_TOKEN` / `ADMIN_TOKEN` are **always masked on initial display**
- They are fetched from the server and temporarily revealed **only when unlocked**
- They are **automatically re-locked after 10 seconds**
- Copy is allowed only while visible
- Copy is disabled while locked
- Added **CSRF protection** to the `reveal_token` POST

### 12. Changed `media_files.php` to a non-token-exposing design
- Improved so `EXPECTED_TOKEN` / `ADMIN_TOKEN` are not directly exposed to the browser
- Image/file upload / cleanup / lock / download are handled through **server-side relay processing inside `media_files.php` itself**
- The frontend operates on a session + CSRF basis
- Improved image URL copy and image copy so tokens are not exposed in URLs
- Added **`parse_file_history_jsonl()`** for restoring display from `file.json` (JSON Lines) history

### 13. Reorganized user resolution logic in `api/image_api.php`
- Supports all of:
  - `user`
  - `dir_user`
  - `username`
- Reorganized the old logic that re-assigned `$_GET['user']` later in the file and effectively invalidated the earlier helper-based resolution
- This improves image retrieval so `dir_user` and `username` based access works as intended
- Image retrieval requires either a logged-in Web UI session for the same user or that user's `EXPECTED_TOKEN`
- Authenticated image responses use `Cache-Control: private, no-store`; resized derivatives may still be cached only on the server under the user's `.cache` directory

### 14. Features up to v1.4.5 continue
- Saving authentication email addresses
- Password reset
- Shared mail sending foundation
- Shared mail settings for all users via `config/mail.php`
- SMTP settings UI / test sending
- `append_api.php`
- `search_api.php`
- `journal_api.php`
- **Snapshot normalization** before sync save
- `.txt` / `.json` import support
- Countermeasures for stringified `categories` / `notes`
- `SESSION_COOKIE_LIFETIME` support
- Stronger XSS protection in `index.php`
- Optional encrypted saving of `data.json`
- Pre-sync-save backup control in the Web UI

### 15. Improved sync safety in `index.php`
- Improved behavior so that even if the Web UI login session expires after a long idle period, **local browser data is less likely to disappear immediately on the spot**
- When **401 / 403** is detected during communication with `notemod_sync.php`, **automatic sync is paused** and the screen now clearly indicates that the session has expired and re-login is required
- After session expiry, **auto load and manual load are conditionally blocked** to reduce the risk of local browser data being overwritten by older server-side data
- Adjusted the warning behavior so that a strong warning is shown **only when local changes were made after session expiry**, and fixed the issue where the red warning could appear repeatedly during normal operation
- After a normal successful sync, the warning-related flags are cleared and the UI returns to the normal state

---

## Directory structure

```text
/index.php
/setup_auth.php
/login.php
/logout.php
/account.php
/forgot_password.php
/reset_password.php
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
  append_api.php
  search_api.php
  journal_api.php
/config/mail.php
/config/<DIR_USER>/
  auth.php
  config.php
  config.api.php
  password_reset.json
/notemod-data/<DIR_USER>/
  data.json
  image_index.json
  file_index.json
  images/
  files/
/logs/<DIR_USER>/
/logs/audit.log
```

---

## Installation

1. Download or clone this repository.
2. Upload the files to the public directory of your server.
3. Configure the runtime storage directory as described below. Using a directory outside the public directory is strongly recommended.
4. Open `login.php` in your browser.
5. After creating the first admin user, check SECRET, API token, encryption settings, and related options in `setup_auth.php`.

> If you are migrating existing data, back up `notemod-data/<DIR_USER>/data.json` and `config/<DIR_USER>/` before making changes.

### Runtime storage outside the public directory (recommended)

Set the `NM_STORAGE_ROOT` environment variable to an absolute path that PHP can read and write. Notemod then stores `config/`, `notemod-data/`, and `logs/` under that path instead of under the application directory.

```text
NM_STORAGE_ROOT=/var/lib/notemod
```

For a new installation, create the directory, grant the PHP process read/write access, and set the environment variable before opening `setup_auth.php`. For an existing installation:

1. Back up the current `config/`, `notemod-data/`, and `logs/` directories.
2. Move all three directories under the new storage root without changing their names or contents.
3. Set `NM_STORAGE_ROOT` in the PHP-FPM pool, Apache environment, container configuration, or hosting control panel.
4. Restart or reload the PHP/web server and verify login, note sync, media access, and logging.

The variable must contain an absolute path and must not point to the filesystem root itself. If it is unset, Notemod keeps using the legacy directories inside the application directory for backward compatibility. In that legacy layout, direct HTTP access must be denied by the web-server configuration; the generated `.htaccess` files only protect Apache-compatible servers.

### Client IP behind a trusted reverse proxy

By default, Notemod uses only `REMOTE_ADDR` and ignores client-supplied forwarding headers. If Notemod is behind a reverse proxy, set `NM_TRUSTED_PROXIES` to the proxy IP addresses or CIDR ranges:

```text
NM_TRUSTED_PROXIES=127.0.0.1,10.0.0.0/8,2001:db8:1234::/48
```

Only when `REMOTE_ADDR` matches this list does Notemod inspect `X-Forwarded-For` and `X-Forwarded-Proto`. It evaluates the address chain from right to left and selects the first address that is not a trusted proxy. A validated `X-Forwarded-Proto: https` value is also used for secure cookies and HTTPS state detection. Configure the proxy to replace incoming forwarding headers with validated values, and keep the trusted ranges as narrow as possible. Leave the variable unset when no reverse proxy is used.

### Canonical public and internal URLs

Set `NM_PUBLIC_BASE_URL` to the canonical URL where Notemod is available. Include the installation subdirectory, if any, and do not include a query or fragment:

```text
NM_PUBLIC_BASE_URL=https://notes.example.com/notemod
```

Password-reset links use only this configured URL and are never generated from the request `Host` header. If it is not configured, reset mail delivery is skipped and the omission is recorded in `logs/forgot_password.log` without exposing account existence to the requester.

The media screen also uses this URL for its server-side API calls. If the public address is not reachable from the server itself, set a separate internal address:

```text
NM_INTERNAL_BASE_URL=http://127.0.0.1/notemod
```

`NM_INTERNAL_BASE_URL` is optional and falls back to `NM_PUBLIC_BASE_URL`. Both values must be absolute `http` or `https` URLs without credentials, query parameters, fragments, or parent-directory segments. API URLs shown in the browser are completed using the configured public URL, or the browser's own origin when it is unset.

---

## Initial setup

### 1. Upload to the server
Upload the full repository contents to your public folder.

### 2. First access
Access `setup_auth.php` / `index.php` and complete the initial setup.

`setup_auth.php` manages the following settings:

- Initial user
- Password
- **Authentication email address**
- Initial pre-sync-save backup settings if needed
- API token-related settings if needed

Main files automatically generated as needed:

- `config/<DIR_USER>/auth.php`
- `config/<DIR_USER>/config.php`
- `config/<DIR_USER>/config.api.php`
- `notemod-data/<DIR_USER>/data.json`
- `notemod-data/<DIR_USER>/.htaccess`
- `logs/<DIR_USER>/.htaccess`
- `api/.htaccess`

`config/mail.php` is created when SMTP settings are saved.  
`image_index.json` / `file_index.json` are generated and updated when images or files are added.

---

## Configuration files

### Shared settings
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
- `MAX_IMAGE_UPLOAD_BYTES`
- `MAX_FILE_UPLOAD_BYTES`
- `MAX_IMAGE_DIMENSION`
- `MAX_IMAGE_PIXELS`
- `MAX_RESIZE_DIMENSION`
- `MAX_RESIZE_PIXELS`
- `DATA_ENCRYPTION_ENABLED`
- `DATA_ENCRYPTION_KEY`
- `SYNC_PRE_SAVE_BACKUP_ENABLED`
- `SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED`

Uploads and image decoding are also limited by the application before files are stored or GD allocates image buffers. Defaults are 10 MiB for images, 25 MiB for other files, 10,000 px / 25 megapixels for source images, and 2,000 px / 4 megapixels for resized images. PHP's `upload_max_filesize` and `post_max_size` remain separate limits; the lowest applicable limit wins.

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

### Authentication settings
`config/<DIR_USER>/auth.php`

Main keys:

- `USERNAME`
- `DIR_USER`
- `PASSWORD_HASH`
- `EMAIL`
- `UPDATED_AT`
- `PASSWORD_RESET_TOKEN`
- `PASSWORD_RESET_TOKEN_HASH`
- `PASSWORD_RESET_TOKEN_EXPIRES_AT`

### Shared mail settings
`config/mail.php`

Main keys:

- `MAIL_TRANSPORT`
- `SMTP_ENABLED`
- `SMTP_HOST`
- `SMTP_PORT`
- `SMTP_ENCRYPTION`
- `SMTP_AUTH`
- `SMTP_USERNAME`
- `SMTP_PASSWORD`
- `SMTP_FROM`
- `SMTP_FROM_NAME`
- `SMTP_FALLBACK_TO_MAIL`
- `UPDATED_AT`

---

## Backups

### Manual backup
- You can run **Back up now** from `bak_settings.php`
- You can also run it via `api/cleanup_api.php?action=backup_now`

### Cleanup backup
- If `CLEANUP_BACKUP_ENABLED` is enabled, a backup is created before dangerous cleanup operations
- `CLEANUP_BACKUP_KEEP` controls how many backups are kept

### Pre-sync-save backup in the Web UI
- If `SYNC_PRE_SAVE_BACKUP_ENABLED` is enabled, a backup is created immediately before actual save during Web UI sync save
- If `SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED` is also enabled, **old backups are pruned** immediately before that
- `CLEANUP_BACKUP_KEEP` is used to determine how many old backups are kept
- The pruning logic is the same as **Delete to keep latest n backups** in `bak_settings.php`

### Backup deletion rule
- Backup lists are sorted in **newest-first order (by file modification time)**
- The newest `n` files are kept, and the rest are deleted
- If `n=0`, all are deleted
- Plaintext and encrypted backups are judged together

---

## Media indexes

### `file_index.json`
- An index that keeps the list of currently existing files
- Incrementally updated when files are added by `api/api.php`
- Regenerated after deletion or purge by `api/cleanup_api.php`
- Each item has `lock` to preserve deletion-exclusion state

### `image_index.json`
- An index that keeps the list of currently existing images
- Incrementally updated when images are added by `api/api.php`
- Regenerated after deletion or purge by `api/cleanup_api.php`
- Each item has `lock` to preserve deletion-exclusion state

### `lock`
- If `true`, the image / file is **locked**
- Locked items are excluded from deletion targets
- If `false`, the item is unlocked and deletable as before

---

## Security

### BASIC authentication is strongly recommended
If possible, configure BASIC authentication for `api/`.

### Web UI authentication
If BASIC authentication is not available, you can still achieve a reasonable level of security by operating with Web UI authentication using `setup_auth.php`, `login.php`, and `logout.php`.

### Additional Web UI protections
The following additional protections are applied:

- response-type-specific security headers for HTML, API/text, and binary responses, including a Content Security Policy (CSP)
- centralized common configuration defaults shared by setup, settings screens, APIs, and logging
- CSRF protection
- rate limiting for `login.php` / `forgot_password.php` / `reset_password.php`
- shared/exclusive file locking and atomic replacement for concurrent rate-limit state updates
- per-index exclusive locking from read through atomic replacement for concurrent upload, media-lock, and cleanup updates
- one shared 10-character Unicode-aware minimum-password check for initial setup, account changes, and password resets
- audit logging
- session regeneration on successful login via `session_regenerate_id(true)`
- reduced plain-text exposure of API tokens in `setup_auth.php`, `clipboard_sync.php`, and `media_files.php`

### `data.json` encryption
- When `DATA_ENCRYPTION_ENABLED` is `true`, `data.json` is stored encrypted
- Export is always **plain JSON**
- If you lose the encryption key, you cannot decrypt the data

### SMTP password
- `SMTP_PASSWORD` in `config/mail.php` is stored in plain text
- Prefer an `NM_STORAGE_ROOT` outside the public directory so that `config/mail.php` cannot be served directly

### Audit log
- Path: `<NM_STORAGE_ROOT>/logs/audit.log` (`logs/audit.log` when `NM_STORAGE_ROOT` is unset)
- Format: JSON Lines
- Secret values such as passwords, API tokens, SECRET, and SMTP password are not recorded

---

## API overview

### `api/api.php`
- Add text
- Upload images
- Upload files
- Auto-create categories if needed
- Update `note_latest.json`
- Update `image_index.json` / `file_index.json`

### `api/read_api.php`
- Read-only
- `latest_note`
- `latest_clip_type`
- `latest_image`
- `latest_file`

> When calling the API, using **`user=<DIR_USER>`** is recommended

### `api/cleanup_api.php`
- Delete by category
- `dry_run`
- Delete backups
- Delete logs
- Bulk delete images / files
- Regenerate `image_index.json` / `file_index.json`
- Update media lock state

### `api/image_api.php`
- Serve authenticated images
- Simple resizing
- Supports user resolution via `user` / `dir_user` / `username`
- Accepts a same-user Web UI session, `Authorization: Bearer <EXPECTED_TOKEN>`, or `X-Notemod-Token: <EXPECTED_TOKEN>`
- Does not accept API tokens in the URL query string
- Returns `Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0` so browsers and shared proxies do not retain protected image responses
- Keeps resized derivatives in a server-side `.cache` directory; this disk cache is never a public HTTP cache policy

Example:

```bash
curl -H 'Authorization: Bearer YOUR_EXPECTED_TOKEN' \
  'https://notes.example.com/notemod/api/image_api.php?user=YOUR_DIR_USER&file=photo.png' \
  --output photo.png
```

### `api/append_api.php`
- Append to the end of an existing note
- Target can be specified by `category + note` or `target_note_id`
- Insert date / time / datetime / category name / note name
- `prefix` / `suffix`
- `dry_run`
- Returns text/plain when `pretty` is omitted

### `api/search_api.php`
- Search category names / note titles / note bodies
- `type`
- `q`
- `match`
- `limit`
- `snippet`
- `category` filter
- Retrieve `note_id`

### `api/journal_api.php`
- Date-based / monthly / weekly / fixed-note append
- `mode=date|month|week|fixed`
- `template=journal|log|plain|task`
- Auto-create categories / notes
- Insert weekday
- `dry_run`
- Returns text/plain when `pretty` is omitted

---

## Logs / session / mail settings

What `log_settings.php` handles:

- File log ON/OFF
- Notemod Logs category log ON/OFF
- `SESSION_COOKIE_LIFETIME`
- Display of `session.gc_maxlifetime`
- IP access notification settings
- **Reflect authentication email** button
- **SMTP settings (expand/collapse)**
- **SMTP test send**

What `bak_settings.php` handles:

- **Enable pre-sync-save backup (SYNC_PRE_SAVE_BACKUP_ENABLED)**
- **Prune old backups before sync save (SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED)**
- **Enable backup (CLEANUP_BACKUP_ENABLED)**
- **Keep latest n backups / n=0 deletes all (CLEANUP_BACKUP_KEEP)**
- Back up now
- Restore backups

What `media_files.php` handles:

- Image list
- File list
- Media deletion
- **Lock / unlock toggle**
- Excluding locked media from deletion
- Upload / cleanup / lock / download through a relay design that does not expose tokens to the browser

What `clipboard_sync.php` handles:

- ClipboardSync download links
- API URL copy
- **Initially masked API token display**
- **Temporary reveal for 10 seconds only when unlocked**
- **Copy only while visible**
- **CSRF-protected token reveal**

Description:
> This is the browser-side retention period. Depending on server-side settings, login may expire earlier.

---

## Links

- [StayHomeLab YouTube ch](https://www.youtube.com/@StayHomeLab)
- [Website](https://stayhomelab.net/notemod-selfhosted-en)
- [ClipboardSync](https://github.com/StayHomeLabNet/ClipboardSync)

---

## Notes

- APIs and cleanup are expected to **always reference `config/<DIR_USER>/config.api.php`**
- Do not revert to the old `/config/config.api.php`-based design
- In `config.php`,
  - `SYNC_PRE_SAVE_BACKUP_ENABLED`
  - `SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED`
  are for controlling pre-sync-save backups in the Web UI
- In `config.api.php`,
  - `CLEANUP_BACKUP_ENABLED`
  - `CLEANUP_BACKUP_KEEP`
  are for cleanup backup control and keep-count control
- `config/mail.php` is shared by all users
- If you use SMTP, verify consistency between the sender address, SPF / DKIM, and SMTP authentication
- `lock` in `file_index.json` / `image_index.json` is used to preserve deletion-exclusion state for each media item
- Items with `lock=true` are excluded from cleanup and deletion operations in `media_files.php`
- `setup_auth.php` does not show existing API tokens in plain text
- `clipboard_sync.php` / `media_files.php` are designed not to directly expose API token values to the browser
- Even when handling broken legacy `data.json` formats, the current code is intended to normalize as much as possible before saving
- `append_api.php` / `search_api.php` / `journal_api.php` are designed to return **human-readable text/plain** equivalent to `pretty=2` when unspecified


---

## License

This project is released under the MIT License.  
The upstream Notemod project is also released under the MIT License.
