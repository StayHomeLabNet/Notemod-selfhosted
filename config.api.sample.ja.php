<?php
// config/config.api.php
// この config/config.api.php は setup_auth.php によって自動生成されました
// EXPECTED_TOKEN : 通常API用トークン（ノート追加・読み取りなど）
// ADMIN_TOKEN    : cleanup 用の強力なトークン（破壊的操作）
// DATA_JSON      : data.json の絶対パス（APIから参照される）
return [
    'EXPECTED_TOKEN' => 'CHANGE_ME_EXPECTED_TOKEN',
    'ADMIN_TOKEN' => 'CHANGE_ME_ADMIN_TOKEN',

    // api.php / read_api.php / cleanup_api.php から見た data.json のパス（絶対パス推奨）
    // このサンプルでは「config/」の1つ上に「notemod-data/」がある想定です
    'DATA_JSON'      => dirname(__DIR__) . '/notemod-data/data.json',

    // 新規作成されるカテゴリ/ノートのデフォルト色
    //（Notemod内部形式に合わせた16進風の文字列）
    'DEFAULT_COLOR'  => '3478bd',

    // cleanup 実行時にバックアップを作成するかどうか
    // true  : 実行前に data.json を data.json.bak-YYYYmmdd-HHiiSS として保存
    // false : バックアップを作成しない
    'CLEANUP_BACKUP_ENABLED' => true,

    //（任意）バックアップファイル名のサフィックス
    // 例：'data.json.bak-' の形式にしたい場合はそれに合わせる
    // ※ ClipboardSender でバックアップ一括削除を使う場合は、基本的に変更しないでください
    'CLEANUP_BACKUP_SUFFIX' => '.bak-',

    // バックアップを最大いくつ残すか（古いものから削除）
    'CLEANUP_BACKUP_KEEP' => 10,
];