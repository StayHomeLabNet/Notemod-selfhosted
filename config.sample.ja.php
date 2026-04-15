<?php
// config/<USER_NAME>/config.php
// この config/<USER_NAME>/config.php は setup_auth.php により自動生成されます
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
    // 生アクセスログ（logs/<USER_NAME>/access-YYYY-MM.log）
    'LOGGER_FILE_ENABLED' => true,

    // Notemod の Logs カテゴリに記録（月別ノート: access-YYYY-MM）
    'LOGGER_NOTEMOD_ENABLED' => false,

    // Web UI の同期保存前に自動バックアップを作成
    'SYNC_PRE_SAVE_BACKUP_ENABLED' => true,

    // Web UI の同期保存前バックアップの直前に、古いバックアップを自動整理
    'SYNC_PRE_SAVE_BACKUP_PRUNE_ENABLED' => false,

    // data.json の暗号化設定
    // 暗号化キーは setup_auth.php で自動生成されます
    'DATA_ENCRYPTION_ENABLED' => false,
    'DATA_ENCRYPTION_KEY' => 'CHANGE_ME_DATA_ENCRYPTION_KEY',

    // セッションCookieの有効期間
    // 0 = ブラウザを閉じるまで
    // 86400 = 1日 / 604800 = 7日 / 2592000 = 30日
    'SESSION_COOKIE_LIFETIME' => 0,

    // 初回IPアクセス通知（メール）
    'IP_ALERT_ENABLED' => false,
    'IP_ALERT_TO' => 'YOUR_EMAIL',
    'IP_ALERT_FROM' => 'no-reply@notemod',
    'IP_ALERT_SUBJECT' => 'Notemod: First-time IP access',
    'IP_ALERT_IGNORE_BOTS' => true,
    'IP_ALERT_IGNORE_IPS' => array(),
    // IP_ALERT_STORE は通常不要です
    // logger.php が notemod-data/<USER_NAME>/_known_ips.json を自動使用します

    // ログ最大行数
    // 0 = 無制限
    'LOGGER_FILE_MAX_LINES' => 500,
    'LOGGER_NOTEMOD_MAX_LINES' => 50,

    // アプリケーション秘密値（内部用）
    // 署名・暗号化・固定キーなどに使用
    // 未指定の場合は setup_auth.php が自動生成します
    'SECRET' => 'CHANGE_ME_SECRET',
];
