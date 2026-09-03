<?php
/**
 * ============================================================
 * PUSAT PENGATURAN TEKS (CAPTIONS & DESCRIPTIONS)
 * ============================================================
 */

return [
    // PENGATURAN UMUM & IKON[cite: 4]
    'site_title'              => 'maimai Queue List | maimaiDX Grand Wisata',
    'sys_time'                => 'Tanggal / Jam : ',
    'window_icon'             => '🎮',
    'admin_window_icon'       => '⚙️',
    'header_icon'             => '',
    'admin_header_icon'       => '',
    
    // HEADER & TITLES[cite: 4]
    'window_title_index'      => 'Player Terminal',
    'panel_title'             => 'Antrian "maimai"',
    'panel_subtitle'          => '<h2>maimaiDX Grand Wisata</h2>Venue : Timezone Living World Grand Wisata Bekasi',
    'window_title_display'    => 'Live Display',
    'display_title'           => 'Antrian "maimai"',
    'window_title_admin'      => '⚙ Akses Operator',
    'admin_title'             => 'Operator Panel',
    'admin_subtitle'          => 'Pengaturan Antrian',
    'window_title_pw'         => 'Password Setup',
    'pw_setup_title'          => 'Ganti Password Admin',
    'pw_setup_subtitle'       => 'Pengaturan Administratif',

    // MESIN & STATUS (MACHINE PANEL)[cite: 4]
    'machine_1_label'         => 'CABINET KIRI [P1]',
    'machine_2_label'         => 'CABINET KANAN [P2]',
    'insert_coin'             => 'KOSONG',
    'machine_disabled'        => 'MAIN SOLO',
    'occupied_by'             => 'Lagi dimainin sama %s',
    'waiting_p2'              => 'Menunggu Partner: ',
    
    // ANTREAN (WAITLIST)[cite: 4]
    'display_next_heading'    => '> Antrian Selanjutnya',
    'display_loading'         => 'Initializing...',
    'empty_queue'             => 'Belum ada player yang ngantri.',
    'ledger_heading'          => '> Daftar Antrian',
    'afk_tag'                 => '[ AFK ]',
    'locked_tag'              => 'Player in Queue',
    'queue_joined'            => 'Joined at:',
    'queue_expires'           => 'Auto-Kick dalam: ',
    'waiting_for'             => 'Lagi nunggu %s',
    'waiting_players'         => 'Lagi nunggu player lain...',

    // TOMBOL-TOMBOL (BUTTONS)[cite: 4]
    'btn_solo'                => 'Solo',
    'btn_multi'               => 'Multi',
    'btn_join_multi'          => 'Main Multi',
    'requeue_btn'             => 'Main lagi',
    'leave_btn'               => 'Selesai',
    'guest_play_btn'          => 'GUEST PLAY',
    'save_btn'                => 'Ganti',
    'skip_btn'                => 'Lewat',
    'afk_btn'                 => 'AFK',
    'back_btn'                => 'Main',
    'remove_btn'              => 'Keluar',
    'join_heading'            => '> Ikut antrian',
    'join_placeholder'        => 'Masukkan nama kamu_',
    'join_btn'                => 'Ikut Main',
    'already_in_prefix'       => 'Selamat Bermain',
    'already_in_suffix'       => '<b>PERHATIAN!!!<br>Jangan menutup browser kalian apabila masih berada di venue.<br>Ini akan mengakibatkan sistem antrian terhambat. Laporkan kepada admin komunitas untuk meminta penanganan lebih lanjut.</b><br><br>Bermainlah secara hati-hati dan tidak kasar. Karena mesin "maimai" punya semua pengunjung.<br><br>Apabila ingin beristirahat atau meninggalkan lokasi sementara, harap untuk mencantumkan label "AFK" agar sistem antrian tidak terhambat.',

    // GPS & LOKASI (ERROR MESSAGES)[cite: 4]
    'loc_checking'            => 'Lagi ngecek lokasi GPS...',
    'loc_err_far'             => 'Jarakmu masih %sm lagi! Kamu harus ada di radius 100m dari arcade buat join.',
    'loc_err_https'           => 'SECURITY_ERROR_443 : Lokasi GPS butuh koneksi HTTPS biar bisa jalan.',
    'loc_err_unavail'         => 'Lokasi nggak ketemu. Coba geser ke luar atau cek GPS kamu.',
    'loc_err_denied'          => 'LOCATION PERMISSION DENIED.',
    'loc_err_timeout'         => 'LOCATION_REQUEST_TIME_OUT : Request lokasi timeout (kelamaan).',
    'gps_err_msg'             => '[SECURE GPS] REGISTRATION FAILED: OUT OF RANGE.',

    // OPERATOR (ADMIN PANEL)[cite: 4]
    'arcade_closed_heading'   => 'Arcade-nya Sudah Tutup',
    'arcade_closed_msg'       => 'Arcade cuma buka dari jam %s sampai %s. Balik lagi nanti ya!',
    'admin_idle_heading'      => '> AKSES OPERATOR',
    'admin_password_ph'       => 'SANDI_',
    'admin_login_btn'         => 'Masuk',
    'admin_active_heading'    => '> Permainan saat ini',
    'admin_free_label'        => 'KOSONG',
    'admin_queue_label'       => 'Panjang Antrian',
    'admin_players_label'     => 'Pemain',
    'admin_players_label_pl'  => 'Pemain',
    'admin_reset_confirm'     => 'Perhatian: Proses ini akan menghapus semua pemain termasuk pemain aktif dalam antrian, lanjutkan?',
    'admin_reset_btn'         => 'Master Reset',
    'admin_logout_btn'        => 'Keluar',
    'admin_change_pw_btn'     => 'Ganti Password Admin',
    
    // OPERATING HOURS SETTINGS[cite: 4]
    'admin_op_heading'        => '> Jam Operasional',
    'admin_op_desc'           => 'Atur jam operasional, diluar dari jam tersebut seluruh antrian aktif akan dihapus.',
    'admin_op_open'           => 'Jam Buka',
    'admin_op_open_desc'      => 'Format 24 jam (contoh 08:00 - 23:59)',
    'admin_op_close'          => 'Jam Tutup',
    'admin_op_close_desc'     => 'Format 24 jam (contoh 08:00 - 23:59)',
    'admin_op_btn'            => 'Simpan Jam Operasional',
    
    // GPS LOCATION SETTINGS[cite: 4]
    'admin_loc_heading'       => '> Setting Geofence (Batas Jarak)',
    'admin_loc_desc'          => 'Input nama player hanya bisa dilakukan apabila jarak player ke arcade kurang dari 100 meter.<br>(Kosongkan jika tidak digunakan)',
    'admin_loc_lat'           => 'LATITUDE (Contoh: -6.2088)',
    'admin_loc_lng'           => 'LONGITUDE (Contoh: 106.8456)',
    'admin_loc_btn'           => 'Simpan Lokasi',
    'admin_players_heading'   => '> Kelola antrian',
    'admin_players_empty'     => 'Belum ada player yang ngantri.',
    'admin_remove_confirm'    => 'Hapus player "%s" dari daftar?',
    'operator_link_in'        => '⚙ Akses Operator - Aktif',
    'operator_link_out'       => '⚙ Akses Operator',

    // TIMER CONFIGURATION & DESCRIPTIONS[cite: 4]
    'admin_timer_heading'     => '> Konfigurasi Timer',
    'admin_timer_desc'        => 'Konfigurasi agar sistem antrian dapat terus berjalan tanpa hambatan.',
    'admin_timer_m1_m2'       => '<b>GAMEPLAY DURATION</b>',
    'admin_timer_m1_m2_desc'  => 'Atur durasi bermain, pastikan sudah diperhitungkan termasuk bermain 2 Track, 3 Track, 4 Track (multiplayer).',
    'admin_timer_skip'        => '<b>AUTO-SKIP DURATION</b>',
    'admin_timer_skip_desc'   => 'Atur durasi giliran apa bila player terlalu lama menunggu.',
    'admin_timer_kick'        => '<b>IDLE QUEUE TIMEOUT</b>',
    'admin_timer_kick_desc'   => 'Atur durasi waktu player berada dalam daftar antrian.',
    'admin_timer_btn'         => 'Simpan Pengaturan Timer',
    'timer_minutes'           => 'Menit',
    'timer_hours'             => 'Jam',
    'timer_hours_half'        => 'Jam 30 Menit',

    // CHANGE PASSWORD (ADMIN)[cite: 4]
    'pw_updated_heading'      => '> Password Berhasil Di-update ',
    'pw_updated_note'         => 'Password admin udah diganti, harap untuk mencatat kembali password yang baru.',
    'pw_change_heading'       => '> Ganti Password',
    'pw_current_ph'           => 'Password Sekarang_',
    'pw_new_ph'               => 'Password Baru_',
    'pw_confirm_ph'           => 'Konfirmasi Password Baru_',
    'pw_update_btn'           => 'UPDATE PASSWORD',
    'admin_back_link'         => '← Balik ke Daftar Antrian',

    // PLAYER GUIDE PANEL[cite: 4]
    'guide_btn'               => 'Cara Menggunakan',
    'guide_close_btn'         => 'Tutup',
    'guide_heading'           => 'HOW THE QUEUE WORKS',
    'guide_step1'             => '
        <b>> CABINET PANEL <</b><br>
        <b>SOLO :</b> Bermain solo, Cabinet sebelah akan terkunci dan player tidak bisa bermain multi.<br><br>
        <b>MULTI :</b> Bermain Multiplay, player akan diberikan waktu selama 2 menit buat join. apabila melebihi batas tersebut akan menjadi SOLO PLAY.<br><br>
        <b>GUEST PLAY :</b> Apabila terdapat pemain random bermain di luar dari daftar antrian (in case of priorities), sehingga tidak menghambat antrian pemain selanjutnya yang terdaftar. Player yang berada dalam daftar bisa menylesaikan permainan apabila GUEST sudah selesai bermain dengan menekan tombol pada Cabinet Panel.<br><br>
        <b>MAIN LAGI (in operational) :</b> Player akan kembali ke daftar antrian apabila player telah menyesaikan permainan.<br><br>
        <b>SELESAI (In Operational) :</b> Player dinyatakan selesai bermain dan tidak mengikuti ke dalam antrian.<br>
        ',
    'guide_step2'             => '
        <b>> PLAYER LIST <</b><br>
        <b>GANTI :</b> Mengubah nama player (jika diinginkan). Tekan terlebih dahulu nama kalian untuk mengubah nama lalu tekan "GANTI", secara otomatis nama akan tersimpan.<br><br>
        <b>LEWAT :</b> Melewati giliran kamu, nama kamu akan berpindah ke antrian paling akhir.<br><br>
        <b>AFK :</b> Nama kamu akan ditahan pada daftar antrian, secara otomatis ketika giliran kamu tiba akan digantikan ke giliran selanjutnya.<br><br>
        <b>KELUAR :</b> Hapus nama kamu dari daftar antrian.
        ',
    'guide_step3'             => '
        <b>> TIMEOUT SYSTEM <</b><br>
        Setiap pemain akan diberikan waktu saat berada dalam antrian. terdapat "Auto-Kick Timer" bagi player tidak menghapus nama player pada daftar antrian akan dilkeluarkan secara otomatis dalam waktu yang sudah ditentukan (ada pada player list). Setiap player mempunyai "Skip Time" pada gilirannya untuk menentukan ingin bermain SOLO, MULTI, atau GUEST PLAY, jika player tidak menentukan pilihannya maka secara otomatis gilirannya akan hilang dan dialihkan ke giliran selanjutnya. Terdapat "AFK Timer" dengan waktu 1 jam, apabila player tidak kembali atau meninggalkan cukup lama akan berubah menjadi "Auto-Kick" timer.
        ',
    'guide_step4'             => '',
    'guide_step5'             => '',

    // SECURITY ERROR PAGE[cite: 4]
    'security_error_heading'  => 'SECURITY CHECK FAILED',
    'security_error_msg'      => 'YOUR SESSION COULD NOT BE VERIFIED. THIS USUALLY HAPPENS IF COOKIES ARE BLOCKED OR DISABLED, OR YOUR SESSION HAS EXPIRED. PLEASE ENABLE COOKIES AND REFRESH.',
    'security_error_btn'      => 'REFRESH PAGE',

    // QR LINK PANEL
    'qr_panel_heading'        => 'Follow us',
    'qr_panel_desc'           => 'Yuk ikut bergabung di komunitas kami, dan jangan lupa share queue ini ke player lain ya!!!',
    'qr_link_1_label'         => '<b>maimai Queue List</b>',
    'qr_link_1_url'           => '',
    'qr_link_2_label'         => '<b>maimaiDX Grand Wisata</b>',
    'qr_link_2_url'           => 'https://discord.gg/K8qNqwvqPm',

    //APP VERSION
    'app_name' => 'Made with ❤️ by RikuVEN♪',
    'app_version' => '1.0.6 - Build 08072026',

];