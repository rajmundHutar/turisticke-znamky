document.addEventListener("DOMContentLoaded", () => {

    initDatepicker()
    initNavbarBurger()
    initModalWindows()
    initConfirmButtons()

});

function ajax(url, params) {

    const searchParams = new URLSearchParams(params);

    const options = {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
    }

    fetch(url + '?' + searchParams.toString(), options)
        .then(function(response) {
            return response.json();
        })
        .then(function(myJson) {

            // Render snippets
            for (const [key, value] of Object.entries(myJson.snippets || {})) {
                document.getElementById(key).innerHTML = value;
            }

        });
}

function initDatepicker() {

    const elems = document.querySelectorAll('input[data-datepicker]');
    elems.forEach((elem)=>{
        const datepicker = new Datepicker(elem, {
            format: {
                toValue(date, format, locale) {
                    return new Date(date);
                },
                toDisplay(date, format, locale) {
                    const day = date.getDate() < 10 ? '0' + date.getDate() : date.getDate();
                    const month = date.getMonth() < 9 ? '0' + (date.getMonth() + 1) : date.getMonth() + 1;
                    return `${date.getFullYear()}-${month}-${day}`;
                },
            },
        });
    })

}

function initNavbarBurger() {

    // Get all "navbar-burger" elements
    const $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);

    // Add a click event on each of them
    $navbarBurgers.forEach( el => {
        el.addEventListener('click', () => {

            // Get the target from the "data-target" attribute
            const target = el.dataset.target;
            const $target = document.getElementById(target);

            // Toggle the "is-active" class on both the "navbar-burger" and the "navbar-menu"
            el.classList.toggle('is-active');
            $target.classList.toggle('is-active');

        });
    });

}

function initModalWindows() {

    document.querySelectorAll('.stamp-edit-button').forEach((el) => {
        var stampid = el.dataset.stampid
        el.addEventListener('click', () => {
            document.querySelector(`.modal[data-stampid="${stampid}"]`).classList.add('is-active')
        })
    })

    // Functions to open and close a modal
    function openModal($el) {
        $el.classList.add('is-active');
    }

    function closeModal($el) {
        $el.classList.remove('is-active');
    }

    function closeAllModals() {
        (document.querySelectorAll('.modal') || []).forEach(($modal) => {
            closeModal($modal);
        });
    }

    // Add a click event on buttons to open a specific modal
    (document.querySelectorAll('.js-modal-trigger') || []).forEach(($trigger) => {
        const modal = $trigger.dataset.target;
        const $target = document.getElementById(modal);

        $trigger.addEventListener('click', () => {
            openModal($target);
        });
    });

    // Add a click event on various child elements to close the parent modal
    (document.querySelectorAll('.modal-background, .modal-close, .modal-card-head .delete, .modal-card-foot .button') || []).forEach(($close) => {
        const $target = $close.closest('.modal');

        $close.addEventListener('click', () => {
            closeModal($target);
        });
    });

    // Add a keyboard event to close all modals
    document.addEventListener('keydown', (event) => {
        const e = event || window.event;

        if (e.keyCode === 27) { // Escape key
            closeAllModals();
        }
    });

}

function initConfirmButtons() {

    const elems = document.querySelectorAll('.confirm');
    elems.forEach((elem)=>{
        elem.addEventListener('click', (e) => {
            const result = confirm('Really commit action?')
            if (!result) {
                e.preventDefault();
            }
        });
    })

}
