(function () {
    'use strict';

    var selectAll = document.querySelector('[data-role="select-all"]');

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('input[name="product_ids[]"]').forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
            });
        });

        document.querySelectorAll('input[name="product_ids[]"]').forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                var all = document.querySelectorAll('input[name="product_ids[]"]');
                var checked = document.querySelectorAll('input[name="product_ids[]"]:checked');
                selectAll.checked = all.length === checked.length;
                selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
            });
        });
    }

    window.shopMasseditorBackend = {
        initialized: true
    };
}());
