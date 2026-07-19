const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { buildAppDom, createLocalStorage } = require('./domHarness');

const scriptPath = path.join(__dirname, '../../wa-apps/shop/plugins/masseditor/js/masseditor.js');
const scriptSource = fs.readFileSync(scriptPath, 'utf8');
const templatePath = path.join(__dirname, '../../wa-apps/shop/plugins/masseditor/templates/actions/backend/Backend.html');
const templateSource = fs.readFileSync(templatePath, 'utf8');
const cssPath = path.join(__dirname, '../../wa-apps/shop/plugins/masseditor/css/masseditor.css');
const cssSource = fs.readFileSync(cssPath, 'utf8');

function boot(options = {}) {
  const app = buildAppDom();
  const timeouts = [];
  const localStorage = createLocalStorage(options.localStorage || {});
  const fetchCalls = [];
  const hasFetchOption = Object.prototype.hasOwnProperty.call(options, 'fetch');
  const reloads = [];
  function FakeFormData(form) {
    this.form = form;
  }
  const window = {
    document: app.document,
    localStorage,
    masseditorI18n: options.i18n || {},
    __masseditor_feature_values_map: options.featureValues || {
      8: [
        { id: 71, value: 'Cotton' },
        { id: 72, value: 'Linen' },
      ],
    },
    fetch: hasFetchOption ? options.fetch : ((url) => {
      fetchCalls.push(url);
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ data: { suggestions: [] }, suggestions: [] }),
      });
    }),
    FormData: Object.prototype.hasOwnProperty.call(options, 'FormData') ? options.FormData : FakeFormData,
    location: {
      reload() {
        reloads.push(true);
      },
    },
    setTimeout(callback, timeout) {
      timeouts.push({ callback, timeout });
      return timeouts.length;
    },
  };
  const context = vm.createContext({
    window,
    document: app.document,
    localStorage,
    console,
    Array,
    JSON,
    Promise,
    parseInt,
    setTimeout: window.setTimeout,
  });

  vm.runInContext(scriptSource, context);

  return { ...app, window, localStorage, timeouts, fetchCalls, reloads };
}

function change(element) {
  element.dispatchEvent({ type: 'change', defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } });
}

function input(element) {
  element.dispatchEvent({ type: 'input', defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } });
}

function submit(form, submitter) {
  const event = {
    type: 'submit',
    submitter,
    defaultPrevented: false,
    preventDefault() {
      this.defaultPrevented = true;
    },
  };
  form.dispatchEvent(event);
  return event;
}

async function flushPromises() {
  await Promise.resolve();
  await Promise.resolve();
  await Promise.resolve();
}

test('initializes toast sources and default operation title', () => {
  const app = boot();

  assert.equal(app.document.querySelector('[data-role="operation-title"]').textContent, 'Change price');
  assert.equal(app.toastStack.children.length, 1);
  assert.equal(app.toastStack.children[0].getAttribute('role'), 'status');
  assert.equal(app.timeouts[0].timeout, 4000);
});

test('fallback strings stay English when i18n dictionary is missing', () => {
  assert.doesNotMatch(scriptSource, /t\('[^']+',\s*'[^']*[А-Яа-яЁё][^']*'\)/);
});

test('uses provided English i18n dictionary for labels and validation', () => {
  const app = boot({
    i18n: {
      toast_success: 'Success',
      toast_error: 'Error',
      toast_info: 'Message',
      toast_close: 'Close notification',
      operation_price: 'Change price',
      operation_tags: 'Tags',
      operation_url: 'Product URLs',
      operation_parameters: 'Operation parameters',
      ready_empty: 'Select products to process.',
      ready_selected_suffix: 'action will be written to the log',
      products_word: 'products',
      stats_selected: 'Selected',
      selected_counter_separator: 'of',
      validation_select_product: 'Select at least one product.',
      validation_url_template: 'Enter a URL template.',
    },
  });
  const openConfirm = app.document.querySelector('[data-role="open-confirm"]');

  assert.equal(app.document.querySelector('[data-role="operation-title"]').textContent, 'Change price');
  assert.equal(app.toastStack.children[0].querySelector('button').getAttribute('aria-label'), 'Close notification');

  openConfirm.click();
  assert.equal(app.toastStack.children[1].querySelector('p').textContent, 'Select at least one product.');
});

test('selection state persists to localStorage and select-all toggles rows', () => {
  const app = boot();
  const selectAll = app.document.querySelector('[data-role="select-all"]');
  const checkboxes = app.document.querySelectorAll('[data-role="product-checkbox"]');
  const selectedCount = app.document.querySelector('[data-role="selected-count"]');

  selectAll.checked = true;
  change(selectAll);

  assert.equal(selectedCount.textContent, 2);
  assert.equal(app.document.querySelector('[data-role="selection-counter-pill"]').textContent, 'Selected 2 of 2');
  assert.equal(checkboxes[0].closest('tr').classList.contains('is-selected'), true);
  assert.equal(app.localStorage.snapshot()['masseditor:selected-products:masseditor'], '[1,2]');
});

test('operation switching updates visible fields and compare-price toggle', () => {
  const app = boot();
  const buttons = app.document.querySelectorAll('[data-role="operation-trigger"]');
  const compareMode = app.document.getElementById('masseditor-compare-price-mode');
  const compareField = app.document.querySelector('[data-compare-mode-field]');

  buttons[1].click();
  assert.equal(app.document.querySelector('[data-role="operation-title"]').textContent, 'Tags');

  buttons[0].click();
  compareMode.value = 'coefficient';
  compareMode.selectedIndex = 1;
  change(compareMode);

  assert.equal(compareField.hidden, false);
  assert.equal(compareField.querySelector('input').disabled, false);
});

test('switching to another operation resets operation form controls', () => {
  const app = boot();
  const buttons = app.document.querySelectorAll('[data-role="operation-trigger"]');
  const numeric = app.document.getElementById('masseditor-numeric-value');
  const compareMode = app.document.getElementById('masseditor-compare-price-mode');
  const compareValue = app.document.getElementById('masseditor-compare-price-value');
  const urlMode = app.document.getElementById('masseditor-url-mode');
  const urlValue = app.document.getElementById('masseditor-url-value');

  numeric.value = '120';
  compareMode.value = 'coefficient';
  compareMode.selectedIndex = 1;
  compareValue.value = '1.15';
  urlMode.value = 'template';
  urlMode.selectedIndex = 1;
  urlValue.value = '{name}-{id}';

  buttons[1].click();

  assert.equal(numeric.value, '');
  assert.equal(compareMode.value, 'keep');
  assert.equal(compareMode.selectedIndex, 0);
  assert.equal(compareValue.value, '');
  assert.equal(urlMode.value, 'regenerate');
  assert.equal(urlMode.selectedIndex, 0);
  assert.equal(urlValue.value, '');
});

test('operation switch preserves product selection and does not reset the active operation twice', () => {
  const app = boot({
    localStorage: {
      'masseditor:selected-products:masseditor': '[1]',
    },
  });
  const buttons = app.document.querySelectorAll('[data-role="operation-trigger"]');
  const tagsValue = app.document.getElementById('masseditor-tags-value');

  buttons[1].click();
  tagsValue.value = 'summer, sale';
  buttons[1].click();

  assert.equal(tagsValue.value, 'summer, sale');
  assert.equal(app.document.querySelector('[data-role="selected-count"]').textContent, 1);
  assert.equal(app.localStorage.snapshot()['masseditor:selected-products:masseditor'], '[1]');
});

test('validation shows error toast when required inputs are missing', () => {
  const app = boot();
  const openConfirm = app.document.querySelector('[data-role="open-confirm"]');

  openConfirm.click();

  assert.equal(app.toastStack.children.length, 2);
  assert.equal(app.toastStack.children[1].getAttribute('role'), 'alert');
  assert.equal(app.modal.hidden, true);
});

test('opening modal fills summary and submit appends hidden product ids with confirmation', () => {
  const app = boot({
    fetch: undefined,
    localStorage: {
      'masseditor:selected-products:masseditor': '[1]',
    },
  });
  const numeric = app.document.getElementById('masseditor-numeric-value');
  const mode = app.document.getElementById('masseditor-mode');
  const compareMode = app.document.getElementById('masseditor-compare-price-mode');
  const openConfirm = app.document.querySelector('[data-role="open-confirm"]');
  const submitter = app.document.createElement('button');
  submitter.setAttribute('data-role', 'confirm-submit');

  numeric.value = '120';
  mode.value = 'decrease_percent';
  mode.selectedIndex = 4;
  compareMode.value = 'keep';
  compareMode.selectedIndex = 0;

  openConfirm.click();
  assert.equal(app.modal.hidden, false);
  assert.equal(app.document.querySelector('[data-role="modal-count"]').textContent, 1);
  assert.equal(app.document.querySelector('[data-role="modal-operation"]').textContent, 'Change price');
  assert.equal(app.document.querySelector('[data-role="modal-mode"]').textContent, 'Decrease percent');
  assert.equal(app.document.body.classList.contains('masseditor-modal-open'), true);

  const event = submit(app.form, submitter);
  assert.equal(event.defaultPrevented, false);
  assert.equal(app.confirmApply.value, '1');

  const persisted = app.form.querySelectorAll('[data-role="persisted-product-id"]');
  assert.equal(persisted.length, 1);
  assert.equal(persisted[0].value, '1');
});

test('confirmed operation switches to progress and waits for manual close after success', async () => {
  let resolveRequest;
  const calls = [];
  const app = boot({
    i18n: {
      operation_progress_title: 'Выполняется массовое изменение',
      operation_progress_message: 'Товары обрабатываются. Не закрывайте это окно.',
      operation_result_success: 'Изменения применены',
      operation_result_error: 'Не удалось применить изменения',
      operation_result_close: 'Закрыть',
      generic_operation_error: 'Операцию не удалось выполнить.',
    },
    localStorage: {
      'masseditor:selected-products:masseditor': '[1]',
    },
    fetch(url, options) {
      calls.push({ url, options });
      return new Promise((resolve) => { resolveRequest = resolve; });
    },
  });
  const numeric = app.document.getElementById('masseditor-numeric-value');
  numeric.value = '25';
  app.document.querySelector('[data-role="open-confirm"]').click();

  const event = submit(app.form, app.confirmSubmit);

  assert.equal(event.defaultPrevented, true);
  assert.equal(app.modal.hidden, true);
  assert.equal(app.progressModal.hidden, false);
  assert.equal(app.progressModal.getAttribute('aria-busy'), 'true');
  assert.equal(app.document.querySelector('[data-role="operation-progress-title"]').textContent, 'Выполняется массовое изменение');
  assert.equal(app.document.querySelector('[data-role="close-progress-modal"]').hidden, true);
  assert.equal(calls.length, 1);
  assert.equal(calls[0].url, '?plugin=masseditor&action=apply');
  assert.equal(calls[0].options.method, 'POST');
  assert.equal(calls[0].options.credentials, 'same-origin');
  assert.equal(calls[0].options.headers['X-Requested-With'], 'XMLHttpRequest');
  assert.equal(calls[0].options.body.form, app.form);

  const duplicateEvent = submit(app.form, app.confirmSubmit);
  assert.equal(duplicateEvent.defaultPrevented, true);
  assert.equal(calls.length, 1);

  resolveRequest({
    ok: true,
    json: () => Promise.resolve({
      status: 'ok',
      data: {
        message: 'Изменение цены · 1 товар · Операция выполнена.',
        reload: true,
        reset_selection: true,
      },
    }),
  });
  await flushPromises();

  assert.equal(app.progressModal.hidden, false);
  assert.equal(app.progressModal.getAttribute('aria-busy'), 'false');
  assert.equal(app.document.querySelector('[data-role="operation-progress-title"]').textContent, 'Изменения применены');
  assert.equal(app.document.querySelector('[data-role="operation-progress-result"]').textContent, 'Изменение цены · 1 товар · Операция выполнена.');
  assert.equal(app.document.querySelector('[data-role="operation-progress-indicator"]').hidden, true);
  assert.equal(app.document.querySelector('[data-role="close-progress-modal"]').hidden, false);
  assert.equal(app.reloads.length, 0);

  app.document.querySelector('[data-role="close-progress-modal"]').click();
  assert.equal(app.localStorage.getItem('masseditor:selected-products:masseditor'), null);
  assert.equal(app.reloads.length, 1);
});

test('failed operation keeps form values after manual result close', async () => {
  const app = boot({
    i18n: {
      operation_progress_title: 'Applying bulk changes',
      operation_progress_message: 'Products are being processed. Keep this window open.',
      operation_result_success: 'Changes applied',
      operation_result_error: 'Could not apply changes',
      operation_result_close: 'Close',
      generic_operation_error: 'The operation could not be completed.',
    },
    localStorage: {
      'masseditor:selected-products:masseditor': '[1]',
    },
    fetch() {
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ status: 'fail', errors: ['Check the operation value.'] }),
      });
    },
  });
  const numeric = app.document.getElementById('masseditor-numeric-value');
  numeric.value = '25';
  app.document.querySelector('[data-role="open-confirm"]').click();
  submit(app.form, app.confirmSubmit);
  await flushPromises();

  assert.equal(app.document.querySelector('[data-role="operation-progress-title"]').textContent, 'Could not apply changes');
  assert.equal(app.document.querySelector('[data-role="operation-progress-result"]').textContent, 'Check the operation value.');

  app.document.querySelector('[data-role="close-progress-modal"]').click();

  assert.equal(app.progressModal.hidden, true);
  assert.equal(numeric.value, '25');
  assert.equal(app.reloads.length, 0);
});

test('submit keeps normal POST fallback when fetch is unavailable', () => {
  const app = boot({
    fetch: undefined,
    localStorage: {
      'masseditor:selected-products:masseditor': '[1]',
    },
  });
  app.document.getElementById('masseditor-numeric-value').value = '25';
  app.document.querySelector('[data-role="open-confirm"]').click();

  const event = submit(app.form, app.confirmSubmit);

  assert.equal(event.defaultPrevented, false);
  assert.equal(app.confirmApply.value, '1');
  assert.equal(app.progressModal.hidden, true);
});


test('new operation fields validate and update confirm summary', () => {
  const app = boot({
    localStorage: {
      'masseditor:selected-products:masseditor': '[1]',
    },
  });
  const buttons = app.document.querySelectorAll('[data-role="operation-trigger"]');
  const stockId = app.document.getElementById('masseditor-stock-id');
  const stockValue = app.document.getElementById('masseditor-stock-value');
  const featureId = app.document.getElementById('masseditor-feature-id');
  const featureValue = app.document.getElementById('masseditor-feature-value');
  const openConfirm = app.document.querySelector('[data-role="open-confirm"]');

  buttons[3].click();
  openConfirm.click();
  assert.equal(app.toastStack.children[1].querySelector('p').textContent, 'Enter a valid stock value.');

  stockId.value = '3';
  stockId.selectedIndex = 1;
  stockValue.value = '12';
  openConfirm.click();
  assert.equal(app.modal.hidden, false);
  assert.equal(app.document.querySelector('[data-role="modal-operation"]').textContent, 'Stock');
  assert.equal(app.document.querySelector('[data-role="modal-mode"]').textContent, 'Set · Main');
  assert.equal(app.document.querySelector('[data-role="modal-value"]').textContent, '12');

  app.document.querySelector('[data-role="close-modal"]').click();
  buttons[4].click();
  featureId.value = '7';
  featureId.selectedIndex = 1;
  openConfirm.click();
  assert.equal(app.toastStack.children[2].querySelector('p').textContent, 'Enter a feature value.');

  featureValue.value = 'cotton';
  openConfirm.click();
  assert.equal(app.document.querySelector('[data-role="modal-operation"]').textContent, 'Feature editing');
  assert.equal(app.document.querySelector('[data-role="modal-value"]').textContent, 'cotton');
});

test('multiple feature switches to safe modes and requires selected values', () => {
  const app = boot({
    localStorage: {
      'masseditor:selected-products:masseditor': '[1]',
    },
  });
  const buttons = app.document.querySelectorAll('[data-role="operation-trigger"]');
  const featureId = app.document.getElementById('masseditor-feature-id');
  const featureMode = app.document.getElementById('masseditor-feature-mode');
  const multiple = app.document.getElementById('masseditor-feature-value-multiple');
  const featureValueField = app.document.querySelector('[data-feature-value-field]');
  const openConfirm = app.document.querySelector('[data-role="open-confirm"]');

  buttons[4].click();
  featureId.value = '8';
  featureId.selectedIndex = 2;
  change(featureId);

  assert.equal(featureMode.value, 'replace');
  assert.equal(multiple.hidden, false);
  assert.equal(multiple.disabled, false);

  openConfirm.click();
  assert.equal(app.toastStack.children[1].querySelector('p').textContent, 'Enter a feature value.');

  multiple.options[0].selected = true;
  openConfirm.click();
  assert.equal(app.modal.hidden, false);
  assert.equal(app.document.querySelector('[data-role="modal-value"]').textContent, 'Cotton');

  app.document.querySelector('[data-role="close-modal"]').click();
  featureMode.value = 'clear';
  featureMode.selectedIndex = 1;
  change(featureMode);
  assert.equal(featureValueField.hidden, true);
  assert.equal(multiple.disabled, true);
});

test('video operation validates URL and disables it in clear mode', () => {
  const app = boot({
    localStorage: {
      'masseditor:selected-products:masseditor': '[1]',
    },
    i18n: {
      operation_video: 'Video',
      validation_video_url: 'Enter a valid HTTP(S) video URL.',
      validation_video_supported_url: 'Скопируйте в это поле адрес видеоролика товара с сайта Rutube, VK, YouTube или Vimeo.',
    },
  });
  const videoButton = app.document.querySelectorAll('[data-role="operation-trigger"]').find((button) => button.getAttribute('data-operation') === 'video');
  const videoMode = app.document.getElementById('masseditor-video-mode');
  const videoUrl = app.document.getElementById('masseditor-video-url');
  const videoUrlField = app.document.querySelector('[data-video-url-field]');
  const videoUrlError = app.document.querySelector('[data-video-url-error]');
  const openConfirm = app.document.querySelector('[data-role="open-confirm"]');

  videoButton.click();
  videoUrl.value = 'ftp://example.com/video';
  openConfirm.click();
  assert.equal(app.toastStack.children[1].querySelector('p').textContent, 'Enter a valid HTTP(S) video URL.');

  videoUrl.value = `https://example.com/${'a'.repeat(240)}`;
  openConfirm.click();
  assert.equal(app.toastStack.children[2].querySelector('p').textContent, 'Enter a valid HTTP(S) video URL.');

  videoUrl.value = 'https://yandex.ru/video/preview/14675653702268624155';
  openConfirm.click();
  assert.equal(app.modal.hidden, true);
  assert.equal(videoUrlField.classList.contains('masseditor-field_invalid'), true);
  assert.equal(videoUrl.getAttribute('aria-invalid'), 'true');
  assert.equal(videoUrlError.hidden, false);
  assert.equal(videoUrlError.textContent, 'Скопируйте в это поле адрес видеоролика товара с сайта Rutube, VK, YouTube или Vimeo.');

  [
    'https://www.youtube.com/watch?v=abc',
    'https://vimeo.com/12345',
    'https://rutube.ru/video/abc-def',
    'https://vk.com/video-123_456',
  ].forEach((supportedUrl) => {
    videoUrl.value = supportedUrl;
    input(videoUrl);
    assert.equal(videoUrlField.classList.contains('masseditor-field_invalid'), false);
    assert.equal(videoUrl.getAttribute('aria-invalid'), 'false');
    assert.equal(videoUrlError.hidden, true);
    openConfirm.click();
    assert.equal(app.modal.hidden, false);
    assert.equal(app.document.querySelector('[data-role="modal-operation"]').textContent, 'Video');
    assert.equal(app.document.querySelector('[data-role="modal-value"]').textContent, supportedUrl);
    app.document.querySelector('[data-role="close-modal"]').click();
  });

  videoMode.value = 'clear';
  videoMode.selectedIndex = 1;
  change(videoMode);
  assert.equal(videoUrlField.hidden, true);
  assert.equal(videoUrl.disabled, true);
  assert.equal(videoUrlField.classList.contains('masseditor-field_invalid'), false);
  assert.equal(videoUrlError.hidden, true);
});

test('video URL field renders an accessible inline validation contract', () => {
  assert.match(templateSource, /data-video-url-error/);
  assert.match(templateSource, /aria-describedby="masseditor-video-url-error"/);
  assert.match(templateSource, /placeholder="\{\$texts\.video_url_placeholder\|escape\}"/);
  assert.match(cssSource, /\.masseditor-field_invalid input\[type="url"\]/);
  assert.match(cssSource, /\.masseditor-field__error/);
});

test('stock operation allows regular count when warehouse is not selected', () => {
  const app = boot({
    localStorage: {
      'masseditor:selected-products:masseditor': '[1]',
    },
  });
  const buttons = app.document.querySelectorAll('[data-role="operation-trigger"]');
  const stockId = app.document.getElementById('masseditor-stock-id');
  const stockValue = app.document.getElementById('masseditor-stock-value');
  const openConfirm = app.document.querySelector('[data-role="open-confirm"]');

  buttons[3].click();
  stockId.value = '0';
  stockValue.value = '12';
  openConfirm.click();

  assert.equal(app.modal.hidden, false);
  assert.equal(app.document.querySelector('[data-role="modal-operation"]').textContent, 'Stock');
  assert.equal(app.document.querySelector('[data-role="modal-value"]').textContent, '12');
});

test('stock operation requires warehouse selection for products with warehouse accounting', () => {
  const app = boot({
    localStorage: {
      'masseditor:selected-products:masseditor': '[1]',
    },
  });
  const buttons = app.document.querySelectorAll('[data-role="operation-trigger"]');
  const stockId = app.document.getElementById('masseditor-stock-id');
  const stockValue = app.document.getElementById('masseditor-stock-value');
  const checkbox = app.document.querySelector('[data-role="product-checkbox"]');
  const openConfirm = app.document.querySelector('[data-role="open-confirm"]');

  buttons[3].click();
  checkbox.setAttribute('data-has-warehouse-stock', '1');
  checkbox.checked = true;
  stockId.value = '0';
  stockValue.value = '12';
  openConfirm.click();

  assert.equal(app.modal.hidden, true);
  assert.equal(stockId.getAttribute('aria-invalid'), 'true');
  assert.equal(stockId.closest('.masseditor-field').classList.contains('masseditor-field_invalid'), true);

  stockId.value = '3';
  change(stockId);

  assert.equal(stockId.getAttribute('aria-invalid'), 'false');
  assert.equal(stockId.closest('.masseditor-field').classList.contains('masseditor-field_invalid'), false);
});

function assertWarehouseSelectionToast(i18n, expected) {
  const app = boot({
    i18n,
    localStorage: {
      'masseditor:selected-products:masseditor': '[1]',
    },
  });
  const buttons = app.document.querySelectorAll('[data-role="operation-trigger"]');
  const stockId = app.document.getElementById('masseditor-stock-id');
  const stockValue = app.document.getElementById('masseditor-stock-value');
  const checkbox = app.document.querySelector('[data-role="product-checkbox"]');
  const openConfirm = app.document.querySelector('[data-role="open-confirm"]');

  buttons[3].click();
  checkbox.setAttribute('data-has-warehouse-stock', '1');
  checkbox.checked = true;
  stockId.value = '0';
  stockValue.value = '12';
  openConfirm.click();

  const toast = app.toastStack.children[1];
  assert.equal(toast.children[0].children[0].textContent, expected.title);
  assert.equal(toast.querySelector('p').textContent, expected.message);
  assert.equal(toast.querySelector('button').getAttribute('aria-label'), expected.closeLabel);
}

test('warehouse stock validation toast is fully localized in Russian', () => {
  assertWarehouseSelectionToast({
    toast_error: 'Ошибка',
    toast_close: 'Закрыть уведомление',
    validation_stock_required_for_accounted_products: 'Для товаров со складским учетом выберите конкретный склад.',
  }, {
    title: 'Ошибка',
    message: 'Для товаров со складским учетом выберите конкретный склад.',
    closeLabel: 'Закрыть уведомление',
  });
});

test('warehouse stock validation toast is fully localized in English', () => {
  assertWarehouseSelectionToast({
    toast_error: 'Error',
    toast_close: 'Close notification',
    validation_stock_required_for_accounted_products: 'For products with warehouse stock accounting, select a specific warehouse.',
  }, {
    title: 'Error',
    message: 'For products with warehouse stock accounting, select a specific warehouse.',
    closeLabel: 'Close notification',
  });
});

test('warehouse stock popover toggles and keeps confirmation flow intact', () => {
  const app = boot({
    localStorage: {
      'masseditor:selected-products:masseditor': '[1]',
    },
  });
  const toggle = app.document.querySelector('[data-role="stock-popover-toggle"]');
  const popover = app.document.querySelector('[data-role="stock-popover"]');
  const numeric = app.document.getElementById('masseditor-numeric-value');
  const openConfirm = app.document.querySelector('[data-role="open-confirm"]');

  assert.equal(popover.hidden, true);

  toggle.click();
  assert.equal(popover.hidden, false);
  assert.equal(toggle.getAttribute('aria-expanded'), 'true');

  toggle.click();
  assert.equal(popover.hidden, true);
  assert.equal(toggle.getAttribute('aria-expanded'), 'false');

  toggle.click();
  app.document.dispatchEvent({ type: 'click', target: app.document.body, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } });
  assert.equal(popover.hidden, true);

  toggle.click();
  app.document.dispatchEvent({ type: 'keydown', key: 'Escape', defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } });
  assert.equal(popover.hidden, true);

  numeric.value = '120';
  openConfirm.click();
  assert.equal(app.modal.hidden, false);
  assert.equal(app.document.querySelector('[data-role="modal-operation"]').textContent, 'Change price');
});

test('mobile product cards hide all desktop-only columns through changed date', () => {
  const mobileBlock = cssSource.slice(cssSource.indexOf('@media (max-width: 1024px)'));

  assert.match(
    mobileBlock,
    /\.masseditor-table_products td:nth-child\(13\)\s*\{\s*display:\s*none;\s*\}/
  );
  assert.doesNotMatch(
    mobileBlock,
    /\.masseditor-table_products td:nth-child\(7\)\s*\{[\s\S]*?grid-column:/
  );
});

test('css defines readable desktop typography contract for backend tables and controls', () => {
  assert.match(
    cssSource,
    /\.masseditor\s*\{[\s\S]*--me-font-body:\s*16px;[\s\S]*--me-font-ui:\s*15px;[\s\S]*--me-font-caption:\s*13px;[\s\S]*--me-control-height:\s*44px;[\s\S]*--me-control-height-compact:\s*36px;/
  );
  assert.match(
    cssSource,
    /\.masseditor input\[type="text"\],[\s\S]*?\.masseditor textarea\s*\{[\s\S]*min-height:\s*var\(--me-control-height\);[\s\S]*font-size:\s*var\(--me-font-body\);[\s\S]*line-height:\s*var\(--me-line-body\);/
  );
  assert.match(
    cssSource,
    /\.masseditor-table th,\s*\.masseditor-table td\s*\{[\s\S]*font-size:\s*var\(--me-font-ui\);[\s\S]*line-height:\s*var\(--me-line-body\);/
  );
  assert.match(
    cssSource,
    /\.masseditor-table thead th\s*\{[\s\S]*font-size:\s*var\(--me-font-caption\);/
  );
  assert.match(
    cssSource,
    /\.masseditor-button\s*\{[\s\S]*min-height:\s*var\(--me-control-height\);[\s\S]*font-size:\s*15px;/
  );
});

test('template and css define accessible indeterminate operation progress modal', () => {
  assert.match(templateSource, /data-role="workspace-form"[^>]+data-apply-url="\{\$apply_url\|escape\}"/);
  assert.match(templateSource, /\{\$wa->csrf\(\)\}/);
  assert.match(templateSource, /data-role="operation-progress-modal"[^>]+aria-busy="false"[^>]+hidden/);
  assert.match(templateSource, /data-role="operation-progress-indicator" role="progressbar" aria-label="\{\$texts\.operation_progress_label\|escape\}"/);
  assert.match(templateSource, /data-role="close-progress-modal" hidden>\{\$texts\.operation_result_close\|escape\}<\/button>/);
  assert.match(cssSource, /\.masseditor-operation-progress__bar\s*\{[\s\S]*animation:\s*masseditor-progress-indeterminate/);
  assert.match(cssSource, /@keyframes masseditor-progress-indeterminate/);
  assert.match(cssSource, /--me-danger-soft:\s*#[0-9a-f]{6};/i);
  assert.match(cssSource, /\.masseditor\.theme-dark\s*\{[\s\S]*--me-danger-soft:\s*rgba\(/);
});

test('css defines larger mobile typography and touch targets', () => {
  const mobileBlock = cssSource.slice(cssSource.indexOf('@media (max-width: 1024px)'));

  assert.match(
    mobileBlock,
    /\.masseditor\s*\{[\s\S]*--me-font-body:\s*30px;[\s\S]*--me-font-ui:\s*30px;[\s\S]*--me-font-small:\s*28px;[\s\S]*--me-font-caption:\s*25px;[\s\S]*--me-control-height:\s*64px;[\s\S]*--me-control-height-compact:\s*56px;/
  );
  assert.match(
    mobileBlock,
    /\.masseditor-table_products tr\s*\{[\s\S]*padding:\s*18px;/
  );
  assert.match(
    mobileBlock,
    /\.masseditor-product-mobile-details__row span\s*\{[\s\S]*font-size:\s*25px;/
  );
  assert.match(
    mobileBlock,
    /\.masseditor-product-mobile-details__row strong\s*\{[\s\S]*font-size:\s*30px;/
  );
  assert.match(
    mobileBlock,
    /\.masseditor-mobile-apply \.masseditor-button\s*\{[\s\S]*min-height:\s*64px;[\s\S]*font-size:\s*25px;/
  );
});

test('select all by filter switches payload mode without losing checkbox mode', () => {
  const app = boot();
  const selectFilter = app.document.querySelector('[data-role="select-filter"]');
  const selectionMode = app.document.querySelector('[data-role="selection-mode"]');
  const openConfirm = app.document.querySelector('[data-role="open-confirm"]');
  const numeric = app.document.getElementById('masseditor-numeric-value');
  const checkbox = app.document.querySelectorAll('[data-role="product-checkbox"]')[0];
  const submitter = app.document.createElement('button');
  submitter.setAttribute('data-role', 'confirm-submit');

  numeric.value = '100';
  selectFilter.click();
  assert.equal(selectionMode.value, 'filter');
  assert.equal(app.document.querySelector('[data-role="selected-count"]').textContent, 8);
  assert.equal(app.document.querySelector('[data-role="select-all"]').checked, true);
  assert.deepEqual(
    Array.from(app.document.querySelectorAll('[data-role="product-checkbox"]')).map((item) => item.checked),
    [true, true]
  );

  openConfirm.click();
  assert.equal(app.document.querySelector('[data-role="modal-count"]').textContent, 8);
  submit(app.form, submitter);
  assert.equal(app.form.querySelectorAll('[data-role="persisted-product-id"]').length, 0);

  checkbox.checked = true;
  change(checkbox);
  assert.equal(selectionMode.value, 'ids');
  assert.equal(app.document.querySelector('[data-role="selected-count"]').textContent, 1);
});

test('template renders compact select-all-found button and left-aligned counter under title', () => {
  assert.match(
    templateSource,
    /<div class="masseditor-table-card__heading">\s*<h2>\{\$texts\.nav_products\|escape\}<\/h2>\s*<span class="masseditor-counter" data-role="selection-counter-pill" data-total="\{\$pagination\.total\|escape\}">\{\$texts\.stats_selected\|escape\} 0 \{\$texts\.selected_counter_separator\|escape\} \{\$pagination\.total\|escape\}<\/span>\s*<\/div>\s*<div class="masseditor-searchbox masseditor-table-search">[\s\S]*data-role="product-search-input"[\s\S]*data-role="product-search-suggestions"[\s\S]*<div class="masseditor-panel__actions masseditor-table-card__actions">[\s\S]*\{if \$can_select_filter\}\s*<button class="button masseditor-button masseditor-button_primary masseditor-button_compact" type="button" data-role="select-filter"/
  );
});

test('template keeps product search in table header instead of filters panel', () => {
  const filtersStart = templateSource.indexOf('masseditor-panel_filters');
  const filtersEnd = templateSource.indexOf('masseditor-panel_library');
  const filtersPanel = templateSource.slice(filtersStart, filtersEnd);

  assert.doesNotMatch(filtersPanel, /data-role="product-search-input"/);
  assert.match(
    templateSource,
    /masseditor-table-card__head[\s\S]*data-role="product-search-input"/
  );
});

test('template renders five price mode options', () => {
  assert.match(
    templateSource,
    /<option value="set"\{if \$operation_form\.mode === 'set'\} selected\{\/if\}>\{\$texts\.set_value\|escape\}<\/option>/
  );
  assert.match(
    templateSource,
    /<option value="add"\{if \$operation_form\.mode === 'add'\} selected\{\/if\}>\{\$texts\.add_value\|escape\}<\/option>/
  );
  assert.match(
    templateSource,
    /<option value="subtract"\{if \$operation_form\.mode === 'subtract'\} selected\{\/if\}>\{\$texts\.subtract_value\|escape\}<\/option>/
  );
  assert.match(
    templateSource,
    /<option value="increase_percent"\{if \$operation_form\.mode === 'increase_percent'\} selected\{\/if\}>\{\$texts\.increase_percent\|escape\}<\/option>/
  );
  assert.match(
    templateSource,
    /<option value="decrease_percent"\{if \$operation_form\.mode === 'decrease_percent'\} selected\{\/if\}>\{\$texts\.decrease_percent\|escape\}<\/option>/
  );
});

test('url template validation requires value', () => {
  const app = boot({
    localStorage: {
      'masseditor:selected-products:masseditor': '[2]',
    },
  });
  const buttons = app.document.querySelectorAll('[data-role="operation-trigger"]');
  const openConfirm = app.document.querySelector('[data-role="open-confirm"]');
  const urlMode = app.document.getElementById('masseditor-url-mode');

  buttons[2].click();
  urlMode.value = 'template';
  urlMode.selectedIndex = 1;
  openConfirm.click();

  assert.equal(app.toastStack.children[1].querySelector('p').textContent, 'Enter a URL template.');
});

test('product search suggestions request current filters and render options', async () => {
  let requestedUrl = '';
  const app = boot({
    fetch(url) {
      requestedUrl = url;
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ suggestions: ['SUM-1', 'Summer dress'] }),
      });
    },
  });
  const query = app.document.querySelector('[data-role="product-search-input"]');

  query.value = 'sum';
  input(query);
  await flushPromises();

  assert.match(requestedUrl, /action=searchSuggestions/);
  assert.match(requestedUrl, /query=sum/);
  assert.match(requestedUrl, /status=published/);
  assert.match(requestedUrl, /availability=available/);
  assert.match(requestedUrl, /category_id=9/);
  assert.match(requestedUrl, /stock_id=5/);
  assert.equal(app.document.querySelector('[data-role="product-search-suggestions"]').children.length, 2);
});

test('selecting product search suggestion fills query and submits filter form', async () => {
  const app = boot({
    fetch() {
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ suggestions: ['SUM-1'] }),
      });
    },
  });
  const query = app.document.querySelector('[data-role="product-search-input"]');
  let submitted = false;

  app.filterForm.submit = () => {
    submitted = true;
  };

  query.value = 'sum';
  input(query);
  await flushPromises();

  app.document.querySelector('[data-role="product-search-suggestion"]').click();

  assert.equal(query.value, 'SUM-1');
  assert.equal(submitted, true);
});

test('product search suggestions are skipped when fetch is unavailable', () => {
  const app = boot({ fetch: undefined });
  const query = app.document.querySelector('[data-role="product-search-input"]');

  query.value = 'sum';
  input(query);

  assert.equal(app.document.querySelector('[data-role="product-search-suggestions"]').children.length, 0);
});

test('product search clear button resets query and submits filter form', () => {
  const app = boot({ fetch: undefined });
  const query = app.document.querySelector('[data-role="product-search-input"]');
  const clear = app.document.querySelector('[data-role="product-search-clear"]');
  let submitted = false;

  app.filterForm.submit = () => {
    submitted = true;
  };

  assert.equal(clear.hidden, true);

  query.value = 'sum';
  input(query);
  assert.equal(clear.hidden, false);

  clear.click();

  assert.equal(query.value, '');
  assert.equal(clear.hidden, true);
  assert.equal(app.document.activeElement, query);
  assert.equal(submitted, true);
});

test('mobile select-all-page checkbox toggles all product checkboxes and persists to localStorage', () => {
  const app = boot();
  const mobileSelectAll = app.document.querySelector('[data-role="select-all-page-mobile"]');
  const checkboxes = app.document.querySelectorAll('[data-role="product-checkbox"]');

  mobileSelectAll.checked = true;
  change(mobileSelectAll);

  assert.equal(checkboxes[0].checked, true);
  assert.equal(checkboxes[1].checked, true);
  assert.equal(app.document.querySelector('[data-role="selected-count"]').textContent, 2);
  assert.equal(app.localStorage.snapshot()['masseditor:selected-products:masseditor'], '[1,2]');
});

test('mobile select-all-page checkbox unchecks all and clears localStorage', () => {
  const app = boot({
    localStorage: { 'masseditor:selected-products:masseditor': '[1,2]' },
  });
  const mobileSelectAll = app.document.querySelector('[data-role="select-all-page-mobile"]');
  const checkboxes = app.document.querySelectorAll('[data-role="product-checkbox"]');

  checkboxes[0].checked = true;
  checkboxes[1].checked = true;

  mobileSelectAll.checked = false;
  change(mobileSelectAll);

  assert.equal(checkboxes[0].checked, false);
  assert.equal(checkboxes[1].checked, false);
  assert.equal(app.document.querySelector('[data-role="selected-count"]').textContent, 0);
  assert.equal(app.localStorage.snapshot()['masseditor:selected-products:masseditor'], undefined);
});

test('mobile select-all-page checkbox shows indeterminate when partial selection', () => {
  const app = boot();
  const mobileSelectAll = app.document.querySelector('[data-role="select-all-page-mobile"]');
  const checkboxes = app.document.querySelectorAll('[data-role="product-checkbox"]');

  checkboxes[0].checked = true;
  change(checkboxes[0]);

  assert.equal(mobileSelectAll.indeterminate, true);
  assert.equal(mobileSelectAll.checked, false);

  checkboxes[1].checked = true;
  change(checkboxes[1]);

  assert.equal(mobileSelectAll.indeterminate, false);
  assert.equal(mobileSelectAll.checked, true);
});
