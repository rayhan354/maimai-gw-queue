(function () {
    'use strict';

    const Engine = window.LoveLetterEngine;
    if (!Engine) return;

    const STORAGE_KEY = 'maimai-gw-queue-love-letter-v1';
    const app = document.getElementById('love-letter-app');
    if (!app) return;

    const setup = document.getElementById('ll-setup');
    const match = document.getElementById('ll-match');
    const playerCount = document.getElementById('ll-player-count');
    const playerFields = document.getElementById('ll-player-fields');
    const firstPlayer = document.getElementById('ll-first-player');
    const startButton = document.getElementById('ll-start-game');
    let state = loadSavedGame();

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[character]);
    }

    function loadSavedGame() {
        try {
            const saved = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || 'null');
            if (!Engine.isValidSavedState(saved)) return null;
            if (saved.phase !== 'handoff' && saved.phase !== 'roundEnd') {
                saved.resumePhase = saved.phase;
                saved.phase = 'handoff';
            }
            return saved;
        } catch (error) {
            return null;
        }
    }

    function persist() {
        try {
            if (state) window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
            else window.localStorage.removeItem(STORAGE_KEY);
        } catch (error) {
            // The match remains playable for this visit when browser storage is unavailable.
        }
    }

    function cardDef(cardOrKey) {
        const key = typeof cardOrKey === 'string' ? cardOrKey : cardOrKey.key;
        return Engine.CARD_DEFS.find(def => def.key === key) || Engine.CARD_DEFS[0];
    }

    function cardFace(card, options) {
        const def = cardDef(card);
        const isButton = options && options.button;
        const disabled = options && options.disabled;
        const extraClass = options && options.extraClass ? ` ${options.extraClass}` : '';
        const inner = `<span class="ll-card-face tone-${def.key}">
            <span class="ll-card-topline"><span>${def.value} · ${def.name}</span><span class="ll-card-number">${def.value}</span></span>
            <span class="ll-card-emblem" aria-hidden="true">${escapeHtml(def.emblem)}</span>
            <span><span class="ll-card-title">${escapeHtml(def.name)}</span><span class="ll-card-copy">${escapeHtml(def.effect)}</span></span>
        </span>`;
        if (!isButton) return `<div class="ll-card-display${extraClass}" aria-label="${escapeHtml(def.value + ' ' + def.name)}">${inner}</div>`;
        return `<button class="ll-card-button${extraClass}" type="button" data-action="${escapeHtml(options.action || 'play')}" data-card-id="${escapeHtml(card.id)}" ${disabled ? 'disabled aria-disabled="true"' : ''} aria-label="${escapeHtml(def.value + ' ' + def.name + (disabled ? ', must play the Countess' : ''))}">${inner}${disabled ? '<span class="ll-card-disabled-note">Countess must be played</span>' : ''}</button>`;
    }

    function smallCard(card) {
        const def = cardDef(card);
        return `<span class="ll-card-mini tone-${def.key}"><b>${def.value}</b> ${escapeHtml(def.name)}</span>`;
    }

    function setupNames() {
        return Array.from(playerFields.querySelectorAll('input[data-player-name]')).map((input, index) => input.value.trim() || `Player ${index + 1}`);
    }

    function updateStarterOptions(selectedIndex) {
        const names = setupNames();
        const selected = Number.isInteger(selectedIndex) ? selectedIndex : Number(firstPlayer.value || 0);
        firstPlayer.innerHTML = names.map((name, index) => `<option value="${index}" ${index === selected ? 'selected' : ''}>${escapeHtml(name)}</option>`).join('');
    }

    function renderNameFields() {
        const previousNames = setupNames();
        const count = Number(playerCount.value);
        playerFields.innerHTML = Array.from({ length: count }, (_, index) => {
            const name = previousNames[index] || `Player ${index + 1}`;
            return `<label class="ll-field" for="ll-player-name-${index + 1}"><span>Player ${index + 1}</span><input id="ll-player-name-${index + 1}" type="text" data-player-name="${index}" maxlength="18" autocomplete="off" value="${escapeHtml(name)}" aria-label="Player ${index + 1} name"></label>`;
        }).join('');
        updateStarterOptions(Math.min(Number(firstPlayer.value || 0), count - 1));
    }

    function playerChip(player, index) {
        const isCurrent = index === state.currentIndex && state.phase !== 'roundEnd';
        const classes = ['ll-player-chip'];
        if (isCurrent) classes.push('is-current');
        if (!player.inRound) classes.push('is-out');
        if (player.protected && player.inRound) classes.push('is-protected');
        const tag = !player.inRound ? 'OUT OF ROUND' : (player.protected ? 'Protected' : (isCurrent ? 'Current player' : 'In the round'));
        return `<div class="${classes.join(' ')}">
            <div class="ll-player-name">${escapeHtml(player.name)}</div>
            <div class="ll-player-score">Favor ${player.score} / ${state.targetTokens}</div>
            <div class="ll-player-state">${tag}</div>
        </div>`;
    }

    function renderMetrics() {
        const active = state.players.filter(player => player.inRound).length;
        const removedCount = state.faceUpRemoved.length;
        return `<div class="ll-metrics" aria-label="Round status">
            <div class="ll-metric"><b>ROUND ${state.round}</b><span>${active} still in</span></div>
            <div class="ll-metric"><b>${state.deck.length} IN DECK</b><span>face-down cards left</span></div>
            <div class="ll-metric"><b>${removedCount} SET ASIDE</b><span>${removedCount ? 'visible to everyone' : 'one hidden reserve'}</span></div>
        </div>`;
    }

    function renderHandReminder() {
        const player = state.players[state.currentIndex];
        if (!player || !player.hand.length) return '';
        return `<div class="ll-stage-copy">Your remaining hand: ${player.hand.map(smallCard).join('')}</div>`;
    }

    function renderHandoff() {
        const player = state.players[state.currentIndex];
        const isResume = !!state.resumePhase;
        return `<section class="ll-stage ll-handoff" aria-labelledby="ll-handoff-title">
            <div class="ll-pass-icon" aria-hidden="true">✉</div>
            <div class="ll-turn-caption">Pass the device to <strong>${escapeHtml(player.name)}</strong></div>
            <h3 id="ll-handoff-title">${isResume ? 'Private turn ready' : 'Your turn is next'}</h3>
            <p class="ll-stage-copy">Keep your hand hidden from the other suitors. When only this player is looking, reveal the turn.</p>
            <button class="ll-primary-button" type="button" data-action="ready">${isResume ? 'Reveal this player’s turn' : 'I’m ready — draw a card'}</button>
        </section>`;
    }

    function renderTurn() {
        const player = state.players[state.currentIndex];
        const mustPlayCountess = Engine.forcedCountess(player.hand);
        const forcedCard = mustPlayCountess ? player.hand.find(card => card.key === 'countess') : null;
        const cards = player.hand.map(card => cardFace(card, {
            button: true,
            action: 'play',
            disabled: !!(forcedCard && card.key !== 'countess')
        })).join('');
        return `<section class="ll-stage" aria-labelledby="ll-turn-title">
            <div class="ll-turn-caption">${escapeHtml(player.name)} is carrying the letter</div>
            <h3 id="ll-turn-title">Choose one card to play</h3>
            <p class="ll-stage-copy">You drew one card. Play one of these two face up, resolve its effect, and keep the other. Your played card will be visible to everyone.</p>
            ${mustPlayCountess ? '<div class="ll-private-banner">You hold the King or a Prince with the Countess, so the Countess is mandatory. You do not have to reveal the other card.</div>' : ''}
            <div class="ll-card-row">${cards}</div>
        </section>`;
    }

    function renderTargetChoice() {
        const pending = state.pending;
        const actor = state.players[state.currentIndex];
        const card = pending.card;
        const targetIndexes = Engine.getValidTargets(state, card.key);
        const isPrince = card.key === 'prince';
        const instructions = {
            guard: 'Choose another unprotected player to name a character in their hand.',
            priest: 'Choose another unprotected player. Their hand will be shown privately to you.',
            baron: 'Choose another unprotected player for a secret hand comparison.',
            king: 'Choose another unprotected player to trade hands with.',
            prince: 'Choose any unprotected player, including yourself, to discard their hand and draw a replacement.'
        }[card.key] || 'Choose an eligible player.';
        return `<section class="ll-stage" aria-labelledby="ll-target-title">
            <div class="ll-turn-caption">${escapeHtml(actor.name)} played ${escapeHtml(cardDef(card).name)}</div>
            ${renderHandReminder()}
            <h3 id="ll-target-title">Choose a player</h3>
            <p class="ll-stage-copy">${instructions}</p>
            ${isPrince && targetIndexes.length === 1 && targetIndexes[0] === state.currentIndex ? '<div class="ll-private-banner">All other players are protected, so you must choose yourself.</div>' : ''}
            <div class="ll-choice-grid">${targetIndexes.map(index => {
                const player = state.players[index];
                const self = index === state.currentIndex;
                return `<button class="ll-choice-button ${self ? 'll-self-choice' : ''}" type="button" data-action="target" data-target-index="${index}">${escapeHtml(player.name)}${self ? ' · YOURSELF' : ''}</button>`;
            }).join('')}</div>
        </section>`;
    }

    function renderGuardGuess() {
        const target = state.players[state.pending.targetIndex];
        const actor = state.players[state.currentIndex];
        return `<section class="ll-stage" aria-labelledby="ll-guess-title">
            <div class="ll-turn-caption">${escapeHtml(actor.name)} played Guard</div>
            ${renderHandReminder()}
            <h3 id="ll-guess-title">Guess ${escapeHtml(target.name)}’s card</h3>
            <p class="ll-stage-copy">Name a character other than Guard. If you are right, that player is out of the round.</p>
            <div class="ll-choice-grid">${Engine.GUESSABLE_CARDS.map(def => `<button class="ll-choice-button ll-guess-choice" type="button" data-action="guess" data-card-key="${def.key}">${def.value} · ${escapeHtml(def.name)}</button>`).join('')}</div>
        </section>`;
    }

    function renderPriestReveal() {
        const target = state.players[state.pending.targetIndex];
        const actor = state.players[state.currentIndex];
        const card = state.pending.revealedCard;
        return `<section class="ll-stage" aria-labelledby="ll-priest-title">
            <div class="ll-turn-caption">${escapeHtml(actor.name)} played Priest</div>
            <h3 id="ll-priest-title">Private look — ${escapeHtml(target.name)}’s hand</h3>
            <div class="ll-private-banner">Only the current player should read this card. Hide it before passing the device.</div>
            <div class="ll-private-card">${cardFace(card)}<div><strong>${escapeHtml(target.name)}</strong><br><span class="ll-stage-copy">This is the card in their hand right now.</span></div></div>
            <button class="ll-primary-button" type="button" data-action="hide-priest">Hide the card and finish turn</button>
        </section>`;
    }

    function renderChancellor() {
        const pending = state.pending;
        const player = state.players[state.currentIndex];
        if (pending.stage === 'keep') {
            return `<section class="ll-stage" aria-labelledby="ll-chancellor-title">
                <div class="ll-turn-caption">${escapeHtml(player.name)} played Chancellor</div>
                <h3 id="ll-chancellor-title">Keep one card</h3>
                <p class="ll-stage-copy">Choose one card to keep. The other ${pending.options.length - 1} card${pending.options.length === 2 ? '' : 's'} will go face down to the bottom of the deck.</p>
                <div class="ll-card-row">${pending.options.map(card => cardFace(card, { button: true, action: 'chancellor-keep' })).join('')}</div>
            </section>`;
        }
        const cards = pending.returnCards;
        const first = cards[0];
        const second = cards[1];
        return `<section class="ll-stage" aria-labelledby="ll-chancellor-order-title">
            <div class="ll-turn-caption">${escapeHtml(player.name)} played Chancellor</div>
            <h3 id="ll-chancellor-order-title">Order the returned cards</h3>
            <p class="ll-stage-copy">Choose which card goes deepest at the bottom first. Both cards stay face down.</p>
            <div class="ll-choice-grid">
                <button class="ll-choice-button" type="button" data-action="chancellor-order" data-first-id="${escapeHtml(first.id)}" data-second-id="${escapeHtml(second.id)}">${escapeHtml(cardDef(first).name)} first, then ${escapeHtml(cardDef(second).name)}</button>
                <button class="ll-choice-button" type="button" data-action="chancellor-order" data-first-id="${escapeHtml(second.id)}" data-second-id="${escapeHtml(first.id)}">${escapeHtml(cardDef(second).name)} first, then ${escapeHtml(cardDef(first).name)}</button>
            </div>
        </section>`;
    }

    function renderResult() {
        const result = state.result;
        const winnerNames = result.winnerIndexes.map(index => state.players[index].name);
        const gameWinnerNames = result.gameWinnerIndexes.map(index => state.players[index].name);
        const spyNames = result.spyAwardIndexes.map(index => state.players[index].name);
        const activeHands = result.activeIndexes.map(index => {
            const player = state.players[index];
            return `<div class="ll-discard-item"><span>${escapeHtml(player.name)}</span><small>Final hand: ${player.hand.map(card => `${card.value} · ${escapeHtml(cardDef(card).name)}`).join(', ') || 'No card'}</small></div>`;
        }).join('');
        const outcome = result.reason === 'deck empty' ? 'The deck ran out. Highest card wins.' : 'Only one player remained in the round.';
        const nextStarter = winnerNames.length === 1 ? `${escapeHtml(winnerNames[0])} starts the next round.` : 'A tied winner will be chosen at random to start the next round.';
        return `<section class="ll-stage ll-round-end" aria-labelledby="ll-result-title">
            <span class="ll-section-kicker">ROUND ${state.round} COMPLETE</span>
            <h3 id="ll-result-title" class="ll-result-title">${state.gameOver ? 'The Princess has chosen' : (winnerNames.length > 1 ? 'A shared favor' : 'Favor earned')}</h3>
            <p class="ll-stage-copy">${outcome}</p>
            <div class="ll-winner-list"><strong>${winnerNames.length ? winnerNames.map(escapeHtml).join(' &amp; ') : 'No round winner'}</strong>${winnerNames.length ? ' earns one favor token' + (winnerNames.length > 1 ? ' each' : '') + '.' : ''}</div>
            ${spyNames.length ? `<p class="ll-stage-copy">Spy bonus: ${spyNames.map(escapeHtml).join(' &amp; ')} earns one additional favor token.</p>` : ''}
            ${activeHands ? `<div class="ll-discard-list" aria-label="Revealed final hands">${activeHands}</div>` : ''}
            ${state.gameOver ? `<div class="ll-private-banner"><strong>Game winner${gameWinnerNames.length > 1 ? 's' : ''}:</strong> ${gameWinnerNames.map(escapeHtml).join(' &amp; ')}.</div>` : `<p class="ll-stage-copy">${nextStarter}</p>`}
            <div class="ll-result-actions">${state.gameOver ? '' : '<button class="ll-primary-button" type="button" data-action="next-round">Deal the next round</button>'}<button class="ll-secondary-button" type="button" data-action="new-game">Start a new game</button></div>
        </section>`;
    }

    function renderDiscardPile() {
        const entries = state.discards.map(entry => {
            const def = cardDef(entry.card);
            const player = state.players[entry.playerIndex];
            return `<div class="ll-discard-item" style="--card-accent:${def.key === 'princess' ? 'var(--pink)' : (def.key === 'spy' ? 'var(--cyan)' : 'var(--amber)')}"><span>${def.value} · ${escapeHtml(def.name)}</span><small>${escapeHtml(player ? player.name : 'Player')} · ${escapeHtml(entry.reason)}</small></div>`;
        }).join('');
        return `<section class="ll-discard-panel" aria-label="Public discard history"><h4>PUBLIC DISCARD HISTORY</h4><div class="ll-discard-list">${entries || '<span class="ll-stage-copy">Played and revealed cards will appear here.</span>'}</div></section>`;
    }

    function renderMatch() {
        if (!state) {
            setup.hidden = false;
            match.hidden = true;
            return;
        }
        setup.hidden = true;
        match.hidden = false;
        const phaseContent = {
            handoff: renderHandoff,
            turn: renderTurn,
            target: renderTargetChoice,
            guess: renderGuardGuess,
            priestReveal: renderPriestReveal,
            chancellorKeep: renderChancellor,
            roundEnd: renderResult
        }[state.phase];
        const current = state.players[state.currentIndex];
        match.innerHTML = `<div class="ll-match-toolbar">
                <div class="ll-toolbar-left"><span class="ll-round-label">ROUND ${state.round} · ${state.players.length} PLAYERS</span></div>
                <div class="ll-toolbar-right"><button class="ll-secondary-button" type="button" data-action="new-game">New game</button></div>
            </div>
            <div class="ll-players" aria-label="Players and favor tokens">${state.players.map(playerChip).join('')}</div>
            <div class="ll-feedback" role="status">${escapeHtml(state.lastEvent || `${current.name} is up next.`)}</div>
            ${renderMetrics()}
            ${phaseContent ? phaseContent() : ''}
            ${renderDiscardPile()}
            <p class="ll-footnote">Cards in your hand are shown only during your private turn. Favor tokens are saved on this device between visits.</p>`;
        const live = app.querySelector('[role="status"]');
        if (live) live.setAttribute('aria-live', 'polite');
    }

    function render() {
        persist();
        renderMatch();
    }

    function showSetup() {
        state = null;
        persist();
        setup.hidden = false;
        match.hidden = true;
        renderNameFields();
    }

    function confirmNewGame() {
        if (window.confirm('Start a new Love Letter game? The current game and favor tokens saved on this device will be cleared.')) showSetup();
    }

    playerCount.addEventListener('change', renderNameFields);
    playerFields.addEventListener('input', () => updateStarterOptions(Number(firstPlayer.value || 0)));
    firstPlayer.addEventListener('focus', () => updateStarterOptions(Number(firstPlayer.value || 0)));
    startButton.addEventListener('click', () => {
        const names = setupNames();
        try {
            state = Engine.createMatch(names, Number(firstPlayer.value));
            render();
        } catch (error) {
            window.alert(error.message);
        }
    });

    match.addEventListener('click', event => {
        const button = event.target.closest('button[data-action]');
        if (!button || button.disabled || !state) return;
        const action = button.dataset.action;
        try {
            switch (action) {
                case 'ready':
                    Engine.startTurn(state);
                    break;
                case 'play':
                    Engine.playCard(state, button.dataset.cardId);
                    break;
                case 'target':
                    Engine.chooseTarget(state, Number(button.dataset.targetIndex));
                    break;
                case 'guess':
                    Engine.resolveGuardGuess(state, button.dataset.cardKey);
                    break;
                case 'hide-priest':
                    Engine.dismissPriestReveal(state);
                    break;
                case 'chancellor-keep':
                    Engine.chooseChancellorKeep(state, button.dataset.cardId);
                    break;
                case 'chancellor-order':
                    Engine.orderChancellorReturns(state, [button.dataset.firstId, button.dataset.secondId]);
                    break;
                case 'next-round':
                    state = Engine.nextRound(state);
                    break;
                case 'new-game':
                    confirmNewGame();
                    return;
                default:
                    return;
            }
            render();
        } catch (error) {
            state.lastEvent = error.message;
            render();
        }
    });

    renderNameFields();
    render();
})();
