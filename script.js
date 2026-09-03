document.addEventListener('DOMContentLoaded', () => {
    function updateClock() {
        const d = new Date(), p = n => String(n).padStart(2, '0');
        const m = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
        const el = document.getElementById('sys-time');
        if (el) el.textContent = `${SYS.time}${p(d.getDate())}-${m[d.getMonth()]}-${d.getFullYear()} [${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}]`;
    }

    function renderTimers() {
        const now = Math.floor(Date.now() / 1000), pad = n => String(n).padStart(2, '0');
        document.querySelectorAll('.timer').forEach(el => {
            const start = parseInt(el.getAttribute('data-start')), dur = parseInt(el.getAttribute('data-duration'));
            if (!start) return; let left = Math.max(0, dur - (now - start));
            const prefix = el.getAttribute('data-prefix') || "";
            if (el.classList.contains('timer-badge')) {
                // Machine timers: minutes and seconds only, no hour segment.
                const totalM = Math.floor(left / 60), s = left % 60;
                el.textContent = prefix + `${pad(totalM)}:${pad(s)}`;
            } else {
                const h = Math.floor(left / 3600), m = Math.floor((left % 3600) / 60), s = left % 60;
                // Fixed H:MM:SS format at all times so the element's width never jumps around as the count changes.
                el.textContent = prefix + `${h}:${pad(m)}:${pad(s)}`;
            }
        });
    }

    setInterval(() => { updateClock(); renderTimers(); }, 1000);
    updateClock(); renderTimers();

    if (SYS.view === 'admin') {
        document.querySelectorAll('.time-input').forEach(i => i.addEventListener('input', function() {
            let v = this.value.replace(/\D/g, '');
            if (v.length > 2) v = v.substring(0, 2) + ':' + v.substring(2, 4); this.value = v;
        }));

        document.querySelectorAll('.select-fake').forEach(wrapper => {
            const select = wrapper.querySelector('select'); if (!select) return;
            const trigger = document.createElement('div'), list = document.createElement('ul');
            trigger.className = 'select-fake-trigger'; trigger.tabIndex = 0; list.className = 'select-fake-list';
            const sync = () => trigger.textContent = select.options[select.selectedIndex]?.textContent.trim() || '';
            const close = () => { list.classList.remove('open'); trigger.classList.remove('open'); };
            const build = () => {
                list.innerHTML = '';
                Array.from(select.options).forEach((opt, i) => {
                    const li = document.createElement('li'); li.textContent = opt.textContent.trim();
                    if (i === select.selectedIndex) li.classList.add('active');
                    li.onclick = () => { select.selectedIndex = i; sync(); build(); close(); }; list.appendChild(li);
                });
            };
            trigger.onclick = e => { e.stopPropagation(); list.classList.contains('open') ? close() : (document.querySelectorAll('.select-fake-list').forEach(l => l.classList.remove('open')), list.classList.add('open')); };
            document.addEventListener('click', close); build(); sync();
            wrapper.append(trigger, list); select.classList.add('js-hidden');
        });
    }

    if (SYS.view === 'terminal') {
        // Tracks the last known GPS state so it can be re-applied after every AJAX swap.
        let gpsState = SYS.loc ? 'checking' : 'disabled'; // 'disabled'|'checking'|'ok'|'far'|'error'
        let lastDist = null;
        let lastLat = null;
        let lastLng = null;

        function applyGpsState() {
            if (!SYS.loc) return;
            const jf = document.getElementById('join-form');
            const ls = document.getElementById('loc-status-message');
            if (!jf || !ls) return;
            if (gpsState === 'checking') {
                jf.style.display = 'none'; ls.style.display = 'block';
                ls.innerHTML = SYS.caps.loc_checking || '...';
            } else if (gpsState === 'ok') {
                ls.style.display = 'none'; jf.style.display = 'flex';
            } else if (gpsState === 'far') {
                jf.style.display = 'none'; ls.style.display = 'block';
                ls.innerHTML = `<span style='color:var(--red);'>${SYS.caps.loc_err_far.replace('%s', Math.round(lastDist))}</span>`;
            } else if (gpsState === 'error') {
                jf.style.display = 'none'; ls.style.display = 'block';
                ls.innerHTML = `<span style='color:var(--red);'>${SYS.caps.loc_err_unavail}</span>`;
            }
        }

        if (SYS.loc) {
            if (!navigator.geolocation) {
                gpsState = 'error'; applyGpsState();
            } else {
                navigator.geolocation.watchPosition(pos => {
                    lastLat = pos.coords.latitude;
                    lastLng = pos.coords.longitude;
                    document.getElementById('user_lat') && (document.getElementById('user_lat').value = lastLat);
                    document.getElementById('user_lng') && (document.getElementById('user_lng').value = lastLng);
                    const R = 6371e3, p1 = SYS.lat * Math.PI/180, p2 = pos.coords.latitude * Math.PI/180;
                    const a = Math.pow(Math.sin((p2-p1)/2), 2) + Math.cos(p1) * Math.cos(p2) * Math.pow(Math.sin((pos.coords.longitude - SYS.lng) * Math.PI/180 / 2), 2);
                    lastDist = R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                    gpsState = lastDist <= 100 ? 'ok' : 'far';
                    applyGpsState();
                }, () => { gpsState = 'error'; applyGpsState(); }, { enableHighAccuracy: true, timeout: 10000 });
            }
        }

        let hash = '';
        setInterval(async () => {
            const el = document.activeElement;
            if (el && el.tagName === 'INPUT' && (el.type === 'text' || el.classList.contains('edit-input'))) return;
            try {
                const r = await fetch('?api=state&hash=' + hash); if (r.status === 304) return;
                const d = await r.json(); hash = d.state_hash;
                const hRes = await fetch('?view=terminal');
                const doc = new DOMParser().parseFromString(await hRes.text(), 'text/html');
                const main = document.getElementById('main-content');
                if (main && doc.getElementById('main-content')) {
                    main.innerHTML = doc.getElementById('main-content').innerHTML;
                    // Re-fill GPS coords into the freshly swapped form inputs.
                    if (SYS.loc && lastDist !== null) {
                        const lat = document.getElementById('user_lat'), lng = document.getElementById('user_lng');
                        if (lat && lastLat !== null) lat.value = lastLat;
                        if (lng && lastLng !== null) lng.value = lastLng;
                        // coords were set by watchPosition — re-apply from the last known position via a fresh watch tick is fine,
                        // but we can directly re-apply GPS state immediately so the form shows/hides correctly right away.
                    }
                    applyGpsState();
                    renderTimers();
                }
            } catch(e){}
        }, 4000);
    }

    if (SYS.view === 'display') {
        let hash = '';
        setInterval(async () => {
            try {
                const r = await fetch('?api=state&hash=' + hash); if (r.status === 304) return;
                const d = await r.json(); hash = d.state_hash;
                if (d.is_open === false) {
                    document.getElementById('main-content').innerHTML = `<div class="card" style="text-align:center; border-color:var(--red); width:100%;"><h2 style="color:var(--red); text-shadow:0 0 10px var(--red); font-size:2.5rem;">${SYS.caps.arcade_closed_heading}</h2><p style="color:var(--cream); font-size:1.8rem; margin-top:20px;">${SYS.caps.arcade_closed_msg.replace('%s', d.op_open_time).replace('%s', d.op_close_time)}</p></div>`; return;
                } else if (!document.getElementById('m1-status')) { location.reload(); return; }

                ['machine_1', 'machine_2'].forEach(k => {
                    const el = document.getElementById(k === 'machine_1' ? 'm1-status' : 'm2-status'), m = d[k];
                    if (m) { el.innerHTML = `${m.name}<span class="timer timer-badge" style="font-size:1.2rem; margin-top:10px; display:block;" data-start="${m.started_at}" data-duration="${d.config.machine_timeout}"></span>`; el.className = 'current-player display-status' + (m.is_guest ? ' guest-play' : ''); el.style = m.is_guest ? 'color:var(--lime); text-shadow:0 0 10px var(--lime);' : ''; }
                    else {
                        const waitBadge = (d.play_mode === 'multi' && d.multi_timer_start) ? `<span class="timer timer-badge" style="font-size:1.2rem; margin-top:10px; display:block; color:var(--amber);" data-prefix="${SYS.caps.waiting_p2}" data-start="${d.multi_timer_start}" data-duration="120"></span>` : '';
                        el.innerHTML = (d.play_mode === 'solo' ? SYS.caps.machine_disabled : SYS.caps.insert_coin) + waitBadge;
                        el.className = 'current-player empty-machine display-status'; el.style = d.play_mode === 'solo' ? 'color:var(--dim); text-shadow:none; animation:none;' : '';
                    }
                });

                const wl = document.getElementById('waitlist-container');
                if (!d.waitlist || !d.waitlist.length) wl.innerHTML = `<p class="empty-note display-empty-note" style="grid-column: 1 / -1;">${SYS.caps.empty_queue}</p>`;
                else {
                    let foundAct = false;
                    wl.innerHTML = d.waitlist.map((p, i) => {
                        let skipStr = '';
                        if (!p.afk && !foundAct) {
                            foundAct = true;
                            if (p.called_at) {
                                skipStr = `<span class="timer tv-skip-timer" data-start="${p.called_at}" data-duration="${d.config.skip_timeout}" data-prefix="Skipping in: "></span>`;
                            }
                        }
                        return `<li class="waitlist-item display-only-item">
                            <div class="tv-wl-head">
                                <span class="rank">#${i + 1}</span>
                                <span class="${p.afk ? 'afk-name' : ''}">${p.name}</span>
                                ${p.afk ? `<span class="tag-afk">${SYS.caps.afk_tag}</span>` : ''}
                            </div>
                            <div class="display-joined-note">
                                <span class="tv-joined-label">${SYS.caps.queue_joined} ${p.joined_formatted}</span>
                                <span class="timer tv-kick-timer" data-start="${p.afk ? p.afk_at : (p.last_activity || p.joined_at)}" data-duration="${p.afk ? 3600 : d.config.kick_timeout}" data-prefix="${p.afk ? 'AFK Kick: ' : SYS.caps.queue_expires}"></span>
                                ${skipStr}
                            </div>
                        </li>`;
                    }).join('');
                }
                renderTimers();
            } catch(e){}
        }, 2000);
    }
});
