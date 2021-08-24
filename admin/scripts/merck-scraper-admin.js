document.addEventListener("DOMContentLoaded", (event) => {
    
    const disableElmAtts = elm => {
        // elm.disabled = true;
        elm.readOnly = true;
        elm.classList.add('disabled');
        // elm.ariaDisabled = true;
        elm.ariaReadOnly = true;
    };
    
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
                const elmTextArea = elm.querySelectorAll('textarea[id*="acf-field"]');
                if (elmTextArea.length) {
                    [...elmTextArea].map(elm => disableElmAtts(elm));
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
