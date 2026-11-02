<?php 
require_once 'core.php'; 
$cap = require 'captions.php'; 
$view = $_GET['view'] ?? 'terminal'; 

// Data mappings based on UI needed
$qCount = count($state['waitlist']); $m1B = $state['machine_1'] !== null; $m2B = $state['machine_2'] !== null;
$firstAct = null; foreach ($state['waitlist'] as $p) { if (empty($p['afk'])) { $firstAct = $p; break; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $view === 'love-letter' ? 'Love Letter — ' . $cap['site_title'] : $cap['site_title'] ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22><?= $view === 'admin' ? $cap['admin_window_icon'] : $cap['window_icon'] ?></text></svg>">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="love-letter.css">
    <style>
        .container { margin: 0 auto; width: 100%; padding: 0 12px; }
        .container-standard { max-width: 768px; } 
        .container-tv { max-width: 100%; padding: 1.5vw; }
        .tv-layout { display: flex; flex-direction: column; gap: 24px; }
        .tv-machines { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; }
        .tv-queue { width: 100%; }
        .tv-queue-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; align-items: start; }
        @media (max-width: 360px) {
            .tv-machines { grid-template-columns: minmax(0, 1fr); }
            .tv-queue-grid { grid-template-columns: minmax(0, 1fr); }
        }
        /* On desktop/TV there's plenty of width, but letting the cabinets stretch to
           fill half the screen each made them absurdly oversized. From this breakpoint
           up, the machine panel becomes a compact fixed-width column on the left,
           stacked vertically, and the queue takes the remaining space on the right. */
        @media (min-width: 1024px) {
            .tv-layout { flex-direction: row; align-items: flex-start; }
            .tv-machines { grid-template-columns: 1fr; width: clamp(260px, 24vw, 360px); flex: 0 0 auto; gap: 18px; }
            .tv-queue { flex: 1 1 0; min-width: 0; }
            .tv-machines .tv-machine-box { min-height: 190px; padding: 14px 14px 16px; }
            .tv-machines .display-machine-h2 { font-size: 0.95rem; }
            .tv-machines .display-status { font-size: 1.2rem; padding: 16px 10px; }
        }
        .terminal-machines { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .edit-input { max-width: 100%; min-width: 0; }
        .wl-actions { flex-wrap: wrap; justify-content: flex-end; }
        .player-locked { word-break: break-all; } 
        
        /* RESTORED: Forces Timers to stack and styles Guest play correctly */
        .timer-badge { display: block; font-size: 0.9rem; margin-top: 8px; color: var(--amber); }
        .guest-play { color: var(--lime) !important; text-shadow: 0 0 10px rgba(57,255,106,.7) !important; }

        /* PLAYER GUIDE PANEL */
        .guide-toggle-row { margin-bottom: 24px; }
        .guide-panel { border-color: var(--cyan); }
        .guide-panel h2 { color: var(--cyan); text-shadow: 0 0 8px rgba(0,230,246,.4); }
        .guide-steps { margin: 0; padding-left: 0; list-style: none; display: flex; flex-direction: column; gap: 14px; }
        .guide-steps li { font-size: 1.15rem; line-height: 1.4; }

        /* QR PANEL */
        .qr-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 4px; }
        .qr-item { display: flex; flex-direction: column; align-items: center; gap: 10px; }
        .qr-item canvas { width: 100% !important; max-width: 160px; border-radius: 8px; border: 2px solid var(--line); background: #fff; padding: 6px; box-sizing: border-box; image-rendering: pixelated; }
        .qr-label { font-size: 0.75rem; color: var(--amber); text-align: center; letter-spacing: .5px; }
    </style>
</head>
<body data-view="<?= htmlspecialchars($view) ?>">
    <div class="container <?= $view === 'display' ? 'container-tv' : 'container-standard' ?>">
        
        <div class="marquee">
            <div class="bulb-strip top"></div>
            <div class="marquee-inner">
                <?php if ($view === 'display'): ?><h1 class="display-h1"><?= $cap['header_icon'] ?> <?= $cap['display_title'] ?> <?= $cap['header_icon'] ?></h1>
                <?php elseif ($view === 'admin'): ?><h1><?= $cap['admin_header_icon'] ?> <?= $cap['admin_title'] ?></h1><div class="subtitle"><?= $cap['admin_subtitle'] ?></div>
                <?php elseif ($view === 'password'): ?><h1><?= $cap['pw_setup_title'] ?></h1><div class="subtitle"><?= $cap['pw_setup_subtitle'] ?></div>
                <?php elseif ($view === 'love-letter'): ?><h1>LOVE LETTER</h1><div class="subtitle">A 21-card game of royal intrigue</div>
                <?php else: ?><h1><?= $cap['header_icon'] ?> <?= $cap['panel_title'] ?></h1><div class="subtitle"><?= $cap['panel_subtitle'] ?></div><?php endif; ?>
                <div id="sys-time" class="datetime<?= $view === 'display' ? ' display-clock' : '' ?>"><?= $cap['sys_time'] ?>--:--:--</div>
            </div>
            <div class="bulb-strip bottom"></div>
        </div>

        <div id="main-content">
            <?php if (!$isOpen && $view !== 'password' && $view !== 'love-letter'): ?>
                <div class="card" style="text-align: center; border-color: var(--red); width: 100%;">
                    <h2 style="color: var(--red); text-shadow: 0 0 10px var(--red); font-size: 1.5rem; text-align: center;"><?= $cap['arcade_closed_heading'] ?></h2>
                    <p style="font-size: 1.2rem; color: var(--cream); margin-top: 10px;"><?= sprintf($cap['arcade_closed_msg'], $state['op_open_time'], $state['op_close_time']) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($view === 'display' && $isOpen): ?>
                <!-- TV DISPLAY VIEW -->
                <div class="tv-layout">
                    <div class="tv-machines" id="display-machines">
                        <?php foreach (['machine_1', 'machine_2'] as $idx => $mKey): ?>
                        <div class="machine tv-machine-box">
                            <h2 class="display-machine-h2"><?= $cap[$mKey.'_label'] ?></h2><div class="slot"></div>
                            <div class="machine-inner" style="justify-content: center;"><div id="m<?= $idx+1 ?>-status" class="current-player empty-machine display-status" style="margin: 0;"><?= $cap['insert_coin'] ?></div></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="card tv-queue" style="border-width: 3px;">
                        <h2 class="display-queue-h2"><?= $cap['display_next_heading'] ?></h2>
                        <ul id="waitlist-container" class="tv-queue-grid"><p class="empty-note display-empty-note" style="grid-column: 1 / -1;"><?= $cap['display_loading'] ?></p></ul>
                    </div>
                </div>

<?php elseif ($view === 'admin'): ?>
                <!-- ==================== ADMIN VIEW ==================== -->
                <?php if ($isAdmin): ?>
                    <div class="card operator"><h2 class="active"><?= $cap['admin_active_heading'] ?></h2>
                        <ul class="status-list">
                            <?php foreach (['machine_1' => $m1B, 'machine_2' => $m2B] as $mKey => $busy): ?>
                            <li><span><?= $cap[$mKey.'_label'] ?></span><b class="<?= $busy ? 'stat-busy' : 'stat-free' ?>" style="display:flex; flex-direction:column; text-align:right;">
                                <?php if ($busy): ?><?= strtoupper($state[$mKey]['name']) ?><span class="timer timer-badge" style="color:var(--red); display:block; margin-top:5px;" data-start="<?= $state[$mKey]['started_at'] ?? time() ?>" data-duration="<?= $config['machine_timeout'] ?>"></span>
                                <?php elseif ($state['play_mode'] === 'solo'): ?><span style="color:var(--dim);"><?= $cap['machine_disabled'] ?></span>
                                <?php else: ?><?= $cap['admin_free_label'] ?><?php if ($state['play_mode'] === 'multi' && !empty($state['multi_timer_start'])): ?><span class="timer timer-badge" style="color:var(--amber); display:block; margin-top:5px;" data-prefix="<?= $cap['waiting_p2'] ?>" data-start="<?= $state['multi_timer_start'] ?>" data-duration="120"></span><?php endif; ?>
                                <?php endif; ?>
                            </b></li>
                            <?php endforeach; ?>
                            <li><span><?= $cap['admin_queue_label'] ?></span><b><?= $qCount ?> <?= $qCount === 1 ? $cap['admin_players_label'] : $cap['admin_players_label_pl'] ?></b></li>
                        </ul>
                        <div class="flex" style="margin-top: 22px;">
                            <form method="POST" style="flex: 1;" onsubmit="return confirm('<?= $cap['admin_reset_confirm'] ?>');"><input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>"><input type="hidden" name="action" value="clear"><input type="hidden" name="return_to" value="?view=admin"><button type="submit" class="btn-leave" style="width: 100%;"><?= $cap['admin_reset_btn'] ?></button></form>
                            <form method="POST" style="flex: 1;"><input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>"><input type="hidden" name="action" value="admin_logout"><input type="hidden" name="return_to" value="?view=admin"><button type="submit" style="width: 100%;"><?= $cap['admin_logout_btn'] ?></button></form>
                        </div>
                        <div class="flex" style="margin-top: 12px;"><a href="?view=password" style="flex:1; display:flex;"><button type="button" style="width: 100%; border-color: var(--cyan); color: var(--cyan);"><?= $cap['admin_change_pw_btn'] ?></button></a></div>
                    </div>

                    <!-- TIMER BLOCK WITH SUB-TEXTS -->
                    <div class="card operator"><h2 style="color: var(--amber);"><?= $cap['admin_timer_heading'] ?></h2>
                        <p style="font-size: 0.9rem; color: var(--dim); margin: -14px 0 16px 0;"><?= $cap['admin_timer_desc'] ?></p>
                        <form method="POST" class="flex" style="flex-direction: column; gap: 14px;"><input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>"><input type="hidden" name="action" value="update_timers"><input type="hidden" name="return_to" value="?view=admin">
                            <div>
                                <label style="font-size:0.85rem; color:var(--dim); display:block; margin-bottom:4px;"><?= $cap['admin_timer_m1_m2'] ?> &mdash; <span style="text-transform:none;"><?= $cap['admin_timer_m1_m2_desc'] ?></span></label>
                                <div class="select-fake"><select name="machine_timeout"><?php for($i=25; $i<=60; $i+=5): $v=$i*60; ?><option value="<?= $v ?>" <?= $config['machine_timeout']==$v?'selected':'' ?>><?= $i ?> <?= $cap['timer_minutes'] ?></option><?php endfor; ?></select></div>
                            </div>
                            <div>
                                <label style="font-size:0.85rem; color:var(--dim); display:block; margin-bottom:4px;"><?= $cap['admin_timer_skip'] ?> &mdash; <span style="text-transform:none;"><?= $cap['admin_timer_skip_desc'] ?></span></label>
                                <div class="select-fake"><select name="skip_timeout"><?php for($i=3; $i<=10; $i++): $v=$i*60; ?><option value="<?= $v ?>" <?= $config['skip_timeout']==$v?'selected':'' ?>><?= $i ?> <?= $cap['timer_minutes'] ?></option><?php endfor; ?></select></div>
                            </div>
                            <div>
                                <label style="font-size:0.85rem; color:var(--dim); display:block; margin-bottom:4px;"><?= $cap['admin_timer_kick'] ?> &mdash; <span style="text-transform:none;"><?= $cap['admin_timer_kick_desc'] ?></span></label>
                                <div class="select-fake"><select name="kick_timeout">
                                    <option value="60" <?= $config['kick_timeout']==60?'selected':'' ?>>1 Menit (For test purposes)</option>
                                    <?php foreach([3, 3.5, 4, 4.5, 5] as $hr): $v=$hr*3600; $lbl = str_replace('.5', ' ' . $cap['timer_hours_half'], $hr . ($hr == floor($hr) ? ' ' . $cap['timer_hours'] : '')); ?><option value="<?= $v ?>" <?= $config['kick_timeout']==$v?'selected':'' ?>><?= $lbl ?></option><?php endforeach; ?>
                                </select></div>
                            </div>
                            <button type="submit" style="border-color: var(--amber); color: var(--amber); margin-top:6px;"><?= $cap['admin_timer_btn'] ?></button>
                        </form>
                    </div>

                    <!-- RUNTIME HOURS BLOCK WITH SUB-TEXTS -->
                    <div class="card operator"><h2 style="color: var(--pink);"><?= $cap['admin_op_heading'] ?></h2>
                        <p style="font-size: 0.9rem; color: var(--dim); margin: -14px 0 16px 0;"><?= $cap['admin_op_desc'] ?></p>
                        <form method="POST" class="flex" style="flex-direction: column; gap: 14px;"><input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>"><input type="hidden" name="action" value="update_hours"><input type="hidden" name="return_to" value="?view=admin">
                            <div style="display:flex; flex-direction:column; gap: 14px;">
                                <div>
                                    <label style="font-size:0.85rem; color:var(--dim); display:block; margin-bottom:4px;"><?= $cap['admin_op_open'] ?> &mdash; <span style="text-transform:none;"><?= $cap['admin_op_open_desc'] ?></span></label>
                                    <input type="text" name="op_open_time" class="time-input" value="<?= htmlspecialchars($state['op_open_time'] ?? '') ?>" placeholder="09:00" style="width:100%; text-align:center; box-sizing:border-box;" maxlength="5">
                                </div>
                                <div>
                                    <label style="font-size:0.85rem; color:var(--dim); display:block; margin-bottom:4px;"><?= $cap['admin_op_close'] ?> &mdash; <span style="text-transform:none;"><?= $cap['admin_op_close_desc'] ?></span></label>
                                    <input type="text" name="op_close_time" class="time-input" value="<?= htmlspecialchars($state['op_close_time'] ?? '') ?>" placeholder="22:30" style="width:100%; text-align:center; box-sizing:border-box;" maxlength="5">
                                </div>
                            </div>
                            <button type="submit" style="border-color: var(--pink); color: var(--pink);"><?= $cap['admin_op_btn'] ?></button>
                        </form>
                    </div>

                    <!-- GPS COORDINATES BLOCK WITH SUB-TEXTS -->
                    <div class="card operator"><h2 style="color: var(--cyan);"><?= $cap['admin_loc_heading'] ?></h2>
                        <p style="font-size: 0.9rem; color: var(--dim); margin: -14px 0 16px 0;"><?= $cap['admin_loc_desc'] ?></p>
                        <form method="POST" class="flex" style="flex-direction: column; gap: 14px;"><input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>"><input type="hidden" name="action" value="update_location"><input type="hidden" name="return_to" value="?view=admin">
                            <input type="text" name="arcade_lat" placeholder="<?= $cap['admin_loc_lat'] ?>" value="<?= htmlspecialchars($state['arcade_lat'] ?? '') ?>"><input type="text" name="arcade_lng" placeholder="<?= $cap['admin_loc_lng'] ?>" value="<?= htmlspecialchars($state['arcade_lng'] ?? '') ?>">
                            <button type="submit" style="border-color: var(--amber); color: var(--amber);"><?= $cap['admin_loc_btn'] ?></button>
                        </form>
                    </div>

                    <?php if ($isOpen): ?><div class="card"><h2><?= $cap['admin_players_heading'] ?></h2>
                        <?php if (empty($state['waitlist'])): ?><p class="empty-note"><?= $cap['empty_queue'] ?></p><?php else: ?><ul>
                        <?php foreach ($state['waitlist'] as $idx => $p): $isAfk = $p['afk'] ?? false; $isFirst = ($firstAct && $p['id'] === $firstAct['id']); ?>
                        <li class="waitlist-item"><div class="wl-head"><span class="rank">#<?= $idx + 1 ?></span><?php if ($isAfk): ?><span class="tag-afk"><?= $cap['afk_tag'] ?></span><?php endif; ?></div>
                            <form method="POST" class="edit-form"><input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>"><input type="hidden" name="action" value="rename_player"><input type="hidden" name="player_id" value="<?= $p['id'] ?>"><input type="hidden" name="return_to" value="?view=admin"><input type="text" name="new_name" value="<?= $p['name'] ?>" class="edit-input<?= $isAfk ? ' afk-name' : '' ?>" required maxlength="15"><button type="submit" class="btn-small" style="border-color: var(--amber); color: var(--amber);"><?= $cap['save_btn'] ?></button></form>
                            <div class="wl-actions">
                                <?php if ($isFirst): ?><form method="POST"><input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>"><input type="hidden" name="action" value="skip_player"><input type="hidden" name="player_id" value="<?= $p['id'] ?>"><input type="hidden" name="return_to" value="?view=admin"><button type="submit" class="btn-small" style="border-color: var(--pink); color: var(--pink);"><?= $cap['skip_btn'] ?></button></form><?php endif; ?>
                                <form method="POST"><input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>"><input type="hidden" name="action" value="toggle_afk"><input type="hidden" name="player_id" value="<?= $p['id'] ?>"><input type="hidden" name="return_to" value="?view=admin"><button type="submit" class="btn-small"><?= $isAfk ? $cap['back_btn'] : $cap['afk_btn'] ?></button></form>
                                <form method="POST" onsubmit="return confirm('<?= sprintf($cap['admin_remove_confirm'], addslashes($p['name'])) ?>');"><input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>"><input type="hidden" name="action" value="remove_player"><input type="hidden" name="player_id" value="<?= $p['id'] ?>"><input type="hidden" name="return_to" value="?view=admin"><button type="submit" class="btn-leave btn-small"><?= $cap['remove_btn'] ?></button></form>
                            </div>
                            <div style="flex-basis:100%; display:flex; flex-direction:column; gap:4px; padding-top:8px; margin-top:6px; border-top:1px dashed var(--line);">
                                <div style="font-size:0.85rem; color:var(--dim);"><?= $cap['queue_joined'] ?> <?= date('d M, H:i', $p['joined_at']) ?> &mdash; <span class="timer" style="color:var(--red);" data-start="<?= $isAfk ? ($p['afk_at'] ?? time()) : ($p['last_activity'] ?? $p['joined_at']) ?>" data-duration="<?= $isAfk ? 3600 : $config['kick_timeout'] ?>" data-prefix="<?= $isAfk ? 'AFK Kick: ' : $cap['queue_expires'] ?>"></span></div>
                                <?php if ($isFirst && isset($p['called_at'])): ?><div style="font-size: 0.9rem;"><span class="timer" style="color:var(--amber);" data-start="<?= $p['called_at'] ?>" data-duration="<?= $config['skip_timeout'] ?>" data-prefix="Skipping in: "></span></div><?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?></ul><?php endif; ?>
                    </div><?php endif; ?>
                <?php else: $adminLocked = arcade_login_locked($config); $lockRemain = $adminLocked ? max(0, $config['login_lockout_until'] - time()) : 0; ?>
                    <div class="card operator"><h2 class="idle"><?= $cap['admin_idle_heading'] ?></h2>
                        <?php if ($adminLocked): ?>
                            <p class="empty-note" style="text-align:left; color: var(--red); margin-bottom: 10px;"><?= $cap['admin_login_locked_msg'] ?? 'Terlalu banyak percobaan gagal. Coba lagi dalam:' ?></p>
                            <div id="admin-lock-countdown" style="text-align:center; font-family:'Press Start 2P',cursive; font-size:1.8rem; color:var(--red); text-shadow:0 0 10px var(--red); padding:18px 10px; border:2px solid var(--line); border-radius:8px; background:#000;">--:--</div>
                            <script>
                                (function(){
                                    var remain = <?= (int)$lockRemain ?>;
                                    var el = document.getElementById('admin-lock-countdown');
                                    function tick(){
                                        if (remain <= 0) { location.reload(); return; }
                                        var m = Math.floor(remain / 60), s = remain % 60;
                                        el.textContent = String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
                                        remain--;
                                    }
                                    tick();
                                    setInterval(tick, 1000);
                                })();
                            </script>
                        <?php else: ?>
                            <form method="POST" class="flex" style="flex-direction: column;"><input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>"><input type="hidden" name="action" value="admin_login"><input type="hidden" name="return_to" value="?view=admin"><input type="password" name="password" placeholder="<?= $cap['admin_password_ph'] ?>" required autofocus><button type="submit"><?= $cap['admin_login_btn'] ?></button></form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="operator-link-row"><a href="?view=terminal" class="operator-link"><?= $cap['admin_back_link'] ?></a></div>

            <?php elseif ($view === 'password'): ?>
                <!-- PASSWORD VIEW -->
                <div class="card operator">
                    <?php if (isset($_GET['success'])): ?><h2 class="active"><?= $cap['pw_updated_heading'] ?></h2><p class="empty-note" style="text-align:left;"><?= $cap['pw_updated_note'] ?></p>
                    <?php else: ?><h2 class="active"><?= $cap['pw_change_heading'] ?></h2>
                        <?php if (isset($_GET['err'])): ?><p class="empty-note" style="text-align:left; color: var(--red);">Error changing password.</p><?php endif; ?>
                        <form method="POST" class="flex" style="flex-direction: column; gap: 14px;"><input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>"><input type="hidden" name="action" value="change_password"><input type="password" name="current_password" placeholder="<?= $cap['pw_current_ph'] ?>" required><input type="password" name="new_password" placeholder="<?= $cap['pw_new_ph'] ?>" required minlength="4"><input type="password" name="confirm_password" placeholder="<?= $cap['pw_confirm_ph'] ?>" required minlength="4"><button type="submit"><?= $cap['pw_update_btn'] ?></button></form>
                    <?php endif; ?>
                </div>
                <div class="operator-link-row"><a href="?view=admin" class="operator-link"><?= $cap['admin_back_link'] ?></a></div>

            <?php elseif ($view === 'love-letter'): ?>
                <?php include __DIR__ . '/love-letter-view.php'; ?>

            <?php elseif ($view === 'terminal'): ?>
                <!-- TERMINAL VIEW -->
                <?php if ($isOpen): ?>
                <?php 
                    $hasAct = !empty($myPlayers) && count(array_intersect($myPlayers, array_column($state['waitlist'], 'id'))) > 0;
                    $canChoose = $firstAct && ($isAdmin || in_array($firstAct['id'], $myPlayers)); $canGuest = $isAdmin || $hasAct;
                    $myEntry = null; $myWhere = null;
                    if ($state['machine_1'] && in_array($state['machine_1']['id'], $myPlayers)) { $myEntry = $state['machine_1']; $myWhere = $cap['machine_1_label']; }
                    elseif ($state['machine_2'] && in_array($state['machine_2']['id'], $myPlayers)) { $myEntry = $state['machine_2']; $myWhere = $cap['machine_2_label']; }
                    else { foreach ($state['waitlist'] as $i => $p) { if (in_array($p['id'], $myPlayers)) { $myEntry = $p; $myWhere = 'Queue #' . ($i + 1); break; } } }
                ?>
                
                <div class="terminal-machines">
                    <?php foreach (['machine_1', 'machine_2'] as $i => $mKey): $m = $state[$mKey]; $isM = $m && in_array($m['id'], $myPlayers); $isG = $m && !empty($m['is_guest']); ?>
                    <div class="machine"><h2><?= $cap[$mKey.'_label'] ?></h2><div class="slot"></div>
                        <div class="machine-inner">
                            <div style="flex:1; display:flex; flex-direction:column; justify-content:center;">
                                <?php if ($m): ?>
                                    <div class="current-player <?= $isG ? 'guest-play' : '' ?>"><?= $m['name'] ?><span class="timer timer-badge" data-start="<?= $m['started_at'] ?? time() ?>" data-duration="<?= $config['machine_timeout'] ?>"></span></div>
                                <?php else: ?>
                                    <div class="current-player empty-machine" <?= $state['play_mode']==='solo' ? 'style="color:var(--dim); text-shadow:none; animation:none;"' : '' ?>><?= $state['play_mode']==='solo' ? $cap['machine_disabled'] : $cap['insert_coin'] ?></div>
                                    <?php if ($state['play_mode'] === 'multi' && !empty($state['multi_timer_start'])): ?>
                                        <span class="timer timer-badge" style="display:block; text-align:center; margin-top:8px;" data-prefix="<?= $cap['waiting_p2'] ?>" data-start="<?= $state['multi_timer_start'] ?>" data-duration="120"></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <div style="margin-top:auto; padding-top:15px;">
                                <?php if ($m): ?>
                                    <?php if ($isAdmin || $isM || $isG): ?><div class="machine-actions">
                                        <?php if (!$isG): ?><form method="POST"><input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>"><input type="hidden" name="action" value="next_<?= $i+1 ?>_requeue"><button type="submit" class="btn-requeue-<?= $i+1 ?>"><?= $cap['requeue_btn'] ?></button></form><?php endif; ?>
                                        <form method="POST"><input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>"><input type="hidden" name="action" value="next_<?= $i+1 ?>_leave"><button type="submit" class="btn-leave"><?= $cap['leave_btn'] ?></button></form></div>
                                    <?php else: ?><div class="locked-note">[<?= sprintf($cap['occupied_by'], '<b>'.strtoupper($m['name']).'</b>') ?>]</div><?php endif; ?>
                                <?php elseif ($state['play_mode'] !== 'solo'): ?>
                                    <?php if ($firstAct && $canChoose): ?>
                                        <?php if ($state['play_mode'] === 'multi'): ?><form method="POST"><input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>"><input type="hidden" name="action" value="pull_m<?= $i+1 ?>"><button type="submit" class="btn-pull" style="width:100%; border-color:var(--cyan); color:var(--cyan);"><?= $cap['btn_join_multi'] ?></button></form>
                                        <?php else: ?><div style="display:flex; gap:8px;"><form method="POST" style="flex:1"><input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>"><input type="hidden" name="action" value="pull_m<?= $i+1 ?>"><input type="hidden" name="play_mode" value="solo"><button type="submit" class="btn-pull" style="width:100%;"><?= $cap['btn_solo'] ?></button></form><form method="POST" style="flex:1"><input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>"><input type="hidden" name="action" value="pull_m<?= $i+1 ?>"><input type="hidden" name="play_mode" value="multi"><button type="submit" class="btn-pull" style="width:100%; border-color:var(--cyan); color:var(--cyan);"><?= $cap['btn_multi'] ?></button></form></div><?php endif; ?>
                                    <?php else: ?><button disabled style="width:100%;"><?= $firstAct ? sprintf($cap['waiting_for'], strtoupper($firstAct['name'])) : $cap['waiting_players'] ?></button><?php endif; ?>
                                    <?php if ($canGuest): ?><form method="POST" style="margin-top:8px;"><input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>"><input type="hidden" name="action" value="pull_guest_m<?= $i+1 ?>"><button type="submit" style="width:100%; border-color:var(--lime); color:var(--lime);"><?= $cap['guest_play_btn'] ?></button></form><?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="card" id="join-status"><h2><?= $cap['join_heading'] ?></h2>
                    <?php if ($myEntry): ?> <div class="my-status"><?= $cap['already_in_prefix'] ?> <b><?= strtoupper($myEntry['name']) ?></b> &mdash; <?= $myWhere ?>.<span class="my-status-sub"><?= $cap['already_in_suffix'] ?></span></div>
                    <?php else: ?>
                        <div id="loc-status-message" class="empty-note" style="margin-top:10px; font-size:1.2rem; color:var(--amber); display:<?= (!empty($state['arcade_lat']) && !empty($state['arcade_lng'])) ? 'block' : 'none' ?>;"><?= $cap['loc_checking'] ?></div>
                        <form method="POST" class="flex" id="join-form" style="display:<?= (!empty($state['arcade_lat']) && !empty($state['arcade_lng'])) ? 'none' : 'flex' ?>;"><input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>"><input type="hidden" name="action" value="add"><input type="hidden" name="user_lat" id="user_lat"><input type="hidden" name="user_lng" id="user_lng"><input type="text" name="player_name" placeholder="<?= $cap['join_placeholder'] ?>" required autocomplete="off" maxlength="15" class="edit-input" style="flex: 1;"><button type="submit" class="btn-join"><?= $cap['join_btn'] ?></button></form>
                    <?php endif; ?>
                </div>

                <div class="card" id="queue-ledger"><h2><?= $cap['ledger_heading'] ?></h2>
                    <?php if (empty($state['waitlist'])): ?><p class="empty-note"><?= $cap['empty_queue'] ?></p><?php else: ?><ul>
                    <?php foreach ($state['waitlist'] as $idx => $p): $isAfk = $p['afk'] ?? false; $isFirst = ($firstAct && $p['id'] === $firstAct['id']); ?>
                        <li class="waitlist-item"><div class="wl-head"><span class="rank">#<?= $idx + 1 ?></span><?php if ($isAfk): ?><span class="tag-afk"><?= $cap['afk_tag'] ?></span><?php endif; ?></div>
                        <?php if ($isAdmin || in_array($p['id'], $myPlayers)): ?>
                            <form method="POST" class="edit-form"><input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>"><input type="hidden" name="action" value="rename_player"><input type="hidden" name="player_id" value="<?= $p['id'] ?>"><input type="text" name="new_name" value="<?= $p['name'] ?>" class="edit-input<?= $isAfk ? ' afk-name' : '' ?>" required maxlength="15"><button type="submit" class="btn-small" style="border-color: var(--amber); color: var(--amber); margin-left: 8px;"><?= $cap['save_btn'] ?></button></form>
                            <div class="wl-actions">
                                <?php if ($isFirst): ?><form method="POST"><input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>"><input type="hidden" name="action" value="skip_player"><input type="hidden" name="player_id" value="<?= $p['id'] ?>"><button type="submit" class="btn-small" style="border-color: var(--pink); color: var(--pink);"><?= $cap['skip_btn'] ?></button></form><?php endif; ?>
                                <form method="POST"><input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>"><input type="hidden" name="action" value="toggle_afk"><input type="hidden" name="player_id" value="<?= $p['id'] ?>"><button type="submit" class="btn-small"><?= $isAfk ? $cap['back_btn'] : $cap['afk_btn'] ?></button></form>
                                <form method="POST"><input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>"><input type="hidden" name="action" value="remove_player"><input type="hidden" name="player_id" value="<?= $p['id'] ?>"><button type="submit" class="btn-leave btn-small"><?= $cap['remove_btn'] ?></button></form>
                            </div>
                        <?php else: ?><div style="display:flex; align-items:center; flex:1 1 auto;"><span class="player-locked<?= $isAfk ? ' afk-name' : '' ?>" style="flex:1 1 auto;"><?= $p['name'] ?></span><span class="tag-locked"><?= $cap['locked_tag'] ?></span></div><?php endif; ?>
                        
                        <div style="flex-basis:100%; display:flex; flex-direction:column; gap:4px; padding-top:8px; margin-top:6px; border-top:1px dashed var(--line);">
                            <div style="font-size:0.85rem; color:var(--dim);">
                                <?= $cap['queue_joined'] ?> <?= date('d M, H:i', $p['joined_at']) ?> &mdash; 
                                <span class="timer" style="color:var(--red);" data-start="<?= $isAfk ? ($p['afk_at'] ?? time()) : ($p['last_activity'] ?? $p['joined_at']) ?>" data-duration="<?= $isAfk ? 3600 : $config['kick_timeout'] ?>" data-prefix="<?= $isAfk ? 'AFK Kick: ' : $cap['queue_expires'] ?>"></span>
                            </div>
                            <?php if ($isFirst && isset($p['called_at'])): ?>
                                <div style="font-size: 0.9rem;">
                                    <span class="timer" style="color:var(--amber);" data-start="<?= $p['called_at'] ?>" data-duration="<?= $config['skip_timeout'] ?>" data-prefix="Skipping in: "></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        </li>
                    <?php endforeach; ?></ul><?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="guide-toggle-row">
                    <button type="button" class="btn-guide" style="width:100%; border-color:var(--cyan); color:var(--cyan);" onclick="var p=document.getElementById('guide-panel'); p.style.display = (p.style.display === 'block') ? 'none' : 'block';"><?= $cap['guide_btn'] ?></button>
                </div>
                <div id="guide-panel" class="card guide-panel" style="display:none;">
                    <h2><?= $cap['guide_heading'] ?></h2>
                    <ul class="guide-steps">
                        <li><?= $cap['guide_step1'] ?></li>
                        <li><?= $cap['guide_step2'] ?></li>
                        <li><?= $cap['guide_step3'] ?></li>
                        <li><?= $cap['guide_step4'] ?></li>
                        <li><?= $cap['guide_step5'] ?></li>
                    </ul>
                    <button type="button" style="width:100%; margin-top:20px;" onclick="document.getElementById('guide-panel').style.display='none';"><?= $cap['guide_close_btn'] ?></button>
                </div>

                <div class="card ll-home-promo">
                    <h2>Take a royal intermission</h2>
                    <p>Play the current 21-card edition of Love Letter with 2–6 players on this device.</p>
                    <a class="ll-home-link" href="?view=love-letter">Play Love Letter</a>
                </div>

            <?php endif; ?>
        </div>
    </div>
    
    <!-- Passes PHP variables securely to JS script -->
    <script>
        const SYS = { view: "<?= $view ?>", time: "<?= $cap['sys_time'] ?>", loc: <?= (!empty($state['arcade_lat']) && !empty($state['arcade_lng'])) ? 'true' : 'false' ?>, lat: <?= (float)($state['arcade_lat'] ?? 0) ?>, lng: <?= (float)($state['arcade_lng'] ?? 0) ?>,
            caps: <?= json_encode(['insert_coin' => $cap['insert_coin'], 'empty_queue' => $cap['empty_queue'], 'afk_tag' => $cap['afk_tag'], 'machine_disabled' => $cap['machine_disabled'], 'queue_joined' => $cap['queue_joined'], 'queue_expires' => $cap['queue_expires'], 'arcade_closed_heading' => $cap['arcade_closed_heading'], 'arcade_closed_msg' => $cap['arcade_closed_msg'], 'loc_checking' => $cap['loc_checking'], 'loc_err_far' => $cap['loc_err_far'], 'loc_err_unavail' => $cap['loc_err_unavail'], 'waiting_p2' => $cap['waiting_p2']]) ?>
        };
    </script>
    <script src="script.js"></script>
    <?php if ($view === 'love-letter'): ?>
    <script src="love-letter-engine.js"></script>
    <script src="love-letter.js"></script>
    <?php endif; ?>

    <?php if ($view === 'terminal' && (!empty($cap['qr_link_1_url']) || !empty($cap['qr_link_2_url']))): ?>
    <div class="container container-standard" style="margin-top:-10px;">
        <div class="card" style="border-color: var(--amber);">
            <h2 style="color:var(--amber); text-shadow:0 0 8px rgba(255,176,0,.4);"><?= $cap['qr_panel_heading'] ?></h2>
            <p style="font-size:0.9rem; color:var(--dim); margin: -14px 0 16px 0;"><?= $cap['qr_panel_desc'] ?></p>
            <div class="qr-grid">
                <?php if (!empty($cap['qr_link_1_url'])): ?>
                <div class="qr-item">
                    <div id="qr-canvas-1" style="background:#fff; padding:8px; border-radius:8px; border:2px solid var(--line); display:inline-block;"></div>
                    <span class="qr-label"><?= $cap['qr_link_1_label'] ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($cap['qr_link_2_url'])): ?>
                <div class="qr-item">
                    <div id="qr-canvas-2" style="background:#fff; padding:8px; border-radius:8px; border:2px solid var(--line); display:inline-block;"></div>
                    <span class="qr-label"><?= $cap['qr_link_2_label'] ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="operator-link-row"><a href="?view=admin" class="operator-link"><?= $isAdmin ? $cap['operator_link_in'] : $cap['operator_link_out'] ?></a></div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        <?php if (!empty($cap['qr_link_1_url'])): ?>
        new QRCode(document.getElementById('qr-canvas-1'), { text: <?= json_encode($cap['qr_link_1_url']) ?>, width: 160, height: 160, colorDark: '#000000', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
        <?php endif; ?>
        <?php if (!empty($cap['qr_link_2_url'])): ?>
        new QRCode(document.getElementById('qr-canvas-2'), { text: <?= json_encode($cap['qr_link_2_url']) ?>, width: 160, height: 160, colorDark: '#000000', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
        <?php endif; ?>
    </script>
    <?php elseif ($view === 'terminal'): ?>
    <div class="container container-standard" style="margin-top:-10px;">
        <div class="operator-link-row"><a href="?view=admin" class="operator-link"><?= $isAdmin ? $cap['operator_link_in'] : $cap['operator_link_out'] ?></a></div>
    </div>
    <?php endif; ?>
    <div class="app-footer"><?= htmlspecialchars($cap['app_name'] ?? 'ARCADE QUEUE SYSTEM') ?> &middot; v<?= htmlspecialchars($cap['app_version'] ?? '1.0.0') ?></div>
</body>
</html>
