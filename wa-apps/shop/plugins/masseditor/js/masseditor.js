(function () {
    'use strict';

    var workspaceForm = document.querySelector('[data-role="workspace-form"]');
    var toastStack = document.querySelector('[data-role="toast-stack"]');
    var toastSourceNotices = Array.prototype.slice.call(document.querySelectorAll('[data-toast-source="true"]'));
    var i18n = window.masseditorI18n || {};

    function t(key, fallback) {
        return i18n[key] || fallback || key;
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
    var stockId = document.getElementById('masseditor-stock-id');
    var stockMode = document.getElementById('masseditor-stock-mode');
    var stockValueField = document.querySelector('[data-stock-value-field]');
    var featureMode = document.getElementById('masseditor-feature-mode');
    var featureValueField = document.querySelector('[data-feature-value-field]');
    var selectAll = document.querySelector('[data-role="select-all"]');
    var selectAllPageMobile = document.querySelector('[data-role="select-all-page-mobile"]');
    var selectFilter = document.querySelector('[data-role="select-filter"]');
    var selectionModeInput = document.querySelector('[data-role="selection-mode"]');
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
    var productSearchInput = document.querySelector('[data-role="product-search-input"]');
    var productSearchClear = document.querySelector('[data-role="product-search-clear"]');
    var productSearchSuggestions = document.querySelector('[data-role="product-search-suggestions"]');
    var stockPopoverToggles = Array.prototype.slice.call(document.querySelectorAll('[data-role="stock-popover-toggle"]'));
    var filterForm = document.getElementById('masseditor-filter-form');
    var selectionStorageKey = buildSelectionStorageKey();
    var selectedProductsMap = loadSelectedProductsMap();
    var searchSuggestionsRequestId = 0;

    var operationLabels = {
        price: t('operation_price', 'Change price'),
        compare_price: t('operation_compare_price', 'Change compare price'),
        visibility: t('operation_visibility', 'Change visibility'),
        availability: t('operation_availability', 'Change availability'),
        description: t('operation_description', 'Description'),
        tags: t('operation_tags', 'Tags'),
        url: t('operation_url', 'Product URLs'),
        stock: t('operation_stock', 'Stock'),
        features: t('operation_features', 'Basic feature editing'),
        categories: t('operation_categories', 'Categories')
    };

    function currentOperation() {
        return operationInput ? operationInput.value : 'price';
    }

    function updateSearchClearButton() {
        if (!productSearchInput || !productSearchClear) {
            return;
        }
        productSearchClear.hidden = productSearchInput.value === '';
    }

    function setSearchSuggestionsExpanded(expanded) {
        if (productSearchInput) {
            productSearchInput.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }
        if (productSearchSuggestions) {
            productSearchSuggestions.hidden = !expanded;
        }
    }

    function clearSearchSuggestions() {
        if (!productSearchSuggestions) {
            return;
        }
        while (productSearchSuggestions.children.length) {
            productSearchSuggestions.removeChild(productSearchSuggestions.children[0]);
        }
        setSearchSuggestionsExpanded(false);
    }

    function appendSearchSuggestionState(message) {
        var state = document.createElement('div');
        state.className = 'masseditor-searchbox__state';
        state.textContent = message;
        productSearchSuggestions.appendChild(state);
        setSearchSuggestionsExpanded(true);
    }

    function currentFilterValue(id, fallback) {
        var field = document.getElementById(id);
        return field ? field.value : fallback;
    }

    function buildSearchSuggestionsUrl(query) {
        var baseUrl = productSearchInput.getAttribute('data-suggestions-url') || '';
        var separator = baseUrl.indexOf('?') === -1 ? '?' : '&';

        return baseUrl
            + separator + 'query=' + encodeURIComponent(query)
            + '&status=' + encodeURIComponent(currentFilterValue('masseditor-status', 'all'))
            + '&availability=' + encodeURIComponent(currentFilterValue('masseditor-availability-filter', 'all'))
            + '&category_id=' + encodeURIComponent(currentFilterValue('masseditor-category', '0'))
            + '&stock_id=' + encodeURIComponent(currentFilterValue('masseditor-stock-filter', '0'));
    }

    function submitFilterForm() {
        if (!filterForm) {
            return;
        }
        if (typeof filterForm.submit === 'function') {
            filterForm.submit();
            return;
        }
        filterForm.dispatchEvent({ type: 'submit', defaultPrevented: false, preventDefault: function () { this.defaultPrevented = true; } });
    }

    function renderSearchSuggestions(suggestions) {
        clearSearchSuggestions();
        if (!productSearchSuggestions || !suggestions.length) {
            return;
        }

        suggestions.forEach(function (suggestion) {
            var option = document.createElement('button');
            option.type = 'button';
            option.className = 'masseditor-searchbox__option';
            option.setAttribute('data-role', 'product-search-suggestion');
            option.setAttribute('role', 'option');
            option.textContent = suggestion;
            option.addEventListener('click', function () {
                productSearchInput.value = suggestion;
                clearSearchSuggestions();
                submitFilterForm();
            });
            productSearchSuggestions.appendChild(option);
        });
        setSearchSuggestionsExpanded(true);
    }

    function initProductSearchSuggestions() {
        if (!productSearchInput || !productSearchSuggestions || !filterForm) {
            return;
        }

        updateSearchClearButton();

        if (productSearchClear) {
            productSearchClear.addEventListener('click', function () {
                var hadValue = productSearchInput.value !== '';
                productSearchInput.value = '';
                clearSearchSuggestions();
                updateSearchClearButton();
                if (typeof productSearchInput.focus === 'function') {
                    productSearchInput.focus();
                }
                if (hadValue) {
                    submitFilterForm();
                }
            });
        }

        productSearchInput.addEventListener('input', function () {
            var query = productSearchInput.value ? productSearchInput.value.trim() : '';
            var requestId = 0;

            updateSearchClearButton();

            if (query.length < 2) {
                clearSearchSuggestions();
                return;
            }

            if (typeof window.fetch !== 'function') {
                return;
            }

            requestId = ++searchSuggestionsRequestId;
            clearSearchSuggestions();
            appendSearchSuggestionState(t('search_suggestions_loading', 'Loading...'));

            window.fetch(buildSearchSuggestionsUrl(query), {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Request failed');
                }
                return response.json();
            }).then(function (payload) {
                var suggestions = payload && payload.data && Array.isArray(payload.data.suggestions)
                    ? payload.data.suggestions
                    : (payload && Array.isArray(payload.suggestions) ? payload.suggestions : []);

                if (requestId !== searchSuggestionsRequestId) {
                    return;
                }
                renderSearchSuggestions(suggestions);
            }).catch(function () {
                if (requestId !== searchSuggestionsRequestId) {
                    return;
                }
                clearSearchSuggestions();
            });
        });
    }

    function closeStockPopovers(exceptPopover) {
        stockPopoverToggles.forEach(function (toggle) {
            var summary = toggle.closest('.masseditor-stock-summary');
            var popover = summary ? summary.querySelector('[data-role="stock-popover"]') : null;
            if (!popover || popover === exceptPopover) {
                return;
            }
            popover.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
        });
    }

    function initStockPopovers() {
        if (!stockPopoverToggles.length) {
            return;
        }

        stockPopoverToggles.forEach(function (toggle) {
            toggle.addEventListener('click', function (event) {
                var summary = toggle.closest('.masseditor-stock-summary');
                var popover = summary ? summary.querySelector('[data-role="stock-popover"]') : null;
                var shouldOpen = false;

                if (event && typeof event.stopPropagation === 'function') {
                    event.stopPropagation();
                }
                if (!popover) {
                    return;
                }

                shouldOpen = popover.hidden;
                closeStockPopovers(popover);
                popover.hidden = !shouldOpen;
                toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
            });
        });

        document.addEventListener('click', function (event) {
            if (event.target && event.target.closest && event.target.closest('.masseditor-stock-summary')) {
                return;
            }
            closeStockPopovers();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeStockPopovers();
            }
        });
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
        if (isFilterSelection()) {
            return filterSelectionTotal();
        }
        return Object.keys(selectedProductsMap).length;
    }

    function isFilterSelection() {
        return selectionModeInput && selectionModeInput.value === 'filter';
    }

    function filterSelectionTotal() {
        return selectFilter ? parseInt(selectFilter.getAttribute('data-total') || '0', 10) : 0;
    }

    function useIdsSelection() {
        if (selectionModeInput) {
            selectionModeInput.value = 'ids';
        }
    }

    function syncCheckboxesFromSelection() {
        productCheckboxes.forEach(function (checkbox) {
            var productId = toProductId(checkbox.value);
            checkbox.checked = isFilterSelection() || !!selectedProductsMap[productId];
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
        useIdsSelection();
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

        if (isFilterSelection()) {
            return;
        }

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
            selectionCounterPill.textContent = t('stats_selected', 'Selected') + ' ' + totalSelected + ' ' + t('selected_counter_separator', 'of') + ' ' + total;
        }

        if (readyCopy) {
            readyCopy.textContent = totalSelected > 0
                ? (isFilterSelection() ? t('filter_selection_summary', 'All products by current filter') + ': ' : '') + totalSelected + ' ' + t('products_word', 'products') + ' · ' + t('ready_selected_suffix', 'action will be written to the log')
                : t('ready_empty', 'Select products to process.');
        }

        if (selectAll) {
            selectAll.checked = checkedCount > 0 && checkedCount === productCheckboxes.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < productCheckboxes.length;
        }

        if (selectAllPageMobile) {
            selectAllPageMobile.checked = checkedCount > 0 && checkedCount === productCheckboxes.length;
            selectAllPageMobile.indeterminate = checkedCount > 0 && checkedCount < productCheckboxes.length;
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
        updateStockValueVisibility();
        updateFeatureValueVisibility();
    }

    function resetOperationForm() {
        operationFieldGroups.forEach(function (field) {
            Array.prototype.slice.call(field.querySelectorAll('input, select, textarea')).forEach(function (input) {
                if (input.tagName === 'SELECT') {
                    input.selectedIndex = 0;
                    input.value = input.options.length ? input.options[0].value : '';
                } else if (input.type === 'checkbox' || input.type === 'radio') {
                    input.checked = false;
                } else {
                    input.value = '';
                }
            });
        });

        setWarehouseSelectionInvalid(false);
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

    function updateStockValueVisibility() {
        if (!stockMode || !stockValueField) {
            return;
        }

        var active = currentOperation() === 'stock' && stockMode.value !== 'infinite';
        stockValueField.hidden = !active;
        Array.prototype.slice.call(stockValueField.querySelectorAll('input')).forEach(function (input) {
            input.disabled = !active;
        });
    }

    function selectedProductsUseWarehouseAccounting() {
        return productCheckboxes.some(function (checkbox) {
            return (checkbox.checked || selectedProductsMap[checkbox.value])
                && checkbox.getAttribute('data-has-warehouse-stock') === '1';
        });
    }

    function setWarehouseSelectionInvalid(isInvalid) {
        var stockField = stockId && stockId.closest ? stockId.closest('.masseditor-field') : null;

        if (!stockId) {
            return;
        }

        if (stockField) {
            stockField.classList.toggle('masseditor-field_invalid', isInvalid);
        }
        stockId.setAttribute('aria-invalid', isInvalid ? 'true' : 'false');
    }

    function updateFeatureValueVisibility() {
        if (!featureMode || !featureValueField) {
            return;
        }

        var active = currentOperation() === 'features' && featureMode.value !== 'clear';
        featureValueField.hidden = !active;

        if (active) {
            showFeatureWidget();
        } else {
            var widgets = featureValueField.querySelectorAll('[data-widget]');
            Array.prototype.slice.call(widgets).forEach(function (widget) {
                widget.hidden = true;
                widget.disabled = true;
            });
        }
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
        if (operation === 'stock') {
            var stockModeText = stockMode ? stockMode.options[stockMode.selectedIndex].text : '—';
            if (stockId && stockId.value !== '0') {
                return stockModeText + ' · ' + stockId.options[stockId.selectedIndex].text;
            }
            return stockModeText;
        }
        if (operation === 'features') {
            return featureMode ? featureMode.options[featureMode.selectedIndex].text : '—';
        }
        if (operation === 'categories') {
            var categoriesMode = document.getElementById('masseditor-categories-mode');
            return categoriesMode ? categoriesMode.options[categoriesMode.selectedIndex].text : '—';
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
        if (operation === 'stock') {
            var stockValue = document.getElementById('masseditor-stock-value');
            return stockMode && stockMode.value === 'infinite' ? t('stock_infinite', 'Make infinite') : (stockValue && stockValue.value ? stockValue.value : '—');
        }
        if (operation === 'features') {
            if (featureMode && featureMode.value === 'clear') {
                return t('clear', 'Clear');
            }
            var feature = document.getElementById('masseditor-feature-id');
            if (feature && feature.value !== '0') {
                var selectedOption = feature.options ? feature.options[feature.selectedIndex] : null;
                var uiType = (selectedOption && selectedOption.getAttribute) ? selectedOption.getAttribute('data-ui') : 'text';
                if (!uiType) { uiType = 'text'; }
                var activeWidget = featureValueField.querySelector('[data-widget="' + uiType + '"]');
                if (!activeWidget) {
                    activeWidget = featureValueField.querySelector('[data-widget="text"]');
                }
                var fallbackValue = document.getElementById('masseditor-feature-value');
                var displayValue = activeWidget ? activeWidget.value : (fallbackValue ? fallbackValue.value : '');
                return displayValue ? displayValue.substring(0, 80) : '—';
            }
            return '—';
        }
        if (operation === 'categories') {
            var category = document.getElementById('masseditor-operation-category-id');
            return category ? category.options[category.selectedIndex].text : '—';
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

        if (operation === 'stock') {
            var stockValue = document.getElementById('masseditor-stock-value');
            if (stockId && stockId.value === '0' && selectedProductsUseWarehouseAccounting()) {
                setWarehouseSelectionInvalid(true);
                showErrorToast(t('validation_stock_required_for_accounted_products', 'For products with warehouse stock accounting, select a specific warehouse.'));
                return false;
            }
            if (stockMode && stockMode.value !== 'infinite' && (!stockValue || !stockValue.value.trim())) {
                showErrorToast(t('invalid_stock_value', 'Enter a valid stock value.'));
                return false;
            }
        }

        if (operation === 'features') {
            var feature = document.getElementById('masseditor-feature-id');
            if (!feature || feature.value === '0') {
                showErrorToast(t('validation_feature', 'Select a feature.'));
                return false;
            }
            if (featureMode && featureMode.value !== 'clear') {
                var selectedOption = feature.options ? feature.options[feature.selectedIndex] : null;
                var uiType = (selectedOption && selectedOption.getAttribute) ? selectedOption.getAttribute('data-ui') : 'text';
                if (!uiType) { uiType = 'text'; }
                var activeWidget = featureValueField.querySelector('[data-widget="' + uiType + '"]');
                if (!activeWidget) {
                    activeWidget = featureValueField.querySelector('[data-widget="text"]');
                }
                var fallbackValue = document.getElementById('masseditor-feature-value');
                var widgetValue = activeWidget ? activeWidget.value : (fallbackValue ? fallbackValue.value : '');
                if (!widgetValue.trim()) {
                    showErrorToast(t('validation_feature_value', 'Enter a feature value.'));
                    return false;
                }
            }
        }

        if (operation === 'categories') {
            var category = document.getElementById('masseditor-operation-category-id');
            if (!category || category.value === '0') {
                showErrorToast(t('validation_category', 'Select a category.'));
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
            var operation = button.getAttribute('data-operation');
            if (operation !== currentOperation()) {
                resetOperationForm();
            }
            setOperation(operation);
        });
    });

    if (comparePriceMode) {
        comparePriceMode.addEventListener('change', updateComparePriceVisibility);
    }

    if (stockMode) {
        stockMode.addEventListener('change', updateStockValueVisibility);
    }

    if (stockId) {
        stockId.addEventListener('change', function () {
            if (stockId.value !== '0') {
                setWarehouseSelectionInvalid(false);
            }
        });
    }

    if (featureMode) {
        featureMode.addEventListener('change', updateFeatureValueVisibility);
    }

    var featureId = document.getElementById('masseditor-feature-id');
    if (featureId) {
        featureId.addEventListener('change', function () {
            showFeatureWidget();
        });
    }

    function showFeatureWidget() {
        if (!featureId || !featureValueField) {
            return;
        }

        var selectedOption = featureId.options ? featureId.options[featureId.selectedIndex] : null;
        var uiType = (selectedOption && selectedOption.getAttribute) ? selectedOption.getAttribute('data-ui') : 'text';
        if (!uiType) {
            uiType = 'text';
        }
        var featureIdValue = featureId.value;

        var widgets = featureValueField.querySelectorAll('[data-widget]');
        Array.prototype.slice.call(widgets).forEach(function (widget) {
            widget.hidden = true;
            widget.disabled = true;
        });

        if (featureIdValue === '0' || featureMode.value === 'clear') {
            return;
        }

        var activeWidget = featureValueField.querySelector('[data-widget="' + uiType + '"]');
        if (!activeWidget) {
            activeWidget = featureValueField.querySelector('[data-widget="text"]');
        }

        if (activeWidget) {
            activeWidget.hidden = false;
            activeWidget.disabled = false;

            if (uiType === 'select') {
                populateSelectValues(activeWidget, featureIdValue);
            }
        }
    }

    function populateSelectValues(selectEl, featureIdValue) {
        var valuesMap = window.__masseditor_feature_values_map || {};
        var values = valuesMap[featureIdValue] || [];
        var texts = window.__masseditor_texts || {};

        while (selectEl.options.length > 1) {
            selectEl.remove(1);
        }

        values.forEach(function (item) {
            var opt = document.createElement('option');
            opt.value = item.value;
            opt.textContent = item.value;
            selectEl.appendChild(opt);
        });

        if (selectEl.options.length > 0) {
            selectEl.options[0].textContent = texts.select_value || '—';
        }
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

    if (selectAllPageMobile) {
        selectAllPageMobile.addEventListener('change', function () {
            productCheckboxes.forEach(function (checkbox) {
                checkbox.checked = selectAllPageMobile.checked;
                setProductSelection(toProductId(checkbox.value), checkbox.checked);
            });
            saveSelectedProductsMap();
            updateSelectionState();
        });
    }

    if (selectFilter) {
        selectFilter.addEventListener('click', function () {
            if (selectionModeInput) {
                selectionModeInput.value = 'filter';
            }
            selectedProductsMap = {};
            saveSelectedProductsMap();
            syncCheckboxesFromSelection();
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
            if (!isFilterSelection()) {
                saveSelectedProductsMap();
            }
            confirmApply.value = '1';
        }
    });

    resetSelectionIfRequested();
    initProductSearchSuggestions();
    initStockPopovers();
    syncCheckboxesFromSelection();
    setOperation(currentOperation());
    updateSelectionState();
    showFeatureWidget();
})();
