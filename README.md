# Notemod-selfhosted v1.4.3

This is a fork based on **[Notemod (upstream)](https://github.com/orayemre/Notemod)** (MIT License), expanded as a **self-hosted memo platform that can run even on shared servers**.  
No database is required, and it uses **`notemod-data/<DIR_USER>/data.json`** as the single data source.

Verified shared hosting servers: Xserver, Sakura Internet, XREA, InfinityFree  
Tested PHP: 8.3.21 (**PHP 8.1 or higher required**)

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
  - Plain text: `data.json.bak-YYYYMMDD-HHMMSS`
  - Encrypted: `data.enc.json.bak-YYYYMMDD-HHMMSS`

---

## Main additions and improvements in v1.4.3

### 1. Added the new append API `append_api.php`
- Added an API that can safely append to the end of an existing note
- The append destination can be specified by either:
  - `category + note`
  - `target_note_id`
- The following can be inserted before or after the append body `text` as needed:
  - date
  - time
  - datetime
  - category name
  - note name
- Supports labeled insertion:
  - `label_date`
  - `label_time`
  - `label_datetime`
  - `label_category`
  - `label_note`
- Supports adding fixed strings before and after using `prefix` / `suffix`
- `dry_run=1` allows previewing without saving
- When `pretty` is omitted, it returns **text/plain** in an easy-to-read format
- `pretty=1` returns formatted JSON, and `pretty=2` returns text display

### 2. Added the new search API `search_api.php`
- Added an API that can search across Notemod categories / note titles / content
- Search targets can be switched by `type`:
  - `all`
  - `note_title`
  - `category`
  - `content`
- Specify search terms with `q`
- Specify matching method with `match=partial|exact`
- Limit the number of results with `limit`
- Filter by category with `category`
- Display content excerpts with `snippet` / `snippet_length`
- Can retrieve `note_id` from search results and link it with `append_api.php`’s `target_note_id`
- When `pretty` is omitted, it returns **text/plain** in an easy-to-read format

### 3. Added the new journal API `journal_api.php`
- Added a high-level API for diaries / daily reports / work logs
- Automatically determines the append destination note according to `mode`:
  - `date`
  - `month`
  - `week`
  - `fixed`
- Automatically creates categories or notes as needed:
  - `create_category_if_missing`
  - `create_if_missing`
- Supports fixed-format recording by `template`:
  - `journal`
  - `log`
  - `plain`
  - `task`
- Supports adding weekdays with `insert_weekday=1` and `weekday_lang=ja|en`
- `dry_run=1` allows previewing without saving
- When `pretty` is omitted, it returns **text/plain** in an easy-to-read format

### 4. Organized the role separation among existing API groups
- `api/api.php`
  - Add-type API (text / image / file)
- `api/read_api.php`
  - Read-type API
- `api/image_api.php`
  - Image delivery API
- `api/cleanup_api.php`
  - Cleanup / delete / backup API
- `api/append_api.php`
  - Existing note append API
- `api/search_api.php`
  - Search API
- `api/journal_api.php`
  - Date-based recording API

### 5. The stabilization content up to v1.4.2 also continues
- **Snapshot normalization** before sync save
- Support for `.txt` / `.json` import
- Countermeasures against stringification corruption of `categories` / `notes`
- Support for `SESSION_COOKIE_LIFETIME`
- Support for log / session settings in `log_settings.php`
- Strengthened XSS protection in `index.php`
- Optional encrypted storage of `data.json`

---

## Directory structure

```text
/index.php
/setup_auth.php
/login.php
/logout.php
/account.php
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
Upload the full repository set to the public folder.

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
If possible, set Basic Authentication on `api/`.

### Web UI authentication
If Basic Authentication cannot be used, operating with Web UI authentication using `setup_auth.php` and `login.php` / `logout.php` can provide a certain level of security.

### `data.json` encryption
- When `DATA_ENCRYPTION_ENABLED` is `true`, `data.json` is stored encrypted
- Export is always **plain JSON**
- If the encryption key is lost, it cannot be decrypted

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

> It is recommended to operate API calls with **`user=<DIR_USER>` attached**

Example of `latest_note`:

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
- Image delivery
- Simple resizing
- Cache control

### `api/append_api.php`
- Append to the end of an existing note
- Specify the target by `category + note` or `target_note_id`
- Insert date / time / datetime / category name / note name
- `prefix` / `suffix`
- `dry_run`
- `text/plain` when `pretty` is omitted

### `api/search_api.php`
- Category name / note title / content search
- `type`
- `q`
- `match`
- `limit`
- `snippet`
- `category` filter
- `note_id` retrieval

### `api/journal_api.php`
- Date-based / monthly / weekly / fixed-note append
- `mode=date|month|week|fixed`
- `template=journal|log|plain|task`
- Automatic category / note creation
- Weekday insertion
- `dry_run`
- `text/plain` when `pretty` is omitted

---

## Overview of append_api.php

### Main parameters
- `token`
- `text`
- `category`
- `note`
- `target_note_id`
- `insert_date`
- `insert_time`
- `insert_datetime`
- `insert_category`
- `insert_note`
- `label_date`
- `label_time`
- `label_datetime`
- `label_category`
- `label_note`
- `prefix`
- `suffix`
- `source_category`
- `source_note`
- `source_pos`
- `dry_run`
- `pretty`

### Main uses
- Appending to existing notes
- Safe appending by specifying `target_note_id`
- Flexible template-like appending
- Preview before saving

---

## Overview of search_api.php

### Main parameters
- `token`
- `q`
- `type=all|note_title|category|content`
- `category`
- `limit`
- `match=partial|exact`
- `case_sensitive`
- `snippet`
- `snippet_length`
- `include_content`
- `pretty`

### Main uses
- Retrieving note IDs
- Cross-category search
- Searching for `target_note_id` for append_api
- Content search

---

## Overview of journal_api.php

### Main parameters
- `token`
- `text`
- `category`
- `mode=date|month|week|fixed`
- `note`
- `create_if_missing`
- `create_category_if_missing`
- `template=journal|log|plain|task`
- `insert_weekday`
- `weekday_lang=ja|en`
- `date_format`
- `time_format`
- `datetime_format`
- `label_date`
- `label_time`
- `label_datetime`
- `prefix`
- `suffix`
- `dry_run`
- `pretty`

### Main uses
- Diaries
- Daily reports
- Work logs
- Weekly / monthly reports
- Fixed-format recording from shortcuts

---

## Backups

### Automatic backups
Backups are created at timings such as the following.

- Immediately before switching encryption settings
- Before sync save
- Before destructive cleanup operations (depending on settings)

### Naming rules
- Plain text: `data.json.bak-YYYYMMDD-HHMMSS`
- Encrypted: `data.enc.json.bak-YYYYMMDD-HHMMSS`

### Restore
You can restore from `bak_settings.php`.  
When restoring encrypted backups, the corresponding `DATA_ENCRYPTION_KEY` is required.

---

## Log / session settings

Items that can be handled in `log_settings.php`:

- File log ON/OFF
- Notemod Logs category log ON/OFF
- `SESSION_COOKIE_LIFETIME`
- Display of `session.gc_maxlifetime`

Description text:
> This is the retention period on the browser side. Depending on server-side settings, login may expire earlier than this.

---

## Links

- [StayHomeLab YouTube ch](https://www.youtube.com/@StayHomeLab)
- [Website](https://stayhomelab.net/notemod-selfhosted)
- [ClipboardSync](https://github.com/StayHomeLabNet/ClipboardSync)

---

## Notes

- APIs and cleanup are based on the assumption that they **always refer to `config/<DIR_USER>/config.api.php`**
- Do not revert to the old assumption of `/config/config.api.php`
- Even when handling broken old-format `data.json`, the current code is intended to normalize it as much as possible before saving
- `append_api.php` / `search_api.php` / `journal_api.php` are designed to return **human-readable text/plain** equivalent to `pretty=2` when omitted
