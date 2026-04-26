const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { buildAppDom, createLocalStorage } = require('./domHarness');

const scriptPath = path.join(__dirname, '../../wa-apps/shop/plugins/masseditor/js/masseditor.js');
const scriptSource = fs.readFileSync(scriptPath, 'utf8');

function boot(options = {}) {
  const app = buildAppDom();
  const timeouts = [];
  const localStorage = createLocalStorage(options.localStorage || {});
  const window = {
    document: app.document,
    localStorage,
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
    parseInt,
    setTimeout: window.setTimeout,
  });

  vm.runInContext(scriptSource, context);

  return { ...app, window, localStorage, timeouts };
}

function change(element) {
  element.dispatchEvent({ type: 'change', defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } });
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

test('initializes toast sources and toggle label', () => {
  const app = boot();
  const toggleLabel = app.document.querySelector('[data-role="soon-operations-toggle-label"]');

  assert.equal(toggleLabel.textContent, 'Выключено');
  assert.equal(app.toastStack.children.length, 1);
  assert.equal(app.toastStack.children[0].getAttribute('role'), 'status');
  assert.equal(app.timeouts[0].timeout, 4000);
});

test('selection state persists to localStorage and select-all toggles rows', () => {
  const app = boot();
  const selectAll = app.document.querySelector('[data-role="select-all"]');
  const checkboxes = app.document.querySelectorAll('[data-role="product-checkbox"]');
  const selectedCount = app.document.querySelector('[data-role="selected-count"]');

  selectAll.checked = true;
  change(selectAll);

  assert.equal(selectedCount.textContent, 2);
  assert.equal(app.document.querySelector('[data-role="selection-counter-pill"]').textContent, '2 из 2');
  assert.equal(checkboxes[0].closest('tr').classList.contains('is-selected'), true);
  assert.equal(app.localStorage.snapshot()['masseditor:selected-products:masseditor'], '[1,2]');
});

test('operation switching updates visible fields and compare-price toggle', () => {
  const app = boot();
  const buttons = app.document.querySelectorAll('[data-role="operation-trigger"]');
  const compareMode = app.document.getElementById('masseditor-compare-price-mode');
  const compareField = app.document.querySelector('[data-compare-mode-field]');

  buttons[1].click();
  assert.equal(app.document.querySelector('[data-role="operation-title"]').textContent, 'Теги');

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
  const compareMode = app.document.getElementById('masseditor-compare-price-mode');
  const openConfirm = app.document.querySelector('[data-role="open-confirm"]');
  const submitter = app.document.createElement('button');
  submitter.setAttribute('data-role', 'confirm-submit');

  numeric.value = '120';
  compareMode.value = 'keep';
  compareMode.selectedIndex = 0;

  openConfirm.click();
  assert.equal(app.modal.hidden, false);
  assert.equal(app.document.querySelector('[data-role="modal-count"]').textContent, 1);
  assert.equal(app.document.querySelector('[data-role="modal-operation"]').textContent, 'Изменить цену');
  assert.equal(app.document.body.classList.contains('masseditor-modal-open'), true);

  const event = submit(app.form, submitter);
  assert.equal(event.defaultPrevented, false);
  assert.equal(app.confirmApply.value, '1');

  const persisted = app.form.querySelectorAll('[data-role="persisted-product-id"]');
  assert.equal(persisted.length, 1);
  assert.equal(persisted[0].value, '1');
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

  assert.equal(app.toastStack.children[1].querySelector('p').textContent, 'Укажите шаблон URL.');
});
