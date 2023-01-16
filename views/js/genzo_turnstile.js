document.addEventListener("DOMContentLoaded", function () {
    if (submitsToCheck && turnstileSiteKey) {
        var div = document.createElement("div");
        div.classList.add('cf-turnstile');
        div.setAttribute('data-sitekey', turnstileSiteKey);
        div.style.margin = '25px 0 0 0';

        for (const submitToCheck in submitsToCheck) {
            var button = document.querySelector('form [name="'+submitToCheck+'"]');
            if (button) {
                div.setAttribute('data-action', submitsToCheck[submitToCheck]);
                button.insertAdjacentElement('beforebegin', div);
            }
        }
    }
});

