# Notemod-selfhosted v1.4.2

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

## v1.4.2 で整理・修正された主な内容

### 1. 同期・インポートまわりの型崩れ対策
- `index.php` の同期スナップショット作成を見直し、`categories` / `notes` / `categoryOrder` / `noteOrder` を **配列として送信** するよう修正
- `notemod_sync.php` の `save` で **`nm_sync_normalize_snapshot()`** を通し、文字列化された配列や `null` / bool を正規化してから保存するよう改善
- インポート時の `selectedLanguage` / `hasSelectedLanguage` / `sidebarState` / `thizaState` / `tema` の扱いを見直し、**二重文字列化が起きにくい** よう修正
- `.txt` / `.json` のインポートに対応

### 2. API / logger / cleanup の保存形式修正
- `api/api.php`
- `logger.php`
- `api/cleanup_api.php`

で、`categories` / `notes` を **再度 `json_encode()` して文字列化してしまう経路** を修正し、**常に配列のまま `data.json` に保存** するよう整理

### 3. read API / cleanup API のユーザー別設定参照の整理
- API は **`config/<DIR_USER>/config.api.php`** を参照する前提に統一
- `config.api.php` から得た `DATA_JSON` の実パスと、対象 `DIR_USER` の整合を取るよう改善
- `read_api.php` の latest系メタ参照先を、**実際の `DATA_JSON` と同じユーザーディレクトリ** に揃えるよう修正

### 4. 同期ボタンの安全性向上
- save 側で「危険な空保存」を止めるガードを追加
- save 前にログイン状態を確認
- 差分判定を追加
- save 前の自動バックアップを追加
- 同期ボタン押下時に **先に localStorage を消さない** よう見直し

### 5. セッション設定を追加
- `SESSION_COOKIE_LIFETIME` を `config/<DIR_USER>/config.php` に保存
- `log_settings.php` から
  - ブラウザを閉じるまで
  - 1日
  - 7日
  - 30日
  を選択可能
- サーバー側の `session.gc_maxlifetime` も画面上で確認可能

### 6. 暗号化設定を setup_auth に集約
- `setup_auth.php` で
  - `DATA_ENCRYPTION_KEY` の自動生成
  - `DATA_ENCRYPTION_ENABLED` の ON/OFF
  - 切替直前バックアップ
  を扱う
- `DATA_ENCRYPTION_KEY` の実値は UI に表示せず、**「設定済み」** のみ表示

---

## ディレクトリ構成

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
Basic認証が使えない場合は、`setup_auth.php` と `login.php` / `logout.php` を使った Web UI認証で運用することで、一定のセキュリティーを確保できます。

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
