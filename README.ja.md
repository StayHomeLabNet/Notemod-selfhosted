# Notemod-selfhosted（改造版 Notemod）

これは **[Notemod（本家）](https://github.com/orayemre/Notemod)**（MIT License）をベースに、**共用サーバーでも動く自己ホスト型メモ基盤**として拡張したフォークです（DB不要）。データベースを使用していないので、ウェブサーバーにアップロードするだけで、すぐに使用可能です。

ファイルをサーバーにアップロードして、秘密キーやトークンを２つの config ファイルに記述するだけで利用できます。
動作確認済みの共用サーバーは、Xサーバー、さくらインターネット、XREA（エクスリア）、InfinityFree
phpバージョンは、8.3.21 でテスト済み。

> **単一データソース:** `notemod-data/data.json`

---

## 開発の目的

iPhone と Windows PC 間のクリップボード連携を少しでも、iPhone と Mac 間の快適さに近づける。
これを外部のサービスに依存せずに実現する。
自分でサーバーを構築することで、外部への依存をさらに減らすことができます。

Windows PC でコピーされたテキストが、即時に Notemod-selfhosted に送られる。　**WindowsアプリClipboardSenderで実現**
iPhone のショートカットを実行したら、そのテキストが iPhone でペーストできるようにする。

---

## 使用方法

### 1. サーバーへ配置
このリポジトリ一式を、サーバーの公開フォルダー（例: `public_html/`）にアップロードします。

> 補足: `config/` や `api/` も `index.php` と同じ階層に置いてください  
> （構成を変える場合は PHP 側のパス調整が必要です）

### 2. 設定ファイルを作成（重要）

#### 共通設定
`config/config.sample.php` または `config/config.sample.ja.php` を `config/config.php` にリネームし、以下を設定します  
- `CHANGE_ME_SECRET` を任意の長い文字列に置き換え（16文字以上推奨）
- `TIMEZONE` を必要に応じて変更

#### API設定
`config/config.api.sample.php` または `config/config.api.sample.php` を `config/config.api.php` にリネームし、以下を設定します  
- `CHANGE_ME_EXPECTED_TOKEN` を任意の文字列に置き換え
- `CHANGE_ME_ADMIN_TOKEN` を任意の文字列に置き換え（cleanup用。EXPECTED_TOKENより強い値推奨）

### 3. SECRET / TOKEN の生成
SECRET や TOKEN の生成にはパスワード生成サイトを利用できます（例）
https://passwords-generator.org/

- 第三者サイトが不安な場合は、OS標準のパスワード生成や `openssl rand -hex 32` 等でもOKです
- *SECRET は、ただの飾りに過ぎないので将来的に廃止予定*

### 4. 初期化（初回のみ）
公開フォルダーへアクセスすると Notemod-selfhosted が開きます。  
表示言語をセットし、最初のカテゴリーを作成してください。

この操作により以下が、なければ自動生成されます
- `notemod-data/` 配下のデータファイル（例: `data.json`）
- `notemod-data/.htaccess`（直アクセス防止）
- （設定により）`logs/` や `logs/.htaccess`
- `api/.htaccess`（デフォルトでは、apiへのアクセスを許可だが、BASIC認証を強く推奨）

![](Notemod-selfhosted.png)

### 必ずBasic認証を設定してください（重要）

Notemod-selfhosted は「個人メモ」や「APIトークン」など重要な情報を扱います。  
`.htaccess` や `robots.txt` を置いていても、公開サーバーに置く以上は **Basic認証の導入を強く推奨** します。

**なぜ必要か**
- `robots.txt` は「検索エンジンへのお願い」であり、アクセス制限にはなりません（保護機能ではありません）
- `/api/` は公開されていると外部から叩けます
- トークンが長期で公開され続けると、総当たり（ブルートフォース）などの対象になり得ます
- Basic認証は「PHPのトークン認証の前」に追加の壁を作れるため、全体の安全性が大きく上がります

**推奨する適用範囲**
- サイト全体を Basic認証で保護（最も安全）
  - もしくは最低限、以下は保護推奨：
    - `/api/`
    - `/notemod-data/`
    - `/config/`（本来公開領域に置かない/直アクセス不可が望ましいが、念のため保護推奨）

**iPhoneショートカットとの相性**
- iPhoneショートカットは Basic認証に対応しているため、API連携を継続したまま安全に運用できます

### 5. 使い方・連携
具体的な利用方法（API叩き方 / iPhoneショートカット / ClipboardSender連携など）は、以下のリンクで紹介します。
- [StayHomeLab YouTube ch](https://www.youtube.com/@StayHomeLab)  
- [Website](https://stayhomelab.net/notemod-selfhosted)  
- [ClipboardSender](https://github.com/StayHomeLabNet/ClipboardSender)  

---

## この改造版で追加した主な機能

- **サーバー同期エンドポイント**（`notemod_sync.php`）
  - トークン認証
  - `save` / `load`
  - `data.json` が無い場合は初期スナップショットで自動生成
  - `notemod-data/` と `config/` に保護用 `.htaccess` を自動作成（存在しない場合）
  - apiフォルダに`.htaccess` が無ければ作成
- **書き込みAPI**（`api/api.php`）
  - 任意カテゴリにノート追加（カテゴリが無ければ自動作成）
- **読み取りAPI**（`api/read_api.php`）
  - 読み取り専用
  - `latest_note` は **Logsカテゴリを常に除外**
  - `pretty=2` で *本文だけのプレーンテキスト* を返す（iPhoneショートカットやCLI向け）
- **削除API（危険）**（`api/cleanup_api.php`）　**必要ない場合は削除**
  - カテゴリ単位で全削除（POST専用）
  - `dry_run` 対応
  - バックアップ作成を設定でON/OFF可能
- **アクセスログ統合**（`logger.php`）
  - `/logs*/access-YYYY-MM.log` へのファイルログ（ON/OFF可能）
  - Notemod の **Logsカテゴリ** への月別ノート追記（ON/OFF可能）
  - タイムゾーンは config から取得
  - ログフォルダが無ければ作成 + `.htaccess` も作成
- **toolbarにコピー / ペーストボタンを追加**（`index.php`）
  - オリジナルのコピー＆ペーストボタン（sag-tik）を無効化
  - カスタムコピーボタンは、何も選択されていない場合は、ノートの内容の全コピー
  - ノート内のテキストが選択されている場合は、選択範囲をコピー
  
---

## 動作要件

- PHP 7.4+ 推奨（多くの共有サーバーで動作）
- Apache 推奨（`.htaccess` を使うため）
- PHP から書き込み可能な場所
  - `notemod-data/`
  - （任意）`logs/` または設定したログフォルダ

---

## 推奨ディレクトリ構成

```
public_html/
├─ index.php                 # Notemod UI
├─ logger.php                # アクセスログ + Logsカテゴリ追記
├─ notemod_sync.php          # 同期エンドポイント（save/load）
├─ api/
│  ├─ api.php                # ノート追加
│  ├─ read_api.php           # 読み取りAPI
│  └─ cleanup_api.php        # カテゴリ全削除（管理者）
├─ notemod-data/
│  └─ data.json              # 単一データソース
├─ config/
│  ├─ config.php             # 共通設定（Gitに入れない）
│  └─ config.api.php         # API設定（Gitに入れない）
└─ robots.txt                # 検索エンジン対策　(全てのクローラーを拒否)
```

---

## セキュリティ（重要）

- `config/config.php` と `config/config.api.php` は **Gitに入れない**
- `notemod_sync.php` が `config/` と `notemod-data/` に `.htaccess` を自動生成（無い場合）
- 共有サーバーなら `/api/` に **Basic認証** を追加するのがおすすめ

---

## 設定ファイル

サーバー上に作成（コミットしない）:

### `config/config.php`

```php
<?php
// config/config.sample.php
// ------------------------------------------------------------
// 公開用サンプル（GitHubに置く用）
// 実運用では同じ内容で config.php を作り、SECRET等を必ず変更してください
// ------------------------------------------------------------

return [
    // アプリ内で「秘密値」として使うもの（署名・暗号化・固定キー用途などに）
    // ★必ず変更（長いランダム推奨）
    'SECRET' => 'CHANGE_ME_SECRET',

    // PHPのタイムゾーン（例）
    // 日本: Asia/Tokyo
    // 米  : America/Los_Angeles / America/New_York
    // カナダ: America/Toronto / America/Vancouver
    // 豪  : Australia/Sydney
    // NZ  : Pacific/Auckland
    // トルコ: Europe/Istanbul
    'TIMEZONE' => 'Asia/Tokyo',

    // デバッグログ等を増やしたい時だけ true
    'DEBUG' => false,

    // ★追加：logger の有効/無効
    // 生ログ（/logs/access-YYYY-MM.log）
    'LOGGER_FILE_ENABLED' => true,

    // Notemod の Logs カテゴリ（月別ノート access-YYYY-MM）
    'LOGGER_NOTEMOD_ENABLED' => true,

    // （任意）logsフォルダ名を変えたい場合
    // 'LOGGER_LOGS_DIRNAME' => 'logs',
  
    // 必要ならNotemodの初期スナップショットを変えられる
    // （JSON文字列として保存する前提）
    'INITIAL_SNAPSHOT' => '{"categories":null,"hasSelectedLanguage":null,"notes":null,"selectedLanguage":null}',
];
```

### `config/config.api.php`

```php
<?php
// config/config.api.sample.php
// ------------------------------------------------------------
// 公開用サンプル（GitHubに置く用）
// 実運用では同じ内容で config.api.php を作り、トークンを必ず変更してください
//
// EXPECTED_TOKEN : 通常の API トークン（ノート追加 / 読み取り等）
// ADMIN_TOKEN    : cleanup 専用の強いトークン（破壊的操作）
// DATA_JSON      : data.json の絶対パス（おすすめは public_html の外）
// ------------------------------------------------------------

return [
    // ★必ず変更（長いランダム推奨）
    'EXPECTED_TOKEN' => 'CHANGE_ME_EXPECTED_TOKEN',
    'ADMIN_TOKEN'    => 'CHANGE_ME_ADMIN_TOKEN',

    // api.php / read_api.php / cleanup_api.php から見た data.json の絶対パス
    // ※サンプルでは「config/ の1つ上に notemod-data がある」想定
    // 運用では public_html の外に置くのがおすすめ
    'DATA_JSON' => dirname(__DIR__) . '/notemod-data/data.json',

    // 新規作成されるカテゴリ/ノートの色（Notemod側の仕様に合わせて16進カラーっぽい文字列）
    'DEFAULT_COLOR' => '3478bd',
    
    // ★追加：cleanup のバックアップを作るか
    // true  : 実行前に data.json を .bak-YYYYmmdd-HHiiSS で保存
    // false : バックアップを作らない
    'CLEANUP_BACKUP_ENABLED' => true,

    // （任意）バックアップファイル名のプレフィックスを変えたい時
    // 例: 'data.json.bak-' にしたいなら 'data.json.bak-'
    'CLEANUP_BACKUP_SUFFIX'  => '.bak-',
];
```

---

## API使用例

### ノート追加

（簡易）GET:

```
/api/api.php?token=EXPECTED_TOKEN&category=INBOX&text=Hello
```

### 最新ノート取得（Logs除外）

iPhoneショートカット等は `pretty=2` 推奨:

```
/api/read_api.php?token=EXPECTED_TOKEN&action=latest_note&pretty=2
```

### カテゴリ全削除（危険）

POST専用:
- `dry_run=1` で件数だけ確認
- 実行時は `confirm=YES` 必須
- バックアップ作成は設定で切替

---

## ClipboardSender（Windowsアプリ）連携。（将来的には、ClipboardSynchronizer）

この改造版は外部クライアントからの投入を前提にしています。(任意)

https://github.com/StayHomeLabNet/ClipboardSender  
[GitHub Releases](https://github.com/StayHomeLabNet/Notemod-selfhosted/releases/new)  

![](ClipboardSender.png)

よくある流れ:
1. ClipboardSender がクリップボードを監視
2. 有効化されている間、クリップボードの内容を
   - `api/api.php` に送信（書き込み）
3. Notemod UI に即反映

推奨パラメータ:
- `token`
- `category`（例：`INBOX`）
- `title`（任意）
- `text`（クリップボード内容）

専用カテゴリ `CLIPBOARD` を作って運用しても便利です。

---

## robots.txt について（検索エンジン対策）

このリポジトリには `robots.txt` を同梱しています。  
Notemod-selfhosted は個人メモ用途を想定しているため、検索エンジンにページが登録されないようにする目的です。

### 推奨設定（全てのクローラーを拒否）
公開フォルダー直下に `robots.txt` を置き、以下の内容にします（推奨）:
User-agent: *
Disallow: /

### 注意（重要）
- `robots.txt` は「検索エンジンへのお願い」であり、アクセス制御ではありません  
  → 秘密情報の保護は **Basic認証** や **.htaccess (Require all denied)** 等で行ってください
- すでにインデックスされている場合、反映には時間がかかることがあります
- 公開URLを完全に秘匿したい場合は、そもそも公開ディレクトリに置かず、アクセス制限をかけてください

---

## ライセンス

MIT License。

本プロジェクトは **Oray Emre Gündüz 氏の Notemod（MIT）** をベースにしています。  
MITライセンスの条件として、**著作権表示とライセンステキストを保持**してください。

---

## クレジット

- Notemod（本家）: https://github.com/orayemre/Notemod
