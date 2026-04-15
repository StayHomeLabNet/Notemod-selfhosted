<?php
// config/config.php
// この config/config.php は setup_auth.php により自動生成されました
return [
    // PHP のタイムゾーン設定（例）
    // 日本 : Asia/Tokyo
    // NZ    : Pacific/Auckland
    // AU    : Australia/Sydney
    // US    : America/Los_Angeles / America/New_York
    // カナダ: America/Toronto / America/Vancouver
    // トルコ: Europe/Istanbul
    'TIMEZONE' => 'Asia/Tokyo',

    // true にするとデバッグログを有効化します
    'DEBUG' => false,

    // ロガーの有効/無効
    // 生アクセスログ（/logs/access-YYYY-MM.log）
    'LOGGER_FILE_ENABLED' => true,

    // Notemod の Logs カテゴリに記録（月別ノート: access-YYYY-MM）
    'LOGGER_NOTEMOD_ENABLED' => false,

    // Web UI の同期保存前に自動バックアップを作成
    'SYNC_PRE_SAVE_BACKUP_ENABLED' => true,

    // Web UI の同期保存前バックアップの直前に、古いバックアップを自動整理
    'SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED' => false,

    // データ暗号化の有効/無効
    'DATA_ENCRYPTION_ENABLED' => false,

    // データ暗号化キー
    // 実運用では十分に長くランダムな文字列を設定してください
    'DATA_ENCRYPTION_KEY' => 'CHANGE_ME_DATA_ENCRYPTION_KEY',

    // セッションクッキー保持期間
    // 0 = ブラウザを閉じるまで
    // 86400 = 1日
    // 604800 = 7日
    // 2592000 = 30日
    'SESSION_COOKIE_LIFETIME' => 0,

    // （任意）Notemod の初期スナップショットをカスタマイズ
    // （JSON文字列として保存する必要があります）
    // 'INITIAL_SNAPSHOT' => '{"categories":null,"hasSelectedLanguage":null,"notes":null,"selectedLanguage":null}',

    // 初回IPアクセス通知（メール）
    'IP_ALERT_ENABLED' => false,                     // 有効化: true
    'IP_ALERT_TO'      => 'YOUR_EMAIL',             // 送信先:
    'IP_ALERT_FROM'    => 'no-reply@notemod',       // 送信元: 任意
    'IP_ALERT_SUBJECT' => 'Notemod: First-time IP access', // 件名
    'IP_ALERT_IGNORE_BOTS' => true,                 // ボットっぽいUser-Agentを無視する
    'IP_ALERT_IGNORE_IPS' => array(),               // 自分の固定IP等、除外したいIPを指定
    // IP_ALERT_STORE は通常不要です
    // 未設定時は logger.php が notemod-data/<DIR_USER>/_known_ips.json を自動使用します

    // 0 = 制限なし（何もしない）
    // 例：月別の生ログ（access-YYYY-MM.log）— 最大 500 行
    //     Notemod Logs — ノート内は最大 50 行
    'LOGGER_FILE_MAX_LINES' => 500,
    'LOGGER_NOTEMOD_MAX_LINES' => 50,

    // アプリケーション秘密値（内部用）
    //（署名、暗号化、固定キーなどに使用）
    // 未指定の場合は setup_auth.php が自動追記します
    'SECRET' => 'CHANGE_ME_SECRET',
];
