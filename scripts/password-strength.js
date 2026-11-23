/*
 * What the page where a person poses a password tells them as they type it (architecture §5.2): how hard
 * the password they are typing would be to guess, on the five rungs the server counts on
 * (Beffroi\Core\Domain\Credential\PasswordStrength).
 *
 * Nothing leaves the browser. It is not a verdict and it does not ask for one: a list of compromised
 * passwords is asked by the server when the form is posted, because calling one from here would put an
 * origin that is not the application's into the Content-Security-Policy of every page, which the provider
 * does not do (ProviderScript), and because a verdict a browser brings back is a boolean from a third party,
 * which nothing of this provider casts (decision 0006).
 *
 * **It is a hint and the server is what pronounces.** The same estimate is written twice, here and in the
 * core, and the two are held together by vectors a test of the core pins rather than by a runner in this
 * repository; where they disagree, the refusal is the server's. A browser running no script at all poses
 * passwords through the same form and is refused by the same rules.
 *
 * The field marked data-password-strength names, in that attribute, the element the rung is written into;
 * data-strength-labels carries the five words for it, in the language of the page, and
 * data-strength-floor the rung the server asks for. The estimate counts the characters of the password, its
 * code points, as the core counts them, so a password of accents is worth here what it is worth there.
 */
(() => {
    'use strict';

    const field = document.querySelector('input[data-password-strength]');
    if (!field) {
        return;
    }
    const meter = document.getElementById(field.dataset.passwordStrength);
    if (!meter) {
        return;
    }

    let labels;
    try {
        labels = JSON.parse(field.dataset.strengthLabels ?? '[]');
    } catch (error) {
        return;
    }
    if (!Array.isArray(labels) || labels.length !== 5) {
        return;
    }
    const floor = Number.parseInt(field.dataset.strengthFloor ?? '0', 10);

    /* The alphabets a character can belong to and what each one adds to the pool, as PasswordStrength
       counts them: one character belongs to one alphabet, and a password that draws from an alphabet at all
       pays for the whole of it. */
    const poolOf = (characters) => {
        let control = 0;
        let digits = 0;
        let capitals = 0;
        let letters = 0;
        let symbols = 0;
        let beyond = 0;
        for (const character of characters) {
            const code = character.codePointAt(0);
            if (code < 32 || code === 127) {
                control = 33;
            } else if (code >= 48 && code <= 57) {
                digits = 10;
            } else if (code >= 65 && code <= 90) {
                capitals = 26;
            } else if (code >= 97 && code <= 122) {
                letters = 26;
            } else if (code >= 128) {
                beyond = 128;
            } else {
                symbols = 33;
            }
        }

        return control + digits + capitals + letters + symbols + beyond;
    };

    /* The distinct characters pay for the pool they are drawn from, the repeated ones for the alphabet the
       password itself turned out to use, which is smaller. Array.from splits a string into code points,
       where its length would count the halves of a surrogate pair. */
    const bitsOf = (value) => {
        const characters = Array.from(value);
        if (characters.length === 0) {
            return 0;
        }
        const distinct = new Set(characters);

        return distinct.size * Math.log2(poolOf(distinct))
            + (characters.length - distinct.size) * Math.log2(distinct.size);
    };

    const rungOf = (bits) => {
        if (bits >= 120) {
            return 4;
        }
        if (bits >= 100) {
            return 3;
        }
        if (bits >= 80) {
            return 2;
        }
        if (bits >= 60) {
            return 1;
        }

        return 0;
    };

    const show = () => {
        const value = field.value;
        if (value === '') {
            meter.hidden = true;
            meter.textContent = '';
            delete meter.dataset.strength;
            delete meter.dataset.strengthReached;

            return;
        }
        const rung = rungOf(bitsOf(value));
        meter.textContent = labels[rung];
        meter.dataset.strength = String(rung);
        meter.dataset.strengthReached = rung >= floor ? 'true' : 'false';
        meter.hidden = false;
    };

    field.addEventListener('input', show);
    /* a value a password manager filled in before this ran is read right away */
    show();
})();
