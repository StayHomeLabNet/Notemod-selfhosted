# Notemod-selfhosted（改造版 Notemod）v1.3.0

これは **[Notemod（本家）](https://github.com/orayemre/Notemod)**（MIT License）をベースに、**共用サーバーでも動く自己ホスト型メモ基盤**として拡張したフォークです。  
**DB不要** で動作し、テキストだけでなく **画像** と **ファイル** のコピー＆ペースト連携、管理画面からの整理、バックアップ、Web UI認証までまとめて扱えます。Windows アプリの Clipboard Sync と連携して、特に iPhone と Windows PC 間のクリップボードの利用を快適にします。

動作確認済みの共用サーバー: Xサーバー、さくらインターネット、XREA（エクスリア）、InfinityFree  
テスト済み PHP: 8.3.21（**PHP 8.1 以上が必須**）

> **単一データソース:** `notemod-data/data.json`

---

## このバージョンのハイライト（v1.3.0）

v1.3.0 では、従来の **テキスト中心のクリップ連携** から一歩進み、**画像とファイルのコピー＆ペースト運用** に対応しました。

- **画像のコピー＆ペースト運用に対応**
- **ファイルのコピー＆ペースト運用に対応**
- **メディア / ファイル管理ページ**（`media_files.php`）を追加
- **画像・ファイルのアップロード / ダウンロード / 整理** に対応
- **画像 / ファイルの選択削除・全削除** に対応
- **Clipboard Sync 設定画面** を追加
- **バックアップ設定画面** と **ログ設定画面** を追加
- **Web UI認証** と **PWA対応** を継続搭載

このアップデートにより、Notemod-selfhosted は **テキスト・画像・ファイルをまとめて扱える自己ホスト環境** になりました。

---

## 開発の目的

iPhone と Windows PC 間のクリップボード連携を、iPhone と Mac 間の快適さに少しでも近づける。  
これを **外部サービスに依存せず** 実現することが目的です。

代表的な使い方:

- Windows PC でコピーしたテキストを Notemod-selfhosted に送る（Windows アプリ ClipboardSync で自動化可能）
- iPhone のショートカットやアプリ連携で最新テキストを取得する
- 画像を保存・取得する
- PDF やその他ファイルを保存・取得する
- Web管理画面からメディアやバックアップを整理する
- Webブラウザからアクセスすることで、異なるデバイスとのテキストやファイルのやり取りが容易になる。

---

## 主な機能

### 1. Notemod 本体の自己ホスト運用
- Notemod UI をサーバー上で動かせます
- データは `notemod-data/data.json` に保存されます
- DB不要です

### 2. テキスト書き込み / 読み取り API
- `api/api.php` でノート追加
- `api/read_api.php` で最新ノート取得
- `latest_note` は Logs カテゴリを除外
- `pretty=2` で本文だけを返す運用に対応

### 3. 画像 / ファイルのコピー＆ペースト対応
- 画像アップロード
- 一般ファイルアップロード
- 最新画像 / 最新ファイル取得
- 最新クリップ種別の判定
- 画像 / ファイルの管理画面からの整理

### 4. Cleanup API
- 管理者トークンで破壊的操作を実行
- バックアップ作成付き削除に対応（バックアップ作成のみも可能）
- ログファイル / バックアップファイルの一括削除
- 画像 / ファイル整理機能

### 5. Web UI認証
- Basic認証が使えない環境でも、ログイン制で管理可能
- 設定画面や各種管理画面を保護

### 6. バックアップ / リストア
- `bak_settings.php` から設定変更
- 今すぐバックアップ
- 最新から n 個残して古いバックアップ削除
- バックアップ一覧から `data.json` を復元

### 7. ログ設定
- `log_settings.php` から設定変更
- アクセスログの有効 / 無効
- アクセスログを Notemod のカテゴリーに保存の有効 / 無効
- アクセスログの最大行数の設定
- 初めてのIPアクセスからのアクセスの際に通知の有効 / 無効
- IPアクセス通知のメールアドレスを設定

### 8. PWA対応
- iPhone / Android のホーム画面追加に対応
- アプリのように起動できます

---

## こんな人に向いています

- 自分専用のクリップ / ノート基盤を持ちたい
- 外部クラウドのクリップ同期に依存したくない
- 共用サーバーで軽く動く仕組みがほしい
- iPhone と Windows の橋渡しを自前で作りたい
- テキストだけでなく画像やファイルも扱いたい

---

## 推奨ディレクトリ構成

```text
public_html/
├─ index.php                 # Notemod UI
├─ logger.php                # アクセスログ + Logsカテゴリ追記
├─ notemod_sync.php          # 同期エンドポイント（save/load）
├─ setup_auth.php            # Web UI 初期セットアップ
├─ login.php / logout.php    # ログイン / ログアウト
├─ auth_common.php           # 認証共通
├─ account.php               # アカウント / 管理メニュー
├─ clipboard_sync.php        # Clipboard Sync 設定画面
├─ bak_settings.php          # バックアップ設定画面
├─ log_settings.php          # ログ設定画面
├─ media_files.php           # 画像 / ファイル管理画面
├─ api/
│  ├─ api.php                # ノート追加 / 画像 / ファイル受け取り
│  ├─ read_api.php           # 読み取りAPI
│  └─ cleanup_api.php        # 破壊的操作（管理者）
├─ config/
│  ├─ config.php             # 共通設定（Gitに入れない）
│  └─ config.api.php         # API設定（Gitに入れない）
├─ notemod-data/
│  ├─ data.json              # 単一データソース
│  └─ .htaccess
├─ pwa/
│  ├─ icon-192.png
│  └─ icon-512.png
├─ manifest.php
├─ service-worker.js / sw.php / sw-register.js
└─ robots.txt
```

---

## 使用方法

### 1. サーバーへ配置
このリポジトリ一式を、サーバーの公開フォルダー（例: `public_html/`）にアップロードします。

> `config/` や `api/` は `index.php` と同じ階層を前提にしています。  
> 構成を変える場合は PHP 側のパス調整が必要です。

### 2. 設定ファイルを作成
リポジトリ一式をサーバーにアップロードして、**index.php にアクセスすることで自動的に作成します。**

#### 共通設定
`config/config.sample.php` を `config/config.php` にコピーまたはリネームし、必要に応じて変更します。

主な項目:
- `SECRET`
- `TIMEZONE`
- `DEBUG`
- `LOGGER_FILE_ENABLED`
- `LOGGER_NOTEMOD_ENABLED`
- `INITIAL_SNAPSHOT`

#### API設定
`config/config.api.sample.php` を `config/config.api.php` にコピーまたはリネームし、必要に応じて変更します。

主な項目:
- `EXPECTED_TOKEN`
- `ADMIN_TOKEN`
- `DATA_JSON`
- `DEFAULT_COLOR`
- `CLEANUP_BACKUP_ENABLED`
- `CLEANUP_BACKUP_SUFFIX`

### 3. SECRET / TOKEN を変更
サンプルのまま使わず、必ず長いランダム値に変更してください。

例:
- `openssl rand -hex 32`

### 4. 初回起動
ブラウザで公開URLへアクセスし、初期セットアップを進めます。

環境や設定に応じて、初回起動時に次のようなファイル / ディレクトリが生成されます。

- `notemod-data/data.json`
- `notemod-data/.htaccess`
- `api/.htaccess`
- `config/config.php` / `config/config.api.php`（Web UIセットアップ使用時）
- ログ関連ファイル（設定による）

---

## セキュリティ（重要）

### Basic認証が使える場合
最低でも `api/` ディレクトリに **Basic認証を強く推奨** します。

理由:
- `robots.txt` はアクセス制御ではありません
- 公開サーバー上の `/api/` は外部から到達可能です
- 長期運用するトークンは総当たり対象になり得ます
- Basic認証は PHP のトークン認証の前段に壁を作れます

### Basic認証が使えない場合
**Web UI認証 + トークン運用** を使ってください。

- 初期セットアップ / 管理: `setup_auth.php`
- ログイン後: `account.php` などから各設定画面へ移動

---

## メディア / ファイル管理（v1.3.0の重要機能）

### 管理画面
`media_files.php`

この画面で次の操作ができます。

- 画像一覧の表示
- ファイル一覧の表示
- 画像のドラッグ＆ドロップアップロード
- ファイルのドラッグ＆ドロップアップロード
- ダウンロード
- チェックボックスによる選択削除
- 全選択 / 全解除
- 画像全削除 / ファイル全削除
- サーバーのアップロード/ダウンロード関連設定の確認

### 保存先
- 画像: `notemod-data/USER_NAME/images/`
- ファイル: `notemod-data/USER_NAME/files/`

※ `` の扱いは実装 / 認証状態に依存します。

### JSON管理の考え方
画像とファイルでは管理方法が少し異なります。

#### 画像側
- `image_latest.json`
  - 最新画像の情報を保持
- 一覧表示はフォルダ走査ベース
- 画像はサムネイルで識別できる前提

#### ファイル側
- `file.json`
  - ファイル履歴ログ
- `file_index.json`
  - 現存ファイル一覧
- `file_latest.json`
  - 最新ファイル情報

ファイル側で `file_index.json` を使う理由は、**保存名ではなく元のファイル名を一覧表示するため** です。

### latest 保護について
`cleanup_api.php` の整理系処理では、現在の実装上 **latest の実体を保護** する仕様があります。  
そのため「全削除」でも latest 実体が残る場合があります。

---

## バックアップ設定

### 管理画面
`bak_settings.php`

主な操作:
- バックアップ機能の有効 / 無効
- 保持数の設定
- 今すぐバックアップ
- 最新から n 個を残して古いバックアップを削除
- バックアップ一覧から `data.json` をリストア

特徴:
- 復元前に現在の `data.json` を自動バックアップ
- `TIMEZONE` を使った日時表示 / ファイル名運用

---

## ログ設定

### 管理画面
`log_settings.php`

主な設定:
- アクセスログの有効 / 無効
- Notemod の Logs カテゴリへの追記の有効 / 無効
- ログ関連設定の保存
- 初めてのIPアクセスからのアクセスの際に通知の有効 / 無効
- IPアクセス通知のメールアドレスを設定

---

## Clipboard Sync 設定

### 管理画面
`clipboard_sync.php`

この画面では、外部クライアント（Windows アプリ ClipboardSync）と連携するための API URL やトークン確認を行えます。
ClipboardSync のダウンロードリンクと iPhone との連携のための iOS ショートカットのダウンロードリンクを提供します。

主な用途:
- Windows アプリの設定
- iPhone ショートカットへのURL転記（ショートカットの初期設定に使用）
- `/api/` のURL確認
- `api.php` / `read_api.php` / `cleanup_api.php` のURL確認

---

## API 概要

### `api/api.php`
主に書き込み側のエンドポイントです。

対応例:
- ノート追加
- 画像アップロード
- ファイルアップロード
- WebP 画像の扱い
- `image_latest.json` / `file.json` / `file_index.json` / `file_latest.json` の更新

### `api/read_api.php`
読み取り側のエンドポイントです。

対応例:
- `latest_note`
- `latest_image`
- `latest_file`
- `latest_clip_type`

### `api/cleanup_api.php`
管理者用の破壊的操作エンドポイントです。

対応例:
- カテゴリ削除
- ログ削除
- バックアップ削除
- 画像整理 / ファイル整理
- 選択削除 / 全削除
- `file_index.json` 再構築
- `file_latest.json` 補正

---

## API使用例

### ノート追加
```text
/api/api.php?token=EXPECTED_TOKEN&category=INBOX&text=Hello
```

### 最新ノート取得（Logs除外・本文のみ）
```text
/api/read_api.php?token=EXPECTED_TOKEN&action=latest_note&pretty=2
```

### 最新画像取得
```text
/api/read_api.php?token=EXPECTED_TOKEN&action=latest_image
```

### 最新ファイル取得
```text
/api/read_api.php?token=EXPECTED_TOKEN&action=latest_file
```

### 最新クリップ種別取得
```text
/api/read_api.php?token=EXPECTED_TOKEN&action=latest_clip_type
```

### ログファイル一括削除（POST）
```bash
curl -X POST "https://USER:PASS@YOUR_SITE/api/cleanup_api.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "token=ADMIN_TOKEN" \
  --data-urlencode "purge_log=1" \
  --data-urlencode "confirm=YES"
```

### バックアップファイル一括削除（POST）
```bash
curl -X POST "https://USER:PASS@YOUR_SITE/api/cleanup_api.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "token=ADMIN_TOKEN" \
  --data-urlencode "purge_bak=1" \
  --data-urlencode "confirm=YES"
```

---

## 外部連携

具体的な利用方法（APIの叩き方 / iPhoneショートカット / Windowsアプリ連携など）は以下も参照してください。

- [StayHomeLab YouTube ch](https://www.youtube.com/@StayHomeLab)
- [Website](https://stayhomelab.net/notemod-selfhosted)
- [ClipboardSender](https://github.com/StayHomeLabNet/ClipboardSender)

---

## 動作要件

- **PHP 8.1 以上**
- Apache 推奨（`.htaccess` 利用のため）
- PHP から書き込み可能な場所
  - `notemod-data/`
  - `config/`（初期セットアップ時）
  - `logs/` または設定したログディレクトリ（任意）

---

## `robots.txt` について

推奨:

```text
User-agent: *
Disallow: /
```

注意:
- `robots.txt` はアクセス制御ではありません
- Basic認証 / Web UI認証 / `.htaccess` 等による保護を併用してください

---

## README とウェブ用マニュアルの役割分担

- **README**: プロジェクト全体の概要、導入、主な機能の案内
- **ウェブ用マニュアル**: 各画面の使い方、設定項目、運用手順、トラブル時の確認ポイント

README だけで全操作を説明しきるのではなく、詳細な操作説明はウェブ用マニュアルへ分ける構成をおすすめします。

---

## ライセンス

MIT License。

本プロジェクトは **Oray Emre Gündüz 氏の Notemod（MIT）** をベースにしています。  
MITライセンスの条件として、**著作権表示とライセンステキストを保持**してください。

---

## クレジット

- Notemod（本家）: https://github.com/orayemre/Notemod
