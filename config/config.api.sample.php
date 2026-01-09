<?php
// config/config.api.sample.php
// ------------------------------------------------------------
// Public sample file (for GitHub)
// For real use, create config.api.php with the same structure
// and ALWAYS change the tokens.
//
// EXPECTED_TOKEN : Regular API token (add/read notes, etc.)
// ADMIN_TOKEN    : Strong token for cleanup APIs (destructive actions)
// DATA_JSON      : Absolute path to data.json (recommended: outside public_html)
// ------------------------------------------------------------

return [
    // ★ MUST CHANGE (use a long, random string)
    'EXPECTED_TOKEN' => 'CHANGE_ME_EXPECTED_TOKEN',
    'ADMIN_TOKEN'    => 'CHANGE_ME_ADMIN_TOKEN',

    // Absolute path to data.json as seen from api.php / read_api.php / cleanup_api.php
    // In this sample, it assumes "notemod-data" exists one level above "config/"
    // For production, it is strongly recommended to place data.json outside public_html
    'DATA_JSON' => dirname(__DIR__) . '/notemod-data/data.json',

    // Default color for newly created categories/notes
    // (String formatted like a hex color, following Notemod's internal convention)
    'DEFAULT_COLOR' => '3478bd',
    
    // ★ Added: enable/disable backups for cleanup operations
    // true  : Create a backup of data.json as .bak-YYYYmmdd-HHiiSS before cleanup
    // false : Do not create a backup
    'CLEANUP_BACKUP_ENABLED' => true,

    // (Optional) Customize the backup filename suffix
    // Example: to generate "data.json.bak-YYYYmmdd-HHiiSS"
    // set this to 'data.json.bak-'
    'CLEANUP_BACKUP_SUFFIX'  => '.bak-',
];