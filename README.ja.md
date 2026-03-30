# Notemod-selfhosted v1.4.5

これは **[Notemod（本家）](https://github.com/orayemre/Notemod)**（MIT License）をベースに、**共用サーバーでも動く自己ホスト型メモ基盤**として拡張したフォークです。  
DB は不要で、単一データソースとして **`notemod-data/<DIR_USER>/data.json`** を使います。

外部サービスに依存せず、**Windows PC と iPhone 間のテキスト・画像・ファイルのやり取りを円滑にする目的で開発**されています。simplenote.com などのノートサービスの代替にもなり得ます。  

動作確認済みの共用サーバー: Xサーバー、さくらインターネット、XREA、InfinityFree  
テスト済み PHP: 8.3.21（**PHP 8.1 以上必須**）

> **単一データソース:** `notemod-data/<DIR_USER>/data.json`

---

## この更新で特に重要なポイント

- **ユーザーごとの設定ファイル**
  - `config/<DIR_USER>/config.php`
  - `config/<DIR_USER>/config.api.php`
  - `config/<DIR_USER>/auth.php`
- **全ユーザー共通のメール設定**
  - `config/mail.php`
- **本体データ**
  - `notemod-data/<DIR_USER>/data.json`
- **認証用メールアドレス**
  - `setup_auth.php` で必須入力
  - `auth.php` の `EMAIL` に保存
- **パスワードリセット**
  - `forgot_password.php`
  - `reset_password.php`
  - `config/<DIR_USER>/password_reset.json`
- **暗号化**
  - `DATA_ENCRYPTION_ENABLED`
  - `DATA_ENCRYPTION_KEY`
  - `data.json` を **AES-256-CBC + HMAC** で暗号化可能
- **セッション保持期間**
  - `SESSION_COOKIE_LIFETIME`
  - `log_settings.php` から変更可能
- **メール送信**
  - `mail()` と SMTP の両対応
  - `auth_common.php` の共通送信基盤で一元管理
- **バックアップ命名**
  - 平文: `data.json.bak-YYYYMMDD-HHMMSS`
  - 暗号化: `data.enc.json.bak-YYYYMMDD-HHMMSS`
- **同期保存前バックアップ設定**
  - `SYNC_PRE_SAVE_BACKUP_ENABLED`
  - `SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED`
  - Web UI の sync save 前バックアップと、その直前の古いバックアップ整理を制御可能

---

## v1.4.5 の主な追加・改善

### 1. Web UI の同期保存前バックアップを設定化
- `config/<DIR_USER>/config.php` に **`SYNC_PRE_SAVE_BACKUP_ENABLED`** を追加
- Web UI の sync save 時に作成される **保存直前バックアップ** の有効 / 無効を切り替え可能
- 未設定時は従来互換のため **有効扱い**
- `bak_settings.php` から ON / OFF を変更可能

### 2. 同期保存前バックアップ直前の自動整理に対応
- `config/<DIR_USER>/config.php` に **`SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED`** を追加
- `SYNC_PRE_SAVE_BACKUP_ENABLED=true` かつ `SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED=true` の場合、
  **同期保存前バックアップを作成する直前に** 既存バックアップの自動整理を実行
- 整理ロジックは `bak_settings.php` の **「最新から n個のバックアップを残す『削除』」** と同じ動作
- 残す件数は `config/<DIR_USER>/config.api.php` の **`CLEANUP_BACKUP_KEEP`** を使用

### 3. `bak_settings.php` に同期保存前バックアップ設定 UI を追加
- Backups セクションに **「同期保存前バックアップを有効（SYNC_PRE_SAVE_BACKUP_ENABLED）」** を追加
- その下に、少しインデントした子項目として
  **「同期保存前に古いバックアップを整理する（SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED）」** を追加
- 従来の `CLEANUP_BACKUP_ENABLED` / `CLEANUP_BACKUP_KEEP` と役割を分けて設定可能

### 4. `notemod_sync.php` の保存フローを改善
- 差分ありで実保存が必要な場合、次の順序で処理
  1. `SYNC_PRE_SAVE_BACKUP_ENABLED` を確認
  2. 必要なら `SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED` に従って古いバックアップ整理
  3. 同期保存前バックアップを作成
  4. 新しい `data.json` を保存
- これにより、sync save 前バックアップを利用しつつバックアップ増加を抑制可能

### 5. `setup_auth.php` / サンプル設定を更新
- 新規生成される `config.php` に
  - `SYNC_PRE_SAVE_BACKUP_ENABLED`
  - `SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED`
  を含めるよう更新
- `config.sample.php` / `config.sample.ja.php` にも新設定を追加

### 6. v1.4.4 までの機能も継続
- 認証用メールアドレス保存
- パスワードリセット
- 共通メール送信基盤
- `config/mail.php` による全ユーザー共通メール設定
- SMTP 設定 UI / テスト送信
- `append_api.php`
- `search_api.php`
- `journal_api.php`
- sync save 前の **スナップショット正規化**
- `.txt` / `.json` インポート対応
- `categories` / `notes` の文字列化崩れ対策
- `SESSION_COOKIE_LIFETIME` 対応
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

## 初期設定

### 1. サーバーへ配置
リポジトリ一式を公開フォルダへアップロードします。

### 2. 初回アクセス
`setup_auth.php` / `index.php` へアクセスし、初回セットアップを行います。

v1.4.5 では `setup_auth.php` で次を設定します。
- 初期ユーザー
- パスワード
- **認証用メールアドレス**
- 必要に応じて同期保存前バックアップ関連の初期設定

必要に応じて自動生成される主なファイル:

- `config/<DIR_USER>/auth.php`
- `config/<DIR_USER>/config.php`
- `config/<DIR_USER>/config.api.php`
- `notemod-data/<DIR_USER>/data.json`
- `notemod-data/<DIR_USER>/.htaccess`
- `logs/<DIR_USER>/.htaccess`
- `api/.htaccess`

`config/mail.php` は SMTP 設定を保存した時点で作成されます。

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
- `SYNC_PRE_SAVE_BACKUP_ENABLED`
- `SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED`

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

### 認証設定
`config/<DIR_USER>/auth.php`

主なキー:

- `USERNAME`
- `DIR_USER`
- `PASSWORD_HASH`
- `EMAIL`
- `UPDATED_AT`

### 共通メール設定
`config/mail.php`

主なキー:

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

## バックアップ

### 手動バックアップ
- `bak_settings.php` から **今すぐバックアップ** を実行可能
- `api/cleanup_api.php?action=backup_now` でも実行可能

### cleanup 用バックアップ
- `CLEANUP_BACKUP_ENABLED` が有効な場合、cleanup 系の危険操作前にバックアップを作成
- `CLEANUP_BACKUP_KEEP` により、残すバックアップ数を制御可能

### Web UI 同期保存前バックアップ
- `SYNC_PRE_SAVE_BACKUP_ENABLED` が有効な場合、Web UI の sync save で実保存直前バックアップを作成
- `SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED` も有効な場合、その直前に **古いバックアップ整理** を実行
- 古いバックアップ整理の件数判定には `CLEANUP_BACKUP_KEEP` を使用
- 整理ロジックは `bak_settings.php` の **「最新から n個のバックアップを残す『削除』」** と同じ

### バックアップ削除の基準
- バックアップ一覧を **新しい順（ファイル更新時刻順）** に並べる
- 先頭から `n` 件を残し、それ以外を削除
- `n=0` の場合は全削除
- 平文バックアップと暗号化バックアップをまとめて判定

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

### SMTP パスワード
- `config/mail.php` の `SMTP_PASSWORD` は平文保存です
- `config/mail.php` は公開されない配置前提で運用してください

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

## ログ / セッション / メール設定

`log_settings.php` で扱えるもの:

- ファイルログ ON/OFF
- Notemod Logsカテゴリログ ON/OFF
- `SESSION_COOKIE_LIFETIME`
- `session.gc_maxlifetime` の確認表示
- IP アクセス通知設定
- **認証用メールを反映** ボタン
- **SMTP 設定（開閉式）**
- **SMTP テスト送信**

`bak_settings.php` で扱えるもの:

- **同期保存前バックアップを有効（SYNC_PRE_SAVE_BACKUP_ENABLED）**
- **同期保存前に古いバックアップを整理する（SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED）**
- **Enable backup（CLEANUP_BACKUP_ENABLED）**
- **Keep latest n backups / n=0 deletes all（CLEANUP_BACKUP_KEEP）**
- 今すぐバックアップ
- バックアップ復元

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
- `config.php` の
  - `SYNC_PRE_SAVE_BACKUP_ENABLED`
  - `SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED`
  は Web UI の sync save 前バックアップ制御用です
- `config.api.php` の
  - `CLEANUP_BACKUP_ENABLED`
  - `CLEANUP_BACKUP_KEEP`
  は cleanup 系バックアップと keep 数制御用です
- `config/mail.php` は全ユーザー共通設定です
- SMTP を使う場合は、送信元アドレスと SPF / DKIM / SMTP 認証の整合を確認してください
- 壊れた旧形式の `data.json` を扱う場合でも、現行コードでは可能な範囲で正規化してから保存する想定です
- `append_api.php` / `search_api.php` / `journal_api.php` は、未指定時に `pretty=2` 相当で **人間が読みやすい text/plain** を返す設計です
