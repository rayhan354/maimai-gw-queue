<section class="ll-wrap" id="love-letter-app" aria-labelledby="ll-page-title">
    <nav class="ll-nav" aria-label="Game navigation">
        <a class="ll-back-link" href="?view=terminal">&larr; Back to maimai queue</a>
        <span class="ll-edition-badge">CURRENT 21-CARD EDITION · 2–6 PLAYERS</span>
    </nav>

    <header class="ll-hero">
        <span class="ll-eyebrow">A quick game of risk, deduction &amp; royal intrigue</span>
        <h2 id="ll-page-title">LOVE <span>LETTER</span></h2>
        <p>Each player joins from their own device. Your hand is private to your seat. Sessions auto-delete after 30 minutes of inactivity unless the creator chooses to keep them indefinitely.</p>
        <div class="ll-hero-stats"><span>10–20 MIN</span><span>2–6 PLAYERS</span><span>PRIVATE HANDS</span><span>SAVED SESSIONS</span></div>
    </header>

    <section class="ll-panel ll-setup" id="ll-session-home" aria-labelledby="ll-setup-title">
        <div class="ll-panel-heading">
            <div>
                <span class="ll-section-kicker">PLAY TOGETHER, EACH ON YOUR OWN DEVICE</span>
                <h3 id="ll-setup-title">Create or join a session</h3>
            </div>
            <span class="ll-seal" aria-hidden="true">♥</span>
        </div>
        <div class="ll-session-options">
            <form class="ll-session-form" id="ll-create-form">
                <h4>Create a saved session</h4>
                <label class="ll-field" for="ll-create-name"><span>Your player name</span><input id="ll-create-name" name="name" maxlength="18" autocomplete="nickname" required placeholder="e.g. Alex"></label>
                <label class="ll-field" for="ll-create-capacity"><span>Players in this game</span><select id="ll-create-capacity" name="capacity"><option value="2">2 players</option><option value="3">3 players</option><option value="4" selected>4 players</option><option value="5">5 players</option><option value="6">6 players</option></select></label>
                <label class="ll-retention-choice" for="ll-create-keep-indefinitely"><input id="ll-create-keep-indefinitely" type="checkbox" name="keepIndefinitely" value="1"><span><strong>Keep this session indefinitely</strong><small>Otherwise, it is automatically deleted after 30 minutes without a session action. You can change this later.</small></span></label>
                <button class="ll-primary-button" type="submit">Create session</button>
                <p class="ll-footnote">You’ll get an 8-character invite code. You can change the save policy later; an admin can delete sessions at any time.</p>
            </form>
            <form class="ll-session-form" id="ll-join-form">
                <h4>Join an existing session</h4>
                <label class="ll-field" for="ll-join-code"><span>Session code</span><input id="ll-join-code" name="code" maxlength="8" autocapitalize="characters" autocomplete="off" required placeholder="ABCD2345"></label>
                <label class="ll-field" for="ll-join-name"><span>Your player name</span><input id="ll-join-name" name="name" maxlength="18" autocomplete="nickname" required placeholder="e.g. Sam"></label>
                <button class="ll-secondary-button ll-join-button" type="submit">Join session</button>
                <p class="ll-footnote">Ask the session creator to share the code. Once the game starts, no more seats can join.</p>
            </form>
        </div>
        <details class="ll-recover-details">
            <summary>Resume the creator’s session on this device</summary>
            <form class="ll-session-form ll-recover-form" id="ll-recover-form">
                <label class="ll-field" for="ll-recover-code"><span>Session code</span><input id="ll-recover-code" name="code" maxlength="8" autocomplete="off" required></label>
                <label class="ll-field" for="ll-recover-secret"><span>Creator recovery key</span><input id="ll-recover-secret" name="creatorSecret" autocomplete="off" required></label>
                <button class="ll-secondary-button ll-join-button" type="submit">Recover creator access</button>
                <p class="ll-footnote">The recovery key is shown once when you create the session. Save it somewhere safe; it can restore your creator seat and delete your session.</p>
            </form>
        </details>
        <p class="ll-footnote" id="ll-home-message" role="status"></p>
    </section>

    <section class="ll-match" id="ll-match" hidden aria-live="polite"></section>

    <details class="ll-panel ll-rules">
        <summary><span>How to play</span><span class="ll-summary-mark" aria-hidden="true">+</span></summary>
        <div class="ll-rules-content">
            <p><strong>Goal.</strong> Win rounds to earn favor tokens. Reach the target first: 6 tokens with 2 players, 5 with 3, 4 with 4, or 3 with 5–6 players.</p>
            <p><strong>Set up each round.</strong> Shuffle all 21 cards. Set one card aside face down without looking. With 2 players, also set aside three cards face up. Deal one card to each player. The starting player draws one card, then plays one of the two cards in their hand.</p>
            <p><strong>Turns and round end.</strong> Play the selected card face up and resolve its effect. Keep the other card. A player knocked out reveals and discards their hand without resolving it, then skips the rest of the round. The round ends when only one player remains or the draw deck runs out. If it runs out, the highest-value hand wins; ties share the round and each tied player gets a token. A Spy bonus is awarded separately when exactly one player still in the round has played or discarded a Spy.</p>
            <p><strong>Starting another round.</strong> Shuffle and redeal. The previous round’s winner starts; after a tie, one tied winner is chosen at random. Scores carry over.</p>
            <div class="ll-rules-grid">
                <article><b>9 · Princess ×1</b><span>If you play or discard her for any reason, you are out of the round.</span></article>
                <article><b>8 · Countess ×1</b><span>No play effect. You must play her if your other card is the King or a Prince.</span></article>
                <article><b>7 · King ×1</b><span>Trade hands with another player still in the round.</span></article>
                <article><b>6 · Chancellor ×2</b><span>Draw up to two cards. Keep one of the three cards in your hand and return the others face down to the bottom in any order. With no cards left in the deck, this has no effect.</span></article>
                <article><b>5 · Prince ×2</b><span>Choose any player, including yourself. They discard their hand and draw a new card. If the discarded card is the Princess, they are out and do not draw. If the deck is empty, draw the set-aside card.</span></article>
                <article><b>4 · Handmaid ×2</b><span>You cannot be chosen for other players’ card effects until the start of your next turn.</span></article>
                <article><b>3 · Baron ×2</b><span>Secretly compare hands with another player. The lower card’s holder is out; a tie has no effect.</span></article>
                <article><b>2 · Priest ×2</b><span>Secretly look at another player’s hand.</span></article>
                <article><b>1 · Guard ×6</b><span>Name a character other than Guard and choose another player. If they hold that character, they are out.</span></article>
                <article><b>0 · Spy ×2</b><span>No play effect. At round end, if you are the only player still in the round who played or discarded a Spy, gain one extra token. This is in addition to a round-win token.</span></article>
            </div>
            <p class="ll-rules-note"><strong>Protection edge case:</strong> If everyone else still in the round is protected by a Handmaid, Guard, Priest, Baron, or King has no effect. For Prince, you must choose yourself if all other players are protected. Playing or discarding the Princess always knocks you out.</p>
        </div>
    </details>
</section>
