/*
 * What a page of the account asks the browser to do for the person reading it: put what the page is
 * showing them into their clipboard (Clipboard API §3). One page shows one such thing and no page shows
 * two: the backup codes of a second factor, shown once, ten of them, and one mistyped is one that will
 * not sign anybody in; or the key of an enrolment, forty characters an app that cannot scan is given by
 * hand.
 *
 * The button is hidden until this script finds a browser that can write a clipboard, so a browser that
 * cannot is never shown a button that does nothing. Where the page shows one value rather than a block of
 * them, the value is clickable too, from the moment a click on it does something and never before:
 * whoever is reading a key clicks the key. The button beside it stays, being what a keyboard reaches, and
 * the value is on the page either way, as text somebody selects: this adds a way and replaces none.
 *
 * Nothing is loaded from anywhere else and nothing is sent anywhere. What is copied is what the page
 * already shows: the script reads the element the button names and holds no value of its own.
 */
(() => {
    'use strict';

    const button = document.querySelector('button[data-copy]');
    if (!button || !navigator.clipboard) {
        return;
    }
    const copies = document.getElementById(button.dataset.copy);
    if (!copies) {
        return;
    }

    const done = document.querySelector('[data-copied]');
    const refused = document.querySelector('[data-copy-refused]');
    /* What is copied is the codes a block holds, one line per item, which is what somebody pastes into
       the file where they keep them, or the value itself where the element holds none. */
    const codes = copies.querySelectorAll('code');

    const copy = async () => {
        const text = codes.length === 0
            ? (copies.textContent ?? '').trim()
            : Array.from(codes, (code) => (code.textContent ?? '').trim()).join('\n');
        try {
            await navigator.clipboard.writeText(text);
            if (done) {
                done.hidden = false;
            }
            if (refused) {
                refused.hidden = true;
            }
        } catch (error) {
            /* A browser that refuses the clipboard says so where the page says everything else, and what
               was to be copied stays where it is. */
            if (refused) {
                refused.hidden = false;
            }
            if (done) {
                done.hidden = true;
            }
        }
    };

    button.hidden = false;
    button.addEventListener('click', copy);

    if (codes.length === 0) {
        /* The stylesheet reads this to say the value is clickable, which it is from here on. */
        copies.dataset.copyClick = '';
        copies.addEventListener('click', copy);
    }
})();
