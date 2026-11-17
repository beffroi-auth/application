/*
 * The script of a picture (issue #462), after templates/visualFlow.js of the WebAuthn demonstration of
 * Symfony Live Paris 2025: one marker between the actors, moved by setting which actor it sits at and
 * letting the stylesheet move it; one hop revealed at a time, with the payload that travelled; a step
 * by step switch and a next button, so the flow waits for a click between two hops.
 *
 * It narrates a trace and never a flow: what it shows was recorded, either by the demonstration taking
 * the hop or by this provider serving it. A hop nobody took is on the plan below and has nothing to
 * show.
 */
(() => {
    'use strict';

    const flow = document.getElementById('flow');
    if (flow === null) {
        return;
    }

    const read = (name, fallback) => {
        try {
            return JSON.parse(flow.dataset[name] || fallback);
        } catch {
            return JSON.parse(fallback);
        }
    };

    const actors = read('actors', '[]');
    const driven = flow.dataset.driven === '1';
    const advance = flow.dataset.advance;
    const marker = document.getElementById('marker');
    const name = document.getElementById('hop-name');
    const where = document.getElementById('hop-where');
    const payload = document.getElementById('hop-payload');
    const jwts = document.getElementById('hop-jwts');
    const state = document.getElementById('state');
    const next = document.getElementById('next');
    const play = document.getElementById('play');
    const stepByStep = document.getElementById('step-by-step');

    /* Two seconds between two hops when nobody is clicking, as the demonstration this follows does. */
    const PACE = 2000;

    let hops = read('trace', '[]');
    let shown = -1;
    let playing = null;
    let asking = false;

    const show = (index) => {
        const hop = hops[index];
        if (hop === undefined) {
            return;
        }
        shown = index;
        flow.style.setProperty('--at', String(Math.max(actors.indexOf(hop.to), 0)));
        marker.toggleAttribute('data-failed', hop.failed === true);
        for (const actor of flow.querySelectorAll('.actor')) {
            actor.toggleAttribute('data-here', actor.dataset.actor === hop.to);
        }
        name.innerHTML = '';
        const named = document.createElement('code');
        named.textContent = hop.name;
        name.append(named);
        if (hop.failed === true) {
            const failed = document.createElement('span');
            failed.className = 'failed';
            failed.textContent = ' ' + (hop.reason || 'refused');
            name.append(failed);
        }
        where.textContent = [
            hop.from + ' → ' + hop.to,
            hop.specification,
            hop.method ? hop.method + ' ' + hop.url : null,
            hop.status ? 'answered ' + hop.status : null,
            hop.at,
        ].filter(Boolean).join('  ·  ');
        payload.textContent = hop.indented || hop.raw || '(nothing travelled)';
        jwts.innerHTML = '';
        for (const jwt of hop.jwts || []) {
            jwts.append(drawn(jwt));
        }
        for (const planned of flow.querySelectorAll('ol.plan li')) {
            planned.toggleAttribute('data-current', planned.dataset.hop === hop.name);
        }
        state.textContent = (index + 1) + ' of ' + hops.length;
    };

    /* A compact JWT read: the token as it travelled, its header, and its claims when they are readable. */
    const drawn = (jwt) => {
        const card = document.createElement('div');
        card.className = 'card';
        const heading = document.createElement('h2');
        heading.textContent = jwt.name + (jwt.claims === null ? ' (encrypted)' : '');
        card.append(heading);
        for (const part of [jwt.raw, jwt.header, jwt.claims]) {
            if (part === null || part === undefined) {
                continue;
            }
            const block = document.createElement('pre');
            block.textContent = part;
            card.append(block);
        }

        return card;
    };

    const take = async () => {
        if (shown + 1 < hops.length) {
            show(shown + 1);

            return true;
        }
        if (!driven || asking) {
            state.textContent = hops.length === 0 ? 'nothing recorded yet' : 'the whole trace is drawn';

            return false;
        }
        asking = true;
        next.disabled = true;
        state.textContent = 'taking the next hop…';
        try {
            const response = await fetch(advance, {method: 'POST', headers: {'Accept': 'application/json'}});
            const drawing = await response.json();
            if (!response.ok) {
                state.textContent = drawing.error || ('the provider answered ' + response.status);

                return false;
            }
            const grown = drawing.hops.length > hops.length;
            hops = drawing.hops;
            replan(drawing.plan);
            if (grown) {
                show(shown + 1);
            } else {
                state.textContent = 'the flow has nothing left to take';
            }

            return grown;
        } catch (error) {
            state.textContent = String(error);

            return false;
        } finally {
            asking = false;
            next.disabled = false;
        }
    };

    /* How many times each hop of the plan has been taken, as the trace grows. */
    const replan = (plan) => {
        for (const planned of plan) {
            const row = flow.querySelector('ol.plan li[data-hop="' + planned.name + '"]');
            if (row === null) {
                continue;
            }
            if (planned.taken > 0) {
                row.setAttribute('data-taken', String(planned.taken));
            } else {
                row.removeAttribute('data-taken');
            }
        }
    };

    const stop = () => {
        if (playing !== null) {
            clearInterval(playing);
            playing = null;
        }
        play.textContent = 'play';
    };

    next.addEventListener('click', () => {
        stop();
        void take();
    });

    play.addEventListener('click', () => {
        if (playing !== null) {
            stop();

            return;
        }
        play.textContent = 'pause';
        playing = setInterval(async () => {
            if (!await take()) {
                stop();
            }
        }, PACE);
        void take();
    });

    stepByStep.addEventListener('change', () => {
        if (stepByStep.checked) {
            stop();
        }
    });

    if (hops.length > 0) {
        show(0);
    } else {
        state.textContent = driven ? 'nothing taken yet' : 'nothing recorded yet';
        payload.textContent = '';
    }
})();
