(function () {
    'use strict';

    const app = document.getElementById('love-letter-app');
    if (!app) return;

    const STORAGE_KEY = 'maimai-gw-queue-love-letter-session-v2';
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const home = document.getElementById('ll-session-home');
    const match = document.getElementById('ll-match');
    const homeMessage = document.getElementById('ll-home-message');
    let saved = loadSaved();
    let snapshot = null;
    let busy = false;
    let pollTimer = null;
    let inFlight = false;
    let actionSequence = 0;
    let matchMessage = '';

    const definitions = [
        { key: 'spy', name: 'Spy', value: 0, emblem: 'S', effect: 'No play effect. Earn a bonus favor if yours is the only Spy played or discarded by a player still in the round.' },
        { key: 'guard', name: 'Guard', value: 1, emblem: 'G', effect: 'Guess a character in another player’s hand. A correct guess knocks them out.' },
        { key: 'priest', name: 'Priest', value: 2, emblem: 'P', effect: 'Secretly look at another player’s hand.' },
        { key: 'baron', name: 'Baron', value: 3, emblem: 'B', effect: 'Secretly compare hands with another player. The lower card is out.' },
        { key: 'handmaid', name: 'Handmaid', value: 4, emblem: 'H', effect: 'You are protected from other players’ card effects until your next turn.' },
        { key: 'prince', name: 'Prince', value: 5, emblem: 'P', effect: 'Choose any player, including yourself. They discard their hand and draw a replacement.' },
        { key: 'chancellor', name: 'Chancellor', value: 6, emblem: 'C', effect: 'Draw up to two cards, keep one, and return the rest to the bottom of the deck.' },
        { key: 'king', name: 'King', value: 7, emblem: 'K', effect: 'Trade hands with another player.' },
        { key: 'countess', name: 'Countess', value: 8, emblem: 'W', effect: 'No play effect. You must play her if your other card is the King or a Prince.' },
        { key: 'princess', name: 'Princess', value: 9, emblem: '♥', effect: 'If you play or discard her for any reason, you are out of the round.' }
    ];
    const cardByKey = Object.fromEntries(definitions.map(def => [def.key, def]));
    const names = definitions.map(def => def.name);

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[character]);
    }

    function loadSaved() {
        try {
            const data = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || 'null');
            return data && /^[A-Z0-9]{8}$/.test(data.code) && data.playerToken ? data : null;
        } catch (error) { return null; }
    }

    function saveSaved() {
        try {
            if (saved) window.localStorage.setItem(STORAGE_KEY, JSON.stringify(saved));
            else window.localStorage.removeItem(STORAGE_KEY);
        } catch (error) { setHomeMessage('Browser storage is unavailable; keep your player token safe or use this browser to resume.'); }
    }

    function setHomeMessage(text, error) {
        if (!homeMessage) return;
        homeMessage.textContent = text || '';
        homeMessage.classList.toggle('ll-error-message', !!error);
    }

    function cardDef(card) {
        return cardByKey[(typeof card === 'string' ? card : card?.key)] || definitions[0];
    }

    function cardFace(card, buttonAction, disabled) {
        const def = cardDef(card);
        const inner = `<span class="ll-card-face tone-${def.key}">
            <span class="ll-card-topline"><span>${def.value} · ${def.name}</span><span class="ll-card-number">${def.value}</span></span>
            <span class="ll-card-emblem" aria-hidden="true">${escapeHtml(def.emblem)}</span>
            <span><span class="ll-card-title">${escapeHtml(def.name)}</span><span class="ll-card-copy">${escapeHtml(def.effect)}</span></span>
        </span>`;
        if (!buttonAction) return `<div class="ll-card-display">${inner}</div>`;
        return `<button class="ll-card-button" type="button" data-action="${escapeHtml(buttonAction)}" data-card-id="${escapeHtml(card.id)}" ${disabled ? 'disabled aria-disabled="true"' : ''} aria-label="${escapeHtml(def.value + ' ' + def.name)}">${inner}${disabled ? '<span class="ll-card-disabled-note">Countess must be played</span>' : ''}</button>`;
    }

    function smallCard(card) {
        const def = cardDef(card);
        return `<span class="ll-card-mini"><b>${def.value}</b> ${escapeHtml(def.name)}</span>`;
    }

    async function apiGet(apiName, params) {
        const url = new URL(window.location.href);
        url.search = '';
        url.searchParams.set('api', apiName);
        Object.entries(params || {}).forEach(([key, value]) => url.searchParams.set(key, value));
        const headers = {};
        if (saved?.playerToken) headers['X-LL-Token'] = saved.playerToken;
        const response = await fetch(url, { method: 'GET', headers, credentials: 'same-origin', cache: 'no-store' });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) throw new Error(data.error || `Request failed (${response.status}).`);
        return data;
    }

    async function apiPost(action, fields) {
        const url = new URL(window.location.href);
        url.search = '';
        url.searchParams.set('api', 'love-letter-action');
        const body = { action, ...(fields || {}) };
        if (saved && body.code == null) body.code = saved.code;
        if (saved && body.playerToken == null) body.playerToken = saved.playerToken;
        const response = await fetch(url, {
            method: 'POST', credentials: 'same-origin', headers: {
                'Content-Type': 'application/json', 'X-CSRF-Token': CSRF
            }, body: JSON.stringify(body)
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.ok) throw new Error(data.error || `Request failed (${response.status}).`);
        return data;
    }

    function enterRoom(data, creatorSecret) {
        saved = {
            code: data.code,
            playerToken: data.playerToken,
            creatorSecret: creatorSecret || null,
            name: data.room?.seats?.find(seat => seat.index === data.room.mySeatIndex)?.name || ''
        };
        snapshot = data.room || null;
        saveSaved();
        render();
        schedulePoll();
    }

    async function refreshSnapshot() {
        if (!saved || inFlight) return;
        inFlight = true;
        const requestSequence = actionSequence;
        try {
            const data = await apiGet('love-letter-session', { code: saved.code });
            if (requestSequence === actionSequence) {
                snapshot = data;
                render();
            }
        } catch (error) {
            if (/not found/i.test(error.message) || /not joined/i.test(error.message)) {
                saved = null; snapshot = null; saveSaved();
                setHomeMessage(error.message + ' You can join again with an invite code.', true);
                render();
            } else {
                setHomeMessage(error.message, true);
            }
        } finally { inFlight = false; }
    }

    function schedulePoll() {
        if (pollTimer) window.clearInterval(pollTimer);
        pollTimer = window.setInterval(() => {
            if (!busy && saved) refreshSnapshot();
        }, 1800);
    }

    async function perform(action, fields) {
        if (busy) return;
        busy = true;
        actionSequence += 1;
        matchMessage = '';
        render();
        try {
            const response = await apiPost(action, fields);
            snapshot = response.game ? { ...snapshot, ...response } : (response.code ? { ...snapshot, ...response } : snapshot);
            if (response.ok && response.room) snapshot = response.room;
            await refreshSnapshot();
        } catch (error) {
            await refreshSnapshot();
            matchMessage = error.message;
        } finally {
            busy = false;
            render();
        }
    }

    function startRecoveryForm() {
        const form = document.getElementById('ll-recover-form');
        const code = document.getElementById('ll-recover-code');
        const secret = document.getElementById('ll-recover-secret');
        if (saved && saved.creatorSecret && code && secret) {
            code.value = saved.code;
            secret.value = saved.creatorSecret;
        }
    }

    function renderLobby() {
        const room = snapshot;
        const seats = room.seats.map(seat => `<div class="ll-lobby-seat ${seat.index === room.mySeatIndex ? 'is-mine' : ''}"><span class="ll-seat-number">SEAT ${seat.index + 1}</span><strong>${escapeHtml(seat.name)}</strong>${seat.isCreator ? '<small>SESSION CREATOR</small>' : ''}</div>`).join('');
        const filled = room.seatCount === room.capacity;
        return `<section class="ll-stage ll-lobby" aria-labelledby="ll-lobby-title">
            <div class="ll-session-code-row"><div><span class="ll-section-kicker">SESSION CODE</span><div class="ll-session-code">${escapeHtml(room.code)}</div></div><button class="ll-secondary-button" type="button" data-action="copy-code">Copy invite link</button></div>
            <h3 id="ll-lobby-title">Waiting room · ${room.seatCount} / ${room.capacity} players</h3>
            <p class="ll-stage-copy">${filled ? 'Everyone is here. The session creator can start the saved game.' : 'Share the code with the other players. Each player opens the same page on their own device and joins with this code.'}</p>
            <div class="ll-lobby-seats">${seats}${Array.from({ length: Math.max(0, room.capacity - room.seatCount) }, (_, i) => `<div class="ll-lobby-seat is-open"><span class="ll-seat-number">OPEN SEAT</span><strong>Waiting for player</strong></div>`).join('')}</div>
            <div class="ll-result-actions">${room.isCreator ? `<button class="ll-primary-button" type="button" data-action="start" ${filled ? '' : 'disabled'}>${filled ? 'Start the game' : `Waiting for ${room.capacity - room.seatCount} more player(s)`}</button>` : `<p class="ll-private-banner">Waiting for the session creator to start the game.</p><button class="ll-secondary-button" type="button" data-action="leave-lobby">Release my seat</button>`}</div>
        </section>`;
    }

    function renderCreatorRecovery() {
        if (!snapshot?.isCreator || !saved?.creatorSecret) return '';
        return `<details class="ll-private-banner ll-recovery-key"><summary>Creator recovery key — save this for later</summary><code>${escapeHtml(saved.creatorSecret)}</code><button class="ll-secondary-button" type="button" data-action="copy-key">Copy recovery key</button><small>This key restores creator access from another browser. Treat it like a password.</small></details>`;
    }

    function renderRetention() {
        if (!snapshot) return '';
        let status;
        let actionLabel = '';
        let nextValue = false;
        if (snapshot.keepIndefinitely) {
            status = 'This session is saved indefinitely.';
            actionLabel = 'Enable 30-minute auto-delete';
        } else {
            const timeout = Number(snapshot.inactivityTimeoutSeconds || 1800);
            const lastActivity = Number(snapshot.updatedAt || Math.floor(Date.now() / 1000));
            const remaining = Math.max(0, Math.ceil(lastActivity + timeout - Date.now() / 1000));
            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            status = `Auto-delete after 30 minutes without a session action · about ${minutes}m ${seconds}s remaining.`;
            actionLabel = 'Keep indefinitely';
            nextValue = true;
        }
        return `<div class="ll-retention-banner"><div><strong>${snapshot.keepIndefinitely ? 'Saved indefinitely' : '30-minute inactivity expiry'}</strong><span>${escapeHtml(status)}</span></div>${snapshot.isCreator ? `<button class="ll-secondary-button" type="button" data-action="set-retention" data-keep-indefinitely="${nextValue}" ${busy ? 'disabled' : ''}>${actionLabel}</button>` : ''}</div>`;
    }

    function renderPlayers(game) {
        return `<div class="ll-players" aria-label="Players and favor tokens">${game.players.map(player => {
            const classes = ['ll-player-chip'];
            if (player.index === game.currentIndex) classes.push('is-current');
            if (!player.inRound) classes.push('is-out');
            if (player.protected && player.inRound) classes.push('is-protected');
            if (player.index === snapshot.mySeatIndex) classes.push('is-mine');
            const stateText = !player.inRound ? 'OUT OF ROUND' : (player.protected ? 'Protected' : (player.isCurrent ? 'Current turn' : 'In the round'));
            return `<div class="${classes.join(' ')}"><div class="ll-player-name">${escapeHtml(player.name)}${player.index === snapshot.mySeatIndex ? ' · YOU' : ''}</div><div class="ll-player-score">Favor ${player.score} / ${game.targetTokens}</div><div class="ll-player-state">${stateText}</div></div>`;
        }).join('')}</div>`;
    }

    function renderMetrics(game) {
        const active = game.players.filter(player => player.inRound).length;
        return `<div class="ll-metrics"><div class="ll-metric"><b>ROUND ${game.round}</b><span>${active} still in</span></div><div class="ll-metric"><b>${game.deckCount} IN DECK</b><span>face-down cards left</span></div><div class="ll-metric"><b>${game.faceUpRemoved.length} SET ASIDE</b><span>${game.faceUpRemoved.length ? 'visible to everyone' : 'one hidden reserve'}</span></div></div>`;
    }

    function renderFaceUpRemoved(game) {
        if (!game.faceUpRemoved?.length) return '';
        return `<section class="ll-discard-panel" aria-label="Face-up cards set aside at setup"><h4>FACE-UP CARDS SET ASIDE</h4><div class="ll-discard-list">${game.faceUpRemoved.map(card => `<div class="ll-discard-item"><span>${card.value} · ${escapeHtml(cardDef(card).name)}</span><small>Visible to all players; not in the draw deck.</small></div>`).join('')}</div></section>`;
    }

    function renderHandoff(game) {
        const current = game.players[game.currentIndex];
        const ownsTurn = snapshot.mySeatIndex === game.currentIndex;
        return `<section class="ll-stage ${ownsTurn ? 'll-handoff' : ''}">
            <div class="ll-pass-icon" aria-hidden="true">✉</div>
            <div class="ll-turn-caption">${escapeHtml(current.name)}’s turn</div>
            <h3>${ownsTurn ? 'Your hand is ready' : 'Waiting for the current player'}</h3>
            <p class="ll-stage-copy">${ownsTurn ? 'Only you can see your cards. When you’re ready, reveal the draw and take your turn.' : 'Other players can see the public game state, but only this player can access their private hand or take the turn.'}</p>
            ${ownsTurn ? `<button class="ll-primary-button" type="button" data-action="ready" ${busy ? 'disabled' : ''}>Reveal my turn</button>` : ''}
        </section>`;
    }

    function renderTurn(game) {
        const ownsTurn = snapshot.mySeatIndex === game.currentIndex;
        if (!ownsTurn) return `<section class="ll-stage"><h3>${escapeHtml(game.players[game.currentIndex].name)} is choosing a card</h3><p class="ll-stage-copy">The current player’s hand is visible only on their own device.</p></section>`;
        const hand = game.privateHand || [];
        const countessMandatory = hand.some(card => card.key === 'countess') && hand.some(card => card.key === 'king' || card.key === 'prince');
        return `<section class="ll-stage"><div class="ll-turn-caption">Your private hand</div><h3>Choose one card to play</h3><p class="ll-stage-copy">You drew one card. Play one face up; keep the other. Your hand is only sent to this player’s device.</p>
            ${countessMandatory ? '<div class="ll-private-banner">You hold the King or Prince with the Countess, so the Countess must be played.</div>' : ''}
            <div class="ll-card-row">${hand.map(card => cardFace(card, 'play', countessMandatory && card.key !== 'countess')).join('')}</div>
        </section>`;
    }

    function renderEffect(game) {
        const actor = game.players[game.currentIndex];
        const ownsTurn = snapshot.mySeatIndex === game.currentIndex;
        if (game.phase === 'target') {
            if (!ownsTurn) return `<section class="ll-stage"><h3>${escapeHtml(actor.name)} is choosing a target</h3><p class="ll-stage-copy">Other players’ private choices are hidden.</p></section>`;
            const cardName = cardDef(game.pendingCard).name;
            const options = (game.validTargets || []).map(index => `<button class="ll-choice-button ${index === snapshot.mySeatIndex ? 'll-self-choice' : ''}" type="button" data-action="target" data-target-index="${index}">${escapeHtml(game.players[index].name)}${index === snapshot.mySeatIndex ? ' · YOURSELF' : ''}</button>`).join('');
            const note = game.pendingCard?.key === 'prince' && game.validTargets?.length === 1 && game.validTargets[0] === snapshot.mySeatIndex ? '<div class="ll-private-banner">All other players are protected; you must choose yourself.</div>' : '';
            return `<section class="ll-stage"><div class="ll-turn-caption">${escapeHtml(actor.name)} played ${escapeHtml(cardName)}</div><div class="ll-stage-copy">Your remaining card: ${(game.privateHand || []).map(smallCard).join('')}</div><h3>Choose an eligible player</h3>${note}<div class="ll-choice-grid">${options}</div></section>`;
        }
        if (game.phase === 'guess') {
            if (!ownsTurn) return `<section class="ll-stage"><h3>${escapeHtml(actor.name)} is making a Guard guess</h3></section>`;
            const target = game.players[game.pendingTargetIndex];
            return `<section class="ll-stage"><div class="ll-turn-caption">You played Guard</div><div class="ll-stage-copy">Your remaining card: ${(game.privateHand || []).map(smallCard).join('')}</div><h3>Guess ${escapeHtml(target.name)}’s card</h3><p class="ll-stage-copy">Name any character other than Guard.</p><div class="ll-choice-grid">${definitions.filter(def => def.key !== 'guard').map(def => `<button class="ll-choice-button ll-guess-choice" type="button" data-action="guess" data-card-key="${def.key}">${def.value} · ${def.name}</button>`).join('')}</div></section>`;
        }
        if (game.phase === 'priestReveal') {
            if (!ownsTurn) return `<section class="ll-stage"><h3>${escapeHtml(actor.name)} is using Priest</h3><p class="ll-stage-copy">The target’s hand is visible only to the current player.</p></section>`;
            const target = game.players[game.priestTargetIndex];
            return `<section class="ll-stage"><h3>Private look — ${escapeHtml(target.name)}’s hand</h3><div class="ll-private-banner">Only you should read this. Hide it before finishing your turn.</div><div class="ll-private-card">${game.priestCard ? cardFace(game.priestCard) : '<span>Card unavailable.</span>'}<div><strong>${escapeHtml(target.name)}</strong><br><span class="ll-stage-copy">This card is visible only to your seat.</span></div></div><button class="ll-primary-button" type="button" data-action="hide-priest">Hide the card</button></section>`;
        }
        if (game.phase === 'chancellorKeep') {
            if (!ownsTurn) return `<section class="ll-stage"><h3>${escapeHtml(actor.name)} is resolving Chancellor</h3><p class="ll-stage-copy">Private choices are hidden from other seats.</p></section>`;
            if (game.pendingStage === 'keep') return `<section class="ll-stage"><h3>Chancellor: keep one card</h3><p class="ll-stage-copy">Choose one. The others return face down to the bottom of the deck.</p><div class="ll-card-row">${(game.chancellorOptions || []).map(card => cardFace(card, 'chancellor-keep')).join('')}</div></section>`;
            const cards = game.chancellorReturnCards || [];
            if (cards.length !== 2) return `<section class="ll-stage"><h3>Finishing Chancellor</h3></section>`;
            return `<section class="ll-stage"><h3>Order the returned cards</h3><p class="ll-stage-copy">Choose which card goes deepest at the bottom first. Both remain face down.</p><div class="ll-choice-grid"><button class="ll-choice-button" type="button" data-action="chancellor-order" data-first-id="${escapeHtml(cards[0].id)}" data-second-id="${escapeHtml(cards[1].id)}">${escapeHtml(cardDef(cards[0]).name)} first, then ${escapeHtml(cardDef(cards[1]).name)}</button><button class="ll-choice-button" type="button" data-action="chancellor-order" data-first-id="${escapeHtml(cards[1].id)}" data-second-id="${escapeHtml(cards[0].id)}">${escapeHtml(cardDef(cards[1]).name)} first, then ${escapeHtml(cardDef(cards[0]).name)}</button></div></section>`;
        }
        return '';
    }

    function renderRoundEnd(game) {
        const result = game.result || {};
        const winners = result.winnerIndexes || [];
        const gameWinners = result.gameWinnerIndexes || [];
        const spyWinners = result.spyAwardIndexes || [];
        const winningNames = winners.map(index => game.players[index].name);
        const winnerThisRound = winners.includes(snapshot.mySeatIndex);
        const finalHands = (game.finalHands || []).map(item => `<div class="ll-discard-item"><span>${escapeHtml(game.players[item.playerIndex].name)}</span><small>Final hand: ${(item.cards || []).map(card => `${card.value} · ${escapeHtml(cardDef(card).name)}`).join(', ') || 'No card'}</small></div>`).join('');
        return `<section class="ll-stage ll-round-end"><span class="ll-section-kicker">ROUND ${game.round} COMPLETE</span><h3 class="ll-result-title">${game.gameOver ? 'The Princess has chosen' : (winners.length > 1 ? 'A shared favor' : 'Favor earned')}</h3><p class="ll-stage-copy">${result.reason === 'deck empty' ? 'The deck ran out. Highest card wins.' : 'Only one player remained in the round.'}</p><div class="ll-winner-list"><strong>${winningNames.map(escapeHtml).join(' &amp; ') || 'No round winner'}</strong> earns one favor token${winners.length > 1 ? ' each' : ''}.</div>${spyWinners.length ? `<p class="ll-stage-copy">Spy bonus: ${spyWinners.map(index => escapeHtml(game.players[index].name)).join(' &amp; ')} earns one extra favor token.</p>` : ''}<div class="ll-discard-list">${finalHands}</div>${game.gameOver ? `<div class="ll-private-banner"><strong>Game winner${gameWinners.length > 1 ? 's' : ''}:</strong> ${gameWinners.map(index => escapeHtml(game.players[index].name)).join(' &amp; ')}.</div>` : `<p class="ll-stage-copy">${winnerThisRound ? 'You may deal the next round.' : 'A round winner can deal the next round.'}</p>`}<div class="ll-result-actions">${!game.gameOver && winnerThisRound ? '<button class="ll-primary-button" type="button" data-action="next-round">Deal next round</button>' : ''}</div></section>`;
    }

    function renderDiscards(game) {
        const entries = (game.discards || []).map(entry => {
            const def = cardDef(entry.card);
            const player = game.players[entry.playerIndex];
            return `<div class="ll-discard-item"><span>${def.value} · ${escapeHtml(def.name)}</span><small>${escapeHtml(player?.name || 'Player')} · ${escapeHtml(entry.reason)}</small></div>`;
        }).join('');
        return `<section class="ll-discard-panel"><h4>PUBLIC DISCARD HISTORY</h4><div class="ll-discard-list">${entries || '<span class="ll-stage-copy">Played and revealed cards appear here.</span>'}</div></section>`;
    }

    function renderMatch() {
        if (!saved || !snapshot) {
            home.hidden = false;
            match.hidden = true;
            startRecoveryForm();
            return;
        }
        home.hidden = true;
        match.hidden = false;
        const creatorTools = snapshot.isCreator ? `<button class="ll-secondary-button" type="button" data-action="delete-session">Delete session</button>` : '';
        const leaveButton = '<button class="ll-secondary-button" type="button" data-action="leave-device">Leave on this device</button>';
        const roomHeader = `<div class="ll-match-toolbar"><div class="ll-toolbar-left"><span class="ll-round-label">SESSION ${escapeHtml(snapshot.code)}</span>${snapshot.game ? `<span class="ll-round-label">ROUND ${snapshot.game.round}</span>` : '<span class="ll-round-label">WAITING ROOM</span>'}</div><div class="ll-toolbar-right">${creatorTools}${leaveButton}</div></div>`;
        let content = '';
        if (snapshot.status === 'lobby') content = renderLobby();
        else if (snapshot.game?.phase === 'roundEnd' || snapshot.status === 'finished') content = renderRoundEnd(snapshot.game);
        else if (snapshot.game) {
            const game = snapshot.game;
            const phaseView = game.phase === 'handoff' ? renderHandoff(game) : game.phase === 'turn' ? renderTurn(game) : renderEffect(game);
            content = `${renderPlayers(game)}<div class="ll-feedback" role="status" aria-live="polite">${escapeHtml(game.lastEvent || '')}</div>${renderMetrics(game)}${renderFaceUpRemoved(game)}${phaseView}${renderDiscards(game)}`;
        }
        const secureNote = '<p class="ll-footnote">The server saves the game. Each device receives only public table state and its own player’s private cards. Do not share your private player link/token.</p>';
        match.innerHTML = `${roomHeader}${matchMessage ? `<div class="ll-feedback ll-error-message" role="alert">${escapeHtml(matchMessage)}</div>` : ''}${renderRetention()}${content}${renderCreatorRecovery()}${secureNote}`;
    }

    function render() {
        renderMatch();
    }

    async function withForm(form, callback) {
        const button = form.querySelector('button[type="submit"]');
        if (button) button.disabled = true;
        setHomeMessage('Connecting to the saved session…');
        try { await callback(new FormData(form)); }
        catch (error) { setHomeMessage(error.message, true); }
        finally { if (button) button.disabled = false; }
    }

    document.getElementById('ll-create-form').addEventListener('submit', event => {
        event.preventDefault();
        withForm(event.currentTarget, async form => {
            const result = await apiPost('create', {
                name: form.get('name'), capacity: Number(form.get('capacity')),
                keepIndefinitely: form.get('keepIndefinitely') === '1'
            });
            enterRoom(result, result.creatorSecret);
            setHomeMessage('Session created. Save the invite code and creator recovery key.');
        });
    });

    document.getElementById('ll-join-form').addEventListener('submit', event => {
        event.preventDefault();
        withForm(event.currentTarget, async form => {
            const result = await apiPost('join', { code: String(form.get('code')).trim().toUpperCase(), name: form.get('name') });
            enterRoom(result, null);
        });
    });

    document.getElementById('ll-recover-form').addEventListener('submit', event => {
        event.preventDefault();
        withForm(event.currentTarget, async form => {
            const result = await apiPost('resume', { code: String(form.get('code')).trim().toUpperCase(), creatorSecret: form.get('creatorSecret') });
            enterRoom(result, String(form.get('creatorSecret')));
            setHomeMessage('Creator access restored.');
        });
    });

    match.addEventListener('click', async event => {
        const button = event.target.closest('button[data-action]');
        if (!button || button.disabled || busy) return;
        const action = button.dataset.action;
        if (action === 'copy-code') {
            const url = new URL(window.location.href); url.search = ''; url.searchParams.set('view', 'love-letter');
            try { await navigator.clipboard.writeText(`Join my Love Letter game (${snapshot.code}): ${url.toString()}`); button.textContent = 'Invite copied'; }
            catch (error) { window.prompt('Copy this invitation:', `Join my Love Letter game (${snapshot.code}): ${url.toString()}`); }
            return;
        }
        if (action === 'copy-key') {
            try { await navigator.clipboard.writeText(saved.creatorSecret); button.textContent = 'Recovery key copied'; }
            catch (error) { window.prompt('Save your creator recovery key:', saved.creatorSecret); }
            return;
        }
        if (action === 'leave-device') {
            if (window.confirm('Remove this device’s saved player access? The session will remain on the server. You will need to rejoin or recover it to come back.')) {
                saved = null; snapshot = null; saveSaved(); home.hidden = false; match.hidden = true;
                setHomeMessage('This device has left the saved session; the session itself is still stored on the server.');
            }
            return;
        }
        if (action === 'leave-lobby') {
            if (!window.confirm('Release your seat in this waiting room? You will need a fresh invite to join again.')) return;
            busy = true;
            try {
                await apiPost('leave-lobby');
                saved = null; snapshot = null; saveSaved();
                setHomeMessage('Your waiting-room seat was released.');
                home.hidden = false; match.hidden = true;
            } catch (error) { matchMessage = error.message; }
            finally { busy = false; render(); }
            return;
        }
        if (action === 'delete-session') {
            if (!window.confirm(`Permanently delete session ${snapshot.code} for every player? This cannot be undone.`)) return;
            busy = true;
            try {
                await apiPost('delete', { code: saved.code, creatorSecret: saved.creatorSecret });
                saved = null; snapshot = null; saveSaved(); home.hidden = false; match.hidden = true;
                setHomeMessage('The session was deleted.');
            } catch (error) { setHomeMessage(error.message, true); }
            finally { busy = false; render(); }
            return;
        }
        const fields = {};
        if (action === 'play' || action === 'chancellor-keep') fields.cardId = button.dataset.cardId;
        if (action === 'target') fields.targetIndex = Number(button.dataset.targetIndex);
        if (action === 'guess') fields.cardKey = button.dataset.cardKey;
        if (action === 'chancellor-order') fields.orderedIds = [button.dataset.firstId, button.dataset.secondId];
        if (action === 'start') fields.creatorSecret = saved.creatorSecret;
        if (action === 'set-retention') {
            fields.keepIndefinitely = button.dataset.keepIndefinitely === 'true';
            fields.creatorSecret = saved.creatorSecret;
        }
        await perform(action, fields);
    });

    const codeInput = document.getElementById('ll-join-code');
    codeInput.addEventListener('input', () => { codeInput.value = codeInput.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8); });
    document.getElementById('ll-recover-code').addEventListener('input', event => { event.currentTarget.value = event.currentTarget.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8); });

    render();
    if (saved) {
        setHomeMessage('Restoring your saved session…');
        refreshSnapshot().then(() => schedulePoll());
    }
})();
