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

            var modal = document.querySelector(`dialog[data-stampid="${stampid}"]`)
            openModal(modal)
            /*modal.showModal();
            modal.classList.add('is-active')*/
        })
    })

    // Functions to open and close a modal
    function openModal($el) {
        $el.classList.add('is-active');
        $el.showModal();
    }

    function closeModal($el) {
        $el.classList.remove('is-active');
        $el.close();
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

class StampMap {
    constructor(divId, stamps, collection, lat, lng, baseUrl, apiKey) {
        this.divId = divId
        this.stamps = stamps
        this.collection = collection
        this.lat = lat
        this.lng = lng
        this.baseUrl = baseUrl
        this.apiKey = apiKey
        this.initMap()
    };

    initMap() {
        /*
        We create the map and set its initial coordinates and zoom.
        See https://leafletjs.com/reference.html#map
        */
        const map = L.map(this.divId).setView([this.lat, this.lng], 16);

        /*
        Then we add a raster tile layer with Mapy NG tiles
        See https://leafletjs.com/reference.html#tilelayer
        */
        L.tileLayer(`https://api.mapy.cz/v1/maptiles/basic/256/{z}/{x}/{y}?apikey=${this.apiKey}`, {
            minZoom: 0,
            maxZoom: 19,
            attribution: '<a href="https://api.mapy.cz/copyright" target="_blank">&copy; Seznam.cz a.s. a další</a>',
        }).addTo(map);

        var LeafIcon = L.Icon.extend({
            options: {
                iconSize:     [33, 50],
                iconAnchor:   [16, 50],
                popupAnchor:  [0, -50]
            }
        });

        var greenStamp = new LeafIcon({iconUrl: this.baseUrl + '/images/green-stamp.png'}),
        redStamp = new LeafIcon({iconUrl: this.baseUrl + '/images/red-stamp.png'});


        for (var stamp of this.stamps) {
            var isCollected = this.collection[stamp['id']] || null;
            var link = "<a href='" + this.baseUrl + '/stamp/' + stamp['id'] + "'>" + stamp['id'] + ' - ' + stamp['name'] + "</a>";
            L.marker([stamp['lat'], stamp['lng']], {icon: isCollected ? greenStamp : redStamp}).addTo(map).bindPopup(link);
        }

        /*
        We also require you to include our logo somewhere over the map.
        We create our own map control implementing a documented interface,
        that shows a clickable logo.
        See https://leafletjs.com/reference.html#control
        */
        const LogoControl = L.Control.extend({
            options: {
                position: 'bottomleft',
            },

            onAdd: function (map) {
                const container = L.DomUtil.create('div');
                const link = L.DomUtil.create('a', '', container);

                link.setAttribute('href', 'http://mapy.cz/');
                link.setAttribute('target', '_blank');
                link.innerHTML = '<img src="https://api.mapy.cz/img/api/logo.svg" />';
                L.DomEvent.disableClickPropagation(link);

                return container;
            },
        });

        // finally we add our LogoControl to the map
        new LogoControl().addTo(map);
    }
}
