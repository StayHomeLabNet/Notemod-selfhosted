<?php
// config/config.api.sample.php
// ------------------------------------------------------------
// Public sample config (for GitHub)
// In production, create config.api.php with the same structure
// and be sure to change all tokens.
// In version 1.1.0, the following credentials can be configured via the Web UI.
//
// EXPECTED_TOKEN : Regular API token (add/read notes, etc.)
// ADMIN_TOKEN    : Strong token for cleanup (destructive operations)
// DATA_JSON      : Absolute path to data.json
// ------------------------------------------------------------

return [
    // ★ Must be changed (long random string recommended)
    'EXPECTED_TOKEN' => 'CHANGE_ME_EXPECTED_TOKEN',
    'ADMIN_TOKEN'    => 'CHANGE_ME_ADMIN_TOKEN',

    // Absolute path to data.json as seen from api.php / read_api.php / cleanup_api.php
    // In this sample, it assumes "notemod-data" exists one level above "config/"
    'DATA_JSON' => dirname(__DIR__) . '/notemod-data/data.json',

    // Default color for newly created categories/notes
    // (Hex-like string, following Notemod’s internal format)
    'DEFAULT_COLOR' => '3478bd',
    
    // Whether to create a backup when running cleanup
    // true  : Save data.json as .bak-YYYYmmdd-HHiiSS before execution
    // false : Do not create a backup
    'CLEANUP_BACKUP_ENABLED' => true,

    // (Optional) Backup filename suffix
    // Example: use 'data.json.bak-' if you want that format
    // Do NOT change this if you want ClipboardSender
    // to bulk-delete backup files
    'CLEANUP_BACKUP_SUFFIX'  => '.bak-',
];