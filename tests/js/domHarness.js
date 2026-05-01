class ClassList {
  constructor(node) {
    this.node = node;
    this.set = new Set();
  }

  add(...tokens) {
    tokens.forEach((token) => this.set.add(token));
    this._sync();
  }

  remove(...tokens) {
    tokens.forEach((token) => this.set.delete(token));
    this._sync();
  }

  toggle(token, force) {
    if (force === true) {
      this.set.add(token);
    } else if (force === false) {
      this.set.delete(token);
    } else if (this.set.has(token)) {
      this.set.delete(token);
    } else {
      this.set.add(token);
    }
    this._sync();
  }

  contains(token) {
    return this.set.has(token);
  }

  _sync() {
    this.node.className = Array.from(this.set).join(' ');
  }
}

class Element {
  constructor(tagName) {
    this.tagName = String(tagName).toUpperCase();
    this.children = [];
    this.parentNode = null;
    this.attributes = {};
    this.listeners = {};
    this.className = '';
    this.classList = new ClassList(this);
    this.hidden = false;
    this.disabled = false;
    this.checked = false;
    this.indeterminate = false;
    this.value = '';
    this.textContent = '';
    this.type = '';
    this.name = '';
    this.id = '';
    this.options = [];
    this.selectedIndex = 0;
  }

  appendChild(child) {
    child.parentNode = this;
    this.children.push(child);
    return child;
  }

  removeChild(child) {
    this.children = this.children.filter((item) => item !== child);
    child.parentNode = null;
    return child;
  }

  setAttribute(name, value) {
    const stringValue = String(value);
    this.attributes[name] = stringValue;
    if (name === 'id') {
      this.id = stringValue;
    }
    if (name === 'name') {
      this.name = stringValue;
    }
    if (name === 'class') {
      stringValue.split(/\s+/).filter(Boolean).forEach((token) => this.classList.add(token));
    }
    if (name === 'type') {
      this.type = stringValue;
    }
  }

  getAttribute(name) {
    if (name === 'class') {
      return this.className;
    }
    if (name === 'id') {
      return this.id || null;
    }
    if (name === 'name') {
      return this.name || null;
    }
    return Object.prototype.hasOwnProperty.call(this.attributes, name) ? this.attributes[name] : null;
  }

  addEventListener(type, listener) {
    this.listeners[type] = this.listeners[type] || [];
    this.listeners[type].push(listener);
  }

  dispatchEvent(event) {
    event.target = this;
    event.currentTarget = this;
    (this.listeners[event.type] || []).forEach((listener) => listener.call(this, event));
    return !event.defaultPrevented;
  }

  click() {
    this.dispatchEvent({ type: 'click', defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } });
  }

  closest(selector) {
    let current = this;
    while (current) {
      if (matchesSelector(current, selector)) {
        return current;
      }
      current = current.parentNode;
    }
    return null;
  }

  querySelector(selector) {
    return this.querySelectorAll(selector)[0] || null;
  }

  querySelectorAll(selector) {
    const selectors = selector.split(',').map((item) => item.trim()).filter(Boolean);
    const results = [];
    walk(this, (node) => {
      if (node !== this && selectors.some((entry) => matchesSelector(node, entry))) {
        results.push(node);
      }
    });
    return results;
  }
}

class Document extends Element {
  constructor() {
    super('#document');
    this.body = new Element('body');
    this.appendChild(this.body);
    this.activeElement = null;
  }

  createElement(tagName) {
    return new Element(tagName);
  }

  getElementById(id) {
    let found = null;
    walk(this, (node) => {
      if (node.id === id && !found) {
        found = node;
      }
    });
    return found;
  }
}

function walk(node, callback) {
  node.children.forEach((child) => {
    callback(child);
    walk(child, callback);
  });
}

function matchesSelector(node, selector) {
  if (selector.startsWith('[data-role="')) {
    return node.getAttribute('data-role') === selector.slice(12, -2);
  }
  if (selector === '[data-operation-fields]') {
    return node.getAttribute('data-operation-fields') !== null;
  }
  if (selector === '[data-compare-mode-field]') {
    return node.getAttribute('data-compare-mode-field') !== null;
  }
  if (selector === '[data-toast-source="true"]') {
    return node.getAttribute('data-toast-source') === 'true';
  }
  if (selector === 'input[name="plugin"]') {
    return node.tagName === 'INPUT' && node.name === 'plugin';
  }
  if (selector === 'input' || selector === 'select' || selector === 'textarea' || selector === 'p' || selector === 'tr' || selector === 'button') {
    return node.tagName === selector.toUpperCase();
  }
  return false;
}

function createNode(document, tag, attrs = {}, text = '') {
  const node = document.createElement(tag);
  Object.entries(attrs).forEach(([key, value]) => {
    if (key === 'value') {
      node.value = value;
      return;
    }
    if (key === 'checked') {
      node.checked = value;
      return;
    }
    node.setAttribute(key, value);
  });
  node.textContent = text;
  return node;
}

function createSelect(document, id, dataRole, options, value) {
  const select = createNode(document, 'select', { id });
  if (dataRole) {
    select.setAttribute('data-role', dataRole);
  }
  select.options = options.map((option, index) => ({
    value: option.value,
    text: option.text,
    selected: option.value === value,
    index,
  }));
  select.selectedIndex = Math.max(0, select.options.findIndex((option) => option.value === value));
  select.value = value;
  return select;
}

function buildAppDom() {
  const document = new Document();
  const form = createNode(document, 'form', { 'data-role': 'workspace-form', 'data-selection-reset': '0' });
  document.body.appendChild(form);

  const pluginInput = createNode(document, 'input', { name: 'plugin', value: 'masseditor' });
  form.appendChild(pluginInput);

  const toastStack = createNode(document, 'div', { 'data-role': 'toast-stack' });
  document.body.appendChild(toastStack);

  const notice = createNode(document, 'div', { 'data-toast-source': 'true', 'data-toast-type': 'success' });
  notice.appendChild(createNode(document, 'p', {}, 'Сохранено'));
  document.body.appendChild(notice);

  const toggle = createNode(document, 'input', { 'data-role': 'soon-operations-toggle', type: 'checkbox' });
  const toggleLabel = createNode(document, 'span', { 'data-role': 'soon-operations-toggle-label' });
  document.body.appendChild(toggle);
  document.body.appendChild(toggleLabel);

  const operationInput = createNode(document, 'input', { 'data-role': 'operation-input', value: 'price' });
  form.appendChild(operationInput);
  const operationTitle = createNode(document, 'div', { 'data-role': 'operation-title' });
  form.appendChild(operationTitle);

  ['price', 'tags', 'url'].forEach((operation) => {
    const button = createNode(document, 'button', { 'data-role': 'operation-trigger', 'data-operation': operation });
    form.appendChild(button);
  });

  const priceFields = createNode(document, 'div', { 'data-operation-fields': 'price,compare_price' });
  const tagsFields = createNode(document, 'div', { 'data-operation-fields': 'tags' });
  const urlFields = createNode(document, 'div', { 'data-operation-fields': 'url' });
  form.appendChild(priceFields);
  form.appendChild(tagsFields);
  form.appendChild(urlFields);

  const numericValue = createNode(document, 'input', { id: 'masseditor-numeric-value', value: '' });
  priceFields.appendChild(numericValue);
  const compareMode = createSelect(document, 'masseditor-compare-price-mode', null, [
    { value: 'keep', text: 'Keep' },
    { value: 'coefficient', text: 'Coefficient' },
  ], 'keep');
  priceFields.appendChild(compareMode);
  const compareValueWrap = createNode(document, 'div', { 'data-compare-mode-field': '1' });
  const compareValue = createNode(document, 'input', { id: 'masseditor-compare-price-value', value: '' });
  compareValueWrap.appendChild(compareValue);
  priceFields.appendChild(compareValueWrap);

  const textValue = createNode(document, 'textarea', { id: 'masseditor-text-value' });
  tagsFields.appendChild(textValue);
  const tagsValue = createNode(document, 'textarea', { id: 'masseditor-tags-value' });
  tagsFields.appendChild(tagsValue);
  const urlMode = createSelect(document, 'masseditor-url-mode', null, [
    { value: 'regenerate', text: 'Regenerate' },
    { value: 'template', text: 'Template' },
  ], 'regenerate');
  urlFields.appendChild(urlMode);
  const urlValue = createNode(document, 'input', { id: 'masseditor-url-value', value: '' });
  urlFields.appendChild(urlValue);

  const modeSelect = createSelect(document, 'masseditor-mode', null, [
    { value: 'set', text: 'Set' },
    { value: 'percent', text: 'Percent' },
  ], 'set');
  form.appendChild(modeSelect);
  const tagsMode = createSelect(document, 'masseditor-tags-mode', null, [
    { value: 'add', text: 'Add' },
    { value: 'remove', text: 'Remove' },
  ], 'add');
  form.appendChild(tagsMode);

  const selectAll = createNode(document, 'input', { 'data-role': 'select-all', type: 'checkbox' });
  document.body.appendChild(selectAll);
  const selectedCount = createNode(document, 'span', { 'data-role': 'selected-count' });
  document.body.appendChild(selectedCount);
  const selectionCounterPill = createNode(document, 'span', { 'data-role': 'selection-counter-pill', 'data-total': '2' });
  document.body.appendChild(selectionCounterPill);
  const readyCopy = createNode(document, 'span', { 'data-role': 'ready-copy' });
  document.body.appendChild(readyCopy);
  const openConfirm = createNode(document, 'button', { 'data-role': 'open-confirm' });
  const openConfirmMobile = createNode(document, 'button', { 'data-role': 'open-confirm-mobile' });
  document.body.appendChild(openConfirm);
  document.body.appendChild(openConfirmMobile);
  const mobileApplyCount = createNode(document, 'span', { 'data-role': 'mobile-apply-count' });
  const mobileApplyOperation = createNode(document, 'span', { 'data-role': 'mobile-apply-operation' });
  document.body.appendChild(mobileApplyCount);
  document.body.appendChild(mobileApplyOperation);

  const confirmApply = createNode(document, 'input', { 'data-role': 'confirm-apply', value: '0' });
  form.appendChild(confirmApply);
  const modal = createNode(document, 'div', { 'data-role': 'confirm-modal' });
  modal.hidden = true;
  const closeModal = createNode(document, 'button', { 'data-role': 'close-modal' });
  modal.appendChild(closeModal);
  const modalCount = createNode(document, 'span', { 'data-role': 'modal-count' });
  const modalOperation = createNode(document, 'span', { 'data-role': 'modal-operation' });
  const modalMode = createNode(document, 'span', { 'data-role': 'modal-mode' });
  const modalValue = createNode(document, 'span', { 'data-role': 'modal-value' });
  modal.appendChild(modalCount);
  modal.appendChild(modalOperation);
  modal.appendChild(modalMode);
  modal.appendChild(modalValue);
  document.body.appendChild(modal);

  const table = createNode(document, 'table');
  document.body.appendChild(table);
  [1, 2].forEach((id) => {
    const row = createNode(document, 'tr');
    const checkbox = createNode(document, 'input', { 'data-role': 'product-checkbox', type: 'checkbox', value: String(id) });
    row.appendChild(checkbox);
    table.appendChild(row);
  });

  return { document, form, modal, confirmApply, toastStack };
}

function createLocalStorage(seed = {}) {
  const store = { ...seed };
  return {
    getItem(key) {
      return Object.prototype.hasOwnProperty.call(store, key) ? store[key] : null;
    },
    setItem(key, value) {
      store[key] = String(value);
    },
    removeItem(key) {
      delete store[key];
    },
    snapshot() {
      return { ...store };
    },
  };
}

module.exports = {
  buildAppDom,
  createLocalStorage,
};
