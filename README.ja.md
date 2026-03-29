# Notemod-selfhosted v1.4.4

これは **[Notemod（本家）](https://github.com/orayemre/Notemod)**（MIT License）をベースに、**共用サーバーでも動く自己ホスト型メモ基盤**として拡張したフォークです。  
DB は不要で、単一データソースとして **`notemod-data/<DIR_USER>/data.json`** を使います。

外部サービスに依存せず、**Windows PC と iPhone 間のテキスト・画像・ファイルのやり取りを円滑にする目的で開発**されています。simplenote.com などのノートサービスの代替にもなり得ます。  

動作確認済みの共用サーバー: Xサーバー、さくらインターネット、XREA、InfinityFree  
テスト済み PHP: 8.3.21（**PHP 8.1 以上必須**）

> **単一データソース:** `notemod-data/<DIR_USER>/data.json`

---

## この版で特に重要なポイント

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

---

## v1.4.4 の主な追加・改善

### 1. 認証用メールアドレス保存に対応
- `setup_auth.php` で **メールアドレスを必須入力** に変更
- 認証情報は `config/<DIR_USER>/auth.php` に配列形式で保存
- `EMAIL` を追加保存
- 既存 `auth.php` に `EMAIL` が無い場合でも、ログインを維持したまま後から追加設定可能
- `setup_auth.php` は
  - 初回セットアップ時は未ログインで利用可能
  - 認証設定済み後はログイン済みユーザーのみ変更可能

### 2. パスワードリセット機能を追加
- `login.php` に **「パスワードを忘れた場合」** リンクを追加
- `forgot_password.php` を追加
  - 入力は **ユーザー名またはメールアドレス**
  - 結果文言は常に同一
- `reset_password.php` を追加
  - `reset_password.php?username=...&token=...` 形式に対応
  - 成功時は `login.php?reset=success` へ戻る
- トークン保存先:
  - `config/<DIR_USER>/password_reset.json`
- トークン仕様:
  - `token_hash`
  - `created_at`
  - `expires_at`
  - `used`
- 有効期限は **30分**
- 新規発行で旧トークンは失効
- リセット時も **10文字以上** のパスワード制約を適用

### 3. `log_settings.php` から認証用メールアドレスを反映可能に
- 通知用メール欄の横に **「認証用メールを反映」** ボタンを追加
- `auth.php` の `EMAIL` を通知用メール欄へセット可能
- `EMAIL` 未設定時は未設定メッセージを表示

### 4. メール送信処理を共通化
- `auth_common.php` にメール送信共通処理を実装
- 既存の初回IP通知と、パスワードリセットメールが同じ送信基盤を利用
- `logger.php` / `forgot_password.php` から直接 `mail()` を呼ばず、共通関数経由で送信する構成へ整理
- `IP_ALERT_FROM` との互換性を維持

### 5. `config/mail.php` による全ユーザー共通メール設定を追加
- SMTP を含むメール設定を **全ユーザー共通** で管理
- 保存先は `config/mail.php`
- 主なキー:
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

### 6. SMTP 送信に対応
- `MAIL_TRANSPORT=smtp` かつ `SMTP_ENABLED=1` のとき SMTP 送信
- SMTP 無効時は従来どおり `mail()` を使用
- SMTP 失敗時に `mail()` へフォールバックするかを設定可能
- **PHPMailer なしの自前実装**
- 対応範囲:
  - 平文SMTP
  - STARTTLS
  - SSL/TLS
  - AUTH LOGIN
- `SMTP_FROM` が空のときは `IP_ALERT_FROM` を流用可能

### 7. `log_settings.php` に SMTP 設定 UI とテスト送信を追加
- SMTP設定を `log_settings.php` から編集可能
- 項目数が多いため、SMTP 設定欄は **通常非表示**
- クリックで開く開閉式 UI を採用
- 開いたときに他の設定より目立つように強調表示
- SMTP テスト送信機能を追加
- SMTP パスワード欄は安全化
  - 既存パスワードを画面に再表示しない
  - 空欄保存時は現在値を保持

### 8. `account.php` からのパスワード変更でも `EMAIL` を保持
- `auth_common.php` の認証設定保存処理を改善
- `account.php` でパスワード変更しても、`auth.php` の `EMAIL` が消えないように修正

### 9. v1.4.3 までの API 拡張と安定化内容も継続
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

v1.4.4 では `setup_auth.php` で次を設定します。
- 初期ユーザー
- パスワード
- **認証用メールアドレス**

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
- `config/mail.php` は全ユーザー共通設定です
- SMTP を使う場合は、送信元アドレスと SPF / DKIM / SMTP 認証の整合を確認してください
- 壊れた旧形式の `data.json` を扱う場合でも、現行コードでは可能な範囲で正規化してから保存する想定です
- `append_api.php` / `search_api.php` / `journal_api.php` は、未指定時に `pretty=2` 相当で **人間が読みやすい text/plain** を返す設計です
