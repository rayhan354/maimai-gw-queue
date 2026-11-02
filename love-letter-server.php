<?php
/**
 * Persistent, server-authoritative Love Letter sessions.
 * Included from core.php after the application bootstrap and crypto helpers.
 */

if (!defined('LOVE_LETTER_SESSIONS_FILE')) {
    define('LOVE_LETTER_SESSIONS_FILE', __DIR__ . '/love_letter_sessions.json');
}

function ll_api_json($payload, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function ll_api_error($message, $status = 400) {
    ll_api_json(['ok' => false, 'error' => $message], $status);
}

function ll_clean_code($code) {
    return strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)$code));
}

function ll_new_secret() {
    return bin2hex(random_bytes(24));
}

function ll_new_room_code($sessions) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 8; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    } while (isset($sessions[$code]));
    return $code;
}

/** Execute a transaction while holding an exclusive lock across read/modify/write. */
function ll_sessions_transaction($callback, $write = false) {
    $fp = @fopen(LOVE_LETTER_SESSIONS_FILE, 'c+');
    if (!$fp || !flock($fp, LOCK_EX)) {
        if ($fp) fclose($fp);
        throw new RuntimeException('Could not lock saved game sessions. Check directory permissions.');
    }
    try {
        @chmod(LOVE_LETTER_SESSIONS_FILE, 0600);
        rewind($fp);
        $raw = stream_get_contents($fp);
        $sessions = [];
        if (trim((string)$raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['_encrypted']) && is_string($decoded['_encrypted'])) {
                $decrypted = arcade_decrypt_data($decoded['_encrypted']);
                if (!is_array($decrypted) || !isset($decrypted['sessions']) || !is_array($decrypted['sessions'])) {
                    throw new RuntimeException('The saved Love Letter session file could not be decrypted; it was left unchanged.');
                }
                $sessions = $decrypted['sessions'];
            } elseif (is_array($decoded) && isset($decoded['sessions']) && is_array($decoded['sessions'])) {
                // Migrate any early plaintext test data immediately to encrypted storage on write.
                $sessions = $decoded['sessions'];
            } else {
                throw new RuntimeException('The saved Love Letter session file is invalid; it was left unchanged.');
            }
        }
        $result = $callback($sessions);
        if ($write) {
            $payload = json_encode(['_encrypted' => arcade_encrypt_data(['sessions' => $sessions])], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($payload === false) throw new RuntimeException('Could not encode saved game sessions.');
            rewind($fp);
            $written = false;
            if (ftruncate($fp, 0)) $written = fwrite($fp, $payload);
            if ($written !== strlen($payload) || !fflush($fp)) {
                throw new RuntimeException('Could not save game sessions. Check directory permissions.');
            }
            @chmod(LOVE_LETTER_SESSIONS_FILE, 0600);
        }
        return $result;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function ll_safe_player_name($name) {
    $name = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string)$name));
    if (function_exists('mb_substr')) $name = mb_substr($name, 0, 18, 'UTF-8');
    else $name = substr($name, 0, 54);
    return $name;
}

function ll_session_by_code(&$sessions, $code) {
    $code = ll_clean_code($code);
    if (strlen($code) !== 8 || !isset($sessions[$code]) || !is_array($sessions[$code])) {
        throw new InvalidArgumentException('Session not found. Check the 8-character code.');
    }
    return $code;
}

function ll_session_creator_index($room) {
    return isset($room['creatorSeat']) && is_int($room['creatorSeat']) ? $room['creatorSeat'] : 0;
}

function ll_require_creator($room, $secret) {
    $hash = hash('sha256', (string)$secret);
    if (empty($room['creatorSecretHash']) || !hash_equals($room['creatorSecretHash'], $hash)) {
        throw new DomainException('Only the session creator can do that.', 403);
    }
}

function ll_find_player_seat($room, $token) {
    if (!is_string($token) || $token === '') return null;
    $hash = hash('sha256', $token);
    foreach ($room['seats'] ?? [] as $index => $seat) {
        if (!empty($seat['tokenHash']) && hash_equals($seat['tokenHash'], $hash)) return $index;
    }
    return null;
}

function ll_card_definitions() {
    return [
        'spy' => ['key' => 'spy', 'name' => 'Spy', 'value' => 0, 'copies' => 2],
        'guard' => ['key' => 'guard', 'name' => 'Guard', 'value' => 1, 'copies' => 6],
        'priest' => ['key' => 'priest', 'name' => 'Priest', 'value' => 2, 'copies' => 2],
        'baron' => ['key' => 'baron', 'name' => 'Baron', 'value' => 3, 'copies' => 2],
        'handmaid' => ['key' => 'handmaid', 'name' => 'Handmaid', 'value' => 4, 'copies' => 2],
        'prince' => ['key' => 'prince', 'name' => 'Prince', 'value' => 5, 'copies' => 2],
        'chancellor' => ['key' => 'chancellor', 'name' => 'Chancellor', 'value' => 6, 'copies' => 2],
        'king' => ['key' => 'king', 'name' => 'King', 'value' => 7, 'copies' => 1],
        'countess' => ['key' => 'countess', 'name' => 'Countess', 'value' => 8, 'copies' => 1],
        'princess' => ['key' => 'princess', 'name' => 'Princess', 'value' => 9, 'copies' => 1]
    ];
}

function ll_favor_target($count) {
    return [2 => 6, 3 => 5, 4 => 4, 5 => 3, 6 => 3][$count] ?? 4;
}

function ll_shuffled_deck() {
    $deck = [];
    foreach (ll_card_definitions() as $key => $definition) {
        for ($copy = 1; $copy <= $definition['copies']; $copy++) {
            $deck[] = ['id' => $key . '-' . $copy, 'key' => $key, 'value' => $definition['value']];
        }
    }
    for ($i = count($deck) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$deck[$i], $deck[$j]] = [$deck[$j], $deck[$i]];
    }
    return $deck;
}

function ll_create_round($priorPlayers, $starterIndex, $roundNumber) {
    $players = [];
    foreach ($priorPlayers as $player) {
        $players[] = [
            'name' => $player['name'], 'score' => (int)($player['score'] ?? 0), 'hand' => [],
            'inRound' => true, 'protected' => false, 'spyPlayed' => false
        ];
    }
    $deck = ll_shuffled_deck();
    $reserveCard = array_pop($deck);
    $faceUpRemoved = count($players) === 2 ? [array_pop($deck), array_pop($deck), array_pop($deck)] : [];
    foreach ($players as &$player) $player['hand'][] = array_pop($deck);
    unset($player);
    return [
        'round' => $roundNumber, 'targetTokens' => ll_favor_target(count($players)), 'players' => $players,
        'deck' => $deck, 'reserveCard' => $reserveCard, 'faceUpRemoved' => $faceUpRemoved,
        'discards' => [], 'currentIndex' => $starterIndex, 'currentDrawn' => false,
        'phase' => 'handoff', 'resumePhase' => null, 'pending' => null, 'result' => null, 'gameOver' => false,
        'lastEvent' => 'Round ' . $roundNumber . ' is ready. Pass the screen to ' . $players[$starterIndex]['name'] . '.'
    ];
}

function ll_active_count($game) {
    $count = 0;
    foreach ($game['players'] as $player) if (!empty($player['inRound'])) $count++;
    return $count;
}

function ll_forced_countess($hand) {
    $hasCountess = false; $hasRoyal = false;
    foreach ($hand as $card) {
        if ($card['key'] === 'countess') $hasCountess = true;
        if ($card['key'] === 'king' || $card['key'] === 'prince') $hasRoyal = true;
    }
    return $hasCountess && $hasRoyal;
}

function ll_record_discard(&$game, $playerIndex, $card, $reason) {
    if (!$card) return;
    $game['discards'][] = ['playerIndex' => $playerIndex, 'card' => $card, 'reason' => $reason];
    if ($card['key'] === 'spy') $game['players'][$playerIndex]['spyPlayed'] = true;
}

function ll_eliminate(&$game, $playerIndex, $reason = 'revealed when eliminated') {
    if (!isset($game['players'][$playerIndex]) || empty($game['players'][$playerIndex]['inRound'])) return;
    foreach ($game['players'][$playerIndex]['hand'] as $card) ll_record_discard($game, $playerIndex, $card, $reason);
    $game['players'][$playerIndex]['hand'] = [];
    $game['players'][$playerIndex]['inRound'] = false;
    $game['players'][$playerIndex]['protected'] = false;
}

function ll_valid_targets($game, $cardKey) {
    $targets = [];
    foreach ($game['players'] as $index => $player) {
        if (empty($player['inRound']) || !empty($player['protected'])) continue;
        if ($cardKey === 'prince' || $index !== $game['currentIndex']) $targets[] = $index;
    }
    return $targets;
}

function ll_finish_round(&$game, $reason) {
    $active = [];
    foreach ($game['players'] as $index => $player) if (!empty($player['inRound'])) $active[] = $index;
    $winners = [];
    if (count($active) === 1) {
        $winners = $active;
    } elseif (count($active) > 1) {
        $highest = -1;
        foreach ($active as $index) {
            $value = $game['players'][$index]['hand'][0]['value'] ?? -1;
            if ($value > $highest) { $highest = $value; $winners = [$index]; }
            elseif ($value === $highest) $winners[] = $index;
        }
    }
    $spyCandidates = [];
    foreach ($active as $index) if (!empty($game['players'][$index]['spyPlayed'])) $spyCandidates[] = $index;
    $spyAwards = count($spyCandidates) === 1 ? $spyCandidates : [];
    foreach ($winners as $index) $game['players'][$index]['score']++;
    foreach ($spyAwards as $index) $game['players'][$index]['score']++;
    $gameWinners = [];
    foreach ($game['players'] as $index => $player) if ($player['score'] >= $game['targetTokens']) $gameWinners[] = $index;
    $game['gameOver'] = count($gameWinners) > 0;
    $game['result'] = [
        'reason' => $reason, 'winnerIndexes' => $winners, 'spyAwardIndexes' => $spyAwards,
        'gameWinnerIndexes' => $gameWinners, 'activeIndexes' => $active, 'targetTokens' => $game['targetTokens']
    ];
    $game['pending'] = null; $game['currentDrawn'] = false; $game['phase'] = 'roundEnd';
    if ($game['gameOver']) {
        $names = array_map(fn($i) => $game['players'][$i]['name'], $gameWinners);
        $game['lastEvent'] = implode(' and ', $names) . ' ' . (count($names) === 1 ? 'has' : 'have') . ' earned enough favor to win the game.';
    } elseif (count($winners) === 1) {
        $game['lastEvent'] = $game['players'][$winners[0]]['name'] . ' wins round ' . $game['round'] . '.';
    } elseif (count($winners) > 1) {
        $names = array_map(fn($i) => $game['players'][$i]['name'], $winners);
        $game['lastEvent'] = 'The round is tied. ' . implode(' and ', $names) . ' share the favor.';
    }
}

function ll_complete_turn(&$game) {
    $game['pending'] = null;
    if (ll_active_count($game) <= 1) { ll_finish_round($game, 'last player standing'); return; }
    if (count($game['deck']) === 0) { ll_finish_round($game, 'deck empty'); return; }
    $count = count($game['players']); $next = $game['currentIndex'];
    for ($step = 0; $step < $count; $step++) {
        $next = ($next + 1) % $count;
        if (!empty($game['players'][$next]['inRound'])) break;
    }
    $game['currentIndex'] = $next; $game['currentDrawn'] = false; $game['phase'] = 'handoff';
    $game['lastEvent'] = 'Pass the screen to ' . $game['players'][$next]['name'] . '.';
}

function ll_start_turn(&$game) {
    if ($game['phase'] !== 'handoff') throw new DomainException('This player does not have a turn to reveal.');
    if (!empty($game['resumePhase'])) {
        $game['phase'] = $game['resumePhase']; $game['resumePhase'] = null; return;
    }
    $index = $game['currentIndex'];
    if (empty($game['players'][$index]['inRound'])) throw new DomainException('This player is no longer in the round.');
    $game['players'][$index]['protected'] = false;
    $drawn = array_pop($game['deck']);
    if ($drawn) $game['players'][$index]['hand'][] = $drawn;
    $game['currentDrawn'] = true; $game['phase'] = 'turn';
    $game['lastEvent'] = $drawn ? $game['players'][$index]['name'] . ' draws a card. Choose one card to play.' : $game['players'][$index]['name'] . ' has no card to draw.';
}

function ll_play_card(&$game, $cardId) {
    if ($game['phase'] !== 'turn' || empty($game['currentDrawn'])) throw new DomainException('Start the turn before playing a card.');
    $actorIndex = $game['currentIndex']; $actor = &$game['players'][$actorIndex];
    $cardIndex = null;
    foreach ($actor['hand'] as $index => $card) if ($card['id'] === $cardId) { $cardIndex = $index; break; }
    if ($cardIndex === null) throw new DomainException('That card is not in your hand.');
    if (ll_forced_countess($actor['hand']) && $actor['hand'][$cardIndex]['key'] !== 'countess') throw new DomainException('The Countess must be played with the King or Prince.');
    $card = $actor['hand'][$cardIndex]; array_splice($actor['hand'], $cardIndex, 1);
    ll_record_discard($game, $actorIndex, $card, 'played');
    $game['pending'] = ['card' => $card, 'actorIndex' => $actorIndex];
    if ($card['key'] === 'princess') {
        ll_eliminate($game, $actorIndex); $game['lastEvent'] = $actor['name'] . ' played the Princess and is out of the round.';
        ll_complete_turn($game); return;
    }
    if ($card['key'] === 'chancellor') {
        if (count($game['deck']) === 0) { $game['lastEvent'] = 'The deck is empty; the Chancellor has no effect.'; ll_complete_turn($game); return; }
        $options = $actor['hand']; $options[] = array_pop($game['deck']);
        if (count($game['deck'])) $options[] = array_pop($game['deck']);
        $game['pending']['options'] = $options; $game['pending']['stage'] = 'keep'; $game['phase'] = 'chancellorKeep';
        $game['lastEvent'] = $actor['name'] . ' uses the Chancellor. Keep one card from the choices.'; return;
    }
    if ($card['key'] === 'handmaid') {
        $actor['protected'] = true; $game['lastEvent'] = $actor['name'] . ' is protected until the start of their next turn.'; ll_complete_turn($game); return;
    }
    if ($card['key'] === 'spy' || $card['key'] === 'countess') {
        $game['lastEvent'] = $card['key'] === 'spy' ? $actor['name'] . ' played the Spy.' : $actor['name'] . ' played the Countess.'; ll_complete_turn($game); return;
    }
    $targets = ll_valid_targets($game, $card['key']);
    if (!$targets) { $game['lastEvent'] = 'No eligible player can be chosen for ' . ucfirst($card['key']) . '; its effect is skipped.'; ll_complete_turn($game); return; }
    $game['phase'] = 'target'; $game['lastEvent'] = $actor['name'] . ' played ' . ucfirst($card['key']) . '. Choose a target.';
}

function ll_choose_target(&$game, $targetIndex) {
    if ($game['phase'] !== 'target' || empty($game['pending'])) throw new DomainException('No target is being selected.');
    $targetIndex = filter_var($targetIndex, FILTER_VALIDATE_INT);
    $cardKey = $game['pending']['card']['key']; $valid = ll_valid_targets($game, $cardKey);
    if ($targetIndex === false || !in_array($targetIndex, $valid, true)) throw new DomainException('That player cannot be chosen for this effect.');
    $actorIndex = $game['currentIndex']; $actorName = $game['players'][$actorIndex]['name']; $targetName = $game['players'][$targetIndex]['name'];
    if ($cardKey === 'guard') {
        $game['pending']['targetIndex'] = $targetIndex; $game['phase'] = 'guess'; $game['lastEvent'] = $actorName . ' chose ' . $targetName . '. Guess the character in their hand.'; return;
    }
    if ($cardKey === 'priest') {
        $game['pending']['targetIndex'] = $targetIndex; $game['pending']['revealedCard'] = $game['players'][$targetIndex]['hand'][0] ?? null;
        $game['phase'] = 'priestReveal'; $game['lastEvent'] = $actorName . ' privately looks at ' . $targetName . '’s hand.'; return;
    }
    if ($cardKey === 'baron') {
        $actorCard = $game['players'][$actorIndex]['hand'][0] ?? null; $targetCard = $game['players'][$targetIndex]['hand'][0] ?? null;
        if ($actorCard && $targetCard && $actorCard['value'] < $targetCard['value']) {
            ll_eliminate($game, $actorIndex); $game['lastEvent'] = $actorName . ' has the lower card and is out of the round.';
        } elseif ($actorCard && $targetCard && $targetCard['value'] < $actorCard['value']) {
            ll_eliminate($game, $targetIndex); $game['lastEvent'] = $targetName . ' has the lower card and is out of the round.';
        } else $game['lastEvent'] = 'The hands tie. Neither ' . $actorName . ' nor ' . $targetName . ' is eliminated.';
        ll_complete_turn($game); return;
    }
    if ($cardKey === 'king') {
        [$game['players'][$actorIndex]['hand'], $game['players'][$targetIndex]['hand']] = [$game['players'][$targetIndex]['hand'], $game['players'][$actorIndex]['hand']];
        $game['lastEvent'] = $actorName . ' and ' . $targetName . ' trade hands.'; ll_complete_turn($game); return;
    }
    if ($cardKey === 'prince') {
        $discarded = $game['players'][$targetIndex]['hand'][0] ?? null;
        if ($discarded) ll_record_discard($game, $targetIndex, $discarded, 'discarded by Prince');
        $game['players'][$targetIndex]['hand'] = [];
        if ($discarded && $discarded['key'] === 'princess') {
            ll_eliminate($game, $targetIndex); $game['lastEvent'] = $targetName . ' discarded the Princess and is out of the round.'; ll_complete_turn($game); return;
        }
        $replacement = array_pop($game['deck']);
        if (!$replacement) { $replacement = $game['reserveCard']; $game['reserveCard'] = null; }
        if ($replacement) $game['players'][$targetIndex]['hand'] = [$replacement];
        $game['lastEvent'] = $targetName . ' discards their hand and draws a new card.'; ll_complete_turn($game); return;
    }
    throw new DomainException('This card effect cannot select another player.');
}

function ll_resolve_guard(&$game, $guessedKey) {
    $defs = ll_card_definitions();
    if ($game['phase'] !== 'guess' || empty($game['pending']) || !isset($defs[$guessedKey]) || $guessedKey === 'guard') throw new DomainException('Choose a valid non-Guard character.');
    $targetIndex = $game['pending']['targetIndex']; $targetName = $game['players'][$targetIndex]['name'];
    if (($game['players'][$targetIndex]['hand'][0]['key'] ?? '') === $guessedKey) {
        ll_eliminate($game, $targetIndex); $game['lastEvent'] = 'Correct guess. ' . $targetName . ' is out of the round.';
    } else $game['lastEvent'] = 'The guess was wrong. ' . $targetName . ' stays in the round.';
    ll_complete_turn($game);
}

function ll_hide_priest(&$game) {
    if ($game['phase'] !== 'priestReveal' || empty($game['pending'])) throw new DomainException('There is no Priest reveal to hide.');
    $name = $game['players'][$game['pending']['targetIndex']]['name'] ?? 'The player';
    $game['lastEvent'] = $name . '’s hand is hidden. Pass the screen to the next player.'; ll_complete_turn($game);
}

function ll_chancellor_keep(&$game, $cardId) {
    if ($game['phase'] !== 'chancellorKeep' || empty($game['pending']) || ($game['pending']['stage'] ?? '') !== 'keep') throw new DomainException('Choose one Chancellor card first.');
    $options = $game['pending']['options']; $keep = null; $returns = [];
    foreach ($options as $card) { if ($card['id'] === $cardId && $keep === null) $keep = $card; else $returns[] = $card; }
    if (!$keep) throw new DomainException('That card is not one of the Chancellor choices.');
    $game['pending']['keepCard'] = $keep; $game['pending']['returnCards'] = $returns;
    if (count($returns) === 2) { $game['pending']['stage'] = 'order'; $game['lastEvent'] = 'Choose the order for the two cards returned to the bottom of the deck.'; return; }
    ll_finish_chancellor($game, $returns);
}

function ll_finish_chancellor(&$game, $returns) {
    $game['players'][$game['currentIndex']]['hand'] = [$game['pending']['keepCard']];
    $game['deck'] = array_merge($returns, $game['deck']);
    $name = $game['players'][$game['currentIndex']]['name'];
    $game['lastEvent'] = $name . ' keeps one card and returns the others to the bottom of the deck.';
    $game['pending'] = null; ll_complete_turn($game);
}

function ll_chancellor_order(&$game, $orderedIds) {
    if ($game['phase'] !== 'chancellorKeep' || empty($game['pending']) || ($game['pending']['stage'] ?? '') !== 'order') throw new DomainException('There are no Chancellor cards to order.');
    $returns = $game['pending']['returnCards'];
    if (!is_array($orderedIds) || count($orderedIds) !== count($returns) || count(array_unique($orderedIds)) !== count($returns)) throw new DomainException('Choose each returned card exactly once.');
    $ordered = [];
    foreach ($orderedIds as $id) {
        $found = null;
        foreach ($returns as $card) if ($card['id'] === $id) $found = $card;
        if (!$found) throw new DomainException('That return order is invalid.');
        $ordered[] = $found;
    }
    ll_finish_chancellor($game, $ordered);
}

function ll_next_round(&$game) {
    if ($game['phase'] !== 'roundEnd' || !empty($game['gameOver']) || empty($game['result'])) throw new DomainException('The game is not ready for another round.');
    $winners = $game['result']['winnerIndexes'];
    if (!$winners) throw new DomainException('There is no round winner.');
    $starter = $winners[random_int(0, count($winners) - 1)];
    $players = $game['players'];
    $game = ll_create_round($players, $starter, $game['round'] + 1);
}

function ll_public_session($room, $seatIndex, $isAdmin = false) {
    $seats = [];
    foreach ($room['seats'] as $index => $seat) {
        $seats[] = ['index' => $index, 'name' => $seat['name'], 'isCreator' => $index === ll_session_creator_index($room)];
    }
    $result = [
        'ok' => true,
        'code' => $room['code'], 'status' => $room['status'], 'createdAt' => $room['createdAt'],
        'updatedAt' => $room['updatedAt'], 'capacity' => $room['capacity'], 'seatCount' => count($seats),
        'seats' => $seats, 'mySeatIndex' => $seatIndex,
        'isCreator' => $seatIndex === ll_session_creator_index($room), 'isAdmin' => $isAdmin,
        'game' => null
    ];
    if ($room['status'] === 'lobby') return $result;
    $game = $room['game'];
    $players = [];
    foreach ($game['players'] as $index => $player) {
        $players[] = [
            'index' => $index, 'name' => $player['name'], 'score' => $player['score'],
            'inRound' => $player['inRound'], 'protected' => $player['protected'],
            'isCurrent' => $index === $game['currentIndex'], 'handCount' => count($player['hand'])
        ];
    }
    $view = [
        'round' => $game['round'], 'targetTokens' => $game['targetTokens'], 'players' => $players,
        'deckCount' => count($game['deck']), 'faceUpRemoved' => $game['faceUpRemoved'],
        'discards' => $game['discards'], 'currentIndex' => $game['currentIndex'],
        'phase' => $game['phase'], 'lastEvent' => $game['lastEvent'], 'result' => $game['result'],
        'gameOver' => $game['gameOver'], 'pendingCard' => $game['pending']['card'] ?? null,
        'pendingTargetIndex' => $game['pending']['targetIndex'] ?? null,
        'pendingStage' => $game['pending']['stage'] ?? null,
        'validTargets' => in_array($game['phase'], ['target'], true) ? ll_valid_targets($game, $game['pending']['card']['key']) : []
    ];
    if ($seatIndex !== null && $seatIndex === $game['currentIndex'] && $game['phase'] !== 'handoff' && $game['phase'] !== 'roundEnd') {
        $view['privateHand'] = $game['players'][$seatIndex]['hand'];
        if ($game['phase'] === 'priestReveal' && ($game['pending']['actorIndex'] ?? null) === $seatIndex) {
            $view['priestCard'] = $game['pending']['revealedCard'] ?? null;
            $view['priestTargetIndex'] = $game['pending']['targetIndex'];
        }
        if ($game['phase'] === 'chancellorKeep' && ($game['pending']['actorIndex'] ?? null) === $seatIndex) {
            $view['chancellorOptions'] = ($game['pending']['stage'] ?? '') === 'keep' ? $game['pending']['options'] : [];
            $view['chancellorReturnCards'] = ($game['pending']['stage'] ?? '') === 'order' ? $game['pending']['returnCards'] : [];
        }
    }
    if ($game['phase'] === 'roundEnd') {
        $view['finalHands'] = [];
        foreach ($game['result']['activeIndexes'] as $index) $view['finalHands'][] = [
            'playerIndex' => $index, 'cards' => $game['players'][$index]['hand']
        ];
    }
    $result['game'] = $view;
    return $result;
}

function ll_room_summary($room) {
    return [
        'code' => $room['code'], 'status' => $room['status'], 'createdAt' => $room['createdAt'],
        'updatedAt' => $room['updatedAt'], 'capacity' => $room['capacity'], 'seatCount' => count($room['seats']),
        'players' => array_map(fn($seat) => $seat['name'], $room['seats']), 'round' => $room['game']['round'] ?? 0
    ];
}

function ll_handle_api($isAdmin) {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $api = $_GET['api'] ?? '';
    if ($method === 'GET' && $api === 'love-letter-admin-sessions') {
        if (!$isAdmin) ll_api_error('Administrator access required.', 403);
        try {
            $list = ll_sessions_transaction(function (&$sessions) { return array_values(array_map('ll_room_summary', $sessions)); });
            ll_api_json(['ok' => true, 'sessions' => $list]);
        } catch (Throwable $e) { ll_api_error($e->getMessage(), 500); }
    }
    if ($method === 'GET' && $api === 'love-letter-session') {
        $code = ll_clean_code($_GET['code'] ?? '');
        $token = $_SERVER['HTTP_X_LL_TOKEN'] ?? '';
        try {
            $payload = ll_sessions_transaction(function (&$sessions) use ($code, $token, $isAdmin) {
                $code = ll_session_by_code($sessions, $code); $room = $sessions[$code];
                $seatIndex = ll_find_player_seat($room, $token);
                if ($seatIndex === null && !($isAdmin && $token === 'admin-readonly')) throw new DomainException('This device is not joined to that session.', 403);
                return ll_public_session($room, $seatIndex, $isAdmin);
            });
            ll_api_json($payload);
        } catch (Throwable $e) { ll_api_error($e->getMessage(), $e instanceof DomainException && $e->getCode() === 403 ? 403 : 404); }
    }
    if ($method !== 'POST' || $api !== 'love-letter-action') return false;
    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf($csrf)) ll_api_error('Security check failed. Refresh the page and try again.', 403);
    $body = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($body)) ll_api_error('Invalid request body.');
    $action = (string)($body['action'] ?? '');
    try {
        $payload = ll_sessions_transaction(function (&$sessions) use ($body, $action, $isAdmin) {
            if ($action === 'create') {
                $name = ll_safe_player_name($body['name'] ?? '');
                $capacity = filter_var($body['capacity'] ?? null, FILTER_VALIDATE_INT);
                if ($name === '' || $capacity === false || $capacity < 2 || $capacity > 6) throw new InvalidArgumentException('Enter your name and choose 2–6 players.');
                if (count($sessions) >= 200) throw new DomainException('This server already has 200 saved sessions. Ask an admin to delete finished or abandoned sessions.');
                $code = ll_new_room_code($sessions); $creatorSecret = ll_new_secret(); $playerToken = ll_new_secret();
                $room = [
                    'code' => $code, 'createdAt' => time(), 'updatedAt' => time(), 'status' => 'lobby', 'capacity' => $capacity,
                    'creatorSeat' => 0, 'creatorSecretHash' => hash('sha256', $creatorSecret),
                    'seats' => [['name' => $name, 'tokenHash' => hash('sha256', $playerToken), 'joinedAt' => time()]], 'game' => null
                ];
                $sessions[$code] = $room;
                return ['ok' => true, 'code' => $code, 'creatorSecret' => $creatorSecret, 'playerToken' => $playerToken, 'room' => ll_public_session($room, 0)];
            }
            if ($action === 'join') {
                $code = ll_session_by_code($sessions, $body['code'] ?? ''); $room = $sessions[$code];
                if ($room['status'] !== 'lobby') throw new DomainException('This game has already started; new players can no longer join.');
                $name = ll_safe_player_name($body['name'] ?? '');
                if ($name === '') throw new InvalidArgumentException('Enter your player name.');
                if (count($room['seats']) >= $room['capacity']) throw new DomainException('This session is full.');
                $token = ll_new_secret(); $seatIndex = count($room['seats']);
                $room['seats'][] = ['name' => $name, 'tokenHash' => hash('sha256', $token), 'joinedAt' => time()];
                $room['updatedAt'] = time(); $sessions[$code] = $room;
                return ['ok' => true, 'code' => $code, 'playerToken' => $token, 'room' => ll_public_session($room, $seatIndex)];
            }
            if ($action === 'resume') {
                $code = ll_session_by_code($sessions, $body['code'] ?? ''); $room = $sessions[$code];
                ll_require_creator($room, $body['creatorSecret'] ?? '');
                $seatIndex = ll_session_creator_index($room); $playerToken = ll_new_secret();
                $room['seats'][$seatIndex]['tokenHash'] = hash('sha256', $playerToken); $room['updatedAt'] = time(); $sessions[$code] = $room;
                return ['ok' => true, 'code' => $code, 'playerToken' => $playerToken, 'room' => ll_public_session($room, $seatIndex)];
            }
            if ($action === 'delete') {
                $code = ll_session_by_code($sessions, $body['code'] ?? ''); $room = $sessions[$code];
                if (!$isAdmin) ll_require_creator($room, $body['creatorSecret'] ?? '');
                unset($sessions[$code]);
                return ['ok' => true, 'deletedCode' => $code];
            }
            $code = ll_session_by_code($sessions, $body['code'] ?? ''); $room = $sessions[$code];
            $token = (string)($body['playerToken'] ?? ''); $seatIndex = ll_find_player_seat($room, $token);
            if ($seatIndex === null) throw new DomainException('This device is not joined to that session.', 403);
            if ($action === 'leave-lobby') {
                if ($room['status'] !== 'lobby') throw new DomainException('A seat cannot be released after the game has started.');
                if ($seatIndex === ll_session_creator_index($room)) throw new DomainException('The creator must delete the session to close it.');
                array_splice($room['seats'], $seatIndex, 1);
                $room['updatedAt'] = time(); $sessions[$code] = $room;
                return ['ok' => true, 'left' => true, 'code' => $code];
            }
            if ($action === 'start') {
                ll_require_creator($room, $body['creatorSecret'] ?? '');
                if ($room['status'] !== 'lobby') throw new DomainException('This session has already started.');
                if (count($room['seats']) !== $room['capacity']) throw new DomainException('Waiting for all ' . $room['capacity'] . ' players to join.');
                $priorPlayers = array_map(fn($seat) => ['name' => $seat['name'], 'score' => 0], $room['seats']);
                $room['game'] = ll_create_round($priorPlayers, 0, 1); $room['status'] = 'playing';
                $room['updatedAt'] = time(); $sessions[$code] = $room;
                return ll_public_session($room, $seatIndex);
            }
            if ($room['status'] !== 'playing' || !is_array($room['game'])) throw new DomainException('This session is not in a playable state.');
            $game = $room['game']; $isCurrent = $seatIndex === ($game['currentIndex'] ?? -1);
            $operations = ['ready', 'play', 'target', 'guess', 'hide-priest', 'chancellor-keep', 'chancellor-order', 'next-round'];
            if (!in_array($action, $operations, true)) throw new InvalidArgumentException('Unknown game action.');
            if ($action === 'next-round') {
                if (($game['phase'] ?? '') !== 'roundEnd' || !in_array($seatIndex, $game['result']['winnerIndexes'] ?? [], true)) throw new DomainException('Only a round winner can deal the next round.', 403);
                ll_next_round($game);
            } else {
                if (!$isCurrent) throw new DomainException('It is not your turn. Refresh to see the latest game state.', 403);
            }
            if ($action === 'ready') ll_start_turn($game);
            elseif ($action === 'play') ll_play_card($game, (string)($body['cardId'] ?? ''));
            elseif ($action === 'target') ll_choose_target($game, $body['targetIndex'] ?? null);
            elseif ($action === 'guess') ll_resolve_guard($game, (string)($body['cardKey'] ?? ''));
            elseif ($action === 'hide-priest') ll_hide_priest($game);
            elseif ($action === 'chancellor-keep') ll_chancellor_keep($game, (string)($body['cardId'] ?? ''));
            elseif ($action === 'chancellor-order') ll_chancellor_order($game, $body['orderedIds'] ?? []);
            $room['game'] = $game; $room['updatedAt'] = time();
            if (!empty($game['gameOver'])) $room['status'] = 'finished';
            $sessions[$code] = $room;
            return ll_public_session($room, $seatIndex);
        }, true);
        ll_api_json($payload);
    } catch (Throwable $e) {
        $status = $e instanceof DomainException && in_array($e->getCode(), [403], true) ? 403 : 400;
        ll_api_error($e->getMessage(), $status);
    }
}
