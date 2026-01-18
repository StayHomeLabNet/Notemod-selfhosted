<?php
// config/config.sample.php
// ------------------------------------------------------------
// 公開用サンプル（GitHubに置く用）
// 実運用では同じ内容で config.php を作り、SECRET等を必ず変更してください
// v1.1.0 でSECRETの自動生成に対応
// ------------------------------------------------------------

return [
    // PHPのタイムゾーン（例）
    // 日本: Asia/Tokyo
    // NZ  : Pacific/Auckland
    // 豪  : Australia/Sydney
    // 米  : America/Los_Angeles / America/New_York
    // カナダ: America/Toronto / America/Vancouver
    // トルコ: Europe/Istanbul
    'TIMEZONE' => 'Asia/Tokyo',

    // デバッグログを保存したいときに true
    'DEBUG' => false,

    // logger の有効/無効
    // 生ログ（/logs/access-YYYY-MM.log）
    'LOGGER_FILE_ENABLED' => true,

    // Notemod の Logs カテゴリ（月別ノート access-YYYY-MM）
    'LOGGER_NOTEMOD_ENABLED' => true,

    // （任意）logsフォルダ名を変えたい場合
    // 'LOGGER_LOGS_DIRNAME' => 'logs',
  
    // 必要ならNotemodの初期スナップショットを変えられる
    // （JSON文字列として保存する前提）
    'INITIAL_SNAPSHOT' => '{"categories":null,"hasSelectedLanguage":null,"notes":null,"selectedLanguage":null}',

    // アプリ内で「秘密値」として使うもの（署名・暗号化・固定キー用途などに）
    // 指定しない場合は、setup_auth.php により自動的に追記
    'SECRET' => 'CHANGE_ME_SECRET',
];