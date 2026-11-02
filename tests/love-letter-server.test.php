<?php
// Standalone rules/session regression tests. Run with: php tests/love-letter-server.test.php
$store = sys_get_temp_dir() . '/ll-test-' . bin2hex(random_bytes(6)) . '.json';
define('LOVE_LETTER_SESSIONS_FILE', $store);
function arcade_encrypt_data($data) { return 'TEST:' . base64_encode(json_encode($data)); }
function arcade_decrypt_data($payload) {
    if (strpos($payload, 'TEST:') !== 0) return null;
    $decoded = json_decode(base64_decode(substr($payload, 5)), true);
    return is_array($decoded) ? $decoded : null;
}
require_once __DIR__ . '/../love-letter-server.php';

function ll_test_assert($condition, $message) {
    if (!$condition) throw new RuntimeException('FAILED: ' . $message);
}
function ll_test_card($key, $id = null) {
    $definitions = ll_card_definitions();
    return ['id' => $id ?: $key, 'key' => $key, 'value' => $definitions[$key]['value']];
}

try {
    $round = ll_create_round([['name' => 'Alice', 'score' => 0], ['name' => 'Bob', 'score' => 0]], 0, 1);
    ll_test_assert(count($round['players']) === 2 && count($round['deck']) === 15, 'two-player round setup deck count');
    ll_test_assert(count($round['faceUpRemoved']) === 3 && count($round['players'][0]['hand']) === 1, 'two-player setup exposes three set-aside cards and deals one each');
    $allCards = array_merge($round['deck'], [$round['reserveCard']], $round['faceUpRemoved'], $round['players'][0]['hand'], $round['players'][1]['hand']);
    ll_test_assert(count($allCards) === 21 && count(array_unique(array_column($allCards, 'id'))) === 21, 'all 21 unique cards are accounted for');
    ll_test_assert(ll_favor_target(2) === 6 && ll_favor_target(3) === 5 && ll_favor_target(4) === 4 && ll_favor_target(5) === 3 && ll_favor_target(6) === 3, 'favor targets match player counts');
    ll_test_assert(ll_forced_countess([ll_test_card('countess'), ll_test_card('prince')]), 'Countess is mandatory with King or Prince');
    ll_test_assert(!ll_forced_countess([ll_test_card('countess'), ll_test_card('guard')]), 'Countess is not mandatory with other cards');

    $targetGame = $round;
    $targetGame['currentIndex'] = 0;
    $targetGame['players'][1]['protected'] = true;
    ll_test_assert(ll_valid_targets($targetGame, 'guard') === [], 'protected opponents cannot be targeted by Guard');
    ll_test_assert(ll_valid_targets($targetGame, 'prince') === [0], 'Prince can target self when all opponents are protected');

    $guardGame = $round;
    $guardGame['players'][0]['hand'] = [ll_test_card('guard', 'guard-play'), ll_test_card('spy', 'spy-kept')];
    $guardGame['players'][1]['hand'] = [ll_test_card('countess', 'countess-held')];
    $guardGame['deck'] = [ll_test_card('priest', 'draw-test')];
    $guardGame['currentIndex'] = 0; $guardGame['phase'] = 'turn'; $guardGame['currentDrawn'] = true;
    ll_play_card($guardGame, 'guard-play');
    ll_test_assert($guardGame['phase'] === 'target', 'Guard requires target selection');
    ll_choose_target($guardGame, 1);
    ll_test_assert($guardGame['phase'] === 'guess', 'Guard target moves to guess phase');
    ll_resolve_guard($guardGame, 'countess');
    ll_test_assert($guardGame['phase'] === 'roundEnd' && !$guardGame['players'][1]['inRound'], 'correct Guard guess eliminates target and closes a two-player round');
    ll_test_assert($guardGame['result']['winnerIndexes'] === [0] && $guardGame['players'][0]['score'] === 1, 'round winner earns a favor token');

    $princeGame = ll_create_round([
        ['name' => 'Alice', 'score' => 0], ['name' => 'Bob', 'score' => 0], ['name' => 'Cara', 'score' => 0]
    ], 0, 1);
    $princeGame['players'][0]['hand'] = [ll_test_card('prince', 'prince-play'), ll_test_card('guard', 'guard-kept')];
    $princeGame['players'][1]['hand'] = [ll_test_card('princess', 'princess-discarded')];
    $princeGame['phase'] = 'turn'; $princeGame['currentDrawn'] = true; $princeGame['currentIndex'] = 0;
    $beforeDeck = count($princeGame['deck']);
    ll_play_card($princeGame, 'prince-play'); ll_choose_target($princeGame, 1);
    ll_test_assert(!$princeGame['players'][1]['inRound'] && count($princeGame['players'][1]['hand']) === 0, 'Prince discarding Princess eliminates without replacement');
    ll_test_assert(count($princeGame['deck']) === $beforeDeck, 'Prince does not draw after Princess is discarded');

    $privacyRoom = [
        'code' => 'TEST2345', 'status' => 'playing', 'createdAt' => time(), 'updatedAt' => time(), 'capacity' => 2,
        'creatorSeat' => 0, 'seats' => [['name' => 'Alice', 'tokenHash' => 'a'], ['name' => 'Bob', 'tokenHash' => 'b']],
        'game' => $guardGame
    ];
    $guardGame['phase'] = 'turn'; $guardGame['currentIndex'] = 0;
    $privacyRoom['game'] = $guardGame;
    $aliceView = ll_public_session($privacyRoom, 0);
    $bobView = ll_public_session($privacyRoom, 1);
    ll_test_assert(isset($aliceView['game']['privateHand']) && !isset($bobView['game']['privateHand']), 'only the current authenticated seat receives its hand');
    $privacyRoom['game']['phase'] = 'priestReveal';
    $privacyRoom['game']['pending'] = ['actorIndex' => 0, 'targetIndex' => 1, 'revealedCard' => ll_test_card('king')];
    $aliceView = ll_public_session($privacyRoom, 0); $bobView = ll_public_session($privacyRoom, 1);
    ll_test_assert(isset($aliceView['game']['priestCard']) && !isset($bobView['game']['priestCard']), 'Priest reveal is only delivered to the actor');

    ll_sessions_transaction(function (&$sessions) { $sessions['TEST2345'] = ['code' => 'TEST2345', 'creatorSecretHash' => hash('sha256', 'secret')]; }, true);
    $loaded = ll_sessions_transaction(function (&$sessions) { return $sessions['TEST2345']['creatorSecretHash'] ?? null; });
    ll_test_assert($loaded === hash('sha256', 'secret'), 'session transactions persist and reload data');
    $rawStore = file_get_contents($store);
    ll_test_assert(strpos($rawStore, 'creatorSecretHash') === false && strpos($rawStore, 'TEST:') !== false, 'session transaction uses the configured storage serializer');

    echo "Love Letter server regression tests passed.\n";
} finally {
    if (file_exists($store)) unlink($store);
}
