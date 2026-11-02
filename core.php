<?php
/**
 * ============================================================
 * SHARED STATE, CONFIG, SECURITY & ACTION ROUTER
 * ============================================================
 */

define('ARCADE_DATA_FILE', __DIR__ . '/arcade_state.json');
define('ARCADE_CONFIG_FILE', __DIR__ . '/arcade_config.json');
// The master encryption key lives in its OWN file, separate from the data it protects.
// Tip: for even stronger isolation, move this one level ABOVE the public web root
// (e.g. dirname(__DIR__) . '/arcade_secure/.arcade_secret.key') if your hosting allows it,
// so the key can never be served over HTTP even if .htaccess is misconfigured or ignored.
define('ARCADE_KEY_FILE', __DIR__ . '/.arcade_secret.key');

// --- SECURITY HELPERS ---
function arcade_set_cookie($name, $value, $expiry = 0) {
    setcookie($name, $value, [
        'expires' => $expiry ?: time() + (86400 * 30),
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']), 
        'httponly' => true, 
        'samesite' => 'Strict'
    ]);
}

function get_csrf_token() {
    if (empty($_COOKIE['csrf_token'])) {
        $token = bin2hex(random_bytes(32));
        arcade_set_cookie('csrf_token', $token);
        return $token;
    }
    return $_COOKIE['csrf_token'];
}

function verify_csrf($token) {
    return hash_equals(get_csrf_token(), $token ?? '');
}

// --- THEMED SECURITY ERROR PAGE ---
function arcade_render_security_error() {
    $cap_file = __DIR__ . '/captions.php';
    $cap = file_exists($cap_file) ? require $cap_file : [];
    $heading = $cap['security_error_heading'] ?? 'SECURITY CHECK FAILED';
    $msg     = $cap['security_error_msg'] ?? 'Your session could not be verified. Please refresh the page.';
    $btn     = $cap['security_error_btn'] ?? 'REFRESH PAGE';
    http_response_code(400);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($heading) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { justify-content: center; }
        .err-wrap { width: 100%; max-width: 420px; position: relative; z-index: 10; }
    </style>
</head>
<body>
    <div class="err-wrap">
        <div class="card" style="text-align:center; border-color:var(--red);">
            <h2 style="color:var(--red); text-shadow:0 0 10px var(--red);"><?= htmlspecialchars($heading) ?></h2>
            <p style="color:var(--cream); font-size:1.2rem; margin:10px 0 22px;"><?= htmlspecialchars($msg) ?></p>
            <button type="button" onclick="location.href = location.pathname;" style="border-color:var(--red); color:var(--red); width:100%;"><?= htmlspecialchars($btn) ?></button>
        </div>
    </div>
</body>
</html>
<?php
    exit;
}

// --- FILE I/O (WITH LOCKING) ---
function safe_file_read($filename, $default) {
    if (!file_exists($filename)) { safe_file_write($filename, $default); return $default; }
    $raw = json_decode(file_get_contents($filename), true);
    return is_array($raw) ? array_merge($default, $raw) : $default;
}

function safe_file_write($filename, $data) {
    $fp = fopen($filename, 'c+');
    if ($fp && flock($fp, LOCK_EX)) {
        ftruncate($fp, 0); rewind($fp);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
        flock($fp, LOCK_UN);
    }
    if ($fp) fclose($fp);
}

// --- DATABASE ENCRYPTION (AES-256-GCM: confidentiality + tamper detection) ---
//
// The master key is kept ONLY in ARCADE_KEY_FILE (0600 permissions, dotfile),
// never inside the JSON files it protects. That way, if arcade_state.json or
// arcade_config.json ever leak (backup, misconfigured server, etc.), the data
// inside them is still useless without the separate key file.
function arcade_get_master_key() {
    if (file_exists(ARCADE_KEY_FILE)) {
        $key = file_get_contents(ARCADE_KEY_FILE);
        if ($key !== false && strlen($key) === 32) return $key;
    }
    // One-time migration: older versions of this app stored the key inside
    // arcade_config.json as 'app_key'. If that's what we find, reuse it so
    // existing encrypted data doesn't get orphaned, then move it out for good.
    if (file_exists(ARCADE_CONFIG_FILE)) {
        $legacy = json_decode(file_get_contents(ARCADE_CONFIG_FILE), true);
        if (is_array($legacy) && !empty($legacy['app_key'])) {
            $migrated = base64_decode($legacy['app_key']);
            if (strlen($migrated) === 32) { arcade_write_master_key($migrated); return $migrated; }
        }
    }
    // Fresh install: generate a brand-new 256-bit key.
    $key = random_bytes(32);
    arcade_write_master_key($key);
    return $key;
}

function arcade_write_master_key($key) {
    file_put_contents(ARCADE_KEY_FILE, $key, LOCK_EX);
    @chmod(ARCADE_KEY_FILE, 0600);
}

function arcade_encrypt_data($data) {
    $key = arcade_get_master_key();
    $ivLen = openssl_cipher_iv_length('aes-256-gcm');
    $iv = random_bytes($ivLen);
    $tag = '';
    $encrypted = openssl_encrypt(json_encode($data), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    // "G2:" prefix marks the new authenticated format, so we can tell it apart
    // from any older AES-256-CBC payloads already on disk (see decrypt below).
    return 'G2:' . base64_encode($iv . $tag . $encrypted);
}

function arcade_decrypt_data($payload) {
    $key = arcade_get_master_key();
    if (strpos($payload, 'G2:') === 0) {
        $raw = base64_decode(substr($payload, 3));
        $ivLen = openssl_cipher_iv_length('aes-256-gcm'); $tagLen = 16;
        if ($raw === false || strlen($raw) <= $ivLen + $tagLen) return null;
        $iv = substr($raw, 0, $ivLen);
        $tag = substr($raw, $ivLen, $tagLen);
        $encrypted = substr($raw, $ivLen + $tagLen);
        $decrypted = openssl_decrypt($encrypted, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        // openssl_decrypt returns false if the auth tag doesn't match, i.e. the
        // ciphertext was corrupted or tampered with — we treat that as "no data".
        return $decrypted !== false ? json_decode($decrypted, true) : null;
    }
    // Legacy AES-256-CBC payload (no integrity tag) from an older version of this
    // app. Decrypt it once for a smooth upgrade; the caller re-saves the result,
    // which automatically upgrades it to the authenticated G2 format above.
    $data = base64_decode($payload);
    $ivLen = openssl_cipher_iv_length('aes-256-cbc');
    if ($data === false || strlen($data) <= $ivLen) return null;
    $iv = substr($data, 0, $ivLen);
    $encrypted = substr($data, $ivLen);
    $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    return $decrypted ? json_decode($decrypted, true) : null;
}

// --- LOGIC & HELPERS ---
// NOTE: this default hash is the LEGACY (unsalted SHA-256) format, kept only so
// a brand-new install still works with the documented default password before
// the operator changes it. The very first successful login transparently
// upgrades it to a modern salted hash — see arcade_verify_password() below.
function arcade_default_config() { return ['password_hash' => '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9', 'machine_timeout' => 1800, 'skip_timeout' => 180, 'kick_timeout' => 10800]; }
function arcade_default_state() { return ['machine_1' => null, 'machine_2' => null, 'waitlist' => [], 'play_mode' => null, 'multi_timer_start' => null, 'arcade_lat' => '', 'arcade_lng' => '', 'op_open_time' => '', 'op_close_time' => '']; }

function arcade_load_state() {
    $raw = safe_file_read(ARCADE_DATA_FILE, []);
    if (isset($raw['_encrypted'])) {
        $wasLegacyFormat = strpos($raw['_encrypted'], 'G2:') !== 0;
        $dec = arcade_decrypt_data($raw['_encrypted']);
        $s = is_array($dec) ? array_merge(arcade_default_state(), $dec) : arcade_default_state();
        if ($wasLegacyFormat && is_array($dec)) arcade_save_state($s); // upgrade to authenticated encryption
    } elseif (!empty($raw)) {
        // Very first run after this update: state file still fully plaintext -> encrypt it now.
        $s = array_merge(arcade_default_state(), $raw);
        arcade_save_state($s);
    } else {
        $s = arcade_default_state();
    }
    if (!is_array($s['waitlist'])) $s['waitlist'] = [];
    return $s;
}

function arcade_save_state($state) {
    // The entire state (player names, join times, arcade location, hours...) is
    // encrypted as one blob before ever touching disk.
    safe_file_write(ARCADE_DATA_FILE, ['_encrypted' => arcade_encrypt_data($state)]);
}

function arcade_load_config() {
    $raw = safe_file_read(ARCADE_CONFIG_FILE, []);
    if (isset($raw['_encrypted'])) {
        $wasLegacyFormat = strpos($raw['_encrypted'], 'G2:') !== 0;
        $dec = arcade_decrypt_data($raw['_encrypted']);
        $c = is_array($dec) ? array_merge(arcade_default_config(), $dec) : arcade_default_config();
        if ($wasLegacyFormat && is_array($dec)) arcade_save_config($c);
    } elseif (!empty($raw)) {
        // Legacy plaintext config (password hash + old app_key sitting in the open) -> encrypt now.
        $c = array_merge(arcade_default_config(), $raw);
        arcade_save_config($c);
    } else {
        $c = arcade_default_config();
        arcade_save_config($c);
    }
    unset($c['app_key']); // obsolete: the key now lives only in ARCADE_KEY_FILE, never here
    return $c;
}

function arcade_save_config($config) {
    unset($config['app_key']); // never persist the encryption key alongside the data it protects
    // The config — including the admin password hash and every operator setting —
    // is encrypted as one blob, the same way the player/queue state is.
    safe_file_write(ARCADE_CONFIG_FILE, ['_encrypted' => arcade_encrypt_data($config)]);
}

function arcade_admin_password_hash() { return arcade_load_config()['password_hash'] ?? ''; }
function arcade_admin_cookie_token() { return hash('sha256', 'arcade_admin_session|' . arcade_admin_password_hash()); }

// --- PASSWORD HASHING (bcrypt/argon2 via PHP's password_hash, salted + slow by design) ---
function arcade_hash_is_legacy($hash) { return $hash !== '' && $hash[0] !== '$'; } // modern hashes always start with '$'
function arcade_hash_password($password) { return password_hash($password, PASSWORD_DEFAULT); }
function arcade_verify_password($password, $hash) {
    if (arcade_hash_is_legacy($hash)) return hash_equals($hash, hash('sha256', $password)); // legacy SHA-256 compare
    return password_verify($password, $hash);
}

// --- LOGIN THROTTLING (escalating brute-force protection on the admin password) ---
// Fail count is NEVER reset by the passage of time — only a correct login clears it.
// That means a scripted attacker can't just "wait out" a fixed delay forever: every
// further wrong guess makes the NEXT lockout longer, not the same length as before.
function arcade_lockout_duration($failCount) {
    $schedule = [5 => 30, 6 => 120, 7 => 600, 8 => 3600, 9 => 21600]; // 30s, 2m, 10m, 1h, 6h
    if ($failCount < 5) return 0;
    if (isset($schedule[$failCount])) return $schedule[$failCount];
    return 86400; // 10th+ consecutive failure: 24 hours
}
function arcade_login_locked($config) { return !empty($config['login_lockout_until']) && time() < $config['login_lockout_until']; }
function arcade_register_login_failure($config) {
    $config['login_fail_count'] = ($config['login_fail_count'] ?? 0) + 1;
    $dur = arcade_lockout_duration($config['login_fail_count']);
    if ($dur > 0) $config['login_lockout_until'] = time() + $dur;
    arcade_save_config($config);
}
function arcade_reset_login_attempts($config) {
    if (!empty($config['login_fail_count']) || !empty($config['login_lockout_until'])) {
        unset($config['login_fail_count'], $config['login_lockout_until']);
        arcade_save_config($config);
    }
}

function is_arcade_open($state) {
    if (empty($state['op_open_time']) || empty($state['op_close_time'])) return true;
    $now = date('H:i'); $o = $state['op_open_time']; $c = $state['op_close_time'];
    return ($o === $c) ? true : (($o < $c) ? ($now >= $o && $now < $c) : ($now >= $o || $now < $c));
}

function haversineGreatCircleDistance($lat1, $lon1, $lat2, $lon2) {
    $R = 6371000;
    return 2 * $R * asin(sqrt(pow(sin(deg2rad($lat2 - $lat1) / 2), 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * pow(sin(deg2rad($lon2 - $lon1) / 2), 2)));
}

function arcade_apply_timeouts(&$state) {
    $c = arcade_load_config(); $n = time(); $changed = false;
    
    if (!is_arcade_open($state)) {
        if ($state['machine_1'] || $state['machine_2'] || !empty($state['waitlist']) || $state['play_mode']) {
            $state['machine_1'] = null; $state['machine_2'] = null; $state['waitlist'] = []; $state['play_mode'] = null; $state['multi_timer_start'] = null; return true;
        } return false;
    }

    foreach (['machine_1', 'machine_2'] as $m) {
        if ($state[$m] && ($n - ($state[$m]['started_at'] ?? $n)) >= $c['machine_timeout']) {
            $p = $state[$m]; $state[$m] = null; $changed = true;
            if (empty($p['is_guest'])) { unset($p['started_at']); $p['afk'] = false; $p['last_activity'] = $n; $p['has_played'] = true; $state['waitlist'][] = $p; }
        }
    }

    if (!empty($state['waitlist'])) {
        $kept = [];
        foreach ($state['waitlist'] as $p) {
            $isAfk = $p['afk'] ?? false; $act = $p['last_activity'] ?? ($p['joined_at'] ?? $n);
            if ($isAfk && ($n - ($p['afk_at'] ?? $n)) >= 3600) { $changed = true; continue; }
            if (!$isAfk && ($n - $act) >= $c['kick_timeout']) { $changed = true; continue; }
            $kept[] = $p;
        } $state['waitlist'] = array_values($kept);
    }

    $m1V = ($state['machine_1'] === null); $m2V = ($state['machine_2'] === null);
    if ($m1V && $m2V && $state['play_mode'] !== null) { $state['play_mode'] = null; $state['multi_timer_start'] = null; $changed = true; }
    elseif ($state['play_mode'] === 'multi') {
        if ($m1V || $m2V) {
            if ($state['multi_timer_start'] === null) { $state['multi_timer_start'] = $n; $changed = true; }
            elseif (($n - $state['multi_timer_start']) >= 120) { $state['play_mode'] = 'solo'; $state['multi_timer_start'] = null; $changed = true; }
        } elseif ($state['multi_timer_start'] !== null) { $state['multi_timer_start'] = null; $changed = true; }
    }

    if (!empty($state['waitlist']) && $state['play_mode'] !== 'solo' && ($m1V || $m2V)) {
        foreach ($state['waitlist'] as $i => $p) {
            if (empty($p['afk'])) {
                if (!isset($state['waitlist'][$i]['called_at'])) { $state['waitlist'][$i]['called_at'] = $n; $changed = true; }
                elseif (($n - $state['waitlist'][$i]['called_at']) >= $c['skip_timeout']) {
                    $p = $state['waitlist'][$i]; unset($p['called_at']);
                    array_splice($state['waitlist'], $i, 1); $state['waitlist'][] = $p; $changed = true;
                } break;
            }
        }
    } elseif (!empty($state['waitlist'])) {
        foreach ($state['waitlist'] as $i => $p) {
            if (isset($state['waitlist'][$i]['called_at'])) { unset($state['waitlist'][$i]['called_at']); $changed = true; }
        }
    }
    return $changed;
}

// --- BOOTSTRAP STATE ---
$state = arcade_load_state();
if (arcade_apply_timeouts($state)) arcade_save_state($state);
$config = arcade_load_config();
$myPlayers = isset($_COOKIE['arcade_my_players']) ? json_decode($_COOKIE['arcade_my_players'], true) : [];
if (!is_array($myPlayers)) $myPlayers = [];
$isAdmin = isset($_COOKIE['arcade_admin']) && $_COOKIE['arcade_admin'] === arcade_admin_cookie_token();
$isOpen = is_arcade_open($state);

// Love Letter uses its own encrypted, transaction-locked session store. It is
// intentionally separate from the arcade queue state and uses the same admin identity.
require_once __DIR__ . '/love-letter-server.php';

// --- SECURITY PRE-CHECK ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['api'])) {
    if (isset($_GET['cc'])) {
        if (empty($_COOKIE['csrf_token'])) arcade_render_security_error();
        $qs = $_GET; unset($qs['cc']);
        header('Location: ?' . http_build_query($qs)); exit;
    }
    if (empty($_COOKIE['csrf_token'])) {
        get_csrf_token();
        $qs = $_GET; $qs['cc'] = '1';
        header('Location: ?' . http_build_query($qs)); exit;
    }
}

// --- AJAX POLLING (GET) ---
$requestedApi = $_GET['api'] ?? '';
if (is_string($requestedApi) && str_starts_with($requestedApi, 'love-letter-')) {
    ll_handle_api($isAdmin);
}
if (isset($_GET['api']) && $_GET['api'] === 'state') {
    $state['is_open'] = $isOpen; $state['config'] = $config;
    if (!empty($state['waitlist'])) foreach ($state['waitlist'] as $k => $p) $state['waitlist'][$k]['joined_formatted'] = date('d M, H:i', $p['joined_at']);
    $hash = md5(json_encode($state));
    if (($_GET['hash'] ?? '') === $hash) { http_response_code(304); exit; }
    $state['state_hash'] = $hash; header('Content-Type: application/json'); echo json_encode($state); exit;
}

// --- POST ACTION PROCESSING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) arcade_render_security_error();
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'admin_login':
            if (arcade_login_locked($config)) break; // still locked out — ignore the attempt
            if (arcade_verify_password($_POST['password'] ?? '', arcade_admin_password_hash())) {
                arcade_reset_login_attempts($config);
                if (arcade_hash_is_legacy($config['password_hash'] ?? '')) {
                    // Transparently upgrade a still-default/legacy hash to a salted one on first successful login.
                    $config['password_hash'] = arcade_hash_password($_POST['password']);
                    arcade_save_config($config);
                }
                arcade_set_cookie('arcade_admin', hash('sha256', 'arcade_admin_session|' . $config['password_hash']), time() + 86400);
            } else {
                arcade_register_login_failure($config);
            }
            break;
        case 'admin_logout':
            arcade_set_cookie('arcade_admin', '', time() - 3600);
            if (($config['kick_timeout'] ?? 10800) == 60) {
                $config['kick_timeout'] = $config['prev_kick_timeout'] ?? 10800;
                arcade_save_config($config);
            }
            break;
        case 'change_password':
            if ($isAdmin) {
                $cur = $_POST['current_password'] ?? ''; $new = $_POST['new_password'] ?? ''; $con = $_POST['confirm_password'] ?? '';
                if ($new !== $con || strlen($new) < 4 || !arcade_verify_password($cur, arcade_admin_password_hash())) { header("Location: ?view=password&err=1"); exit; }
                $config['password_hash'] = arcade_hash_password($new); arcade_save_config($config);
                arcade_set_cookie('arcade_admin', hash('sha256', 'arcade_admin_session|' . $config['password_hash']), time() + 86400); header("Location: ?view=password&success=1"); exit;
            } break;
        case 'update_location': if ($isAdmin) { $state['arcade_lat'] = trim($_POST['arcade_lat'] ?? ''); $state['arcade_lng'] = trim($_POST['arcade_lng'] ?? ''); } break;
        case 'update_hours': if ($isAdmin) { $state['op_open_time'] = trim($_POST['op_open_time'] ?? ''); $state['op_close_time'] = trim($_POST['op_close_time'] ?? ''); } break;
        case 'update_timers':
            if ($isAdmin) {
                $config['machine_timeout'] = (int)($_POST['machine_timeout'] ?? 1800);
                $config['skip_timeout'] = (int)($_POST['skip_timeout'] ?? 180);
                $new_kick = (int)($_POST['kick_timeout'] ?? 10800);
                if ($new_kick == 60) {
                    if (($config['kick_timeout'] ?? 10800) != 60) {
                        $config['prev_kick_timeout'] = $config['kick_timeout'] ?? 10800;
                    }
                } else {
                    $config['prev_kick_timeout'] = $new_kick;
                }
                $config['kick_timeout'] = $new_kick;
                arcade_save_config($config);
            } break;
        case 'add':
            if (!empty(trim($_POST['player_name']))) {
                if (!empty($state['arcade_lat']) && !empty($state['arcade_lng']) && haversineGreatCircleDistance($state['arcade_lat'], $state['arcade_lng'], (float)($_POST['user_lat'] ?? 0), (float)($_POST['user_lng'] ?? 0)) > 100) { header("Location: ?view=terminal&err=gps"); exit; }
                $active = false;
                foreach ($myPlayers as $pid) { if (($state['machine_1'] && $state['machine_1']['id'] === $pid) || ($state['machine_2'] && $state['machine_2']['id'] === $pid) || in_array($pid, array_column($state['waitlist'], 'id'))) { $active = true; break; } }
                if (!$active) { $id = uniqid('p_'); $state['waitlist'][] = ['id' => $id, 'name' => htmlspecialchars(trim($_POST['player_name'])), 'afk' => false, 'joined_at' => time(), 'last_activity' => time()]; $myPlayers[] = $id; arcade_set_cookie('arcade_my_players', json_encode($myPlayers)); }
            } break;
        case 'pull_m1': case 'pull_guest_m1': case 'pull_m2': case 'pull_guest_m2':
            $mk = str_contains($action, 'm1') ? 'machine_1' : 'machine_2'; $ok = ($mk === 'machine_1') ? 'machine_2' : 'machine_1';
            if ($state[$mk] === null && $state['play_mode'] !== 'solo') {
                if (str_contains($action, 'guest') && ($isAdmin || count(array_intersect($myPlayers, array_column($state['waitlist'], 'id'))) > 0)) {
                    $state[$mk] = ['id' => uniqid('guest_'), 'name' => 'GUEST PLAY', 'is_guest' => true, 'started_at' => $state[$ok] ? $state[$ok]['started_at'] : time()];
                    $state['play_mode'] = ($state['play_mode'] === 'multi') ? 'multi' : 'multi'; $state['multi_timer_start'] = ($state['play_mode'] === 'multi') ? null : time();
                } else {
                    foreach ($state['waitlist'] as $k => $p) {
                        if (empty($p['afk']) && ($isAdmin || in_array($p['id'], $myPlayers))) {
                            unset($p['called_at']); $p['started_at'] = $state[$ok] ? $state[$ok]['started_at'] : time(); $state[$mk] = $p;
                            $m = $_POST['play_mode'] ?? 'multi'; $state['play_mode'] = ($m === 'solo') ? 'solo' : 'multi'; $state['multi_timer_start'] = ($m === 'multi' && $state['play_mode'] !== 'multi') ? time() : null; array_splice($state['waitlist'], $k, 1); break;
                        }
                    }
                }
            } break;
        case 'next_1_leave': case 'next_1_requeue': case 'next_2_leave': case 'next_2_requeue':
            $mk = str_contains($action, '1') ? 'machine_1' : 'machine_2';
            if ($state[$mk] && ($isAdmin || in_array($state[$mk]['id'], $myPlayers) || ($state[$mk]['is_guest'] ?? false))) {
                if (str_contains($action, 'requeue') && empty($state[$mk]['is_guest'])) { $state[$mk]['afk'] = false; unset($state[$mk]['started_at']); $state[$mk]['last_activity'] = time(); $state[$mk]['has_played'] = true; $state['waitlist'][] = $state[$mk]; }
                $state[$mk] = null;
            } break;
        case 'rename_player': case 'toggle_afk': case 'remove_player': case 'skip_player':
            if ($isAdmin || in_array($_POST['player_id'] ?? '', $myPlayers)) {
                foreach ($state['waitlist'] as $k => $p) {
                    if ($p['id'] === ($_POST['player_id'] ?? '')) {
                        if ($action === 'rename_player' && trim($_POST['new_name'] ?? '')) $state['waitlist'][$k]['name'] = htmlspecialchars(trim($_POST['new_name']));
                        elseif ($action === 'toggle_afk') { $g = !($p['afk'] ?? false); $state['waitlist'][$k]['afk'] = $g; unset($state['waitlist'][$k]['called_at']); $state['waitlist'][$k][$g ? 'afk_at' : 'last_activity'] = time(); }
                        elseif ($action === 'remove_player') array_splice($state['waitlist'], $k, 1);
                        elseif ($action === 'skip_player') { unset($p['called_at']); array_splice($state['waitlist'], $k, 1); $state['waitlist'][] = $p; }
                        break;
                    }
                }
            } break;
        case 'clear':
            if ($isAdmin) { $l = $state['arcade_lat']; $g = $state['arcade_lng']; $o = $state['op_open_time']; $c = $state['op_close_time']; $state = arcade_default_state(); $state['arcade_lat'] = $l; $state['arcade_lng'] = $g; $state['op_open_time'] = $o; $state['op_close_time'] = $c; }
            break;
    }
    arcade_save_state($state); header("Location: " . ($_POST['return_to'] ?? '?view=terminal')); exit;
}
?>
