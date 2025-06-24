var forms = [];

document.addEventListener("DOMContentLoaded", function () {
    initTurnstileForms();
});

document.addEventListener('runControllerSuccessful', function (event) {
    var controllers = ['contact', 'discussions', 'events'];

    if (controllers.includes(event.detail)) {
        initTurnstileForms();
    }
});

function loadCloudflareFile() {

    var src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

    if (document.querySelector('script[src="' + src + '"]') === null) {
        var script = document.createElement('script');
        script.src = src;
        script.crossOrigin = 'anonymous';
        script.defer = true;
        script.async = true;
        script.onload = function () {
            renderAutoloadValidations();
        }

        document.head.appendChild(script);
    } else {
        renderAutoloadValidations();
    }


}

function renderTurnstileValidations(form, dataAction = '', elementAfterTurnstileWidget = null) {

    if (!dataAction) {
        dataAction = 'default';
    }

    var id = 'turnstile-'+dataAction;

    if (!elementAfterTurnstileWidget) {
        elementAfterTurnstileWidget = getElementAfterTurnstileWidget(form);
    }

    // Check if turnstile element already exits
    var turnstileWidget = elementAfterTurnstileWidget.parentElement.querySelector('#'+id);

    if (!turnstileWidget) {
        // Check if there is a global widget in a different form with same name
        var turnstileWidgetGlobal = document.getElementById(id);

        if (turnstileWidgetGlobal) {
            // move element
            elementAfterTurnstileWidget.parentElement.insertBefore(turnstileWidgetGlobal, elementAfterTurnstileWidget);
            window.turnstile.reset('#' + id);
        } else {

            // Create turnstile container
            turnstileWidget = document.createElement("div");
            turnstileWidget.id = id;
            turnstileWidget.setAttribute('data-sitekey', turnstileSiteKey);
            turnstileWidget.style.margin = '20px 0 10px 0';
            turnstileWidget.setAttribute('data-action', dataAction);

            var where = 'beforebegin';

            elementAfterTurnstileWidget.insertAdjacentElement(where, turnstileWidget);

            window.turnstile.render('#' + id);
        }
    }

}

function renderAutoloadValidations() {
    forms.forEach(function (formObj) {
        if (formObj.autoload && turnstile) {
            renderTurnstileValidations(formObj.htmlElement, formObj.submitName);
        }
    });
}

document.addEventListener('input', function (event) {
    var form = event.target.closest('form');
    forms.forEach(function (formObj) {
        if (turnstile && formObj.htmlElement===form) {
            renderTurnstileValidations(formObj.htmlElement, formObj.submitName);
        }
    });
});


function initTurnstileForms(autoload = true) {

    if (submitsToCheck && turnstileSiteKey) {

        for (const submitToCheck in submitsToCheck) {

            var buttons = document.querySelectorAll('form [name="'+submitToCheck+'"]');

            if (!buttons.length) {
                buttons = document.querySelectorAll('form[name="'+submitToCheck+'"] button');
            }

            if (buttons.length) {

                buttons.forEach(function (button) {
                    var form = button.closest('form');

                    if (form.hasAttribute('data-turnstile-autoload')) {
                        autoload = form.getAttribute('data-turnstile-autoload');
                    }
                    else {
                        autoload = submitsToCheck[submitToCheck];
                    }

                    if (autoload==='false') {
                        autoload = false;
                    }
                    else if (autoload==='true') {
                        autoload = true;
                    }

                    forms.push ({
                        htmlElement: form,
                        submitName: submitToCheck,
                        button: button,
                        autoload: autoload
                    });
                });
            }
        }

        if (forms.length) {
            loadCloudflareFile();
        }

    }
}

function getElementAfterTurnstileWidget(form) {

    var turnstileElement = form.querySelector('.turnstileElement');

    if (turnstileElement) {
        return turnstileElement;
    }

    var button = form.querySelector('button');

    if (!button) {
        button = form.querySelector('[type="submit"]');
    }

    return button;
}
