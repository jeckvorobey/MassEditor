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
  const window = {
    document: app.document,
    localStorage,
    masseditorI18n: options.i18n || {},
    fetch: hasFetchOption ? options.fetch : ((url) => {
      fetchCalls.push(url);
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ data: { suggestions: [] }, suggestions: [] }),
      });
    }),
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

  return { ...app, window, localStorage, timeouts, fetchCalls };
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

test('initializes toast sources and toggle label', () => {
  const app = boot();
  const toggleLabel = app.document.querySelector('[data-role="soon-operations-toggle-label"]');

  assert.equal(toggleLabel.textContent, 'Disabled');
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
      enabled: 'Enabled',
      disabled: 'Disabled',
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
  const toggleLabel = app.document.querySelector('[data-role="soon-operations-toggle-label"]');
  const openConfirm = app.document.querySelector('[data-role="open-confirm"]');

  assert.equal(toggleLabel.textContent, 'Disabled');
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


test('new operation fields validate and update confirm summary', () => {
  const app = boot({
    localStorage: {
      'masseditor:selected-products:masseditor': '[1]',
    },
  });
  const buttons = app.document.querySelectorAll('[data-role="operation-trigger"]');
  const stockValue = app.document.getElementById('masseditor-stock-value');
  const featureValue = app.document.getElementById('masseditor-feature-value');
  const openConfirm = app.document.querySelector('[data-role="open-confirm"]');

  buttons[3].click();
  openConfirm.click();
  assert.equal(app.toastStack.children[1].querySelector('p').textContent, 'Enter a valid stock value.');

  stockValue.value = '12';
  openConfirm.click();
  assert.equal(app.modal.hidden, false);
  assert.equal(app.document.querySelector('[data-role="modal-operation"]').textContent, 'Stock');
  assert.equal(app.document.querySelector('[data-role="modal-mode"]').textContent, 'Set · Main');
  assert.equal(app.document.querySelector('[data-role="modal-value"]').textContent, '12');

  app.document.querySelector('[data-role="close-modal"]').click();
  buttons[4].click();
  openConfirm.click();
  assert.equal(app.toastStack.children[2].querySelector('p').textContent, 'Enter a feature value.');

  featureValue.value = 'cotton';
  openConfirm.click();
  assert.equal(app.document.querySelector('[data-role="modal-operation"]').textContent, 'Basic feature editing');
  assert.equal(app.document.querySelector('[data-role="modal-value"]').textContent, 'cotton');
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
    /<div class="masseditor-table-card__heading">\s*<h2>\{\$texts\.nav_products\|escape\}<\/h2>\s*<span class="masseditor-counter" data-role="selection-counter-pill" data-total="\{\$pagination\.total\|escape\}">\{\$texts\.stats_selected\|escape\} 0 \{\$texts\.selected_counter_separator\|escape\} \{\$pagination\.total\|escape\}<\/span>\s*<\/div>\s*<div class="masseditor-searchbox masseditor-table-search">[\s\S]*data-role="product-search-input"[\s\S]*data-role="product-search-suggestions"[\s\S]*<div class="masseditor-panel__actions masseditor-table-card__actions">\s*\{if \$can_select_filter\}\s*<button class="button masseditor-button masseditor-button_primary masseditor-button_compact" type="button" data-role="select-filter"/
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
