(function () {
    'use strict';

    var soonOperationsToggle = document.querySelector('[data-role="soon-operations-toggle"]');
    var soonOperationsToggleLabel = document.querySelector('[data-role="soon-operations-toggle-label"]');
    var workspaceForm = document.querySelector('[data-role="workspace-form"]');
    var toastStack = document.querySelector('[data-role="toast-stack"]');
    var toastSourceNotices = Array.prototype.slice.call(document.querySelectorAll('[data-toast-source="true"]'));
    var i18n = window.masseditorI18n || {};

    function t(key, fallback) {
        return i18n[key] || fallback || key;
    }

    function updateSoonOperationsToggleLabel() {
        if (!soonOperationsToggle || !soonOperationsToggleLabel) {
            return;
        }

        soonOperationsToggleLabel.textContent = soonOperationsToggle.checked ? t('enabled', 'Enabled') : t('disabled', 'Disabled');
    }

    if (soonOperationsToggle) {
        soonOperationsToggle.addEventListener('change', updateSoonOperationsToggleLabel);
        updateSoonOperationsToggleLabel();
    }

    function toastTitle(type) {
        if (type === 'success') {
            return t('toast_success', 'Success');
        }
        if (type === 'error') {
            return t('toast_error', 'Error');
        }

        return t('toast_info', 'Message');
    }

    function removeToast(toast) {
        if (!toast || !toast.parentNode) {
            return;
        }

        toast.parentNode.removeChild(toast);
    }

    function showToast(type, message, options) {
        if (!toastStack || !message) {
            return;
        }

        var settings = options || {};
        var toastType = type || 'info';
        var toast = document.createElement('section');
        var body = document.createElement('div');
        var title = document.createElement('strong');
        var text = document.createElement('p');
        var closeButton = document.createElement('button');
        var timeout = 0;

        toast.className = 'masseditor-toast masseditor-toast_' + toastType;
        toast.setAttribute('role', toastType === 'error' ? 'alert' : 'status');

        body.className = 'masseditor-toast__body';
        title.className = 'masseditor-toast__title';
        title.textContent = settings.title || toastTitle(toastType);

        text.className = 'masseditor-toast__message';
        text.textContent = message;

        closeButton.type = 'button';
        closeButton.className = 'masseditor-toast__close';
        closeButton.setAttribute('aria-label', t('toast_close', 'Close notification'));
        closeButton.textContent = '×';
        closeButton.addEventListener('click', function () {
            removeToast(toast);
        });

        body.appendChild(title);
        body.appendChild(text);
        toast.appendChild(body);
        toast.appendChild(closeButton);
        toastStack.appendChild(toast);

        timeout = typeof settings.timeout === 'number'
            ? settings.timeout
            : (toastType === 'success' ? 4000 : 0);

        if (timeout > 0) {
            window.setTimeout(function () {
                removeToast(toast);
            }, timeout);
        }
    }

    function showErrorToast(message) {
        showToast('error', message, { timeout: 0 });
    }

    function initToastSources() {
        if (!toastStack || !toastSourceNotices.length) {
            return;
        }

        toastSourceNotices.forEach(function (notice) {
            var messages = Array.prototype.slice.call(notice.querySelectorAll('p')).map(function (node) {
                return node.textContent ? node.textContent.trim() : '';
            }).filter(function (message) {
                return message !== '';
            });
            var type = notice.getAttribute('data-toast-type') || 'info';

            if (!messages.length) {
                return;
            }

            notice.classList.add('is-toast-hidden');

            messages.forEach(function (message) {
                showToast(type, message);
            });
        });
    }

    initToastSources();

    if (!workspaceForm) {
        return;
    }

    var operationInput = workspaceForm.querySelector('[data-role="operation-input"]');
    var operationTitle = workspaceForm.querySelector('[data-role="operation-title"]');
    var operationButtons = Array.prototype.slice.call(document.querySelectorAll('[data-role="operation-trigger"]'));
    var operationFieldGroups = Array.prototype.slice.call(document.querySelectorAll('[data-operation-fields]'));
    var comparePriceMode = document.getElementById('masseditor-compare-price-mode');
    var comparePriceValueField = document.querySelector('[data-compare-mode-field]');
    var selectAll = document.querySelector('[data-role="select-all"]');
    var productCheckboxes = Array.prototype.slice.call(document.querySelectorAll('[data-role="product-checkbox"]'));
    var selectedCount = document.querySelector('[data-role="selected-count"]');
    var selectionCounterPill = document.querySelector('[data-role="selection-counter-pill"]');
    var readyCopy = document.querySelector('[data-role="ready-copy"]');
    var openConfirmButton = document.querySelector('[data-role="open-confirm"]');
    var openConfirmMobileButton = document.querySelector('[data-role="open-confirm-mobile"]');
    var mobileApplyCount = document.querySelector('[data-role="mobile-apply-count"]');
    var mobileApplyOperation = document.querySelector('[data-role="mobile-apply-operation"]');
    var confirmApply = document.querySelector('[data-role="confirm-apply"]');
    var modal = document.querySelector('[data-role="confirm-modal"]');
    var closeModalButtons = Array.prototype.slice.call(document.querySelectorAll('[data-role="close-modal"]'));
    var modalCount = document.querySelector('[data-role="modal-count"]');
    var modalOperation = document.querySelector('[data-role="modal-operation"]');
    var modalMode = document.querySelector('[data-role="modal-mode"]');
    var modalValue = document.querySelector('[data-role="modal-value"]');
    var selectionStorageKey = buildSelectionStorageKey();
    var selectedProductsMap = loadSelectedProductsMap();

    var operationLabels = {
        price: t('operation_price', 'Change price'),
        compare_price: t('operation_compare_price', 'Change compare price'),
        visibility: t('operation_visibility', 'Change visibility'),
        availability: t('operation_availability', 'Change availability'),
        description: t('operation_description', 'Description'),
        tags: t('operation_tags', 'Tags'),
        url: t('operation_url', 'Product URLs')
    };

    function currentOperation() {
        return operationInput ? operationInput.value : 'price';
    }

    function buildSelectionStorageKey() {
        var pluginIdField = workspaceForm.querySelector('input[name="plugin"]');
        var pluginId = pluginIdField ? pluginIdField.value : 'masseditor';
        return 'masseditor:selected-products:' + pluginId;
    }

    function toProductId(value) {
        var id = parseInt(value, 10);
        return id > 0 ? id : 0;
    }

    function loadSelectedProductsMap() {
        var map = {};
        var raw = '';

        try {
            raw = window.localStorage.getItem(selectionStorageKey) || '';
        } catch (e) {
            raw = '';
        }

        if (!raw) {
            return map;
        }

        try {
            var ids = JSON.parse(raw);
            if (!Array.isArray(ids)) {
                return map;
            }

            ids.forEach(function (id) {
                var productId = toProductId(id);
                if (productId) {
                    map[productId] = true;
                }
            });
        } catch (e) {
            return {};
        }

        return map;
    }

    function saveSelectedProductsMap() {
        var ids = Object.keys(selectedProductsMap).map(function (key) {
            return parseInt(key, 10);
        }).filter(function (id) {
            return id > 0;
        });

        try {
            if (ids.length) {
                window.localStorage.setItem(selectionStorageKey, JSON.stringify(ids));
            } else {
                window.localStorage.removeItem(selectionStorageKey);
            }
        } catch (e) {
        }
    }

    function resetSelectionIfRequested() {
        if (workspaceForm.getAttribute('data-selection-reset') !== '1') {
            return;
        }

        selectedProductsMap = {};
        saveSelectedProductsMap();
    }

    function selectedProductsCount() {
        return Object.keys(selectedProductsMap).length;
    }

    function syncCheckboxesFromSelection() {
        productCheckboxes.forEach(function (checkbox) {
            var productId = toProductId(checkbox.value);
            checkbox.checked = !!selectedProductsMap[productId];
        });
    }

    function syncRowSelectionState() {
        productCheckboxes.forEach(function (checkbox) {
            if (!checkbox || !checkbox.closest) {
                return;
            }

            var row = checkbox.closest('tr');
            if (!row) {
                return;
            }

            row.classList.toggle('is-selected', checkbox.checked);
        });
    }

    function setProductSelection(productId, isSelected) {
        if (!productId) {
            return;
        }

        if (isSelected) {
            selectedProductsMap[productId] = true;
        } else {
            delete selectedProductsMap[productId];
        }
    }

    function appendPersistedProductInputs() {
        var staleInputs = workspaceForm.querySelectorAll('input[data-role="persisted-product-id"]');
        Array.prototype.slice.call(staleInputs).forEach(function (input) {
            if (input && input.parentNode) {
                input.parentNode.removeChild(input);
            }
        });

        Object.keys(selectedProductsMap).forEach(function (key) {
            var productId = toProductId(key);
            if (!productId) {
                return;
            }

            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'product_ids[]';
            input.value = String(productId);
            input.setAttribute('data-role', 'persisted-product-id');
            workspaceForm.appendChild(input);
        });
    }

    function countCheckedProducts() {
        return productCheckboxes.filter(function (checkbox) {
            return checkbox.checked;
        }).length;
    }

    function updateSelectionState() {
        var checkedCount = countCheckedProducts();
        var totalSelected = selectedProductsCount();
        var total = selectionCounterPill ? parseInt(selectionCounterPill.getAttribute('data-total') || '0', 10) : 0;

        if (selectedCount) {
            selectedCount.textContent = totalSelected;
        }

        if (selectionCounterPill) {
            selectionCounterPill.textContent = totalSelected + ' ' + t('selected_counter_separator', 'of') + ' ' + total;
        }

        if (readyCopy) {
            readyCopy.textContent = totalSelected > 0
                ? totalSelected + ' ' + t('products_word', 'products') + ' · ' + t('ready_selected_suffix', 'action will be written to the log')
                : t('ready_empty', 'Select products to process.');
        }

        if (selectAll) {
            selectAll.checked = checkedCount > 0 && checkedCount === productCheckboxes.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < productCheckboxes.length;
        }

        if (mobileApplyCount) {
            mobileApplyCount.textContent = totalSelected + ' ' + t('products_word', 'products');
        }

        syncRowSelectionState();
    }

    function setOperation(operation) {
        if (!operationInput) {
            return;
        }

        operationInput.value = operation;

        operationButtons.forEach(function (button) {
            button.classList.toggle('is-active', button.getAttribute('data-operation') === operation);
        });

        if (operationTitle) {
            operationTitle.textContent = operationLabels[operation] || t('operation_parameters', 'Operation parameters');
        }

        if (mobileApplyOperation) {
            mobileApplyOperation.textContent = operationLabels[operation] || t('operation_parameters', 'Operation parameters');
        }

        operationFieldGroups.forEach(function (field) {
            var operations = (field.getAttribute('data-operation-fields') || '').split(',');
            var active = operations.indexOf(operation) !== -1;
            field.hidden = !active;

            Array.prototype.slice.call(field.querySelectorAll('input, select, textarea')).forEach(function (input) {
                input.disabled = !active;
            });
        });

        updateComparePriceVisibility();
    }

    function updateComparePriceVisibility() {
        if (!comparePriceMode || !comparePriceValueField) {
            return;
        }

        var active = currentOperation() === 'price' && comparePriceMode.value === 'coefficient';
        comparePriceValueField.hidden = !active;

        Array.prototype.slice.call(comparePriceValueField.querySelectorAll('input')).forEach(function (input) {
            input.disabled = !active;
        });
    }

    function operationModeText(operation) {
        if (operation === 'price' || operation === 'compare_price') {
            var mode = document.getElementById('masseditor-mode');
            return mode ? mode.options[mode.selectedIndex].text : '—';
        }
        if (operation === 'visibility') {
            var visibility = document.getElementById('masseditor-visibility-status');
            return visibility ? visibility.options[visibility.selectedIndex].text : '—';
        }
        if (operation === 'availability') {
            var availability = document.getElementById('masseditor-availability-value');
            return availability ? availability.options[availability.selectedIndex].text : '—';
        }
        if (operation === 'description') {
            var descriptionMode = document.getElementById('masseditor-description-mode');
            return descriptionMode ? descriptionMode.options[descriptionMode.selectedIndex].text : '—';
        }
        if (operation === 'tags') {
            var tagsMode = document.getElementById('masseditor-tags-mode');
            return tagsMode ? tagsMode.options[tagsMode.selectedIndex].text : '—';
        }
        if (operation === 'url') {
            var urlMode = document.getElementById('masseditor-url-mode');
            return urlMode ? urlMode.options[urlMode.selectedIndex].text : '—';
        }

        return '—';
    }

    function operationValueText(operation) {
        if (operation === 'price' || operation === 'compare_price') {
            var numeric = document.getElementById('masseditor-numeric-value');
            return numeric && numeric.value ? numeric.value : '—';
        }
        if (operation === 'description') {
            var text = document.getElementById('masseditor-text-value');
            return text && text.value ? text.value.substring(0, 80) : '—';
        }
        if (operation === 'tags') {
            var tags = document.getElementById('masseditor-tags-value');
            return tags && tags.value ? tags.value.substring(0, 80) : '—';
        }
        if (operation === 'url') {
            var url = document.getElementById('masseditor-url-value');
            var urlMode = document.getElementById('masseditor-url-mode');
            if (urlMode && urlMode.value === 'regenerate') {
                return t('value_from_product_name', 'From product name');
            }
            return url && url.value ? url.value : '—';
        }

        return '—';
    }

    function validateBeforeModal() {
        var checkedCount = selectedProductsCount();
        var operation = currentOperation();

        if (checkedCount === 0) {
            showErrorToast(t('validation_select_product', 'Select at least one product.'));
            return false;
        }

        if ((operation === 'price' || operation === 'compare_price') && !document.getElementById('masseditor-numeric-value').value.trim()) {
            showErrorToast(t('validation_numeric', 'Enter a value for the bulk operation.'));
            return false;
        }

        if (operation === 'description' && !document.getElementById('masseditor-text-value').value.trim()) {
            showErrorToast(t('validation_description', 'Enter description text.'));
            return false;
        }

        if (operation === 'tags' && !document.getElementById('masseditor-tags-value').value.trim()) {
            showErrorToast(t('validation_tags', 'Enter at least one tag.'));
            return false;
        }

        if (operation === 'url') {
            var mode = document.getElementById('masseditor-url-mode');
            var value = document.getElementById('masseditor-url-value');
            if (mode && mode.value === 'template' && value && !value.value.trim()) {
                showErrorToast(t('validation_url_template', 'Enter a URL template.'));
                return false;
            }
        }

        if (operation === 'price' && comparePriceMode && comparePriceMode.value === 'coefficient') {
            var coefficient = document.getElementById('masseditor-compare-price-value');
            if (!coefficient || !coefficient.value.trim()) {
                showErrorToast(t('validation_compare_coefficient', 'Enter a compare price coefficient.'));
                return false;
            }
        }

        return true;
    }

    function openModal() {
        var operation = currentOperation();

        if (!validateBeforeModal() || !modal) {
            return;
        }

        if (modalCount) {
            modalCount.textContent = selectedProductsCount();
        }
        if (modalOperation) {
            modalOperation.textContent = operationLabels[operation] || '—';
        }
        if (modalMode) {
            modalMode.textContent = operationModeText(operation);
        }
        if (modalValue) {
            modalValue.textContent = operationValueText(operation);
        }

        confirmApply.value = '0';
        modal.hidden = false;
        document.body.classList.add('masseditor-modal-open');
    }

    function closeModal() {
        if (!modal) {
            return;
        }

        modal.hidden = true;
        confirmApply.value = '0';
        document.body.classList.remove('masseditor-modal-open');
    }

    operationButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (button.disabled) {
                return;
            }
            setOperation(button.getAttribute('data-operation'));
        });
    });

    if (comparePriceMode) {
        comparePriceMode.addEventListener('change', updateComparePriceVisibility);
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            productCheckboxes.forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
                setProductSelection(toProductId(checkbox.value), checkbox.checked);
            });
            saveSelectedProductsMap();
            updateSelectionState();
        });
    }

    productCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            setProductSelection(toProductId(checkbox.value), checkbox.checked);
            saveSelectedProductsMap();
            updateSelectionState();
        });
    });

    if (openConfirmButton) {
        openConfirmButton.addEventListener('click', openModal);
    }

    if (openConfirmMobileButton) {
        openConfirmMobileButton.addEventListener('click', openModal);
    }

    closeModalButtons.forEach(function (button) {
        button.addEventListener('click', closeModal);
    });

    workspaceForm.addEventListener('submit', function (event) {
        var submitter = event.submitter || document.activeElement;
        if (submitter && submitter.getAttribute('data-role') === 'confirm-submit') {
            if (!validateBeforeModal()) {
                event.preventDefault();
                closeModal();
                return;
            }
            appendPersistedProductInputs();
            saveSelectedProductsMap();
            confirmApply.value = '1';
        }
    });

    resetSelectionIfRequested();
    syncCheckboxesFromSelection();
    setOperation(currentOperation());
    updateSelectionState();
})();
