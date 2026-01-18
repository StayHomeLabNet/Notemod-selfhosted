<?php
// config/config.sample.php
// ------------------------------------------------------------
// Public sample config (for GitHub)
// In production, create config.php with the same contents
// and be sure to change SECRET and other values.
// Automatic SECRET generation is supported since v1.1.0
// ------------------------------------------------------------

return [
    // PHP timezone setting (examples)
    // Japan : Asia/Tokyo
    // NZ    : Pacific/Auckland
    // AU    : Australia/Sydney
    // US    : America/Los_Angeles / America/New_York
    // Canada: America/Toronto / America/Vancouver
    // Turkey: Europe/Istanbul
    'TIMEZONE' => 'Asia/Tokyo',

    // Set to true to enable debug logging
    'DEBUG' => false,

    // Enable/disable logger
    // Raw access logs (/logs/access-YYYY-MM.log)
    'LOGGER_FILE_ENABLED' => true,

    // Notemod Logs category (monthly note: access-YYYY-MM)
    'LOGGER_NOTEMOD_ENABLED' => true,

    // (Optional) Change the logs directory name
    // 'LOGGER_LOGS_DIRNAME' => 'logs',
  
    // Optional: customize Notemod initial snapshot
    // (Must be stored as a JSON string)
    'INITIAL_SNAPSHOT' => '{"categories":null,"hasSelectedLanguage":null,"notes":null,"selectedLanguage":null}',

    // Application secret used as a private value
    // (signing, encryption, fixed keys, etc.)
    // If not specified, setup_auth.php will append it automatically
    'SECRET' => 'CHANGE_ME_SECRET',
];