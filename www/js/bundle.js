document.addEventListener("DOMContentLoaded", () => {

    initDatepicker(document)
    initNavbarBurger()
    initCollectSheet()
    initLiveFilter()

});

// AJAX for forms and links with the class 'ajax' (https://naja.js.org); back and forward refetch the page instead of
// restoring cached snippets, which would show the stamp list (and the collection) as it was before later changes (#50)
naja.initialize({ snippetCache: 'off' })
naja.snippetHandler.addEventListener('afterUpdate', (event) => initDatepicker(event.detail.snippet))

// Czech locale of vanillajs-datepicker (locale files are not bundled in datepicker-full.min.js)
Datepicker.locales.cs = {
    days: ['neděle', 'pondělí', 'úterý', 'středa', 'čtvrtek', 'pátek', 'sobota'],
    daysShort: ['ned', 'pon', 'úte', 'stř', 'čtv', 'pát', 'sob'],
    daysMin: ['ne', 'po', 'út', 'st', 'čt', 'pá', 'so'],
    months: ['leden', 'únor', 'březen', 'duben', 'květen', 'červen', 'červenec', 'srpen', 'září', 'říjen', 'listopad', 'prosinec'],
    monthsShort: ['led', 'úno', 'bře', 'dub', 'kvě', 'čvn', 'čvc', 'srp', 'zář', 'říj', 'lis', 'pro'],
    today: 'Dnes',
    clear: 'Vymazat',
    titleFormat: 'MM y',
    weekStart: 1,
};

// binds the datepicker to the date inputs in the element, skipping inputs that already have one
function initDatepicker(element) {

    const elems = element.querySelectorAll('input[data-datepicker]');
    elems.forEach((elem)=>{
        if (elem.datepicker) {
            return
        }
        const typed = elem.value
        const datepicker = new Datepicker(elem, {
            language: 'cs',
            weekStart: 1,
            orientation: 'top', // the input is in the bottom sheet, there is no room below it
            format: {
                toValue(date, format, locale) {
                    // parse d. m. yyyy (or legacy Y-m-d) as a local calendar date
                    let m = date.match(/^\s*(\d{1,2})\.\s*(\d{1,2})\.\s*(\d{4})\s*$/);
                    const [year, month, day] = m ? [m[3], m[2], m[1]] : (date.match(/^(\d{4})-(\d{2})-(\d{2})$/) || []).slice(1);
                    if (!year) {
                        return undefined;
                    }
                    const value = new Date(Number(year), Number(month) - 1, Number(day));
                    // reject impossible dates like 31. 2. instead of rolling them over to March
                    return value.getMonth() === Number(month) - 1 && value.getDate() === Number(day) ? value : undefined;
                },
                toDisplay(date, format, locale) {
                    return `${date.getDate()}. ${date.getMonth() + 1}. ${date.getFullYear()}`;
                },
            },
        });
        // the datepicker clears a value it can't parse; keep what the user typed so the form error makes sense
        if (typed && !elem.value) {
            elem.value = typed
        }
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

// The bottom sheet with the shared collect form (#48). Everything is delegated from the document, because the
// .js-collect buttons and the sheet itself are replaced by snippet redraws.
function initCollectSheet() {

    let trigger = null // the .js-collect button that opened the sheet

    const findSheet = () => document.getElementById('collect-sheet')
    const findButton = (stampId) => document.querySelector(`.js-collect[data-stamp-id="${CSS.escape(stampId)}"]`)

    // today in the Czech format of the form, j. n. Y, in local time
    function today() {
        const date = new Date()
        return `${date.getDate()}. ${date.getMonth() + 1}. ${date.getFullYear()}`
    }

    // fills the sheet for the stamp of the button; values only when opening, not when showing a form with errors
    function fill(sheet, button, withValues) {
        const form = sheet.querySelector('form')
        const collected = button.dataset.collected === '1'
        const title = collected ? sheet.dataset.titleEdit : sheet.dataset.titleAdd
        sheet.querySelector('.collect-sheet-title').textContent = `${title} #${button.dataset.stampNumber} ${button.dataset.stampName}`
        form.elements.namedItem('remove').classList.toggle('is-hidden', !collected)
        if (!withValues) {
            return
        }
        form.elements.namedItem('id').value = button.dataset.stampId
        const date = form.elements.namedItem('date')
        date.value = collected ? button.dataset.date : today()
        date.datepicker?.update()
        form.elements.namedItem('comment').value = button.dataset.note
        sheet.querySelectorAll('.help.is-danger, .collect-sheet-error').forEach((error) => error.remove())
    }

    function show(sheet) {
        if (!sheet.open) {
            sheet.showModal()
        }
    }

    // a sheet rendered with a form error (on page load or by a snippet redraw) opens for the stamp in its form
    function showWithErrors(sheet) {
        if (!sheet?.hasAttribute('data-open')) {
            return
        }
        const button = findButton(sheet.querySelector('form').elements.namedItem('id').value)
        if (button) {
            fill(sheet, button, false)
        }
        show(sheet)
        // the invalid input instead of the first focusable element (the close button) that showModal() focused
        const form = sheet.querySelector('form')
        const invalid = form.querySelector('[aria-invalid="true"]') ?? form.elements.namedItem('date')
        invalid.focus()
    }

    // disables or enables the submit buttons of the sheet, against a double submit while a request is pending
    function setPending(pending) {
        findSheet()?.querySelectorAll('form [type="submit"]').forEach((submit) => submit.disabled = pending)
    }

    // a failed request (network or server error) keeps the sheet open with a short message
    function showError() {
        const sheet = findSheet()
        if (!sheet) {
            return
        }
        let error = sheet.querySelector('.collect-sheet-error')
        if (!error) {
            error = document.createElement('div')
            error.className = 'notification is-danger is-light collect-sheet-error'
            error.setAttribute('role', 'alert')
            error.textContent = sheet.dataset.errorText
            sheet.querySelector('form').prepend(error)
        }
        show(sheet)
    }

    document.addEventListener('click', (event) => {
        const button = event.target.closest('.js-collect')
        const sheet = findSheet()
        if (button && sheet) {
            trigger = button
            fill(sheet, button, true)
            show(sheet)
            return
        }

        const confirmed = event.target.closest('[data-confirm]')
        if (confirmed && !confirm(confirmed.dataset.confirm)) {
            event.preventDefault()
            return
        }

        // the close button, or a click on the backdrop (outside the sheet's box)
        if (event.target.closest('.collect-sheet-close')) {
            sheet.close()
        } else if (event.target === sheet) {
            const box = sheet.getBoundingClientRect()
            if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) {
                sheet.close()
            }
        }
    })

    // Esc closes the open datepicker (its own handler) first, not the whole sheet
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && event.target.datepicker?.active) {
            event.preventDefault()
        }
    }, true)

    // the dialog returns the focus to the trigger itself; a trigger replaced by a snippet redraw gets it here
    function focusReplacedTrigger() {
        if (trigger && !trigger.isConnected) {
            findButton(trigger.dataset.stampId)?.focus()
        }
    }

    document.addEventListener('close', (event) => {
        if (event.target.id === 'collect-sheet') {
            focusReplacedTrigger()
        }
    }, true)

    naja.snippetHandler.addEventListener('afterUpdate', (event) => showWithErrors(event.detail.snippet.querySelector('#collect-sheet')))

    // requests of the collect form (the form itself or its submit button) are marked in their options
    naja.uiHandler.addEventListener('interaction', (event) => {
        if (event.detail.element.closest('#collect-sheet')) {
            event.detail.options.collectSheet = true
        }
    })
    naja.addEventListener('start', (event) => {
        if (event.detail.options.collectSheet) {
            setPending(true)
        }
    })
    // also after an error or an abort (Esc)
    naja.addEventListener('complete', (event) => {
        if (event.detail.options.collectSheet) {
            setPending(false)
        }
    })
    naja.addEventListener('error', (event) => {
        if (event.detail.options.collectSheet) {
            showError()
        }
    })

    naja.addEventListener('success', (event) => {
        const { payload } = event.detail
        if (payload.stampId === undefined) {
            return
        }
        findSheet()?.close()
        focusReplacedTrigger() // also when the open sheet itself was replaced by the redraw (Nearby)
        // "My collection" lists collected stamps only; the server sends no snippet for a removed one
        if (payload.collected === false) {
            document.querySelector(`[data-only-collected] #stamp-${payload.stampId}`)?.closest('li')?.remove()
        }
        // ... and "Nemám" the not collected ones (#50)
        if (payload.collected === true) {
            document.querySelector(`[data-not-collected] #stamp-${payload.stampId}`)?.closest('li')?.remove()
        }
    })

    showWithErrors(findSheet())

}

// The stamp list filter applies itself (#50): the search after a short pause in typing, the sort on change, both by AJAX
// with the URL in the history. The URL is built from the current one (not from the form, whose hidden fields keep the
// state of the page load, e.g. the switch Vše / Mám / Nemám) and has no form signal, so a number typed so far doesn't
// jump to the stamp. Enter is an explicit submit: a normal page load with the signal, which jumps to an existing stamp.
function initLiveFilter() {

    const form = document.querySelector('form.js-live-filter')
    if (!form) {
        return
    }
    const search = form.elements.namedItem('search')
    let timer = null
    let deferred = false // a search typed while the collect sheet was open, applied after it closes
    let searchEntryUrl = null // the history entry created by typing, replaced as the typing goes on

    function filterUrl(submit) {
        const url = new URL(window.location.href)
        const params = url.searchParams
        params.delete('do')
        params.delete('ok')
        params.delete('_fid') // flash messages of a past redirect
        params.delete('paginator-page') // a new filter starts on the first page
        const select = form.elements.namedItem('sort') // the select may have been redrawn
        search.value ? params.set('search', search.value) : params.delete('search')
        select.value && select.value !== select.options[0]?.value ? params.set('sort', select.value) : params.delete('sort') // the default not in the URL
        if (submit) {
            params.set('do', 'filterForm-submit')
        }
        url.hash = ''
        return url.toString()
    }

    // own unique key: a filter request aborts the previous filter request, not a collect form POST, and vice versa
    function apply(typing) {
        clearTimeout(timer)
        deferred = false
        const url = filterUrl(false)
        if (url === window.location.href) {
            return
        }
        // typing makes one history entry, not one per pause; a sort change makes its own
        const history = typing && window.location.href === searchEntryUrl ? 'replace' : true
        searchEntryUrl = typing ? url : null
        naja.makeRequest('GET', url, null, { history, unique: 'filter' })
    }

    function applyTyping() {
        if (document.getElementById('collect-sheet')?.open) { // the list would be replaced under the open sheet
            deferred = true
            return
        }
        apply(true)
    }

    document.addEventListener('close', (event) => {
        if (event.target.id === 'collect-sheet' && deferred) {
            applyTyping()
        }
    }, true)

    search.addEventListener('input', () => {
        clearTimeout(timer)
        timer = setTimeout(applyTyping, 300)
    })
    // delegated, the sort select is replaced by a snippet redraw
    form.addEventListener('change', (event) => {
        if (event.target.name === 'sort') {
            apply(false)
        }
    })
    form.addEventListener('submit', (event) => {
        event.preventDefault()
        clearTimeout(timer)
        deferred = false
        window.location.href = filterUrl(true)
    })

    // back and forward restore the list by Naja (a refetch, the snippet cache is off); the search input is not part of it
    window.addEventListener('popstate', () => {
        clearTimeout(timer) // a pending search must not push a new entry over the restored one
        deferred = false
        search.value = new URL(window.location.href).searchParams.get('search') ?? ''
    })
    // ... the refetch is a filter request too
    naja.snippetCache.addEventListener('fetch', (event) => {
        event.detail.options.unique = 'filter'
    })

}

// Nearby (#51): the location from the browser with its status in Czech, the nearest stamps for it by AJAX.
// Called from the Nearby template; the button locates again.
function initNearby() {

    const status = document.getElementById('nearby-status')
    const text = status.querySelector('.nearby-status-text')
    const button = document.querySelector('.js-locate')

    function setStatus(state, message) {
        status.dataset.state = state
        text.textContent = message
    }

    function locate() {
        if (!navigator.geolocation) {
            setStatus('error', status.dataset.unsupported)
            return
        }
        setStatus('locating', status.dataset.locating)
        button.disabled = true
        navigator.geolocation.getCurrentPosition((position) => {
            button.disabled = false
            const { latitude, longitude, accuracy } = position.coords
            setStatus('located', status.dataset.located.replace('{accuracy}', Math.round(accuracy).toLocaleString('cs-CZ')))
            // from the current URL, which keeps the switch Vše / Mám / Nemám; via Naja, so the collect form
            // in the redrawn snippet submits by AJAX too
            const url = new URL(window.location.href)
            url.searchParams.delete('do')
            url.searchParams.delete('_fid')
            url.searchParams.set('lat', latitude)
            url.searchParams.set('lng', longitude)
            url.hash = ''
            naja.makeRequest('GET', url.toString(), null, { history: false })
        }, (error) => {
            button.disabled = false
            const messages = {
                [error.PERMISSION_DENIED]: status.dataset.denied,
                [error.TIMEOUT]: status.dataset.timeout,
            }
            setStatus('error', messages[error.code] ?? status.dataset.unavailable)
        }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 })
    }

    button.addEventListener('click', locate)
    locate()

}

// The description of a stamp on its detail (#51): a few lines (styles.css), the button expands the whole text;
// the button only when the text is longer
function initDescription() {

    const description = document.querySelector('.js-description')
    const button = document.querySelector('.js-description-toggle')
    if (!description || description.scrollHeight <= description.clientHeight) {
        return
    }
    button.hidden = false
    button.addEventListener('click', () => {
        const expanded = description.classList.toggle('is-expanded')
        button.setAttribute('aria-expanded', expanded)
        button.textContent = expanded ? button.dataset.less : button.dataset.more
    })

}

class StampMap {
    constructor(divId, stamps, collection, lat, lng, baseUrl, apiKey, salespoints = []) {
        this.divId = divId
        this.stamps = stamps
        this.collection = collection
        this.lat = lat
        this.lng = lng
        this.baseUrl = baseUrl
        this.apiKey = apiKey
        this.salespoints = salespoints
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
        L.tileLayer(`https://api.mapy.cz/v1/maptiles/outdoor/256/{z}/{x}/{y}?apikey=${this.apiKey}`, {
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

        // Salespoints of the stamp as small blue circles, distinct from the stamp markers
        for (var salespoint of this.salespoints) {
            var popup = document.createElement('span');
            popup.textContent = salespoint['name'];
            L.circleMarker([salespoint['lat'], salespoint['lng']], {radius: 8, color: '#3273dc', fillOpacity: 0.6}).addTo(map).bindPopup(popup);
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
