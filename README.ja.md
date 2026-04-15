# Notemod-selfhosted v1.4.6

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
- **画像インデックス**
  - `notemod-data/<DIR_USER>/image_index.json`
- **ファイルインデックス**
  - `notemod-data/<DIR_USER>/file_index.json`
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
- **メディアロック**
  - `file_index.json` / `image_index.json` の各要素に `lock: true/false` を保持
  - `true` はロック状態、`false` はアンロック状態
- **認証系セキュリティ強化**
  - security header
  - CSRF 対策
  - login / forgot password / reset password の rate limit
  - audit log
- **トークン露出対策**
  - `setup_auth.php` で API トークンを平文表示しない
  - `clipboard_sync.php` は初期伏字 + 一時表示
  - `media_files.php` はブラウザへ token を出さずサーバー側中継方式に変更

---

## v1.4.6 の主な追加・改善

### 1. `image_index.json` に対応
- これまで画像には `file_index.json` に相当する一覧インデックスが存在しませんでしたが、v1.4.6 で **`image_index.json`** を追加
- 画像アップロード時に差分更新
- 画像削除や purge 後に再生成
- `media_files.php` では `image_index.json` を優先して画像一覧を構築

### 2. `file_index.json` / `image_index.json` に `lock` フラグを追加
- 各画像・各ファイルの要素に **`lock`** を追加
- 値は **boolean** で保持
  - `true` = ロック状態
  - `false` = アンロック状態
- 新規追加時の既定値は `false`

### 3. `media_files.php` にロック / アンロック UI を追加
- 画像とファイルの各行で、チェックボックスの右側に **ロックアイコン** を追加
- クリックするたびに
  - ロック状態
  - ロック解除状態
  を切り替え可能
- UI は既存画面になじむよう、小さめのアイコンボタンに調整

### 4. ロック中メディアは削除対象から除外
- `lock=true` の画像・ファイルは削除対象から除外
- 一括削除や個別削除の操作対象に含まれていても、ロック中のものは削除されません
- ロック解除状態は従来どおり削除可能

### 5. cleanup 時に lock 状態を引き継ぐよう改善
- `api/cleanup_api.php` で `file_index.json` / `image_index.json` を再生成する際、
  既存 index に同名ファイルがある場合は **`lock` 状態を引き継ぐ** よう改善
- これにより、cleanup や purge 後もロック設定が失われにくくなりました

### 6. 認証まわりのセキュリティを強化
- `auth_common.php` を中心に、認証まわりの共通セキュリティ処理を整理
- HTML系画面で共通の security header を付与
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: SAMEORIGIN`
  - `Referrer-Policy: same-origin`
  - `Cache-Control: no-store, no-cache, must-revalidate, max-age=0`
  - `Pragma: no-cache`
- `login.php` のログイン成功時に `session_regenerate_id(true)` を実施
- 未ログイン時 redirect を返す画面でも、security header が付くよう改善

### 7. CSRF 対策を追加
- 主要なフォーム系画面に **CSRF token** を導入
- 対象:
  - `login.php`
  - `setup_auth.php`
  - `account.php`
  - `log_settings.php`
  - `bak_settings.php`
  - `forgot_password.php`
  - `reset_password.php`
  - `clipboard_sync.php` の token reveal
  - `media_files.php` の download / upload / cleanup / lock 操作
- token 欠落や改ざん時は処理を拒否するよう改善

### 8. ログイン / パスワードリセット系に rate limit を追加
- `login.php`
- `forgot_password.php`
- `reset_password.php`

上記に短時間連続試行の抑止を追加

- ログイン失敗の連続試行を制限
- パスワードリセット申請の短時間連続送信を制限
- パスワード再設定の短時間連続試行を制限
- 正常成功時には必要な bucket をクリアするよう調整

### 9. 監査ログを追加
- `auth_common.php` に監査ログ共通処理を追加
- 保存先は **`logs/system/audit.log`**（JSON Lines）
- 次のようなイベントを記録
  - `login_success`
  - `login_failed`
  - `login_rate_limited`
  - `password_reset_requested`
  - `password_reset_request_rate_limited`
  - `password_reset_failed`
  - `password_reset_completed`
  - `password_reset_rate_limited`
  - `setup_auth_updated`
  - `account` / `log_settings` / `bak_settings` の各種操作イベント
- 秘密情報の実値は記録せず、
  - changed フラグ
  - from / to
  - masked email
  など必要最小限の差分のみ記録

### 10. `setup_auth.php` で API トークンを平文表示しないよう改善
- `EXPECTED_TOKEN` / `ADMIN_TOKEN` を既存値のまま平文表示しない方式に変更
- 入力欄は空欄表示
- 既存トークンは placeholder / 説明文で「設定済み」であることだけを示す
- 空欄保存時は既存値を維持
- 新しい値を入力した時だけ更新

### 11. `clipboard_sync.php` のトークン表示を強化
- `EXPECTED_TOKEN` / `ADMIN_TOKEN` は **初期表示では常に伏字**
- **ロック解除時だけ** サーバーから取得して一時表示
- **10秒後に自動再ロック**
- 表示中だけコピー可能
- ロック中はコピー不可
- `reveal_token` の POST には **CSRF 保護** を追加

### 12. `media_files.php` をトークン非露出方式に変更
- ブラウザへ `EXPECTED_TOKEN` / `ADMIN_TOKEN` を直接出さないよう改善
- 画像 / ファイルの upload / cleanup / lock / download を **`media_files.php` 自身のサーバー側中継** で処理
- フロント側は session + CSRF ベースで操作
- 画像URLコピーや画像コピーでも token をURLへ出さないよう改善
- `file.json`（JSON Lines）履歴からの復元表示用に **`parse_file_history_jsonl()`** を追加

### 13. `api/image_api.php` の user 解決ロジックを整理
- `user`
- `dir_user`
- `username`

のいずれでも利用できるようにしつつ、  
後半で `$_GET['user']` を再代入して先頭の補助解決を無効化していた処理を整理
- `dir_user` や `username` 経由でも意図どおり画像取得できるよう改善

### 14. v1.4.5 までの機能も継続
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
- Web UI の同期保存前バックアップ制御

### 15. `index.php` の同期安全性を改善
- 長時間放置などで Web UI のログインセッションが失効した後の **同期誤動作リスク** を軽減
- `notemod_sync.php` との通信で **401 / 403** を検出した場合、**自動同期を停止** し、再ログインが必要であることを画面上で明示するよう改善
- 危険な条件では **auto load / manual load を抑止** し、古いサーバーデータによるローカル上書き事故を防ぎやすく改善
- セッション失効後にローカル変更が発生した場合のみ強い警告を表示するよう調整し、通常時に毎回警告が出続ける誤表示を修正
- 正常に同期成功した後は、警告フラグを解除して通常状態へ戻るよう調整

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
  image_index.json
  file_index.json
  images/
  files/
/logs/<DIR_USER>/
/logs/system/
  audit.log
```

---

## 初期設定

### 1. サーバーへ配置
リポジトリ一式を公開フォルダへアップロードします。

### 2. 初回アクセス
`setup_auth.php` / `index.php` へアクセスし、初回セットアップを行います。

v1.4.6 では `setup_auth.php` で次を設定します。

- 初期ユーザー
- パスワード
- **認証用メールアドレス**
- 必要に応じて同期保存前バックアップ関連の初期設定
- 必要に応じて API トークン関連設定

必要に応じて自動生成される主なファイル:

- `config/<DIR_USER>/auth.php`
- `config/<DIR_USER>/config.php`
- `config/<DIR_USER>/config.api.php`
- `notemod-data/<DIR_USER>/data.json`
- `notemod-data/<DIR_USER>/.htaccess`
- `logs/<DIR_USER>/.htaccess`
- `api/.htaccess`

`config/mail.php` は SMTP 設定を保存した時点で作成されます。  
`image_index.json` / `file_index.json` は、画像やファイルが追加された時点で生成・更新されます。

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
- `PASSWORD_RESET_TOKEN`
- `PASSWORD_RESET_TOKEN_HASH`
- `PASSWORD_RESET_TOKEN_EXPIRES_AT`

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

## メディアインデックス

### `file_index.json`
- 現在存在しているファイル一覧を保持するインデックス
- `api/api.php` でファイル追加時に差分更新
- `api/cleanup_api.php` で削除や purge 後に再生成
- 各要素は `lock` を持ち、削除除外状態を保持

### `image_index.json`
- 現在存在している画像一覧を保持するインデックス
- `api/api.php` で画像追加時に差分更新
- `api/cleanup_api.php` で削除や purge 後に再生成
- 各要素は `lock` を持ち、削除除外状態を保持

### `lock`
- `true` の場合、その画像 / ファイルは **ロック状態**
- ロック状態の項目は削除対象から除外
- `false` の場合はアンロック状態で、従来どおり削除可能

---

## セキュリティ

### Basic認証を強く推奨
可能なら `api/` に Basic認証を設定してください。

### Web UI認証
Basic認証が使えない場合は、`setup_auth.php` と `login.php` / `logout.php` を使った Web UI認証で運用することで、一定のセキュリティを確保できます。

### Web UI の追加保護
v1.4.6 では、次の追加保護を導入しています。

- security header
- CSRF 対策
- `login.php` / `forgot_password.php` / `reset_password.php` の rate limit
- 監査ログ
- `session_regenerate_id(true)` によるログイン成功時のセッション再生成
- `setup_auth.php` / `clipboard_sync.php` / `media_files.php` での API トークン平文露出削減

### `data.json` 暗号化
- `DATA_ENCRYPTION_ENABLED` が `true` のとき、`data.json` は暗号化保存されます
- エクスポートは **平文 JSON 固定** です
- 暗号化キーを失うと復号できません

### SMTP パスワード
- `config/mail.php` の `SMTP_PASSWORD` は平文保存です
- `config/mail.php` は公開されない配置前提で運用してください

### 監査ログ
- 保存先: `logs/system/audit.log`
- 形式: JSON Lines
- パスワード / API token / SECRET / SMTP password などの実値は記録しない方針です

---

## API の概要

### `api/api.php`
- テキスト追加
- 画像アップロード
- ファイルアップロード
- 必要ならカテゴリ自動作成
- `note_latest.json` 更新
- `image_index.json` / `file_index.json` 更新

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
- `image_index.json` / `file_index.json` 再生成
- メディアロック状態の更新

### `api/image_api.php`
- 画像配信
- 簡易リサイズ
- キャッシュ制御
- `user` / `dir_user` / `username` によるユーザー解決に対応

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

`media_files.php` で扱えるもの:

- 画像一覧
- ファイル一覧
- メディアの削除
- **ロック / アンロック切替**
- ロック中メディアの削除除外
- token をブラウザへ出さない中継方式による upload / cleanup / lock / download

`clipboard_sync.php` で扱えるもの:

- ClipboardSync ダウンロードリンク
- API URL コピー
- **API トークンの初期伏字表示**
- **ロック解除時だけ 10 秒間の一時表示**
- **表示中のみコピー**
- **CSRF 保護付き token reveal**

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
- `file_index.json` / `image_index.json` の `lock` は、各メディアの削除除外状態を保持するための項目です
- `lock=true` の項目は cleanup や media_files.php の削除操作から除外されます
- `setup_auth.php` では、既存の API トークンを平文表示しない方針です
- `clipboard_sync.php` / `media_files.php` では、ブラウザへ API token 実値を直接出さない方針です
- 壊れた旧形式の `data.json` を扱う場合でも、現行コードでは可能な範囲で正規化してから保存する想定です
- `append_api.php` / `search_api.php` / `journal_api.php` は、未指定時に `pretty=2` 相当で **人間が読みやすい text/plain** を返す設計です
