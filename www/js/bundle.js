document.addEventListener("DOMContentLoaded", () => {

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

    document.querySelectorAll('.stamp-edit-button').forEach((el) => {
        var stampid = el.dataset.stampid
        el.addEventListener('click', () => {
            document.querySelector(`.modal[data-stampid="${stampid}"]`).classList.add('is-active')
        })
    })

    document.querySelectorAll('.modal-close').forEach((el) => {
        el.addEventListener('click', () => {
            document.querySelector(`.modal.is-active`).classList.remove('is-active')
        })
    })

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
