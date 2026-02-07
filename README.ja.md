# Notemod-selfhosted（改造版 Notemod）v1.2.3

これは **[Notemod（本家）](https://github.com/orayemre/Notemod)**（MIT License）をベースに、**共用サーバーでも動く自己ホスト型メモ基盤**として拡張したフォークです（DB不要）。  
データベースを使わないため、ウェブサーバーにアップロードして設定ファイルを用意すればすぐ使えます。

動作確認済みの共用サーバー: Xサーバー、さくらインターネット、XREA（エクスリア）、InfinityFree  
テスト済み PHP: 8.3.21（**PHP 8.1 以上が必須**）

> **単一データソース:** `notemod-data/data.json`

---

## 開発の目的

iPhone と Windows PC 間のクリップボード連携を、iPhone と Mac 間の快適さに少しでも近づける。  
これを **外部サービスに依存せず** 実現する。

- Windows PC でコピーされたテキストが即時に Notemod-selfhosted に送られる（**Windowsアプリ ClipboardSync**）
- iPhone のショートカットを実行すると、そのテキストが iPhone でペーストできる
- iPhone でコピーされたテキストが、iPhone ショートカットの実行で、即時に Notemod-selfhosted に送られる
- Windows PC で、ホットキー（例 Ctrl + Alt + R）を実行すると、すぐにペーストできる（**Windowsアプリ ClipboardSync**）

---

## v1.2.3 の主な変更点

### 1) 設定アイコン（歯車）に「クリップボード同期」を追加
- 設定（歯車）に **Clipboard Sync** を追加
- **ClipboardSync（Windowsアプリ）＋ iPhoneショートカットの設定が大幅に簡素化**
  - ClipboardSync側の設定は **コピー＆ペースト中心**で完了
  - iPhoneショートカットは **ダウンロード可能**（自作不要）
  - iPhoneショートカットの初期設定も **ほぼコピー＆ペースト**で完了

### 2) バックアップ設定の強化（リストア + 今すぐバックアップ）
- 設定（歯車）のバックアップ設定（`bak_settings.php`）に以下を追加
  - **リストア機能**
  - **「今すぐバックアップを作成」ボタン**
    - 現在の `notemod-data/data.json` をコピーしてバックアップとして保存（タイムスタンプ付きファイル名）

### 3) 各種設定で「戻る」などの導線を上部＋下部に配置
- 各種設定ページで、リンク（例：「戻る」など）を **上部と下部の両方**に配置し、操作性を改善

### 4) 各種設定で Notemod 本体の言語設定を引き継ぐ
- カスタム設定ページは **日本語/英語のみ**対応
- Notemod本体は多言語対応のため、設定ページ側は **Notemod本体の言語設定を参照して自動選択**
  - `selectedLanguage === "JA"` の場合は日本語
  - それ以外（英語/日本語以外を含む）は **英語に誘導**
- 低負荷な方法として **localStorage から読み取り**

### 5) TIMEZONE の適用範囲を拡張（バックアップにも反映）
- これまで Log の時刻にだけ反映していた `config/config.php` の **TIMEZONE** を、バックアップにも反映
  - **バックアップファイル名のタイムスタンプ**
  - **バックアップ一覧の日時表示**
  が、サーバー時刻ではなく **指定TIMEZONE** に統一されます

### 6) ログの上限設定（x行まで）
- **月別の生ログ**
- **Notemod Logs ノート**
に対して、**最大 x 行まで**の上限設定を可能にし、ログ肥大化を抑制

### 7) `read_api.php` のスピード改善
- `api/read_api.php` の処理を見直し、読み取り系のレスポンスを改善

## v1.1.6 の追加点

- Web UI にログ設定とバックアップ設定を追加
- メールによる初回IPアクセス通知機能を追加（初めてのIPアドレスからアクセスがあった場合、メールで通知）
- 今回のアップデートにより、**config.php** と **config.api.php** の編集は、Web UI で可能になりました
  - データファイルのパスの変更など、ソースコードの改変が伴う可能性のある改造は **config.php** と **config.api.php** を編集してください。
- 設定アイコン（歯車） にバックアップ設定とログ設定を追加

## v1.1.0 の追加点

- Web UIでの初期設定に対応
- **Web UIでの認証機能**（BASIC認証を使用できないサーバーに対応）
- Notemod のUIに **設定アイコン（歯車）** を追加（アカウント/認証情報）
- `setup_auth.php` による **config.php** と **config.api.php** の自動生成に対応
- `cleanup_api.php` で **ログファイル** と **バックアップファイル** の一括削除に対応
- **PHP 8.1 以上が必要**
- **PWA対応**（ホーム画面に追加してアプリっぽく使える）

---

## 使用方法

### 1. サーバーへ配置
このリポジトリ一式を、サーバーの公開フォルダー（例: `public_html/`）にアップロードします。

> 補足: `config/` や `api/` も `index.php` と同じ階層に置いてください  
> （構成を変える場合は PHP 側のパス調整が必要です）

### 2. 設定ファイルを作成（重要）

#### 設定ファイルの自動生成
v1.1.0 で Web UI での 初期設定の際に自動的に生成されます。
タイムゾーンやログのオン/オフ、バックアップファイル自動作成のオン/オフなどは、下記設定ファイルを編集してください。

#### 共通設定
`config/config.sample.php` または `config/config.sample.ja.php` を `config/config.php` にリネームし、以下を設定します  
- `CHANGE_ME_SECRET` を任意の長い文字列に置き換え（16文字以上推奨）
- `TIMEZONE` を必要に応じて変更

#### API設定
`config/config.api.sample.php` を `config/config.api.php` にリネームし、以下を設定します  
- `CHANGE_ME_EXPECTED_TOKEN` を任意の文字列に置き換え
- `CHANGE_ME_ADMIN_TOKEN` を任意の文字列に置き換え（cleanup用。EXPECTED_TOKENより強い値推奨）

### 3. SECRET / TOKEN の生成
SECRET や TOKEN の生成にはパスワード生成サイトを利用できます（例）
https://passwords-generator.org/

- 第三者サイトが不安な場合は、OS標準のパスワード生成や `openssl rand -hex 32` 等でもOKです
- ※「SECRET」は将来的に廃止する可能性があります

### 4. 初期化（初回のみ）
公開URLへアクセスすると Notemod-selfhosted が開きます。  
表示言語をセットし、最初のカテゴリーを作成してください。

この操作により以下が、なければ自動生成されます（環境/設定による）
- `notemod-data/data.json`（初期スナップショット）
- `notemod-data/.htaccess`（直アクセス防止）
- （設定により）`logs/` や `logs/.htaccess`
- `api/.htaccess`（デフォルトではAPIへのアクセスを許可。認証設定を推奨）

![](Notemod-selfhosted.png)

---

## セキュリティ（重要）

### Basic認証が使える場合は「api/ に強く推奨」
Notemod-selfhosted は「クリップボードの内容」「個人メモ」などの個人情報を扱います。  
`.htaccess` や `robots.txt` を置いていても、公開サーバーに置く以上は、最低でも api/ ディレクトリーに **Basic認証の導入を強く推奨** します。

**なぜ必要か**
- `robots.txt` は「検索エンジンへのお願い」であり、アクセス制限にはなりません
- `/api/` は公開されていると外部から叩けます
- トークンが長期で公開され続けると、総当たり（ブルートフォース）などの対象になり得ます
- Basic認証は「PHPのトークン認証の前」に追加の壁を作れるため、安全性が大きく上がります

### Basic認証を使えない場合：Web UI認証（v1.1.0）
共用サーバーによっては Basic認証を提供していない場合があります。  
その場合は **Web UI認証 + トークン** で運用してください。

- 初期セットアップ/管理: `setup_auth.php`
- 未ログイン: 重要値は伏字、編集不可
- ログイン中: アカウント/認証情報の管理が可能

---

## 使い方・連携
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
  - `api/` に `.htaccess` が無ければ作成
- **書き込みAPI**（`api/api.php`）
  - 任意カテゴリにノート追加（カテゴリが無ければ自動作成）
- **読み取りAPI**（`api/read_api.php`）
  - 読み取り専用
  - `latest_note` は **Logsカテゴリを常に除外**
  - `pretty=2` で *本文だけのプレーンテキスト* を返す（iPhoneショートカットやCLI向け）
- **削除API**（`api/cleanup_api.php`）※不要なら削除推奨
  - カテゴリ単位で全削除（POST専用）
  - `dry_run` 対応
  - バックアップ作成を設定でON/OFF可能
  - **バックアップ一括削除**（`purge_bak=1`）（v1.1.0）
  - **ログファイル一括削除**（`purge_log=1`）（v1.1.0）
- **アクセスログ統合**（`logger.php`）
  - `/logs*/access-YYYY-MM.log` へのファイルログ（ON/OFF可能）
  - Notemod の **Logsカテゴリ** への月別ノート追記（ON/OFF可能）
  - タイムゾーンは config から取得
  - ログフォルダが無ければ作成 + `.htaccess` も作成
- **toolbarにコピー / ペーストボタンを追加**（`index.php`）
  - オリジナルのコピー＆ペーストボタン（sag-tik）を無効化
  - 選択が無い場合はノート全体をコピー、選択がある場合は選択範囲をコピー
- **Web UI認証**（v1.1.0）
  - Basic認証なしでログイン制にできる
  - UIに設定アイコン（歯車）を追加し、アカウント/認証情報へ遷移
- **PWA対応**（v1.1.0）
  - iPhone/Android の「ホーム画面に追加」でアプリっぽく起動（HTTPS推奨/実質必須）

---

## 動作要件

- **PHP 8.1 以上**
- Apache 推奨（`.htaccess` を使うため）
- PHP から書き込み可能な場所
  - `notemod-data/`
  - （任意）`logs/` または設定したログフォルダ

---

## 推奨ディレクトリ構成

```text
public_html/
├─ index.php
├─ notemod_sync.php
├─ logger.php
├─ auth_common.php
├─ setup_auth.php
├─ login.php
├─ logout.php
├─ account.php
├─ bak_settings.php
├─ clipboard_sync.php
├─ log_settings.php
├─ api/
│  ├─ api.php
│  ├─ read_api.php
│  └─ cleanup_api.php
├─ config/
│  ├─ config.php             # サーバー側で作成（秘密情報/設定。Git管理しない）
│  ├─ config.sample.php      # サンプル
│  ├─ config.api.php         # サーバー側で作成（トークン等。Git管理しない）
│  └─ config.api.sample.php  # サンプル
├─ notemod-data/
│  └─ data.json              # 単一データソース（Single source of truth）
└─ pwa/
   └─ （PWA関連ファイル）
```

---

## 設定ファイル（例）

### `config/config.php`

```php
<?php
return [
    'SECRET' => 'CHANGE_ME_SECRET',
    'TIMEZONE' => 'Asia/Tokyo',
    'DEBUG' => false,

    'LOGGER_FILE_ENABLED' => true,
    'LOGGER_NOTEMOD_ENABLED' => true,

    'INITIAL_SNAPSHOT' => '{"categories":null,"hasSelectedLanguage":null,"notes":null,"selectedLanguage":null}',
];
```

### `config/config.api.php`

```php
<?php
return [
    'EXPECTED_TOKEN' => 'CHANGE_ME_EXPECTED_TOKEN',
    'ADMIN_TOKEN'    => 'CHANGE_ME_ADMIN_TOKEN',

    'DATA_JSON' => dirname(__DIR__) . '/notemod-data/data.json',
    'DEFAULT_COLOR' => '3478bd',

    'CLEANUP_BACKUP_ENABLED' => true,
    'CLEANUP_BACKUP_SUFFIX'  => '.bak-',
];
```

---

## API使用例

### ノート追加（GET/POST）
```text
/api/api.php?token=EXPECTED_TOKEN&category=INBOX&text=Hello
```

### 最新ノート取得（Logs除外・本文のみ）
```text
/api/read_api.php?token=EXPECTED_TOKEN&action=latest_note&pretty=2
```

### ログファイル一括削除（POST）【v1.1.0】
```bash
curl -X POST "https://USER:PASS@YOUR_SITE/api/cleanup_api.php"   -H "Content-Type: application/x-www-form-urlencoded"   --data-urlencode "token=ADMIN_TOKEN"   --data-urlencode "purge_log=1"   --data-urlencode "confirm=YES"
```

### バックアップファイル一括削除（POST）【v1.1.0】
```bash
curl -X POST "https://USER:PASS@YOUR_SITE/api/cleanup_api.php"   -H "Content-Type: application/x-www-form-urlencoded"   --data-urlencode "token=ADMIN_TOKEN"   --data-urlencode "purge_bak=1"   --data-urlencode "confirm=YES"
```

---

## ClipboardSender（Windowsアプリ）連携

この改造版は外部クライアントからの投入を前提にしています（任意）。

https://github.com/StayHomeLabNet/ClipboardSender  
![](ClipboardSender.png)

よくある流れ:
1. ClipboardSender がクリップボードを監視
2. 有効化されている間、クリップボードの内容を `api/api.php` に送信
3. Notemod UI に即反映

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

- Notemod（本家）: https://github.com/orayemre/Notemod
