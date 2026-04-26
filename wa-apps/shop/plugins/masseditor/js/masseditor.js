(function () {
    'use strict';

    var workspaceForm = document.querySelector('[data-role="workspace-form"]');

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
    var confirmApply = document.querySelector('[data-role="confirm-apply"]');
    var modal = document.querySelector('[data-role="confirm-modal"]');
    var closeModalButtons = Array.prototype.slice.call(document.querySelectorAll('[data-role="close-modal"]'));
    var modalCount = document.querySelector('[data-role="modal-count"]');
    var modalOperation = document.querySelector('[data-role="modal-operation"]');
    var modalMode = document.querySelector('[data-role="modal-mode"]');
    var modalValue = document.querySelector('[data-role="modal-value"]');

    var operationLabels = {
        price: 'Изменить цену',
        compare_price: 'Изменить compare price',
        visibility: 'Изменить видимость',
        availability: 'Изменить доступность',
        description: 'Описание',
        tags: 'Теги',
        url: 'URL товаров'
    };

    function currentOperation() {
        return operationInput ? operationInput.value : 'price';
    }

    function countCheckedProducts() {
        return productCheckboxes.filter(function (checkbox) {
            return checkbox.checked;
        }).length;
    }

    function updateSelectionState() {
        var checkedCount = countCheckedProducts();

        if (selectedCount) {
            selectedCount.textContent = checkedCount;
        }

        if (selectionCounterPill) {
            var total = selectionCounterPill.textContent.split('из').pop();
            selectionCounterPill.textContent = checkedCount + ' из' + total;
        }

        if (readyCopy) {
            readyCopy.textContent = checkedCount > 0
                ? checkedCount + ' товара(ов) будут обработаны после подтверждения.'
                : 'Выберите товары и подтвердите действие в модальном окне.';
        }

        if (selectAll) {
            selectAll.checked = checkedCount > 0 && checkedCount === productCheckboxes.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < productCheckboxes.length;
        }
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
            operationTitle.textContent = operationLabels[operation] || 'Параметры операции';
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
                return 'Из названия товара';
            }
            return url && url.value ? url.value : '—';
        }

        return '—';
    }

    function validateBeforeModal() {
        var checkedCount = countCheckedProducts();
        var operation = currentOperation();

        if (checkedCount === 0) {
            window.alert('Выберите хотя бы один товар.');
            return false;
        }

        if ((operation === 'price' || operation === 'compare_price') && !document.getElementById('masseditor-numeric-value').value.trim()) {
            window.alert('Укажите значение для массовой операции.');
            return false;
        }

        if (operation === 'description' && !document.getElementById('masseditor-text-value').value.trim()) {
            window.alert('Введите текст для описания.');
            return false;
        }

        if (operation === 'tags' && !document.getElementById('masseditor-tags-value').value.trim()) {
            window.alert('Укажите хотя бы один тег.');
            return false;
        }

        if (operation === 'url') {
            var mode = document.getElementById('masseditor-url-mode');
            var value = document.getElementById('masseditor-url-value');
            if (mode && mode.value === 'template' && value && !value.value.trim()) {
                window.alert('Укажите шаблон URL.');
                return false;
            }
        }

        if (operation === 'price' && comparePriceMode && comparePriceMode.value === 'coefficient') {
            var coefficient = document.getElementById('masseditor-compare-price-value');
            if (!coefficient || !coefficient.value.trim()) {
                window.alert('Укажите коэффициент для compare price.');
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
            modalCount.textContent = countCheckedProducts();
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
            });
            updateSelectionState();
        });
    }

    productCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', updateSelectionState);
    });

    if (openConfirmButton) {
        openConfirmButton.addEventListener('click', openModal);
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
            confirmApply.value = '1';
        }
    });

    setOperation(currentOperation());
    updateSelectionState();
})();
