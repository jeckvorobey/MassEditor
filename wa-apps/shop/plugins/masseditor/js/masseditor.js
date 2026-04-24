(function () {
    'use strict';

    var selectAll = document.querySelector('[data-role="select-all"]');

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            var checkboxes = document.querySelectorAll('input[name="product_ids[]"]');

            checkboxes.forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
            });
        });
    }

    window.shopMasseditorBackend = {
        initialized: true
    };
}());
