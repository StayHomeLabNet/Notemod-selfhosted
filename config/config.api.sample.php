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