<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $clinicName ?? config('app.name') }}</title>

    <style>
        :root {
            --tv-bg: #0b3d91;
            --tv-fg: #ffffff;
        }
        html, body {
            height: 100%;
        }
        body {
            margin: 0;
            background: var(--tv-bg);
            color: var(--tv-fg);
            font-family: 'Century Gothic', 'AppleGothic', system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .wrap {
            width: min(1200px, 92vw);
            text-align: center;
        }
        .clinic {
            font-size: clamp(28px, 3.2vw, 52px);
            font-weight: 600;
            letter-spacing: 0.5px;
            margin: 0 0 24px;
        }
        .time {
            font-size: clamp(64px, 10vw, 140px);
            font-weight: 800;
            line-height: 1;
            margin: 0 0 18px;
        }
        .patient {
            font-size: clamp(28px, 4.2vw, 64px);
            font-weight: 400;
            margin: 0 0 8px;
        }
        .dossier {
            font-size: clamp(18px, 2.2vw, 34px);
            font-weight: 400;
            opacity: 0.95;
            margin: 0;
        }
        .empty {
            font-size: clamp(22px, 3vw, 42px);
            font-weight: 600;
            margin: 0;
        }
        .overlay {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            text-align: center;
            background: rgba(11, 61, 145, 0.94);
            padding: 24px;
        }
        .overlay.show { display: flex; }
        .overlay-card {
            max-width: 760px;
        }
        .overlay-title {
            font-size: clamp(24px, 3vw, 42px);
            font-weight: 700;
            margin: 0 0 10px;
        }
        .overlay-sub {
            font-size: clamp(16px, 2vw, 26px);
            opacity: 0.95;
            margin: 0;
        }
    </style>
</head>
<body>
@php
    $time = $time ?? null;
    $slotIso = $slotIso ?? null;
    $rdvItems = $rdvItems ?? [];
    $message = $message ?? null;
@endphp

    <main class="wrap">
        <!-- <h4 class="clinic">{{ $clinicName ?? config('app.name') }}</h4> -->
        <h2 class="clinic">{{ __('Prochain rendez-vous') }}</h2>
        <p class="empty" id="emptyEl" @if (!empty($rdvItems)) style="display:none" @endif>{{ $message ?: 'Aucun rendez-vous' }}</p>

        <div class="time" id="timeEl" @if (empty($rdvItems)) style="display:none" @endif>{{ $time ?? '--:--' }}</div>

        <div id="itemsEl" @if (empty($rdvItems)) style="display:none" @endif>
            @foreach ($rdvItems as $it)
                <div class="patient">{{ $it['patientId'] }} - {{ $it['patientName'] }}</div>
                <p class="dossier">
                    {{ $it['motif'] }}
                    @if (!empty($it['medecin']))
                        avec Dr {{ $it['medecin'] }}
                    @endif
                </p>
            @endforeach
        </div>
    </main>

    <div class="overlay" id="audioOverlay" role="button" aria-label="Activer l'audio">
        <div class="overlay-card">
            <p class="overlay-title">Touchez pour activer l'audio</p>
            <p class="overlay-sub">L'appel vocal peut être bloqué sans interaction utilisateur.</p>
        </div>
    </div>

    <script>
        (function () {
            let slotIso = @json($slotIso);
            let rdvItems = @json($rdvItems);
            const refreshSeconds = @json((int)($refreshSeconds ?? 15));

            const emptyEl = document.getElementById('emptyEl');
            const timeEl = document.getElementById('timeEl');
            const itemsEl = document.getElementById('itemsEl');
            const overlay = document.getElementById('audioOverlay');

            // Certains navigateurs bloquent l'audio sans interaction utilisateur.
            // On "prépare" la synthèse au premier clic/tap.
            let audioUnlocked = localStorage.getItem('tv_audio_unlocked') === '1';
            function requestFullscreen() {
                try {
                    const el = document.documentElement;
                    const fn = el.requestFullscreen
                        || el.webkitRequestFullscreen
                        || el.mozRequestFullScreen
                        || el.msRequestFullscreen;
                    if (fn) fn.call(el);
                } catch (e) {
                    // silencieux
                }
            }
            function unlockOnce() {
                audioUnlocked = true;
                try { localStorage.setItem('tv_audio_unlocked', '1'); } catch (e) {}
                requestFullscreen();
                document.removeEventListener('click', unlockOnce);
                document.removeEventListener('touchstart', unlockOnce);
                if (overlay) overlay.classList.remove('show');
            }
            document.addEventListener('click', unlockOnce, { once: true });
            document.addEventListener('touchstart', unlockOnce, { once: true });

            if (overlay) {
                if (!audioUnlocked) overlay.classList.add('show');
                overlay.addEventListener('click', unlockOnce);
                overlay.addEventListener('touchstart', unlockOnce);
            }

            function getAllVoices() {
                try {
                    return (window.speechSynthesis && window.speechSynthesis.getVoices)
                        ? (window.speechSynthesis.getVoices() || [])
                        : [];
                } catch (e) {
                    return [];
                }
            }

            function pickFemaleFrVoice() {
                const voices = getAllVoices();
                if (!voices.length) return null;

                // NB: l'API ne fournit pas un champ "gender" standard.
                // On applique donc une heuristique sur le nom + la langue.
                const fr = voices.filter(v => (v.lang || '').toLowerCase().startsWith('fr'));
                const candidates = fr.length ? fr : voices;

                const preferredNameParts = [
                    'female',
                    'femme',
                    'amélie',
                    'amelie',
                    'marie',
                    'julie',
                    'charlotte',
                ];

                const byName = candidates.find(v => {
                    const name = String(v.name || '').toLowerCase();
                    return preferredNameParts.some(p => name.includes(p));
                });
                return byName || candidates[0] || null;
            }

            let cachedVoice = null;
            function resolveVoice() {
                if (cachedVoice) return cachedVoice;
                cachedVoice = pickFemaleFrVoice();
                return cachedVoice;
            }

            // Certaines plateformes ne chargent les voix qu'après l'événement voiceschanged.
            if ('speechSynthesis' in window) {
                try {
                    window.speechSynthesis.addEventListener('voiceschanged', () => {
                        cachedVoice = pickFemaleFrVoice();
                    });
                } catch (e) {
                    // silencieux
                }
            }

            function speakOnce(text) {
                return new Promise((resolve) => {
                    if (!('speechSynthesis' in window) || !('SpeechSynthesisUtterance' in window)) return resolve(false);

                    const u = new SpeechSynthesisUtterance(text);
                    u.lang = 'fr-FR';
                    u.rate = 1;
                    u.pitch = 1;
                    const v = resolveVoice();
                    if (v) u.voice = v;

                    let done = false;
                    const finish = () => {
                        if (done) return;
                        done = true;
                        resolve(true);
                    };

                    u.onend = finish;
                    u.onerror = finish;

                    try {
                        window.speechSynthesis.speak(u);
                    } catch (e) {
                        return resolve(false);
                    }

                    // Fallback: si onend ne se déclenche pas, on libère au bout de 12s.
                    setTimeout(finish, 12000);
                });
            }

            function waitMs(ms) {
                return new Promise((r) => setTimeout(r, ms));
            }

            function shouldAnnounce(nowMs, rdvMs) {
                // Annonce à partir de l'heure du RDV, pendant une fenêtre de 10 minutes
                return nowMs >= rdvMs && (nowMs - rdvMs) <= 10 * 60 * 1000;
            }

            const queue = [];
            let isRunning = false;

            async function runQueue() {
                if (isRunning) return;
                isRunning = true;
                try {
                    while (queue.length) {
                        const item = queue.shift();
                        if (!item) continue;

                        const { id, phrase } = item;
                        const doneKey = 'tv_announce_done_' + id;
                        const queuedKey = 'tv_announce_queued_' + id;
                        if (localStorage.getItem(doneKey) === '1') continue;

                        // 1ère annonce
                        const ok1 = await speakOnce(phrase);
                        if (!ok1) {
                            // Si la synthèse échoue (souvent à cause d'un blocage navigateur),
                            // on ne marque pas comme "fait" et on autorise un retry.
                            try { localStorage.removeItem(queuedKey); } catch (e) {}
                            continue;
                        }
                        // Attente 15s
                        await waitMs(15000);
                        // 2ème annonce
                        const ok2 = await speakOnce(phrase);
                        if (!ok2) {
                            try { localStorage.removeItem(queuedKey); } catch (e) {}
                            continue;
                        }

                        try {
                            localStorage.setItem(doneKey, '1');
                        } catch (e) {
                            // silencieux
                        }
                    }
                } finally {
                    isRunning = false;
                }
            }

            function tick() {
                const slotMs = Date.parse(slotIso);
                if (!isFinite(slotMs)) return;
                const nowMs = Date.now();
                if (!shouldAnnounce(nowMs, slotMs)) return;

                if (!audioUnlocked) return;

                for (const it of (rdvItems || [])) {
                    const id = String(it.id || it.iso || '');
                    if (!id) continue;

                    const doneKey = 'tv_announce_done_' + id;
                    const queuedKey = 'tv_announce_queued_' + id;
                    if (localStorage.getItem(doneKey) === '1') continue;
                    if (localStorage.getItem(queuedKey) === '1') continue;

                    const patientName = String(it.patientName || '').trim();
                    if (!patientName) continue;

                    const patientId = String(it.patientId || '').trim();

                    const base = patientId
                        ? (patientName + ', dossier ' + patientId)
                        : patientName;

                    const phrase = 'Prochain rendez-vous. ' + base;

                    // On marque "queued" tout de suite pour éviter que tick() ne l'ajoute plusieurs fois.
                    localStorage.setItem(queuedKey, '1');
                    queue.push({ id, phrase });
                }

                if (queue.length) runQueue();
            }

            function render(data) {
                const items = (data && Array.isArray(data.rdvItems)) ? data.rdvItems : [];
                const time = (data && typeof data.time === 'string' && data.time) ? data.time : '--:--';
                const message = (data && typeof data.message === 'string' && data.message) ? data.message : '';

                slotIso = data ? data.slotIso : null;
                rdvItems = items;

                if (!items.length) {
                    if (emptyEl) {
                        emptyEl.textContent = message || 'Aucun rendez-vous';
                        emptyEl.style.display = '';
                    }
                    if (timeEl) timeEl.style.display = 'none';
                    if (itemsEl) itemsEl.style.display = 'none';
                    return;
                }

                if (emptyEl) emptyEl.style.display = 'none';
                if (timeEl) {
                    timeEl.textContent = time;
                    timeEl.style.display = '';
                }
                if (itemsEl) {
                    itemsEl.style.display = '';
                    itemsEl.innerHTML = items.map(it => {
                        const pid = String(it.patientId || '');
                        const name = String(it.patientName || '');
                        const motif = String(it.motif || '');
                        const med = String(it.medecin || '');
                        const medText = med ? (' avec Dr ' + med) : '';
                        return (
                            '<div class="patient">' + escapeHtml(pid) + ' - ' + escapeHtml(name) + '</div>' +
                            '<p class="dossier">' + escapeHtml(motif) + medText + '</p>'
                        );
                    }).join('');
                }
            }

            function escapeHtml(s) {
                return String(s)
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            async function refreshData() {
                try {
                    const res = await fetch('/tv/data', { cache: 'no-store' });
                    if (!res.ok) return;
                    const data = await res.json();
                    render(data);
                } catch (e) {
                    // silencieux
                }
            }

            // Vérifie toutes les secondes
            setInterval(tick, 1000);
            tick();

            // Met à jour la liste sans recharger la page (ne casse pas la voix)
            setInterval(refreshData, Math.max(5, refreshSeconds) * 1000);
            refreshData();
        })();
    </script>
</body>
</html>
