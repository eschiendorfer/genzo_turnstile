document.addEventListener("DOMContentLoaded", function () {
    if (submitsToCheck && turnstileSiteKey) {
        for (const submitToCheck in submitsToCheck) {
            var button = document.querySelector('form [name="'+submitToCheck+'"]');
            if (button) {
                // Create the div for cloudflare (note: there can be multiple validations on a single site)
                var div = document.createElement("div");
                div.classList.add('cf-turnstile');
                div.setAttribute('data-sitekey', turnstileSiteKey);
                div.style.margin = '25px 0 0 0';
                div.setAttribute('data-action', submitsToCheck[submitToCheck]);

                button.insertAdjacentElement('beforebegin', div);
            }
        }
    }
});

