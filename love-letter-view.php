<section class="ll-wrap" id="love-letter-app" aria-labelledby="ll-page-title">
    <nav class="ll-nav" aria-label="Game navigation">
        <a class="ll-back-link" href="?view=terminal">&larr; Back to maimai queue</a>
        <span class="ll-edition-badge">CURRENT 21-CARD EDITION · 2–6 PLAYERS</span>
    </nav>

    <header class="ll-hero">
        <span class="ll-eyebrow">A quick game of risk, deduction &amp; royal intrigue</span>
        <h2 id="ll-page-title">LOVE <span>LETTER</span></h2>
        <p>Carry your letter to the Princess. Read the room, protect your hand, and earn her favor.</p>
        <div class="ll-hero-stats"><span>10–20 MIN</span><span>2–6 SUITORS</span><span>21 CHARACTER CARDS</span></div>
    </header>

    <section class="ll-panel ll-setup" id="ll-setup" aria-labelledby="ll-setup-title">
        <div class="ll-panel-heading">
            <div>
                <span class="ll-section-kicker">SET THE TABLE</span>
                <h3 id="ll-setup-title">Who is courting the Princess?</h3>
            </div>
            <span class="ll-seal" aria-hidden="true">♥</span>
        </div>
        <label class="ll-field ll-count-field" for="ll-player-count">
            <span>Players</span>
            <select id="ll-player-count">
                <option value="2">2 players</option>
                <option value="3">3 players</option>
                <option value="4" selected>4 players</option>
                <option value="5">5 players</option>
                <option value="6">6 players</option>
            </select>
        </label>
        <div class="ll-player-fields" id="ll-player-fields"></div>
        <label class="ll-field ll-starter-field" for="ll-first-player">
            <span>Who most recently hand-wrote a letter?</span>
            <select id="ll-first-player"></select>
            <small>That player takes the first turn.</small>
        </label>
        <button class="ll-primary-button" id="ll-start-game" type="button">Deal the first round</button>
        <p class="ll-footnote">Pass-and-play on one device. Cards stay hidden during each handoff; pass the screen to the named player before revealing their turn.</p>
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
