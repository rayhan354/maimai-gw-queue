'use strict';

const assert = require('node:assert/strict');
const Engine = require('../love-letter-engine.js');

const deterministic = () => 0.42;
const card = (key, id = key) => ({ id, key, value: Engine.CARD_DEFS.find(def => def.key === key).value });

function testDeckAndSetup() {
    const deck = Engine.makeDeck(deterministic);
    assert.equal(deck.length, 21, 'the current edition has 21 cards');
    for (const def of Engine.CARD_DEFS) {
        assert.equal(deck.filter(item => item.key === def.key).length, def.copies, `${def.name} copy count`);
    }
    const two = Engine.createMatch(['A', 'B'], 0, deterministic);
    assert.equal(two.players.length, 2);
    assert.equal(two.faceUpRemoved.length, 3);
    assert.equal(two.deck.length, 15);
    assert.equal(two.targetTokens, 6);
    const six = Engine.createMatch(['A', 'B', 'C', 'D', 'E', 'F'], 4, deterministic);
    assert.equal(six.players.length, 6);
    assert.equal(six.deck.length, 14);
    assert.equal(six.currentIndex, 4);
    assert.equal(six.targetTokens, 3);
    assert.deepEqual([3, 4, 5, 6].map(Engine.favorTarget), [5, 4, 3, 3]);
}

function testCountessMandatoryRule() {
    const state = Engine.createMatch(['A', 'B'], 0, deterministic);
    Engine.startTurn(state);
    state.players[0].hand = [card('countess'), card('king')];
    assert.equal(Engine.forcedCountess(state.players[0].hand), true);
    assert.throws(() => Engine.playCard(state, 'king'), /Countess must be played/);
    Engine.playCard(state, 'countess');
    assert.equal(state.phase, 'handoff');
    assert.equal(state.discards.at(-1).card.key, 'countess');
}

function testProtectedTargetsAndPrinceSelfRule() {
    const state = Engine.createMatch(['A', 'B', 'C'], 0, deterministic);
    state.players[1].protected = true;
    state.players[2].protected = true;
    assert.deepEqual(Engine.getValidTargets(state, 'guard'), []);
    assert.deepEqual(Engine.getValidTargets(state, 'prince'), [0]);
    state.players[2].protected = false;
    assert.deepEqual(Engine.getValidTargets(state, 'prince'), [0, 2]);
}

function testPrinceDiscardingPrincessEliminatesWithoutDraw() {
    const state = Engine.createMatch(['A', 'B', 'C'], 0, deterministic);
    Engine.startTurn(state);
    state.players[0].hand = [card('prince'), card('guard')];
    state.players[1].hand = [card('princess')];
    const deckBefore = state.deck.length;
    Engine.playCard(state, 'prince');
    Engine.chooseTarget(state, 1);
    assert.equal(state.players[1].inRound, false);
    assert.equal(state.players[1].hand.length, 0);
    assert.equal(state.deck.length, deckBefore);
    assert.equal(state.discards.some(entry => entry.card.key === 'princess' && entry.reason === 'discarded by Prince'), true);
}

function testGuardGuessAndChancellorReturnOrder() {
    const guardState = Engine.createMatch(['A', 'B', 'C'], 0, deterministic);
    Engine.startTurn(guardState);
    guardState.players[0].hand = [card('guard'), card('priest')];
    guardState.players[1].hand = [card('chancellor')];
    Engine.playCard(guardState, 'guard');
    Engine.chooseTarget(guardState, 1);
    Engine.resolveGuardGuess(guardState, 'chancellor');
    assert.equal(guardState.players[1].inRound, false);

    const chancellorState = Engine.createMatch(['A', 'B', 'C'], 0, deterministic);
    Engine.startTurn(chancellorState);
    chancellorState.players[0].hand = [card('chancellor'), card('handmaid')];
    chancellorState.deck = [card('king', 'king-bottom'), card('spy', 'spy-bottom')];
    Engine.playCard(chancellorState, 'chancellor');
    assert.equal(chancellorState.pending.options.length, 3);
    const keep = chancellorState.pending.options[0];
    const returnCards = chancellorState.pending.options.slice(1);
    Engine.chooseChancellorKeep(chancellorState, keep.id);
    assert.equal(chancellorState.phase, 'chancellorKeep');
    Engine.orderChancellorReturns(chancellorState, [returnCards[1].id, returnCards[0].id]);
    assert.equal(chancellorState.players[0].hand[0].id, keep.id);
    assert.deepEqual(chancellorState.deck.slice(0, 2).map(item => item.id), [returnCards[1].id, returnCards[0].id]);
}

function testSpyBonusAndTiedRoundScoring() {
    const spyState = Engine.createMatch(['A', 'B'], 0, deterministic);
    Engine.startTurn(spyState);
    spyState.players[0].hand = [card('spy'), card('guard')];
    spyState.players[1].hand = [card('princess')];
    spyState.deck = [];
    Engine.playCard(spyState, 'spy');
    assert.equal(spyState.phase, 'roundEnd');
    assert.deepEqual(spyState.result.winnerIndexes, [1]);
    assert.deepEqual(spyState.result.spyAwardIndexes, [0]);
    assert.deepEqual(spyState.players.map(player => player.score), [1, 1]);

    const tieState = Engine.createMatch(['A', 'B'], 0, deterministic);
    Engine.startTurn(tieState);
    tieState.players[0].hand = [card('countess'), card('guard')];
    tieState.players[1].hand = [card('guard', 'guard-other')];
    tieState.deck = [];
    Engine.playCard(tieState, 'countess');
    assert.deepEqual(tieState.result.winnerIndexes, [0, 1]);
    assert.deepEqual(tieState.players.map(player => player.score), [1, 1]);
}

function testNextRoundStarterAndSavedStateValidation() {
    const state = Engine.createMatch(['A', 'B'], 0, deterministic);
    Engine.startTurn(state);
    state.players[0].hand = [card('countess'), card('princess')];
    state.players[1].hand = [card('guard')];
    state.deck = [];
    Engine.playCard(state, 'countess');
    const next = Engine.nextRound(state, deterministic);
    assert.equal(next.round, 2);
    assert.equal(next.currentIndex, 0);
    assert.equal(next.players[0].score, 1);
    assert.equal(Engine.isValidSavedState(next), true);
}

[
    testDeckAndSetup,
    testCountessMandatoryRule,
    testProtectedTargetsAndPrinceSelfRule,
    testPrinceDiscardingPrincessEliminatesWithoutDraw,
    testGuardGuessAndChancellorReturnOrder,
    testSpyBonusAndTiedRoundScoring,
    testNextRoundStarterAndSavedStateValidation
].forEach(test => test());

console.log('Love Letter rules engine tests passed.');
