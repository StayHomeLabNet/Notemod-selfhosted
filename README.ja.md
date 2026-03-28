# Notemod-selfhosted v1.4.3

これは **[Notemod（本家）](https://github.com/orayemre/Notemod)**（MIT License）をベースに、**共用サーバーでも動く自己ホスト型メモ基盤**として拡張したフォークです。  
DB は不要で、単一データソースとして **`notemod-data/<DIR_USER>/data.json`** を使います。

動作確認済みの共用サーバー: Xサーバー、さくらインターネット、XREA、InfinityFree  
テスト済み PHP: 8.3.21（**PHP 8.1 以上必須**）

> **単一データソース:** `notemod-data/<DIR_USER>/data.json`

---

## この版で特に重要なポイント

- **ユーザーごとの設定ファイル**
  - `config/<DIR_USER>/config.php`
  - `config/<DIR_USER>/config.api.php`
- **本体データ**
  - `notemod-data/<DIR_USER>/data.json`
- **暗号化**
  - `DATA_ENCRYPTION_ENABLED`
  - `DATA_ENCRYPTION_KEY`
  - `data.json` を **AES-256-CBC + HMAC** で暗号化可能
- **セッション保持期間**
  - `SESSION_COOKIE_LIFETIME`
  - `log_settings.php` から変更可能
- **バックアップ命名**
  - 平文: `data.json.bak-YYYYMMDD-HHMMSS`
  - 暗号化: `data.enc.json.bak-YYYYMMDD-HHMMSS`

---

## v1.4.3 の主な追加・改善

### 1. 新しい追記 API `append_api.php` を追加
- 既存ノートの末尾へ、安全に追記できる API を追加
- 追記先は
  - `category + note`
  - `target_note_id`
  のどちらでも指定可能
- 追記本文 `text` の前後に、必要に応じて以下を挿入可能
  - 日付
  - 時刻
  - 日時
  - カテゴリ名
  - ノート名
- ラベル付き挿入に対応
  - `label_date`
  - `label_time`
  - `label_datetime`
  - `label_category`
  - `label_note`
- `prefix` / `suffix` による固定文字列の前後追加に対応
- `dry_run=1` で保存せずにプレビュー確認可能
- `pretty` 未指定時は **text/plain** で見やすく返却
- `pretty=1` で整形 JSON、`pretty=2` でテキスト表示

### 2. 新しい検索 API `search_api.php` を追加
- Notemod のカテゴリ / ノートタイトル / 本文を横断検索できる API を追加
- `type` により検索対象を切り替え可能
  - `all`
  - `note_title`
  - `category`
  - `content`
- `q` による検索語指定
- `match=partial|exact` による一致方法指定
- `limit` による件数制限
- `category` によるカテゴリ絞り込み
- `snippet` / `snippet_length` による本文抜粋表示
- 検索結果から `note_id` を取得し、`append_api.php` の `target_note_id` と連携可能
- `pretty` 未指定時は **text/plain** で見やすく返却

### 3. 新しいジャーナル API `journal_api.php` を追加
- 日記 / 日報 / 作業ログ向けの高水準 API を追加
- `mode` に応じて追記先ノートを自動決定
  - `date`
  - `month`
  - `week`
  - `fixed`
- 必要に応じてカテゴリやノートを自動作成
  - `create_category_if_missing`
  - `create_if_missing`
- `template` による定型記録に対応
  - `journal`
  - `log`
  - `plain`
  - `task`
- `insert_weekday=1` と `weekday_lang=ja|en` による曜日付与に対応
- `dry_run=1` で保存せずにプレビュー確認可能
- `pretty` 未指定時は **text/plain** で見やすく返却

### 4. 既存 API 群との役割分担を整理
- `api/api.php`
  - 追加系 API（テキスト / 画像 / ファイル）
- `api/read_api.php`
  - 読み取り系 API
- `api/image_api.php`
  - 画像配信 API
- `api/cleanup_api.php`
  - 整理 / 削除 / バックアップ API
- `api/append_api.php`
  - 既存ノート追記 API
- `api/search_api.php`
  - 検索 API
- `api/journal_api.php`
  - 日付ベース記録 API

### 5. v1.4.2 までの安定化内容も継続
- sync save 前の **スナップショット正規化**
- `.txt` / `.json` インポート対応
- `categories` / `notes` の文字列化崩れ対策
- `SESSION_COOKIE_LIFETIME` 対応
- `log_settings.php` のログ / セッション設定対応
- `index.php` の XSS 対策強化
- `data.json` の任意暗号化保存

---

## ディレクトリ構成

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

## 初期設定

### 1. サーバーへ配置
リポジトリ一式を公開フォルダへアップロードします。

### 2. 初回アクセス
`setup_auth.php` / `index.php` へアクセスし、初回セットアップを行います。

必要に応じて自動生成される主なファイル:

- `config/<DIR_USER>/config.php`
- `config/<DIR_USER>/config.api.php`
- `notemod-data/<DIR_USER>/data.json`
- `notemod-data/<DIR_USER>/.htaccess`
- `logs/<DIR_USER>/.htaccess`
- `api/.htaccess`

---

## 設定ファイル

### 共通設定
`config/<DIR_USER>/config.php`

主なキー:

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

### API設定
`config/<DIR_USER>/config.api.php`

主なキー:

- `EXPECTED_TOKEN`
- `ADMIN_TOKEN`
- `DATA_JSON`
- `DEFAULT_COLOR`
- `CLEANUP_BACKUP_ENABLED`
- `CLEANUP_BACKUP_SUFFIX`
- `CLEANUP_BACKUP_KEEP`

---

## セキュリティ

### Basic認証を強く推奨
可能なら `api/` に Basic認証を設定してください。

### Web UI認証
Basic認証が使えない場合は、`setup_auth.php` と `login.php` / `logout.php` を使った Web UI認証で運用することで、一定のセキュリティを確保できます。

### `data.json` 暗号化
- `DATA_ENCRYPTION_ENABLED` が `true` のとき、`data.json` は暗号化保存されます
- エクスポートは **平文 JSON 固定** です
- 暗号化キーを失うと復号できません

---

## API の概要

### `api/api.php`
- テキスト追加
- 画像アップロード
- ファイルアップロード
- 必要ならカテゴリ自動作成
- `note_latest.json` 更新

### `api/read_api.php`
- 読み取り専用
- `latest_note`
- `latest_clip_type`
- `latest_image`
- `latest_file`

> API 呼び出し時は **`user=<DIR_USER>` を付ける運用を推奨** します

`latest_note` の例:

- JSONで取得  
  `...?token=...&user=USER_NAME&action=latest_note&pretty=1`
- 本文だけ取得  
  `...?token=...&user=USER_NAME&action=latest_note&pretty=2`

### `api/cleanup_api.php`
- カテゴリ単位削除
- `dry_run`
- バックアップ削除
- ログ削除
- 画像 / ファイルの一括削除

### `api/image_api.php`
- 画像配信
- 簡易リサイズ
- キャッシュ制御

### `api/append_api.php`
- 既存ノートの末尾に追記
- `category + note` または `target_note_id` で対象指定
- 日付 / 時刻 / 日時 / カテゴリ名 / ノート名の挿入
- `prefix` / `suffix`
- `dry_run`
- `pretty` 未指定で text/plain

### `api/search_api.php`
- カテゴリ名 / ノートタイトル / 本文検索
- `type`
- `q`
- `match`
- `limit`
- `snippet`
- `category` 絞り込み
- `note_id` 取得

### `api/journal_api.php`
- 日付ベース / 月次 / 週次 / 固定ノート追記
- `mode=date|month|week|fixed`
- `template=journal|log|plain|task`
- カテゴリ / ノート自動作成
- 曜日挿入
- `dry_run`
- `pretty` 未指定で text/plain

---

## append_api.php の概要

### 主なパラメータ
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

### 主な用途
- 既存ノートへの追記
- target_note_id 指定による安全な追記
- テンプレート風の柔軟な追記
- 保存前プレビュー

---

## search_api.php の概要

### 主なパラメータ
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

### 主な用途
- ノートIDの取得
- カテゴリ横断検索
- append_api 用の target_note_id 探索
- 本文検索

---

## journal_api.php の概要

### 主なパラメータ
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

### 主な用途
- 日記
- 日報
- 作業ログ
- 週報 / 月報
- ショートカットからの定型記録

---

## バックアップ

### 自動バックアップ
以下のようなタイミングでバックアップが作成されます。

- 暗号化設定切替直前
- 同期 save 前
- cleanup 系破壊操作前（設定による）

### 命名規則
- 平文: `data.json.bak-YYYYMMDD-HHMMSS`
- 暗号化: `data.enc.json.bak-YYYYMMDD-HHMMSS`

### 復元
`bak_settings.php` から復元できます。  
暗号化バックアップ復元時は、対応する `DATA_ENCRYPTION_KEY` が必要です。

---

## ログ / セッション設定

`log_settings.php` で扱えるもの:

- ファイルログ ON/OFF
- Notemod Logsカテゴリログ ON/OFF
- `SESSION_COOKIE_LIFETIME`
- `session.gc_maxlifetime` の確認表示

説明文:
> ブラウザ側の保持期間です。サーバー側設定によっては、それより早くログインが切れる場合があります

---

## 連携

- [StayHomeLab YouTube ch](https://www.youtube.com/@StayHomeLab)
- [Website](https://stayhomelab.net/notemod-selfhosted)
- [ClipboardSync](https://github.com/StayHomeLabNet/ClipboardSync)

---

## 注意

- APIや cleanup は、**必ず `config/<DIR_USER>/config.api.php`** を参照する前提です
- 旧仕様の `/config/config.api.php` 前提には戻さないでください
- 壊れた旧形式の `data.json` を扱う場合でも、現行コードでは可能な範囲で正規化してから保存する想定です
- `append_api.php` / `search_api.php` / `journal_api.php` は、未指定時に `pretty=2` 相当で **人間が読みやすい text/plain** を返す設計です
