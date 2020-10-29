document.addEventListener("DOMContentLoaded", () => {

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
