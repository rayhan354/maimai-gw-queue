(function (root, factory) {
    const engine = factory();
    if (typeof module === 'object' && module.exports) module.exports = engine;
    if (root) root.LoveLetterEngine = engine;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    const CARD_DEFS = [
        { key: 'spy', name: 'Spy', value: 0, copies: 2, emblem: 'S', effect: 'No play effect. Earn a bonus favor if yours is the only Spy played or discarded by a player still in the round.' },
        { key: 'guard', name: 'Guard', value: 1, copies: 6, emblem: 'G', effect: 'Guess a character in another player’s hand. A correct guess knocks them out.' },
        { key: 'priest', name: 'Priest', value: 2, copies: 2, emblem: 'P', effect: 'Secretly look at another player’s hand.' },
        { key: 'baron', name: 'Baron', value: 3, copies: 2, emblem: 'B', effect: 'Secretly compare hands with another player. The lower card is out.' },
        { key: 'handmaid', name: 'Handmaid', value: 4, copies: 2, emblem: 'H', effect: 'You are protected from other players’ card effects until your next turn.' },
        { key: 'prince', name: 'Prince', value: 5, copies: 2, emblem: 'P', effect: 'Choose any player, including yourself. They discard their hand and draw a new card.' },
        { key: 'chancellor', name: 'Chancellor', value: 6, copies: 2, emblem: 'C', effect: 'Draw up to two cards, keep one, and return the rest to the bottom of the deck.' },
        { key: 'king', name: 'King', value: 7, copies: 1, emblem: 'K', effect: 'Trade hands with another player.' },
        { key: 'countess', name: 'Countess', value: 8, copies: 1, emblem: 'W', effect: 'No play effect. You must play her if your other card is the King or a Prince.' },
        { key: 'princess', name: 'Princess', value: 9, copies: 1, emblem: '♥', effect: 'If you play or discard her for any reason, you are out of the round.' }
    ];
    const CARD_BY_KEY = Object.fromEntries(CARD_DEFS.map(card => [card.key, card]));
    const GUESSABLE_CARDS = CARD_DEFS.filter(card => card.key !== 'guard');
    const TARGETING_CARDS = new Set(['guard', 'priest', 'baron', 'handmaid', 'prince', 'chancellor', 'king', 'countess', 'spy', 'princess']);
    const PHASES = new Set(['handoff', 'turn', 'target', 'guess', 'priestReveal', 'chancellorKeep', 'roundEnd']);

    function favorTarget(playerCount) {
        return ({ 2: 6, 3: 5, 4: 4, 5: 3, 6: 3 })[playerCount] || 4;
    }

    function makeDeck(random) {
        const deck = [];
        CARD_DEFS.forEach(def => {
            for (let copy = 1; copy <= def.copies; copy += 1) {
                deck.push({ id: `${def.key}-${copy}`, key: def.key, value: def.value });
            }
        });
        for (let i = deck.length - 1; i > 0; i -= 1) {
            const j = Math.floor(random() * (i + 1));
            [deck[i], deck[j]] = [deck[j], deck[i]];
        }
        return deck;
    }

    function safeName(value, index) {
        const name = String(value == null ? '' : value).trim().slice(0, 18);
        return name || `Player ${index + 1}`;
    }

    function createMatch(names, starterIndex, random) {
        if (!Array.isArray(names) || names.length < 2 || names.length > 6) {
            throw new Error('Love Letter needs 2 to 6 players.');
        }
        const rng = typeof random === 'function' ? random : Math.random;
        const players = names.map((name, index) => ({ name: safeName(name, index), score: 0 }));
        return createRound(players, starterIndex, 1, rng);
    }

    function createRound(priorPlayers, starterIndex, roundNumber, random) {
        const rng = typeof random === 'function' ? random : Math.random;
        const players = priorPlayers.map((player, index) => ({
            name: safeName(player.name, index),
            score: Number.isFinite(player.score) ? player.score : 0,
            hand: [],
            inRound: true,
            protected: false,
            spyPlayed: false
        }));
        const start = Number.isInteger(starterIndex) && starterIndex >= 0 && starterIndex < players.length ? starterIndex : 0;
        const deck = makeDeck(rng);
        const reserveCard = deck.pop();
        const faceUpRemoved = players.length === 2 ? [deck.pop(), deck.pop(), deck.pop()] : [];
        players.forEach(player => player.hand.push(deck.pop()));
        return {
            version: 1,
            round: Number.isInteger(roundNumber) && roundNumber > 0 ? roundNumber : 1,
            targetTokens: favorTarget(players.length),
            players,
            deck,
            reserveCard,
            faceUpRemoved,
            discards: [],
            currentIndex: start,
            currentDrawn: false,
            phase: 'handoff',
            resumePhase: null,
            pending: null,
            lastEvent: `Round ${Number.isInteger(roundNumber) && roundNumber > 0 ? roundNumber : 1} is ready. Pass the screen to ${players[start].name}.`,
            result: null,
            gameOver: false
        };
    }

    function currentPlayer(state) {
        return state.players[state.currentIndex];
    }

    function countActive(state) {
        return state.players.reduce((count, player) => count + (player.inRound ? 1 : 0), 0);
    }

    function forcedCountess(hand) {
        return hand.some(card => card.key === 'countess') && hand.some(card => card.key === 'king' || card.key === 'prince');
    }

    function recordDiscard(state, playerIndex, card, reason) {
        if (!card) return;
        state.discards.push({ playerIndex, card, reason });
        if (card.key === 'spy') state.players[playerIndex].spyPlayed = true;
    }

    function eliminatePlayer(state, playerIndex, reason) {
        const player = state.players[playerIndex];
        if (!player || !player.inRound) return;
        player.hand.forEach(card => recordDiscard(state, playerIndex, card, reason || 'revealed when eliminated'));
        player.hand = [];
        player.inRound = false;
        player.protected = false;
    }

    function getValidTargets(state, cardKey) {
        const actorIndex = state.currentIndex;
        const others = state.players
            .map((player, index) => ({ player, index }))
            .filter(item => item.player.inRound && item.index !== actorIndex && !item.player.protected)
            .map(item => item.index);
        if (cardKey === 'prince') return [actorIndex, ...others];
        return others;
    }

    function finishRound(state, reason) {
        if (state.phase === 'roundEnd') return state;
        const active = state.players.map((player, index) => ({ player, index })).filter(item => item.player.inRound);
        let winnerIndexes = [];
        if (active.length === 1) {
            winnerIndexes = [active[0].index];
        } else if (active.length > 1) {
            const highest = Math.max(...active.map(item => item.player.hand.length ? item.player.hand[0].value : -1));
            winnerIndexes = active.filter(item => (item.player.hand.length ? item.player.hand[0].value : -1) === highest).map(item => item.index);
        }

        const spyCandidates = active.filter(item => item.player.spyPlayed).map(item => item.index);
        const spyAwardIndexes = spyCandidates.length === 1 ? [spyCandidates[0]] : [];
        winnerIndexes.forEach(index => { state.players[index].score += 1; });
        spyAwardIndexes.forEach(index => { state.players[index].score += 1; });
        const gameWinnerIndexes = state.players.map((player, index) => ({ player, index }))
            .filter(item => item.player.score >= state.targetTokens).map(item => item.index);
        state.gameOver = gameWinnerIndexes.length > 0;
        state.result = {
            reason,
            winnerIndexes,
            spyAwardIndexes,
            gameWinnerIndexes,
            activeIndexes: active.map(item => item.index),
            targetTokens: state.targetTokens
        };
        state.pending = null;
        state.currentDrawn = false;
        state.phase = 'roundEnd';
        if (state.gameOver) {
            state.lastEvent = gameWinnerIndexes.length === 1
                ? `${state.players[gameWinnerIndexes[0]].name} has earned enough favor to win the game.`
                : `${gameWinnerIndexes.map(index => state.players[index].name).join(' and ')} have reached the victory target together.`;
        } else if (winnerIndexes.length === 1) {
            state.lastEvent = `${state.players[winnerIndexes[0]].name} wins round ${state.round}.`;
        } else if (winnerIndexes.length > 1) {
            state.lastEvent = `The round is tied. ${winnerIndexes.map(index => state.players[index].name).join(' and ')} share the favor.`;
        } else {
            state.lastEvent = 'The round has ended.';
        }
        return state;
    }

    function completeTurn(state) {
        state.pending = null;
        if (countActive(state) <= 1) return finishRound(state, 'last player standing');
        if (state.deck.length === 0) return finishRound(state, 'deck empty');
        const total = state.players.length;
        let next = state.currentIndex;
        for (let step = 0; step < total; step += 1) {
            next = (next + 1) % total;
            if (state.players[next].inRound) break;
        }
        state.currentIndex = next;
        state.currentDrawn = false;
        state.phase = 'handoff';
        state.lastEvent = `Pass the screen to ${state.players[next].name}.`;
        return state;
    }

    function startTurn(state) {
        if (state.phase !== 'handoff') throw new Error('This is not a handoff.');
        if (state.resumePhase) {
            state.phase = state.resumePhase;
            state.resumePhase = null;
            return state;
        }
        const player = currentPlayer(state);
        if (!player || !player.inRound) throw new Error('The next player is no longer in the round.');
        player.protected = false;
        const drawn = state.deck.pop();
        if (drawn) player.hand.push(drawn);
        state.currentDrawn = true;
        state.phase = 'turn';
        state.lastEvent = drawn ? `${player.name} draws a card. Choose one card to play.` : `${player.name} has no card to draw.`;
        return state;
    }

    function playCard(state, cardId) {
        if (state.phase !== 'turn' || !state.currentDrawn) throw new Error('Start the player’s turn before playing a card.');
        const player = currentPlayer(state);
        const cardIndex = player.hand.findIndex(card => card.id === cardId);
        if (cardIndex < 0) throw new Error('That card is not in this hand.');
        const selected = player.hand[cardIndex];
        if (forcedCountess(player.hand) && selected.key !== 'countess') {
            throw new Error('The Countess must be played when held with the King or a Prince.');
        }
        const card = player.hand.splice(cardIndex, 1)[0];
        recordDiscard(state, state.currentIndex, card, 'played');
        state.pending = { card, actorIndex: state.currentIndex };

        if (card.key === 'princess') {
            eliminatePlayer(state, state.currentIndex, 'revealed when eliminated');
            state.lastEvent = `${player.name} played the Princess and is out of the round.`;
            return completeTurn(state);
        }
        if (card.key === 'chancellor') {
            if (state.deck.length === 0) {
                state.lastEvent = 'The deck is empty; the Chancellor has no effect.';
                return completeTurn(state);
            }
            const options = player.hand.slice();
            options.push(state.deck.pop());
            if (state.deck.length > 0) options.push(state.deck.pop());
            state.pending.options = options;
            state.pending.stage = 'keep';
            state.phase = 'chancellorKeep';
            state.lastEvent = `${player.name} uses the Chancellor. Keep one card from the choices.`;
            return state;
        }
        if (card.key === 'handmaid') {
            player.protected = true;
            state.lastEvent = `${player.name} is protected until the start of their next turn.`;
            return completeTurn(state);
        }
        if (card.key === 'spy' || card.key === 'countess') {
            state.lastEvent = card.key === 'spy' ? `${player.name} played the Spy.` : `${player.name} played the Countess.`;
            return completeTurn(state);
        }
        if (TARGETING_CARDS.has(card.key)) {
            const validTargets = getValidTargets(state, card.key);
            if (validTargets.length === 0) {
                state.lastEvent = `No eligible player can be chosen for ${card.name}; its effect is skipped.`;
                return completeTurn(state);
            }
            state.phase = 'target';
            state.lastEvent = `${player.name} played ${card.name}. Choose a target.`;
            return state;
        }
        return completeTurn(state);
    }

    function chooseTarget(state, targetIndex) {
        if (state.phase !== 'target' || !state.pending) throw new Error('No target is being selected.');
        const cardKey = state.pending.card.key;
        const valid = getValidTargets(state, cardKey);
        if (!valid.includes(targetIndex)) throw new Error('That player cannot be chosen for this effect.');
        const actorIndex = state.currentIndex;
        const actor = state.players[actorIndex];
        const target = state.players[targetIndex];

        if (cardKey === 'guard') {
            state.pending.targetIndex = targetIndex;
            state.phase = 'guess';
            state.lastEvent = `${actor.name} chose ${target.name}. Guess the character in their hand.`;
            return state;
        }
        if (cardKey === 'priest') {
            const targetCard = target.hand[0];
            state.pending.targetIndex = targetIndex;
            state.pending.revealedCard = targetCard;
            state.phase = 'priestReveal';
            state.lastEvent = `${actor.name} privately looks at ${target.name}’s hand.`;
            return state;
        }
        if (cardKey === 'baron') {
            const actorCard = actor.hand[0];
            const targetCard = target.hand[0];
            if (actorCard.value < targetCard.value) {
                eliminatePlayer(state, actorIndex, 'revealed when eliminated');
                state.lastEvent = `${actor.name} has the lower card and is out of the round.`;
            } else if (targetCard.value < actorCard.value) {
                eliminatePlayer(state, targetIndex, 'revealed when eliminated');
                state.lastEvent = `${target.name} has the lower card and is out of the round.`;
            } else {
                state.lastEvent = `The hands tie. Neither ${actor.name} nor ${target.name} is eliminated.`;
            }
            return completeTurn(state);
        }
        if (cardKey === 'king') {
            [actor.hand, target.hand] = [target.hand, actor.hand];
            state.lastEvent = `${actor.name} and ${target.name} trade hands.`;
            return completeTurn(state);
        }
        if (cardKey === 'prince') {
            const discarded = target.hand[0];
            recordDiscard(state, targetIndex, discarded, 'discarded by Prince');
            target.hand = [];
            if (discarded && discarded.key === 'princess') {
                eliminatePlayer(state, targetIndex, 'revealed when eliminated');
                state.lastEvent = `${target.name} discarded the Princess and is out of the round.`;
                return completeTurn(state);
            }
            const replacement = state.deck.pop() || state.reserveCard;
            if (replacement === state.reserveCard) state.reserveCard = null;
            if (replacement) target.hand = [replacement];
            state.lastEvent = `${target.name} discards their hand and draws a new card.`;
            return completeTurn(state);
        }
        throw new Error('This card effect cannot select another player.');
    }

    function resolveGuardGuess(state, guessedKey) {
        if (state.phase !== 'guess' || !state.pending || !GUESSABLE_CARDS.some(card => card.key === guessedKey)) {
            throw new Error('Choose a valid character for the Guard guess.');
        }
        const targetIndex = state.pending.targetIndex;
        const target = state.players[targetIndex];
        if (!target || !target.inRound) throw new Error('The chosen player is no longer in the round.');
        if (target.hand[0] && target.hand[0].key === guessedKey) {
            eliminatePlayer(state, targetIndex, 'revealed when eliminated');
            state.lastEvent = `Correct guess. ${target.name} is out of the round.`;
        } else {
            state.lastEvent = `The guess was wrong. ${target.name} stays in the round.`;
        }
        return completeTurn(state);
    }

    function dismissPriestReveal(state) {
        if (state.phase !== 'priestReveal' || !state.pending) throw new Error('There is no Priest reveal to hide.');
        const target = state.players[state.pending.targetIndex];
        state.lastEvent = `${target ? target.name : 'The player'}’s hand is hidden. Pass the screen to the next player.`;
        return completeTurn(state);
    }

    function chooseChancellorKeep(state, cardId) {
        if (state.phase !== 'chancellorKeep' || !state.pending || state.pending.stage !== 'keep') throw new Error('Choose a card for the Chancellor first.');
        const options = state.pending.options;
        const keep = options.find(card => card.id === cardId);
        if (!keep) throw new Error('That card is not one of the Chancellor choices.');
        const returns = options.filter(card => card.id !== cardId);
        state.pending.keepCard = keep;
        state.pending.returnCards = returns;
        if (returns.length === 2) {
            state.pending.stage = 'order';
            state.lastEvent = 'Choose the order for the two cards returned to the bottom of the deck.';
            return state;
        }
        return finishChancellor(state, returns);
    }

    function orderChancellorReturns(state, orderedIds) {
        if (state.phase !== 'chancellorKeep' || !state.pending || state.pending.stage !== 'order') throw new Error('There are no Chancellor cards to order.');
        const returns = state.pending.returnCards;
        if (!Array.isArray(orderedIds) || orderedIds.length !== returns.length || new Set(orderedIds).size !== returns.length) {
            throw new Error('Choose each returned card exactly once.');
        }
        const ordered = orderedIds.map(id => returns.find(card => card.id === id));
        if (ordered.some(card => !card)) throw new Error('That return order is invalid.');
        return finishChancellor(state, ordered);
    }

    function finishChancellor(state, returnedCards) {
        const player = currentPlayer(state);
        player.hand = [state.pending.keepCard];
        state.deck.unshift(...returnedCards);
        state.lastEvent = `${player.name} keeps one card and returns the others to the bottom of the deck.`;
        state.pending = null;
        return completeTurn(state);
    }

    function nextRound(state, random) {
        if (state.phase !== 'roundEnd' || state.gameOver || !state.result) throw new Error('The game is not ready for another round.');
        const winners = state.result.winnerIndexes;
        if (winners.length === 0) throw new Error('No round winner was recorded.');
        let starter = winners[0];
        if (winners.length > 1) {
            const rng = typeof random === 'function' ? random : Math.random;
            starter = winners[Math.floor(rng() * winners.length)];
        }
        return createRound(state.players, starter, state.round + 1, random);
    }

    function isValidSavedState(state) {
        return !!state && state.version === 1 && Array.isArray(state.players) && state.players.length >= 2 && state.players.length <= 6 &&
            Number.isInteger(state.currentIndex) && state.currentIndex >= 0 && state.currentIndex < state.players.length && PHASES.has(state.phase) &&
            Array.isArray(state.deck) && Array.isArray(state.discards) && state.players.every(player => Array.isArray(player.hand));
    }

    return {
        CARD_DEFS,
        GUESSABLE_CARDS,
        favorTarget,
        makeDeck,
        createMatch,
        startTurn,
        playCard,
        getValidTargets,
        forcedCountess,
        chooseTarget,
        resolveGuardGuess,
        dismissPriestReveal,
        chooseChancellorKeep,
        orderChancellorReturns,
        nextRound,
        isValidSavedState
    };
});
