/* WMS Pro — barcode-print.js
 * Helper to generate barcodes using JsBarcode (Code128).
 * Loaded via CDN in the print label view.
 */
(function () {
    'use strict';

    /**
     * Generate a barcode into a given SVG or Canvas element.
     *
     * @param {string} selector  - CSS selector for the target element (#barcode)
     * @param {string} value     - The barcode value string
     * @param {object} options   - Optional JsBarcode options override
     */
    function generateBarcode(selector, value, options) {
        if (typeof JsBarcode === 'undefined') {
            console.error('JsBarcode library not loaded.');
            return;
        }

        const defaults = {
            format: 'CODE128',
            width: 2,
            height: 40,
            displayValue: false,
            margin: 0,
            background: '#ffffff',
            lineColor: '#000000',
        };

        JsBarcode(selector, value, Object.assign({}, defaults, options));
    }

    // Expose globally so inline scripts in Blade views can call it
    window.WMSBarcode = { generate: generateBarcode };

})();
