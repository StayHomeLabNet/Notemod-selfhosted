<?php
// config/config.sample.php
// ------------------------------------------------------------
// Public sample file (for GitHub)
// For real use, create config.php with the same structure
// and ALWAYS change SECRET and other sensitive values.
// ------------------------------------------------------------

return [
    // Secret value used internally by the application
    // (for signing, encryption, fixed keys, etc.)
    // ★ MUST CHANGE (use a long, random string)
    'SECRET' => 'CHANGE_ME_SECRET',

    // PHP timezone setting (examples)
    // Japan : Asia/Tokyo
    // NZ    : Pacific/Auckland
    // AU    : Australia/Sydney
    // US    : America/Los_Angeles / America/New_York
    // Canada: America/Toronto / America/Vancouver
    // Turkey: Europe/Istanbul
    'TIMEZONE' => 'Pacific/Auckland',

    // Enable this only when you want more verbose debug logs
    'DEBUG' => false,

    // ★ Added: enable / disable logger
    // Raw access logs (/logs/access-YYYY-MM.log)
    'LOGGER_FILE_ENABLED' => true,

    // Notemod Logs category (monthly notes: access-YYYY-MM)
    'LOGGER_NOTEMOD_ENABLED' => true,

    // (Optional) Change the logs directory name
    // 'LOGGER_LOGS_DIRNAME' => 'logs',
  
    // Optional: customize the initial Notemod snapshot
    // (Must be stored as a JSON string)
    'INITIAL_SNAPSHOT' => '{"categories":null,"hasSelectedLanguage":null,"notes":null,"selectedLanguage":null}',
];