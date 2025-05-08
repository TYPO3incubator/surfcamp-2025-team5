document.addEventListener("DOMContentLoaded", () => {
    const sepa_checkbox = document.querySelector('.sepa-accepted-js');
    if (sepa_checkbox) {
        const sepa_toggle = document.querySelector('#' + sepa_checkbox.dataset.toggle);
        const sepa_label = document.querySelector('.sepa-required-js');
        if (sepa_toggle) {
            sepa_checkbox.addEventListener('change', () => {
                if (sepa_checkbox.checked) {
                    sepa_toggle.required = 'required';
                    sepa_label.style.display = 'none';
                } else {
                    sepa_toggle.required = false;
                    sepa_label.style.display = '';
                }
            })
        }
    }
});