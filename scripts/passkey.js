/*
 * The one script of the provider (architecture §5.2): the browser side of the two WebAuthn ceremonies.
 * A form marked data-passkey="login" or data-passkey="enrol" names, in data-options-url, where the
 * options come from; the script fetches them, hands them to the authenticator of the browser
 * (navigator.credentials.get or .create, WebAuthn Level 3 §5.1.3 and §5.1.4), writes what it signed
 * into the hidden "_passkey" field and submits the form, so that the response is a redirection the
 * browser follows, as with the password form. A login form also asks the browser to offer the passkeys
 * it holds in the username field (conditional mediation, §5.1.3.1) when it can; a click on the button
 * cancels that and asks outright. What a refusal is said in is the element marked data-passkey-error of
 * that form, or the one of the page when the form carries none, a form of an action being a button and
 * its hidden fields and not the place a sentence is read. Nothing is loaded from anywhere else.
 */
(() => {
    'use strict';

    const supported = typeof window.PublicKeyCredential === 'function'
        && typeof PublicKeyCredential.parseRequestOptionsFromJSON === 'function'
        && typeof PublicKeyCredential.parseCreationOptionsFromJSON === 'function';

    const fetchOptions = async (url) => {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) {
            throw new Error(`The options could not be fetched (${response.status}).`);
        }

        return response.json();
    };

    const submit = (form, credential) => {
        form.querySelector('input[name="_passkey"]').value = JSON.stringify(credential.toJSON());
        form.submit();
    };

    const fail = (form, error) => {
        if (error && error.name === 'AbortError') {
            return;
        }
        const message = form.querySelector('[data-passkey-error]') ?? document.querySelector('[data-passkey-error]');
        if (message) {
            message.hidden = false;
        }
    };

    const login = (form) => {
        let conditional = null;
        const get = async (mediation) => {
            const options = await fetchOptions(form.dataset.optionsUrl);
            const request = { publicKey: PublicKeyCredential.parseRequestOptionsFromJSON(options) };
            if (mediation === 'conditional') {
                conditional = new AbortController();
                request.mediation = 'conditional';
                request.signal = conditional.signal;
            }

            return navigator.credentials.get(request);
        };

        form.querySelector('[data-passkey-button]').addEventListener('click', async () => {
            if (conditional) {
                conditional.abort();
                conditional = null;
            }
            try {
                submit(form, await get('default'));
            } catch (error) {
                fail(form, error);
            }
        });

        if (typeof PublicKeyCredential.isConditionalMediationAvailable !== 'function') {
            return;
        }
        PublicKeyCredential.isConditionalMediationAvailable().then(async (available) => {
            if (!available) {
                return;
            }
            try {
                submit(form, await get('conditional'));
            } catch (error) {
                fail(form, error);
            }
        });
    };

    const enrol = (form) => {
        form.querySelector('[data-passkey-button]').addEventListener('click', async () => {
            if (!form.reportValidity()) {
                return;
            }
            try {
                const options = await fetchOptions(form.dataset.optionsUrl);
                const credential = await navigator.credentials.create({
                    publicKey: PublicKeyCredential.parseCreationOptionsFromJSON(options),
                });
                submit(form, credential);
            } catch (error) {
                fail(form, error);
            }
        });
    };

    const start = () => {
        if (!supported) {
            return;
        }
        for (const unsupported of document.querySelectorAll('[data-passkeys-unsupported]')) {
            unsupported.hidden = true;
        }
        for (const form of document.querySelectorAll('form[data-passkey]')) {
            form.hidden = false;
            if (form.dataset.passkey === 'login') {
                login(form);
            } else if (form.dataset.passkey === 'enrol') {
                enrol(form);
            }
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
