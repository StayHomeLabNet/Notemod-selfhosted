# Notemod-selfhosted v1.4.6

This is a fork based on **[Notemod (upstream)](https://github.com/orayemre/Notemod)** (MIT License), extended as a **self-hosted note platform that can run on shared hosting environments**.  
No database is required, and **`notemod-data/<DIR_USER>/data.json`** is used as the single data source.

It is developed to **smoothly exchange text, images, and files between Windows PCs and iPhones** without relying on external services. It can also serve as an alternative to note services such as simplenote.com.

Verified shared hosting environments: Xserver, Sakura Internet, XREA, InfinityFree  
Tested PHP: 8.3.21 (**PHP 8.1 or later is required**)

> **Single data source:** `notemod-data/<DIR_USER>/data.json`

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
- Stored in **`logs/system/audit.log`** (JSON Lines)
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
/logs/system/
  audit.log
```

---

## Initial setup

### 1. Upload to the server
Upload the full repository contents to your public folder.

### 2. First access
Access `setup_auth.php` / `index.php` and complete the initial setup.

In v1.4.6, `setup_auth.php` sets:

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
- `DATA_ENCRYPTION_ENABLED`
- `DATA_ENCRYPTION_KEY`
- `SYNC_PRE_SAVE_BACKUP_ENABLED`
- `SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED`

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
v1.4.6 introduces the following extra protections:

- security headers
- CSRF protection
- rate limiting for `login.php` / `forgot_password.php` / `reset_password.php`
- audit logging
- session regeneration on successful login via `session_regenerate_id(true)`
- reduced plain-text exposure of API tokens in `setup_auth.php`, `clipboard_sync.php`, and `media_files.php`

### `data.json` encryption
- When `DATA_ENCRYPTION_ENABLED` is `true`, `data.json` is stored encrypted
- Export is always **plain JSON**
- If you lose the encryption key, you cannot decrypt the data

### SMTP password
- `SMTP_PASSWORD` in `config/mail.php` is stored in plain text
- Operate on the assumption that `config/mail.php` is not publicly accessible

### Audit log
- Path: `logs/system/audit.log`
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
- Serve images
- Simple resizing
- Cache control
- Supports user resolution via `user` / `dir_user` / `username`

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
