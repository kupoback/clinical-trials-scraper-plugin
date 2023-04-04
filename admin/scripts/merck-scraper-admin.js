document.addEventListener("DOMContentLoaded", (event) => {

    /**
     * Add disabled/read-only attributes to the HTML
     * @param {HTMLElement} elm
     */
    const disableElmAtts = elm => {
        // elm.disabled = true;
        elm.readOnly = true;
        elm.classList.add('disabled');
        // elm.ariaDisabled = true;
        elm.ariaReadOnly = true;
    };

    /**
     * Disable dropdown elements
     * @param {HTMLElement} elm
     */
    const disableDropdown = elm => {
        disableElmAtts(elm)
        elm.onClick = false;
        elm.onKeyDown = false
        elm.style.pointerEvents = 'none';
    }

    const disableElms = apiDataElms => {
        if (apiDataElms.length) {
            // Map through them
            [...apiDataElms].map(elm => {
                // Select all non clone and not set fields
                const elmInput = elm.querySelectorAll('input[id*="acf-field"]:not([id*="acfcloneindex"])');
                if (elmInput.length) {
                    // Disable them
                    [...elmInput].map(elm => disableElmAtts(elm));
                }
                const elmBtn = elm.querySelectorAll('[class*="button"]');
                if (elmBtn.length) {
                    [...elmBtn].map(elm => {
                        elm.dataset.name = null;
                        elm.style.pointerEvents = 'none';
                        elm.style.cursor = 'not-allowed';
                        disableElmAtts(elm);
                    });
                }

                /**
                 * Iterate through textarea fields
                 */
                const elmTextArea = elm.querySelectorAll('textarea[id*="acf-field"]');
                if (elmTextArea.length) {
                    [...elmTextArea].map(elm => disableElmAtts(elm));
                }

                /**
                 * Iterate through select elements
                 */
                const elmSelect = elm.querySelectorAll('select[id*="acf-field"]');
                console.log(elmSelect)
                if (elmSelect.length) {
                    [...elmSelect].map(elm => disableDropdown(elm));
                }
            });
        }
    }

    // Check if ACF is defined
    if (typeof acf !== undefined) {
        const apiDataElms = document.querySelectorAll('[data-name^="api_data_"]');
        // Grab all the API Data Fields
        disableElms(apiDataElms);
    }
});
