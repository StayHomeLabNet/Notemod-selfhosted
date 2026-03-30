# Notemod-selfhosted v1.4.5

This is a fork based on **[Notemod (upstream)](https://github.com/orayemre/Notemod)** (MIT License), extended as a **self-hosted memo platform that can also run on shared hosting**.  
It does not require a database, and uses **`notemod-data/<DIR_USER>/data.json`** as its single data source.

It is developed to **facilitate the exchange of text, images, and files between a Windows PC and an iPhone** without depending on external services. It can also serve as an alternative to note services such as simplenote.com.  

Shared hosting services confirmed to work: Xserver, Sakura Internet, XREA, InfinityFree  
Tested PHP version: 8.3.21 (**PHP 8.1 or later is required**)

> **Single data source:** `notemod-data/<DIR_USER>/data.json`

---

## Particularly important points in this update

- **Per-user configuration files**
  - `config/<DIR_USER>/config.php`
  - `config/<DIR_USER>/config.api.php`
  - `config/<DIR_USER>/auth.php`
- **Mail settings shared by all users**
  - `config/mail.php`
- **Main data**
  - `notemod-data/<DIR_USER>/data.json`
- **Authentication email address**
  - Required input in `setup_auth.php`
  - Saved to `EMAIL` in `auth.php`
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
  - Centrally managed by the common mail-sending foundation in `auth_common.php`
- **Backup naming**
  - Plaintext: `data.json.bak-YYYYMMDD-HHMMSS`
  - Encrypted: `data.enc.json.bak-YYYYMMDD-HHMMSS`
- **Pre-sync-save backup settings**
  - `SYNC_PRE_SAVE_BACKUP_ENABLED`
  - `SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED`
  - Can control the Web UI pre-sync-save backup and the pruning of old backups immediately before it

---

## Main additions and improvements in v1.4.5

### 1. Made the Web UI pre-sync-save backup configurable
- Added **`SYNC_PRE_SAVE_BACKUP_ENABLED`** to `config/<DIR_USER>/config.php`
- You can now enable / disable the **backup created immediately before saving** during Web UI sync save
- If unset, it is treated as **enabled** for backward compatibility
- Can be changed ON / OFF from `bak_settings.php`

### 2. Added automatic pruning immediately before the pre-sync-save backup
- Added **`SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED`** to `config/<DIR_USER>/config.php`
- When `SYNC_PRE_SAVE_BACKUP_ENABLED=true` and `SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED=true`,
  automatic pruning of existing backups is executed **immediately before creating the pre-sync-save backup**
- The pruning logic works the same way as **“Delete” under “Keep latest n backups”** in `bak_settings.php`
- The number of backups to keep uses **`CLEANUP_BACKUP_KEEP`** in `config/<DIR_USER>/config.api.php`

### 3. Added pre-sync-save backup settings UI to `bak_settings.php`
- Added **“Enable pre-sync-save backup (SYNC_PRE_SAVE_BACKUP_ENABLED)”** to the Backups section
- Under it, as a slightly indented child item, added
  **“Prune old backups before pre-sync-save backup (SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED)”**
- These can now be configured separately from the existing `CLEANUP_BACKUP_ENABLED` / `CLEANUP_BACKUP_KEEP`

### 4. Improved the save flow in `notemod_sync.php`
- When there is a difference and an actual save is required, processing now runs in the following order:
  1. Check `SYNC_PRE_SAVE_BACKUP_ENABLED`
  2. If necessary, prune old backups according to `SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED`
  3. Create the pre-sync-save backup
  4. Save the new `data.json`
- This makes it possible to use the pre-sync-save backup while also suppressing backup growth

### 5. Updated `setup_auth.php` / sample config files
- Updated newly generated `config.php` to include:
  - `SYNC_PRE_SAVE_BACKUP_ENABLED`
  - `SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED`
- Added the new settings to `config.sample.php` / `config.sample.ja.php` as well

### 6. Features up to v1.4.4 are also continued
- Saving authentication email addresses
- Password reset
- Common mail sending foundation
- Shared mail settings for all users via `config/mail.php`
- SMTP settings UI / test sending
- `append_api.php`
- `search_api.php`
- `journal_api.php`
- **Snapshot normalization** before sync save
- `.txt` / `.json` import support
- Countermeasures against stringification corruption of `categories` / `notes`
- `SESSION_COOKIE_LIFETIME` support
- Strengthened XSS countermeasures in `index.php`
- Optional encrypted saving of `data.json`

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
  images/
  files/
/logs/<DIR_USER>/
```

---

## Initial setup

### 1. Upload to the server
Upload the entire repository to your public folder.

### 2. First access
Access `setup_auth.php` / `index.php` and perform the initial setup.

In v1.4.5, `setup_auth.php` configures the following:
- Initial user
- Password
- **Authentication email address**
- Initial settings related to pre-sync-save backups, if needed

Main files automatically generated as needed:

- `config/<DIR_USER>/auth.php`
- `config/<DIR_USER>/config.php`
- `config/<DIR_USER>/config.api.php`
- `notemod-data/<DIR_USER>/data.json`
- `notemod-data/<DIR_USER>/.htaccess`
- `logs/<DIR_USER>/.htaccess`
- `api/.htaccess`

`config/mail.php` is created when SMTP settings are saved.

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

### Backup for cleanup
- If `CLEANUP_BACKUP_ENABLED` is enabled, a backup is created before dangerous cleanup operations
- The number of backups to keep can be controlled with `CLEANUP_BACKUP_KEEP`

### Web UI pre-sync-save backup
- If `SYNC_PRE_SAVE_BACKUP_ENABLED` is enabled, a backup is created immediately before the actual save in Web UI sync save
- If `SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED` is also enabled, **old backup pruning** is executed immediately before that
- `CLEANUP_BACKUP_KEEP` is used for the count judgment of old backup pruning
- The pruning logic is the same as **“Delete” under “Keep latest n backups”** in `bak_settings.php`

### Criteria for deleting backups
- The backup list is sorted in **newest-first order (file modification time order)**
- The first `n` items are kept, and the rest are deleted
- If `n=0`, all backups are deleted
- Plaintext backups and encrypted backups are judged together

---

## Security

### Basic authentication is strongly recommended
If possible, set up Basic authentication for `api/`.

### Web UI authentication
If Basic authentication cannot be used, you can secure a certain level of security by operating with Web UI authentication using `setup_auth.php` and `login.php` / `logout.php`.

### `data.json` encryption
- When `DATA_ENCRYPTION_ENABLED` is `true`, `data.json` is saved encrypted
- Export is always **fixed to plaintext JSON**
- If you lose the encryption key, it cannot be decrypted

### SMTP password
- `SMTP_PASSWORD` in `config/mail.php` is stored in plaintext
- Operate on the assumption that `config/mail.php` is placed in a non-public location

---

## API overview

### `api/api.php`
- Add text
- Upload images
- Upload files
- Automatically create categories when necessary
- Update `note_latest.json`

### `api/read_api.php`
- Read-only
- `latest_note`
- `latest_clip_type`
- `latest_image`
- `latest_file`

> When calling the API, it is recommended to include **`user=<DIR_USER>`**

### `api/cleanup_api.php`
- Delete by category
- `dry_run`
- Delete backups
- Delete logs
- Bulk delete images / files

### `api/image_api.php`
- Image delivery
- Simple resize
- Cache control

### `api/append_api.php`
- Append to the end of an existing note
- Specify the target by `category + note` or `target_note_id`
- Insert date / time / date and time / category name / note name
- `prefix` / `suffix`
- `dry_run`
- Returns `text/plain` when `pretty` is omitted

### `api/search_api.php`
- Search category names / note titles / body text
- `type`
- `q`
- `match`
- `limit`
- `snippet`
- `category` filter
- Get `note_id`

### `api/journal_api.php`
- Date-based / monthly / weekly / fixed-note append
- `mode=date|month|week|fixed`
- `template=journal|log|plain|task`
- Automatic category / note creation
- Insert weekday
- `dry_run`
- Returns `text/plain` when `pretty` is omitted

---

## Log / session / mail settings

Items handled in `log_settings.php`:

- File log ON/OFF
- Notemod Logs category log ON/OFF
- `SESSION_COOKIE_LIFETIME`
- Confirmation display of `session.gc_maxlifetime`
- IP access notification settings
- **Reflect authentication email** button
- **SMTP settings (collapsible)**
- **SMTP test sending**

Items handled in `bak_settings.php`:

- **Enable pre-sync-save backup (SYNC_PRE_SAVE_BACKUP_ENABLED)**
- **Prune old backups before pre-sync-save backup (SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED)**
- **Enable backup (CLEANUP_BACKUP_ENABLED)**
- **Keep latest n backups / n=0 deletes all (CLEANUP_BACKUP_KEEP)**
- Back up now
- Restore backup

Description:
> This is the retention period on the browser side. Depending on the server-side settings, the login may expire earlier than this.

---

## Links

- [StayHomeLab YouTube ch](https://www.youtube.com/@StayHomeLab)
- [Website](https://stayhomelab.net/notemod-selfhosted-en)
- [ClipboardSync](https://github.com/StayHomeLabNet/ClipboardSync)

---

## Notes

- APIs and cleanup are based on the assumption that they **always refer to `config/<DIR_USER>/config.api.php`**
- Do not revert to the old specification that assumed `/config/config.api.php`
- In `config.php`,
  - `SYNC_PRE_SAVE_BACKUP_ENABLED`
  - `SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED`
  are for controlling the Web UI pre-sync-save backup
- In `config.api.php`,
  - `CLEANUP_BACKUP_ENABLED`
  - `CLEANUP_BACKUP_KEEP`
  are for cleanup-related backups and keep-count control
- `config/mail.php` is a shared setting for all users
- If you use SMTP, check the consistency of the sender address, SPF / DKIM, and SMTP authentication
- Even when handling a broken old-format `data.json`, the current code assumes it will normalize it as much as possible before saving
- `append_api.php` / `search_api.php` / `journal_api.php` are designed to return **human-readable text/plain** equivalent to `pretty=2` when omitted
