<?php
/**
 * data_crypto.php
 *
 * 役割:
 * - data.json の平文 / 暗号化保存を共通化
 * - AES-256-CBC + HMAC による暗号化 / 復号
 * - 切替直前バックアップ作成
 *
 * 前提:
 * - 呼び出し元で config/<USER_NAME>/config.php を読み込み、配列設定を利用する
 *   例: $cfg = require __DIR__ . '/config/<USER_NAME>/config.php';
 *
 * 主に参照する設定:
 *   $cfg['DATA_ENCRYPTION_ENABLED']
 *   $cfg['DATA_ENCRYPTION_KEY']
 *
 * 互換性のため、同名の定数が定義済みならそちらも参照する。
 *
 * 配置:
 * - index.php と同じ階層
 */

if (!function_exists('nm_get_runtime_config_value')) {
    function nm_get_runtime_config_value($key, $default = null)
    {
        if (defined($key)) {
            return constant($key);
        }

        $candidates = array();

        if (isset($GLOBALS['cfg']) && is_array($GLOBALS['cfg'])) {
            $candidates[] = $GLOBALS['cfg'];
        }
        if (isset($GLOBALS['config']) && is_array($GLOBALS['config'])) {
            $candidates[] = $GLOBALS['config'];
        }
        if (isset($GLOBALS['NM_CONFIG']) && is_array($GLOBALS['NM_CONFIG'])) {
            $candidates[] = $GLOBALS['NM_CONFIG'];
        }
        if (isset($GLOBALS['notemod_config']) && is_array($GLOBALS['notemod_config'])) {
            $candidates[] = $GLOBALS['notemod_config'];
        }

        foreach ($candidates as $arr) {
            if (array_key_exists($key, $arr)) {
                return $arr[$key];
            }
        }

        return $default;
    }
}

if (!function_exists('nm_data_encryption_enabled')) {
    function nm_data_encryption_enabled()
    {
        return (bool) nm_get_runtime_config_value('DATA_ENCRYPTION_ENABLED', false);
    }
}

if (!function_exists('nm_data_encryption_key_is_set')) {
    function nm_data_encryption_key_is_set()
    {
        $value = nm_get_runtime_config_value('DATA_ENCRYPTION_KEY', '');
        return trim((string) $value) !== '';
    }
}

if (!function_exists('nm_data_encryption_key_raw')) {
    function nm_data_encryption_key_raw()
    {
        $value = nm_get_runtime_config_value('DATA_ENCRYPTION_KEY', '');
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return hash('sha256', $value, true);
    }
}

if (!function_exists('nm_generate_encryption_key')) {
    function nm_generate_encryption_key($bytes = 32)
    {
        $bytes = (int) $bytes;
        if ($bytes <= 0) {
            $bytes = 32;
        }

        return bin2hex(random_bytes($bytes));
    }
}

if (!function_exists('nm_normalize_data_array')) {
    function nm_normalize_data_array($data)
    {
        return is_array($data) ? $data : array();
    }
}

if (!function_exists('nm_json_encode_data')) {
    function nm_json_encode_data($data)
    {
        return json_encode(
            nm_normalize_data_array($data),
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }
}

if (!function_exists('nm_encrypt_json_string')) {
    function nm_encrypt_json_string($plainText)
    {
        $key = nm_data_encryption_key_raw();
        if ($key === '') {
            return false;
        }

        $cipher = 'AES-256-CBC';
        $ivLength = openssl_cipher_iv_length($cipher);
        if (!$ivLength) {
            return false;
        }

        $iv = random_bytes($ivLength);
        $cipherTextRaw = openssl_encrypt(
            (string) $plainText,
            $cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($cipherTextRaw === false) {
            return false;
        }

        $hmac = hash_hmac('sha256', $iv . $cipherTextRaw, $key, true);

        $payload = array(
            'format' => 'nm_encrypted_v1',
            'cipher' => $cipher,
            'iv'     => base64_encode($iv),
            'hmac'   => base64_encode($hmac),
            'data'   => base64_encode($cipherTextRaw),
        );

        return json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }
}

if (!function_exists('nm_decrypt_json_string')) {
    function nm_decrypt_json_string($encryptedJson)
    {
        $key = nm_data_encryption_key_raw();
        if ($key === '') {
            return false;
        }

        $payload = json_decode((string) $encryptedJson, true);
        if (!is_array($payload)) {
            return false;
        }

        if (
            !isset($payload['format']) ||
            $payload['format'] !== 'nm_encrypted_v1' ||
            !isset($payload['cipher']) ||
            !isset($payload['iv']) ||
            !isset($payload['hmac']) ||
            !isset($payload['data'])
        ) {
            return false;
        }

        $cipher = (string) $payload['cipher'];
        $iv = base64_decode((string) $payload['iv'], true);
        $hmac = base64_decode((string) $payload['hmac'], true);
        $cipherTextRaw = base64_decode((string) $payload['data'], true);

        if ($iv === false || $hmac === false || $cipherTextRaw === false) {
            return false;
        }

        $calcHmac = hash_hmac('sha256', $iv . $cipherTextRaw, $key, true);
        if (!hash_equals($hmac, $calcHmac)) {
            return false;
        }

        $plainText = openssl_decrypt(
            $cipherTextRaw,
            $cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($plainText === false) {
            return false;
        }

        return $plainText;
    }
}

if (!function_exists('nm_is_encrypted_data_json')) {
    function nm_is_encrypted_data_json($raw)
    {
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded)
            && isset($decoded['format'])
            && $decoded['format'] === 'nm_encrypted_v1'
            && isset($decoded['cipher'])
            && isset($decoded['iv'])
            && isset($decoded['hmac'])
            && isset($decoded['data']);
    }
}

if (!function_exists('nm_decode_data_payload_to_array')) {
    function nm_decode_data_payload_to_array($raw)
    {
        $raw = (string) $raw;
        if (trim($raw) === '') {
            return array();
        }

        if (nm_is_encrypted_data_json($raw)) {
            $plain = nm_decrypt_json_string($raw);
            if ($plain === false) {
                return false;
            }

            $data = json_decode($plain, true);
            return is_array($data) ? $data : false;
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : false;
    }
}

/**
 * 戻り値:
 * array(true,  $dataArray, null)                成功
 * array(true,  array(),    'missing')           ファイルなし（空データ扱い）
 * array(false, null,       'read_failed')       読み込み失敗
 * array(false, null,       'decode_failed')     JSON/復号失敗
 */
if (!function_exists('nm_try_load_data_file')) {
    function nm_try_load_data_file($filePath)
    {
        if (!is_file($filePath)) {
            return array(true, array(), 'missing');
        }

        $raw = @file_get_contents($filePath);
        if ($raw === false) {
            return array(false, null, 'read_failed');
        }

        $data = nm_decode_data_payload_to_array($raw);
        if (!is_array($data)) {
            return array(false, null, 'decode_failed');
        }

        return array(true, $data, null);
    }
}

if (!function_exists('nm_load_data_file')) {
    function nm_load_data_file($filePath)
    {
        list($ok, $data, $reason) = nm_try_load_data_file($filePath);
        if (!$ok || !is_array($data)) {
            return array();
        }

        return $data;
    }
}

if (!function_exists('nm_write_file_atomic')) {
    function nm_write_file_atomic($filePath, $content)
    {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            return false;
        }

        $tmpPath = $filePath . '.tmp-' . getmypid() . '-' . mt_rand(1000, 9999);
        $bytes = @file_put_contents($tmpPath, (string) $content, LOCK_EX);
        if ($bytes === false) {
            return false;
        }

        if (!@rename($tmpPath, $filePath)) {
            @unlink($tmpPath);
            return false;
        }

        return true;
    }
}

if (!function_exists('nm_save_data_file_mode')) {
    function nm_save_data_file_mode($filePath, $data, $encrypt)
    {
        $json = nm_json_encode_data($data);
        if ($json === false) {
            return false;
        }

        if ($encrypt) {
            $json = nm_encrypt_json_string($json);
            if ($json === false) {
                return false;
            }
        }

        return nm_write_file_atomic($filePath, $json);
    }
}

if (!function_exists('nm_save_data_file')) {
    function nm_save_data_file($filePath, $data)
    {
        return nm_save_data_file_mode($filePath, $data, nm_data_encryption_enabled());
    }
}

if (!function_exists('nm_get_backup_basename')) {
    function nm_get_backup_basename($isEncrypted, $timestamp)
    {
        $stamp = $timestamp ? (string) $timestamp : date('Ymd-His');

        if ($isEncrypted) {
            return 'data.enc.json.bak-' . $stamp;
        }

        return 'data.json.bak-' . $stamp;
    }
}

if (!function_exists('nm_get_backup_file_path')) {
    function nm_get_backup_file_path($dataFilePath, $isEncrypted, $timestamp)
    {
        $dir = dirname($dataFilePath);
        return rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . nm_get_backup_basename($isEncrypted, $timestamp);
    }
}

if (!function_exists('nm_create_data_backup')) {
    function nm_create_data_backup($dataFilePath, $isEncrypted, $timestamp)
    {
        $data = nm_load_data_file($dataFilePath);
        $backupPath = nm_get_backup_file_path($dataFilePath, $isEncrypted, $timestamp);

        $ok = nm_save_data_file_mode($backupPath, $data, $isEncrypted);
        if (!$ok) {
            return false;
        }

        return $backupPath;
    }
}

if (!function_exists('nm_is_encrypted_backup_filename')) {
    function nm_is_encrypted_backup_filename($filename)
    {
        $filename = basename((string) $filename);
        return strpos($filename, 'data.enc.json.bak-') === 0;
    }
}

if (!function_exists('nm_is_plain_backup_filename')) {
    function nm_is_plain_backup_filename($filename)
    {
        $filename = basename((string) $filename);
        return strpos($filename, 'data.json.bak-') === 0
            && strpos($filename, 'data.enc.json.bak-') !== 0;
    }
}

if (!function_exists('nm_is_supported_backup_filename')) {
    function nm_is_supported_backup_filename($filename)
    {
        return nm_is_encrypted_backup_filename($filename) || nm_is_plain_backup_filename($filename);
    }
}

if (!function_exists('nm_export_plain_json_string')) {
    function nm_export_plain_json_string($dataFilePath)
    {
        $data = nm_load_data_file($dataFilePath);
        return nm_json_encode_data($data);
    }
}

if (!function_exists('nm_import_data_file_to_current_mode')) {
    function nm_import_data_file_to_current_mode($importFilePath, $targetDataFilePath)
    {
        if (!is_file($importFilePath)) {
            return array(false, 'file_not_found');
        }

        $raw = @file_get_contents($importFilePath);
        if ($raw === false || trim($raw) === '') {
            return array(false, 'empty_or_unreadable_file');
        }

        $data = nm_decode_data_payload_to_array($raw);
        if (!is_array($data)) {
            return array(false, 'invalid_or_undecryptable_json');
        }

        $ok = nm_save_data_file($targetDataFilePath, $data);
        if (!$ok) {
            return array(false, 'save_failed');
        }

        return array(true, null);
    }
}

if (!function_exists('nm_restore_backup_to_mode')) {
    function nm_restore_backup_to_mode($backupFilePath, $targetDataFilePath, $encrypt)
    {
        if (!is_file($backupFilePath)) {
            return array(false, 'backup_not_found');
        }

        $raw = @file_get_contents($backupFilePath);
        if ($raw === false || trim($raw) === '') {
            return array(false, 'empty_or_unreadable_backup');
        }

        $data = nm_decode_data_payload_to_array($raw);
        if (!is_array($data)) {
            return array(false, 'invalid_or_undecryptable_backup');
        }

        $ok = nm_save_data_file_mode($targetDataFilePath, $data, $encrypt);
        if (!$ok) {
            return array(false, 'restore_save_failed');
        }

        return array(true, null);
    }
}