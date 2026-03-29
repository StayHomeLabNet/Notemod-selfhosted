# Notemod-selfhosted v1.4.4

This is a fork based on **[Notemod (original)](https://github.com/orayemre/Notemod)** (MIT License), extended as a **self-hosted memo platform that can run even on shared hosting**.  
No database is required, and it uses **`notemod-data/<DIR_USER>/data.json`** as the single data source.

It was developed to **smoothly exchange text, images, and files between a Windows PC and an iPhone** without relying on external services. It can also serve as an alternative to note services such as simplenote.com.  

Tested shared hosting providers: Xserver, Sakura Internet, XREA, InfinityFree  
Tested PHP: 8.3.21 (**PHP 8.1 or later is required**)

> **Single data source:** `notemod-data/<DIR_USER>/data.json`

---

## Particularly important points in this version

- **Per-user configuration files**
  - `config/<DIR_USER>/config.php`
  - `config/<DIR_USER>/config.api.php`
  - `config/<DIR_USER>/auth.php`
- **Shared mail settings for all users**
  - `config/mail.php`
- **Main data**
  - `notemod-data/<DIR_USER>/data.json`
- **Authentication email address**
  - Required in `setup_auth.php`
  - Saved to `EMAIL` in `auth.php`
- **Password reset**
  - `forgot_password.php`
  - `reset_password.php`
  - `config/<DIR_USER>/password_reset.json`
- **Encryption**
  - `DATA_ENCRYPTION_ENABLED`
  - `DATA_ENCRYPTION_KEY`
  - `data.json` can be encrypted with **AES-256-CBC + HMAC**
- **Session lifetime**
  - `SESSION_COOKIE_LIFETIME`
  - Can be changed from `log_settings.php`
- **Mail sending**
  - Supports both `mail()` and SMTP
  - Centrally managed by the shared sending infrastructure in `auth_common.php`
- **Backup naming**
  - Plaintext: `data.json.bak-YYYYMMDD-HHMMSS`
  - Encrypted: `data.enc.json.bak-YYYYMMDD-HHMMSS`

---

## Main additions and improvements in v1.4.4

### 1. Added support for saving an authentication email address
- Changed `setup_auth.php` so that an **email address is required**
- Authentication information is saved in array format to `config/<DIR_USER>/auth.php`
- Added saving of `EMAIL`
- Even if an existing `auth.php` does not have `EMAIL`, it can be added later while keeping login available
- `setup_auth.php` now behaves as follows:
  - Available without login during initial setup
  - Can only be changed by a logged-in user after authentication settings have been created

### 2. Added password reset functionality
- Added a **"Forgot your password?"** link to `login.php`
- Added `forgot_password.php`
  - Input is **username or email address**
  - Result message is always the same
- Added `reset_password.php`
  - Supports `reset_password.php?username=...&token=...`
  - On success, returns to `login.php?reset=success`
- Token save location:
  - `config/<DIR_USER>/password_reset.json`
- Token structure:
  - `token_hash`
  - `created_at`
  - `expires_at`
  - `used`
- Expiration time is **30 minutes**
- Issuing a new token invalidates the previous token
- The **10-character minimum** password requirement is also applied during reset

### 3. Can now apply the authentication email address from `log_settings.php`
- Added an **"Use auth email"** button next to the notification email field
- Can set the `EMAIL` from `auth.php` into the notification email field
- Displays a message when `EMAIL` is not set

### 4. Unified mail sending processing
- Implemented shared mail sending logic in `auth_common.php`
- Existing first-IP notification emails and password reset emails now use the same sending infrastructure
- Reorganized the structure so that `logger.php` / `forgot_password.php` do not call `mail()` directly, but send through a shared function
- Maintains compatibility with `IP_ALERT_FROM`

### 5. Added shared mail settings for all users via `config/mail.php`
- Mail settings including SMTP are managed **shared across all users**
- Save location is `config/mail.php`
- Main keys:
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

### 6. Added SMTP sending support
- Uses SMTP when `MAIL_TRANSPORT=smtp` and `SMTP_ENABLED=1`
- Uses `mail()` as before when SMTP is disabled
- Can be configured whether to fall back to `mail()` when SMTP fails
- **Custom implementation without PHPMailer**
- Supported range:
  - Plain SMTP
  - STARTTLS
  - SSL/TLS
  - AUTH LOGIN
- When `SMTP_FROM` is empty, `IP_ALERT_FROM` can be reused

### 7. Added SMTP settings UI and test sending to `log_settings.php`
- SMTP settings can now be edited from `log_settings.php`
- Because there are many items, the SMTP settings section is **hidden by default**
- Uses a collapsible UI that opens on click
- When opened, it is visually emphasized so it stands out more than the other settings
- Added SMTP test sending functionality
- SMTP password field has been hardened
  - Existing password is not redisplayed on the screen
  - Saving a blank value keeps the current value

### 8. Preserve `EMAIL` even when changing the password from `account.php`
- Improved the authentication settings save process in `auth_common.php`
- Fixed the issue so that `EMAIL` in `auth.php` is not lost even when changing the password in `account.php`

### 9. Continued the API expansion and stability improvements up to v1.4.3
- `append_api.php`
- `search_api.php`
- `journal_api.php`
- **Snapshot normalization** before sync save
- `.txt` / `.json` import support
- Measures against broken stringified `categories` / `notes`
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
Upload the full repository to the public folder.

### 2. First access
Access `setup_auth.php` / `index.php` and perform the initial setup.

In v1.4.4, `setup_auth.php` is used to configure the following:
- Initial user
- Password
- **Authentication email address**

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

### General settings
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

## Security

### Basic Authentication is strongly recommended
If possible, set up Basic Authentication for `api/`.

### Web UI authentication
If Basic Authentication cannot be used, a certain level of security can be ensured by operating with Web UI authentication using `setup_auth.php` and `login.php` / `logout.php`.

### `data.json` encryption
- When `DATA_ENCRYPTION_ENABLED` is `true`, `data.json` is saved in encrypted form
- Export is always **plain JSON**
- If you lose the encryption key, it cannot be decrypted

### SMTP password
- `SMTP_PASSWORD` in `config/mail.php` is stored in plain text
- Operate on the assumption that `config/mail.php` is placed in a non-public location

---

## API overview

### `api/api.php`
- Add text
- Upload images
- Upload files
- Automatically create categories if needed
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
- Deliver images
- Simple resize
- Cache control

### `api/append_api.php`
- Append to the end of an existing note
- Specify target by `category + note` or `target_note_id`
- Insert date / time / datetime / category name / note name
- `prefix` / `suffix`
- `dry_run`
- `text/plain` when `pretty` is omitted

### `api/search_api.php`
- Search category names / note titles / note content
- `type`
- `q`
- `match`
- `limit`
- `snippet`
- Filter by `category`
- Get `note_id`

### `api/journal_api.php`
- Date-based / monthly / weekly / fixed-note append
- `mode=date|month|week|fixed`
- `template=journal|log|plain|task`
- Auto-create categories / notes
- Insert weekday
- `dry_run`
- `text/plain` when `pretty` is omitted

---

## Log / session / mail settings

Items handled in `log_settings.php`:

- File log ON/OFF
- Notemod Logs category log ON/OFF
- `SESSION_COOKIE_LIFETIME`
- Display check for `session.gc_maxlifetime`
- IP access notification settings
- **Use auth email** button
- **SMTP settings (collapsible)**
- **SMTP test sending**

Description:
> This is the retention period on the browser side. Depending on the server-side settings, the login may expire earlier than this.

---

## Links

- [StayHomeLab YouTube ch](https://www.youtube.com/@StayHomeLab)
- [Website](https://stayhomelab.net/notemod-selfhosted)
- [ClipboardSync](https://github.com/StayHomeLabNet/ClipboardSync)

---

## Notes

- API and cleanup operations are based on always referring to **`config/<DIR_USER>/config.api.php`**
- Do not revert to the old `/config/config.api.php` assumption
- `config/mail.php` is a shared setting for all users
- When using SMTP, check the consistency of the sender address, SPF / DKIM, and SMTP authentication
- Even when handling broken old-style `data.json`, the current code is designed to normalize it as much as possible before saving
- `append_api.php` / `search_api.php` / `journal_api.php` are designed to return **human-readable `text/plain`** equivalent to `pretty=2` when omitted
