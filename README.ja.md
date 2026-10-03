<div align="center">

# 📝 Notemod-selfhosted

## PHP同期・API・暗号化・バックアップ・メディア保存に対応したセルフホスト版 Notemod

**Notemod-selfhosted は、共有サーバーでも動作しやすい、データベース不要のセルフホスト型メモプラットフォームです。  
メモデータは `notemod-data/<DIR_USER>/data.json` に保存され、Web UI同期、API連携、クリップボード連携、画像/ファイル保存、バックアップ、SMTP設定、AES-256-CBC + HMAC による任意の暗号化保存に対応しています。**

[English](README.md) | [日本語](README.ja.md)

<br>

[概要](#概要) ・ [機能](#機能) ・ [動作要件](#動作要件) ・ [インストール](#インストール) ・ [初期設定](#初期設定) ・ [ディレクトリ構成](#ディレクトリ構成) ・ [API](#api-の概要) ・ [セキュリティ](#セキュリティ) ・ [バックアップ](#バックアップ) ・ [連携](#連携) ・ [ライセンス](#ライセンス)

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
  <img src="https://img.shields.io/badge/Ko--fi-Support%20this%20project-ff5e5b?style=for-the-badge&logo=kofi&logoColor=white" alt="Ko-fi で支援">
</a>
<a href="https://buymeacoffee.com/stayhomelabnet">
  <img src="https://img.shields.io/badge/Buy%20Me%20a%20Coffee-Support%20this%20project-ffdd00?style=for-the-badge&logo=buymeacoffee&logoColor=000000" alt="Buy Me a Coffee で支援">
</a>

</div>

---

## 概要

これは **[Notemod（本家）](https://github.com/orayemre/Notemod)**（MIT License）をベースに、**共用サーバーでも動く自己ホスト型メモ基盤**として拡張したフォークです。  
DB は不要で、単一データソースとして **`notemod-data/<DIR_USER>/data.json`** を使います。

外部サービスに依存せず、**Windows PC と iPhone 間のテキスト・画像・ファイルのやり取りを円滑にする目的で開発**されています。simplenote.com などのノートサービスの代替にもなり得ます。

> **単一データソース:** `notemod-data/<DIR_USER>/data.json`

---

## 機能

- データベース不要の JSON ベース保存
- Web UI によるメモ編集・同期保存
- `api.php` / `read_api.php` / `cleanup_api.php` などの API 連携
- ClipboardSync との連携による PC / iPhone 間のテキスト・画像・ファイル共有
- 画像 / ファイル保存、一覧表示、削除、ロック保護
- AES-256-CBC + HMAC による任意のデータ暗号化
- 自動バックアップ、復元、同期保存前バックアップ
- `mail()` / SMTP による通知・パスワードリセット
- CSRF、rate limit、audit log、security header などの認証系セキュリティ強化

---

## 動作要件

- PHP 8.1 以上
- ファイル書き込み可能な PHP 対応Webサーバー
- データベース不要
- 共用サーバー対応

動作確認済みの共用サーバー: Xサーバー、さくらインターネット、XREA、InfinityFree  
テスト済み PHP: 8.3.21

### 自動テスト

外部依存のないPHPテストとHTTPセキュリティヘッダーのスモークテストは、ローカルで次のように実行できます。

```bash
php tests/run.php
bash tests/http_smoke.sh
```

PHPテストでは、設定既定値、保存先、信頼プロキシ経由のIP・HTTPS判定、正規URL検証、Unicodeパスワード長、メディア上限、rate limitの同時更新、インデックスロック、認証設定の原子的保存、暗号化データの完全性を確認します。HTTPスモークテストでは、隔離した一時保存先を使い、HTML、API、未認証画像応答のセキュリティ方針を検証します。

GitHub Actionsでは、pushとpull requestごとにPHP 8.1から8.5でPHP構文検査と両テストを実行します。

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

## v1.4.7 の主な追加・改善

- `NM_STORAGE_ROOT`を追加し、ユーザー別保存構造に合わせて`.gitignore`を更新して、稼働データと秘密情報をバージョン管理対象から除外
- `NM_TRUSTED_PROXIES`による信頼プロキシ対応のクライアントIP・HTTPS判定を追加
- `Host`ヘッダー由来のURL生成を廃止し、検証済みの`NM_PUBLIC_BASE_URL`と任意の`NM_INTERNAL_BASE_URL`を使用
- 画像APIの認証要件を明確化し、保護対象の応答にprivate・no-storeのキャッシュ制御を適用
- rate limit状態、認証設定、メディアインデックスの更新をロックと原子的保存で競合に強い構成へ改善
- アップロード、画像処理、リクエストサイズにアプリケーション側の上限を追加
- Unicode対応のパスワード長判定、セキュリティヘッダー、CSP処理、共通設定の既定値を統一
- 外部依存のないPHPテスト、HTTPセキュリティスモークテスト、PHP 8.1から8.5のGitHub Actionsテスト行列を追加

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
- 保存先は `NM_STORAGE_ROOT` 配下の **`logs/audit.log`**（JSON Lines）
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
- 画像取得には、同じユーザーでログイン済みのWeb UIセッション、またはそのユーザーの`EXPECTED_TOKEN`が必要です
- 認証済み画像の応答は`Cache-Control: private, no-store`とし、リサイズ画像はユーザーの`.cache`ディレクトリ内に限ってサーバー側でキャッシュします

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
/logs/audit.log
```

---

## インストール

1. リポジトリをダウンロードまたは clone します。
2. サーバー上の公開ディレクトリへファイルをアップロードします。
3. 下記の手順で稼働データの保存先を設定します。公開ディレクトリ外の利用を強く推奨します。
4. ブラウザで `login.php` にアクセスします。
5. 初回 admin 作成後、`setup_auth.php` で SECRET、API token、暗号化設定などを確認します。

> 既存データを移行する場合は、作業前に `notemod-data/<DIR_USER>/data.json` と `config/<DIR_USER>/` を必ずバックアップしてください。

### 稼働データを公開ディレクトリ外へ置く（推奨）

PHP が読み書きできる絶対パスを環境変数 `NM_STORAGE_ROOT` に設定します。Notemod はアプリケーションディレクトリ内ではなく、そのパスの配下に `config/`、`notemod-data/`、`logs/` を保存します。

```text
NM_STORAGE_ROOT=/var/lib/notemod
```

新規インストールでは、保存先ディレクトリを作成して PHP プロセスに読み書き権限を与え、`setup_auth.php` を開く前に環境変数を設定します。既存環境を移行する場合:

1. 現在の `config/`、`notemod-data/`、`logs/` をバックアップします。
2. 3つのディレクトリを、名前と内容を変えずに新しい保存ルートの配下へ移動します。
3. PHP-FPM pool、Apache の環境設定、コンテナ設定、またはホスティングの管理画面で `NM_STORAGE_ROOT` を設定します。
4. PHP/Web サーバーを再起動または再読み込みし、ログイン、メモ同期、メディア表示、ログ出力を確認します。

環境変数には絶対パスを指定し、ファイルシステムのルート自体は指定しないでください。未設定時は後方互換性のため、従来どおりアプリケーションディレクトリ内の保存先を使用します。従来配置では Web サーバー側でも直接アクセスを拒否してください。自動生成される `.htaccess` が保護できるのは Apache 互換サーバーだけです。

### 信頼するリバースプロキシ経由のクライアントIP

Notemod は既定で`REMOTE_ADDR`だけを使い、クライアントから送られた転送ヘッダーを無視します。リバースプロキシの背後で運用する場合は、プロキシのIPアドレスまたはCIDRを`NM_TRUSTED_PROXIES`に設定します。

```text
NM_TRUSTED_PROXIES=127.0.0.1,10.0.0.0/8,2001:db8:1234::/48
```

`REMOTE_ADDR`がこの一覧に一致する場合だけ、Notemodは`X-Forwarded-For`と`X-Forwarded-Proto`を参照します。IPチェーンを右端から順に検証し、信頼するプロキシではない最初のアドレスをクライアントIPとして採用します。検証済みの`X-Forwarded-Proto: https`は、Cookieの`Secure`属性とHTTPS状態判定にも使います。プロキシ側では、受信した転送ヘッダーを検証済みの値で置き換え、信頼範囲は必要最小限にしてください。リバースプロキシを使わない場合、この環境変数は設定しません。

### 正規の公開URLと内部URL

Notemodを公開する正規URLを`NM_PUBLIC_BASE_URL`に設定します。サブディレクトリへ設置する場合はそのパスまで含め、クエリやフラグメントは含めません。

```text
NM_PUBLIC_BASE_URL=https://notes.example.com/notemod
```

パスワード再設定リンクはこの設定値だけから生成し、リクエストの`Host`ヘッダーは使用しません。未設定の場合は再設定メールを送信せず、アカウントの存在を要求元へ示さないまま`logs/forgot_password.log`へ記録します。

メディア画面のサーバー内部API呼び出しにもこのURLを使用します。公開URLへサーバー自身から接続できない場合は、内部用URLを別に設定します。

```text
NM_INTERNAL_BASE_URL=http://127.0.0.1/notemod
```

`NM_INTERNAL_BASE_URL`は任意で、未設定時は`NM_PUBLIC_BASE_URL`を使います。どちらも認証情報、クエリ、フラグメント、親ディレクトリ要素を含まない`http`または`https`の絶対URLである必要があります。ブラウザーに表示するAPI URLは、設定済みの公開URL、または未設定時にはブラウザー自身のオリジンから補完します。

---

## 初期設定

### 1. サーバーへ配置
リポジトリ一式を公開フォルダへアップロードします。

### 2. 初回アクセス
`setup_auth.php` / `index.php` へアクセスし、初回セットアップを行います。

`setup_auth.php`では次の項目を設定します。

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

アップロードの保存前とGDによる画像展開前にも、アプリ側で上限を検査します。初期値は画像10 MiB、一般ファイル25 MiB、元画像10,000 px・2,500万画素、リサイズ後2,000 px・400万画素です。PHPの `upload_max_filesize` と `post_max_size` は別の上限として引き続き適用され、最も小さい上限が有効になります。

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
次の追加保護を適用しています。

- HTML、API・テキスト、バイナリの各応答種別に合わせたCSPを含む共通security header
- 初期設定、設定画面、API、ロガーで共有する設定既定値の一元化
- CSRF 対策
- `login.php` / `forgot_password.php` / `reset_password.php` の rate limit
- Rate limit状態の同時更新に対する共有・排他ファイルロックと原子的置換
- アップロード、メディアロック変更、削除後再構築が競合しないインデックス単位の排他ロックと原子的置換
- 初期設定、アカウント変更、パスワード再設定で共通利用するUnicode対応の最低10文字判定
- 監査ログ
- `session_regenerate_id(true)` によるログイン成功時のセッション再生成
- `setup_auth.php` / `clipboard_sync.php` / `media_files.php` での API トークン平文露出削減

### `data.json` 暗号化
- `DATA_ENCRYPTION_ENABLED` が `true` のとき、`data.json` は暗号化保存されます
- エクスポートは **平文 JSON 固定** です
- 暗号化キーを失うと復号できません

### SMTP パスワード
- `config/mail.php` の `SMTP_PASSWORD` は平文保存です
- `config/mail.php` が直接配信されないよう、公開ディレクトリ外の `NM_STORAGE_ROOT` を推奨します

### 監査ログ
- 保存先: `<NM_STORAGE_ROOT>/logs/audit.log`（`NM_STORAGE_ROOT`未設定時は`logs/audit.log`）
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
- 認証付き画像配信
- 簡易リサイズ
- `user` / `dir_user` / `username` によるユーザー解決に対応
- 同一ユーザーのWeb UIセッション、`Authorization: Bearer <EXPECTED_TOKEN>`、または`X-Notemod-Token: <EXPECTED_TOKEN>`を受け付けます
- URLのクエリ文字列にAPIトークンは指定できません
- `Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0`を返し、ブラウザーや共有プロキシに保護対象画像を保存させません
- リサイズ結果はサーバー内の`.cache`ディレクトリに保持しますが、これは公開HTTPキャッシュとは別のものです

例:

```bash
curl -H 'Authorization: Bearer YOUR_EXPECTED_TOKEN' \
  'https://notes.example.com/notemod/api/image_api.php?user=YOUR_DIR_USER&file=photo.png' \
  --output photo.png
```

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


---

## ライセンス

このプロジェクトは MIT License です。  
本家 Notemod も MIT License のもとで公開されています。
