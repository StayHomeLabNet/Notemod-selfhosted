# Notemod-selfhosted（改造版 Notemod）v1.4.1

これは **[Notemod（本家）](https://github.com/orayemre/Notemod)**（MIT License）をベースに、**共用サーバーでも動く自己ホスト型メモ基盤**として拡張したフォークです（DB不要）。
データベースを使わないため、ウェブサーバーにアップロードして設定ファイルを用意すればすぐ使えます。

動作確認済みの共用サーバー: Xサーバー、さくらインターネット、XREA（エクスリア）、InfinityFree  
テスト済み PHP: 8.3.21（**PHP 8.1 以上が必須**）

> **単一データソース:** `notemod-data/<USER_NAME>/data.json`

---

## 開発の目的

iPhone と Windows PC 間のクリップボード連携を、iPhone と Mac 間の快適さに少しでも近づける。  
これを **外部サービスに依存せず** 実現する。

- Windows PC でコピーされたテキストが即時に Notemod-selfhosted に送られる（**Windowsアプリ ClipboardSync**）
- iPhone のショートカットを実行すると、そのテキストが iPhone でペーストできる
- iPhone でコピーされたテキストが、iPhone ショートカットの実行で、即時に Notemod-selfhosted に送られる
- Windows PC で、ホットキー（例 Ctrl + Alt + R）を実行すると、すぐにペーストできる（**Windowsアプリ ClipboardSync**）

---

## v1.4.1 の主な変更点

### 1) `data.json` の暗号化対応
- `data.json` を **AES-256-CBC + HMAC** で暗号化保存できるようになりました
- 暗号化設定は **`setup_auth.php` の Web UI** から切り替え可能です
- 暗号化キーは **`config/<USER_NAME>/config.php`** に保存されます
- **`DATA_ENCRYPTION_KEY` の実値は UI に表示しません**
- 暗号化設定の切り替え直前に、**現在の `data.json` のバックアップを 1 つ自動作成**します
- 形式変換に失敗した場合は、**設定を変更せず安全側に戻します**
- エクスポートは **常に平文 JSON** のままです

### 2) 暗号化対応を保存・読込・バックアップ処理まで横断反映
- `data_crypto.php` を追加し、平文/暗号化の保存処理を共通化しました
- `notemod_sync.php`、`api/api.php`、`api/read_api.php`、`api/cleanup_api.php`、`bak_settings.php` などが暗号化データを扱えるようになりました
- バックアップからの復元時は、**現在の暗号化設定に合わせて `data.json` に再保存**します

### 3) メディア / ファイル管理画面を追加
- `media_files.php` を追加し、**画像・ファイルの一覧表示 / 件数表示 / アップロード / ダウンロード / 削除導線**を整理しました
- 画像はサムネイル付きで一覧表示できます
- ファイルは `file_index.json` を優先して一覧表示し、必要に応じて履歴情報から復元します
- `index.php` から **Media & Files** ボタンで遷移できます

### 4) 画像配信用 API を追加
- `api/image_api.php` を追加しました
- 指定ユーザー配下の画像を **バイナリ配信**できます
- 幅・高さを指定したリサイズ表示、キャッシュ、基本的な入力検証に対応しています

### 5) ユーザーごとのディレクトリー構成を前提とした整理
- 設定は **`config/<DIR_USER>/`**
- データは **`notemod-data/<DIR_USER>/`**
- ログは **`logs/<DIR_USER>/`**
- `USERNAME` と保存先ディレクトリー名の `DIR_USER` を分離し、運用時の整合性を改善しました
- ユーザー名を変更しても、**保存ディレクトリー名は変更されません**

### 6) 「最新クリップ種別」判定の強化
- テキスト追加時に **`note_latest.json`** を更新するようになりました
- `read_api.php?action=latest_clip_type` で、**テキスト / 画像 / ファイルのどれが最後に送られたか**を判定できます
- ClipboardSync 側の「最新を受信」機能と連携しやすくなりました

### 7) バックアップ / クリーンアップまわりの改善
- バックアップファイル名は状態に応じて次の形式になります
  - 平文: `data.json.bak-YYYYMMDD-HHMMSS`
  - 暗号化: `data.enc.json.bak-YYYYMMDD-HHMMSS`
- `cleanup_api.php` は `dry_run=2` に対応し、**削除対象の件数だけ**を返せます
- `bak_settings.php` からバックアップの作成 / 一覧 / 復元 / 削除を扱えます

### 8) Web UI の整理と周辺ページの統一
- `setup_auth.php` / `account.php` / `clipboard_sync.php` / `log_settings.php` / `bak_settings.php` / `media_files.php` などで、**JP/EN 切替・Dark/Light 切替・ログインユーザー表示**の整合性を改善しました
- `robots.txt` の自動生成に対応し、検索エンジン向けの初期ガードを入れています

---

## 使用方法

### 1. サーバーへ配置
このリポジトリ一式を、サーバーの公開フォルダー（例: `public_html/`）にアップロードします。

> 補足: `config/` や `api/` も `index.php` と同じ階層に置いてください  
> （構成を変える場合は PHP 側のパス調整が必要です）

### 2. 設定ファイルを作成（重要）

#### 設定ファイルの自動生成
v1.1.0 以降は、Web UI での初期設定時に自動的に生成されます。  
v1.4.1 では、ユーザーごとに以下へ保存されます。

- `config/<DIR_USER>/config.php`
- `config/<DIR_USER>/config.api.php`

タイムゾーンやログの ON/OFF、バックアップファイル自動作成の ON/OFF などは、上記設定ファイルを編集してください。

#### 共通設定
`config.sample.php` または `config.sample.ja.php` を参考に、`config/<DIR_USER>/config.php` を用意します。

主な設定例:
- `SECRET`
- `TIMEZONE`
- `DEBUG`
- `LOGGER_FILE_ENABLED`
- `LOGGER_NOTEMOD_ENABLED`
- `IP_ALERT_*`
- `DATA_ENCRYPTION_ENABLED`
- `DATA_ENCRYPTION_KEY`

#### API設定
`config.api.sample.php` または `config.api.sample.ja.php` を参考に、`config/<DIR_USER>/config.api.php` を用意します。

主な設定例:
- `EXPECTED_TOKEN`
- `ADMIN_TOKEN`
- `DATA_JSON`
- `DEFAULT_COLOR`
- `CLEANUP_BACKUP_ENABLED`
- `CLEANUP_BACKUP_SUFFIX`
- `CLEANUP_BACKUP_KEEP`

### 3. SECRET / TOKEN の生成
SECRET や TOKEN の生成にはパスワード生成サイトを利用できます（例）  
<https://passwords-generator.org/>

- 第三者サイトが不安な場合は、OS標準のパスワード生成や `openssl rand -hex 32` 等でも OK です
- `DATA_ENCRYPTION_KEY` は通常、`setup_auth.php` 側で自動生成できます

### 4. 初期化（初回のみ）
公開URLへアクセスすると Notemod-selfhosted が開きます。  
表示言語をセットし、最初のカテゴリーを作成してください。

この操作により以下が、なければ自動生成されます（環境/設定による）。

- `config/<DIR_USER>/config.php`
- `config/<DIR_USER>/config.api.php`
- `notemod-data/<DIR_USER>/data.json`
- `notemod-data/<DIR_USER>/.htaccess`
- （設定により）`logs/<DIR_USER>/` や `logs/<DIR_USER>/.htaccess`
- `api/.htaccess`
- `robots.txt`

---

## セキュリティ（重要）

### Basic認証が使える場合は「api/ に強く推奨」
Notemod-selfhosted は「クリップボードの内容」「個人メモ」などの個人情報を扱います。  
`.htaccess` や `robots.txt` を置いていても、公開サーバーに置く以上は、最低でも `api/` ディレクトリーに **Basic認証の導入を強く推奨** します。

**なぜ必要か**
- `robots.txt` は「検索エンジンへのお願い」であり、アクセス制限にはなりません
- `/api/` は公開されていると外部から叩けます
- トークンが長期で公開され続けると、総当たり（ブルートフォース）などの対象になり得ます
- Basic認証は「PHPのトークン認証の前」に追加の壁を作れるため、安全性が大きく上がります

### Basic認証を使えない場合：Web UI認証
共用サーバーによっては Basic認証を提供していない場合があります。  
その場合は **Web UI認証 + トークン** で運用してください。

- 初期セットアップ/管理: `setup_auth.php`
- 未ログイン: 重要値は伏字、編集不可
- ログイン中: アカウント/認証情報の管理が可能

### `data.json` 暗号化について
v1.4.1 では、保存される `data.json` を暗号化できます。  
ただし、暗号化はサーバー侵入そのものを防ぐものではなく、**漏えい時の平文露出を減らすための追加レイヤー**です。

- まずは **Basic認証 / Web UI認証 / 強いトークン / 公開範囲の最小化** を優先してください
- 暗号化キーを失うと復号できなくなるため、**安全な方法で別保管**してください
- エクスポートは平文 JSON のため、エクスポートファイルの扱いにも注意してください

---

## 使い方・連携

具体的な利用方法（API叩き方 / iPhoneショートカット / ClipboardSync連携など）は、以下のリンクで紹介します。

- [StayHomeLab YouTube ch](https://www.youtube.com/@StayHomeLab)
- [Website](https://stayhomelab.net/notemod-selfhosted)
- [ClipboardSync](https://github.com/StayHomeLabNet/ClipboardSync)

---

## この改造版で追加した主な機能

- **サーバー同期エンドポイント**（`notemod_sync.php`）
  - トークン認証
  - `save` / `load`
  - `data.json` が無い場合は初期スナップショットで自動生成
  - ユーザー別ディレクトリー構成に対応
  - 暗号化保存データの読み書きに対応

- **書き込みAPI**（`api/api.php`）
  - 任意カテゴリにノート追加（カテゴリが無ければ自動作成）
  - 画像アップロード対応
  - ファイルアップロード対応
  - テキスト追加時に `note_latest.json` を更新
  - WebP画像を受信した場合、サーバー側で PNG に変換して保存

- **読み取りAPI**（`api/read_api.php`）
  - 読み取り専用
  - `latest_note` は **Logsカテゴリを常に除外**
  - `pretty=2` で *本文だけのプレーンテキスト* を返す（iPhoneショートカットやCLI向け）
  - `latest_clip_type` で、最後に送られた種別（テキスト/画像/ファイル）を判定
  - 暗号化保存データの読み取りに対応

- **画像配信API**（`api/image_api.php`）
  - 指定ユーザー配下の画像を配信
  - 幅/高さ指定の簡易リサイズ
  - キャッシュ制御

- **削除API**（`api/cleanup_api.php`）※不要なら削除推奨
  - カテゴリ単位で全削除（POST専用）
  - `dry_run` 対応
  - `dry_run=2` で対象数のみ取得
  - バックアップ作成を設定で ON/OFF 可能
  - バックアップ一括削除（`purge_bak=1`）
  - ログファイル一括削除（`purge_log=1`）
  - 画像 / ファイルの一括削除
  - `file_index.json` の再生成補助

- **メディア / ファイル管理画面**（`media_files.php`）
  - サーバー設定の確認
  - images/files 件数表示
  - 画像一覧（サムネ + ダウンロード）
  - ファイル一覧（履歴 / インデックス参照）
  - ドラッグ＆ドロップアップロード

- **アクセスログ統合**（`logger.php`）
  - `/logs/<USER_NAME>/access-YYYY-MM.log` へのファイルログ（ON/OFF可能）
  - Notemod の **Logsカテゴリ** への月別ノート追記（ON/OFF可能）
  - タイムゾーンは config から取得
  - ログフォルダーが無ければ作成 + `.htaccess` も作成

- **toolbarにコピー / ペーストボタンを追加**（`index.php`）
  - オリジナルのコピー＆ペーストボタン（sag-tik）を無効化
  - 選択が無い場合はノート全体をコピー、選択がある場合は選択範囲をコピー

- **Web UI認証**
  - Basic認証なしでログイン制にできる
  - UIに設定アイコン（歯車）を追加し、アカウント/認証情報へ遷移

- **PWA対応**
  - iPhone/Android の「ホーム画面に追加」でアプリっぽく起動（HTTPS推奨/実質必須）

---

## 動作要件

- **PHP 8.1 以上**
- Apache 推奨（`.htaccess` を使うため）
- PHP から書き込み可能な場所
  - `config/`
  - `notemod-data/`
  - （任意）`logs/`

---

## 推奨ディレクトリ構成

```text
public_html/
├─ index.php
├─ notemod_sync.php
├─ logger.php
├─ auth_common.php
├─ data_crypto.php
├─ setup_auth.php
├─ login.php
├─ logout.php
├─ account.php
├─ bak_settings.php
├─ clipboard_sync.php
├─ log_settings.php
├─ media_files.php
├─ manifest.php
├─ sw-register.js
├─ sw.php
├─ service-worker.js
├─ api/
│  ├─ api.php
│  ├─ read_api.php
│  ├─ image_api.php
│  └─ cleanup_api.php
├─ config/
│  └─ <DIR_USER>/
│     ├─ config.php
│     └─ config.api.php
├─ notemod-data/
│  └─ <DIR_USER>/
│     ├─ data.json
│     ├─ note_latest.json
│     ├─ image_latest.json
│     ├─ file_latest.json
│     ├─ file.json
│     ├─ file_index.json
│     ├─ images/
│     └─ files/
├─ logs/
│  └─ <DIR_USER>/
│     └─ access-YYYY-MM.log
└─ pwa/
   ├─ icon-192.png
   └─ icon-512.png
```

---

## 設定ファイル（例）

### `config/<DIR_USER>/config.php`

```php
<?php
return [
    'TIMEZONE' => 'Asia/Tokyo',
    'DEBUG' => false,
    'LOGGER_FILE_ENABLED' => true,
    'LOGGER_NOTEMOD_ENABLED' => true,
    'IP_ALERT_ENABLED' => true,
    'IP_ALERT_TO' => 'YOUR_EMAIL',
    'IP_ALERT_FROM' => 'notemod@localhost',
    'IP_ALERT_SUBJECT' => 'Notemod: First-time IP access',
    'IP_ALERT_IGNORE_BOTS' => true,
    'IP_ALERT_IGNORE_IPS' => [''],
    'LOGGER_FILE_MAX_LINES' => 500,
    'LOGGER_NOTEMOD_MAX_LINES' => 50,
    'SECRET' => 'CHANGE_ME_SECRET',
    'DATA_ENCRYPTION_ENABLED' => false,
    'DATA_ENCRYPTION_KEY' => 'CHANGE_ME_ENCRYPTION_KEY',
];
```

### `config/<DIR_USER>/config.api.php`

```php
<?php
return [
    'EXPECTED_TOKEN' => 'CHANGE_ME_EXPECTED_TOKEN',
    'ADMIN_TOKEN' => 'CHANGE_ME_ADMIN_TOKEN',
    'DATA_JSON' => dirname(__DIR__, 2) . '/notemod-data/<DIR_USER>/data.json',
    'DEFAULT_COLOR' => '3478bd',
    'CLEANUP_BACKUP_ENABLED' => true,
    'CLEANUP_BACKUP_SUFFIX' => '.bak-',
    'CLEANUP_BACKUP_KEEP' => 10,
];
```

---

## API使用例

### ノート追加（GET/POST）

```text
/api/api.php?token=EXPECTED_TOKEN&user=YOUR_DIR_USER&category=INBOX&text=Hello
```

### 最新ノート取得（Logs除外・本文のみ）

```text
/api/read_api.php?token=EXPECTED_TOKEN&user=YOUR_DIR_USER&action=latest_note&pretty=2
```

### 最新クリップ種別の取得

```text
/api/read_api.php?token=EXPECTED_TOKEN&user=YOUR_DIR_USER&action=latest_clip_type
```

### 画像取得

```text
/api/image_api.php?user=YOUR_DIR_USER&file=photo.png&w=300
```

### ログファイル一括削除（POST）

```bash
curl -X POST "https://USER:PASS@YOUR_SITE/api/cleanup_api.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "user=YOUR_DIR_USER" \
  --data-urlencode "token=ADMIN_TOKEN" \
  --data-urlencode "purge_log=1" \
  --data-urlencode "confirm=YES"
```

### バックアップファイル一括削除（POST）

```bash
curl -X POST "https://USER:PASS@YOUR_SITE/api/cleanup_api.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "user=YOUR_DIR_USER" \
  --data-urlencode "token=ADMIN_TOKEN" \
  --data-urlencode "purge_bak=1" \
  --data-urlencode "confirm=YES"
```

---

## ClipboardSync（Windowsアプリ）連携

この改造版は外部クライアントからの投入を前提にしています（任意）。

<https://github.com/StayHomeLabNet/ClipboardSync>

よくある流れ:
1. ClipboardSync がクリップボードを監視
2. 有効化されている間、クリップボードの内容を `api/api.php` に送信
3. `read_api.php?action=latest_clip_type` を利用して、最後に送った種別に応じた受信ができる
4. Notemod UI または受信ホットキーで即反映

---

## robots.txt について（検索エンジン対策）

推奨（全クローラー拒否）

```text
User-agent: *
Disallow: /
```

注意：
- `robots.txt` はアクセス制御ではありません  
  → **Basic認証** または **Web UI認証**、`.htaccess` 等で保護してください

---

## ライセンス

MIT License。  
本プロジェクトは **Oray Emre Gündüz 氏の Notemod（MIT）** をベースにしています。  
MITライセンスの条件として、**著作権表示とライセンステキストを保持**してください。

---

## クレジット

- Notemod（本家）: <https://github.com/orayemre/Notemod>
