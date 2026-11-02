(function () {
    'use strict';
    const container = document.getElementById('ll-admin-session-list');
    if (!container) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, character => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[character]);
    }

    async function loadSessions() {
        try {
            const url = new URL(window.location.href);
            url.search = '';
            url.searchParams.set('api', 'love-letter-admin-sessions');
            const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.error || 'Could not load saved sessions.');
            if (!result.sessions.length) {
                container.innerHTML = '<p class="ll-footnote">There are no saved Love Letter sessions.</p>';
                return;
            }
            container.innerHTML = result.sessions.map(session => `<article class="ll-admin-session">
                <div><strong>${escapeHtml(session.code)}</strong><span>${escapeHtml(session.status)} · ${session.seatCount}/${session.capacity} players${session.round ? ` · round ${session.round}` : ''}</span><small>${session.players.map(escapeHtml).join(', ')}</small></div>
                <button class="ll-secondary-button" type="button" data-delete-code="${escapeHtml(session.code)}">Delete</button>
            </article>`).join('');
        } catch (error) {
            container.innerHTML = `<p class="ll-error-message">${escapeHtml(error.message)}</p>`;
        }
    }

    container.addEventListener('click', async event => {
        const button = event.target.closest('button[data-delete-code]');
        if (!button) return;
        const code = button.dataset.deleteCode;
        if (!window.confirm(`Permanently delete Love Letter session ${code}? All players will lose access.`)) return;
        button.disabled = true;
        try {
            const url = new URL(window.location.href);
            url.search = '';
            url.searchParams.set('api', 'love-letter-action');
            const response = await fetch(url, {
                method: 'POST', credentials: 'same-origin', headers: {
                    'Content-Type': 'application/json', 'X-CSRF-Token': csrf
                }, body: JSON.stringify({ action: 'delete', code })
            });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.error || 'Could not delete session.');
            await loadSessions();
        } catch (error) {
            button.disabled = false;
            window.alert(error.message);
        }
    });

    loadSessions();
})();
