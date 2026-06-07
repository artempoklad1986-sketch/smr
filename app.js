// ============================================================
// app.js — PrintCRM v3.0
// Чистая архитектура. Без дублей. Правильные финансы.
// PHP 8.2 + SQLite backend
// ============================================================

'use strict';

// ════════════════════════════════════════════════════════════
//  API CLIENT — единственный способ общения с сервером
// ════════════════════════════════════════════════════════════
const Api = {

  _pending: new Map(),

  async call(endpoint, method = 'GET', body = null, params = {}) {
    const qs = new URLSearchParams({ key: API_KEY, ...params }).toString();
    const url = `${API_URL}${endpoint}?${qs}`;

    const opts = {
      method,
      headers: apiHeaders,
    };
    if (body) opts.body = JSON.stringify(body);

    try {
      const res  = await fetch(url, opts);
      const text = await res.text();
      if (!text || text.trim() === '') return { ok: false, error: 'empty' };
      return JSON.parse(text);
    } catch (e) {
      console.warn(`Api.call(${endpoint}) error:`, e.message);
      return { ok: false, error: e.message };
    }
  },

  get:    (ep, params) => Api.call(ep, 'GET', null, params),
  post:   (ep, body, params) => Api.call(ep, 'POST', body, params),
  put:    (ep, body, params) => Api.call(ep, 'PUT', body, params),
  delete: (ep, params) => Api.call(ep, 'DELETE', null, params),

  // ── Специализированные методы ──────────────────────────

  // Заказы
  orders:  {
    list:   (p) => Api.get('orders', p),
    create: (b) => Api.post('orders', b),
    update: (id, b) => Api.put('orders', b, { id }),
    remove: (id) => Api.delete('orders', { id }),
  },

  // Финансы
  finance: {
    list:   (p) => Api.get('finance', p),
    create: (b) => Api.post('finance', b),
    remove: (id) => Api.delete('finance', { id }),
    summary:(p) => Api.get('finance/summary', p),
  },

  // Клиенты
  clients: {
    list:   (p) => Api.get('clients', p),
    create: (b) => Api.post('clients', b),
    update: (id, b) => Api.put('clients', b, { id }),
    remove: (id) => Api.delete('clients', { id }),
  },

  // Склад
  warehouse: {
    list:    (p) => Api.get('warehouse', p),
    create:  (b) => Api.post('warehouse', b),
    restock: (b) => Api.post('warehouse/restock', b),
    deduct:  (b) => Api.post('warehouse/deduct', b),
    remove:  (id) => Api.delete('warehouse', { id }),
    history: ()  => Api.get('warehouse/history'),
  },

  // Заметки
  notes: {
    list:   () => Api.get('notes'),
    create: (b) => Api.post('notes', b),
    remove: (id) => Api.delete('notes', { id }),
  },

  // Календарь
  calendar: {
    list:   (p) => Api.get('calendar', p),
    create: (b) => Api.post('calendar', b),
    remove: (id) => Api.delete('calendar', { id }),
  },

  // Настройки
  settings: {
    get:  ()  => Api.get('settings'),
    save: (b) => Api.post('settings', b),
  },

  // Статистика
  stats:   (p) => Api.get('stats', p),

  // Здоровье системы
  ping:    ()  => Api.get('ping'),

  // Модули
  module:  (id, action, body, p) =>
    Api.call('module', body ? 'POST' : 'GET', body, { module: id, action, ...p }),

  // Загрузка файлов
  upload:  (formData) => {
    const qs = new URLSearchParams({ key: API_KEY }).toString();
    return fetch(`${API_URL}upload?${qs}`, {
      method: 'POST',
      body: formData,
    }).then(r => r.json());
  },

  // ── Эндпоинты для будущих интеграций (POS, вебхуки) ──
  integrations: {
    list:    ()  => Api.get('integrations'),
    test:    (id) => Api.post('integrations/test', { id }),
    webhook: (channel, body) => Api.post(`webhooks/${channel}`, body),
  },

  // Уведомления
  notify: (event, data) => Api.post('notify', { event, data }),

  // Документы/парсеры
  docs: {
    parse:  (formData) => {
      const qs = new URLSearchParams({ key: API_KEY }).toString();
      return fetch(`${API_URL}docs/parse?${qs}`, {
        method: 'POST', body: formData,
      }).then(r => r.json());
    },
  },
};

// ════════════════════════════════════════════════════════════
//  STATE — глобальное состояние приложения
// ════════════════════════════════════════════════════════════
const State = {
  orders:    [],
  finance:   [],
  clients:   [],
  notes:     [],
  warehouse: [],
  calendar:  [],
  settings:  {},
  loaded:    false,
  syncing:   false,

  // Обновить раздел
  set(key, data) {
    this[key] = data;
  },

  // Получить заказ по ID
  order(id) {
    return this.orders.find(o => String(o.id) === String(id));
  },

  // Получить клиента по имени
  client(name) {
    return this.clients.find(c => c.name === name);
  },
};

// ════════════════════════════════════════════════════════════
//  SYNC — загрузка/синхронизация данных
// ════════════════════════════════════════════════════════════
const Sync = {

  _timer: null,
  _pollInterval: null,

  async loadAll() {
    Ui.syncStatus('loading');
    try {
      // Параллельная загрузка основных данных
      const [ordRes, finRes, cliRes, setRes] = await Promise.all([
        Api.orders.list(),
        Api.finance.list(),
        Api.clients.list(),
        Api.settings.get(),
      ]);

      if (ordRes.ok) State.set('orders',   ordRes.data  || []);
      if (finRes.ok) State.set('finance',  finRes.data  || []);
      if (cliRes.ok) State.set('clients',  cliRes.data  || []);
      if (setRes.ok) State.set('settings', setRes.data  || {});

      State.loaded = true;
      Ui.syncStatus('ok');

      // Обновить отображение
      App.renderCurrent();
      App.updateBadges();
      Ui.updateDbInfo();

      console.log('✅ Данные загружены | orders:', State.orders.length,
                  '| finance:', State.finance.length,
                  '| clients:', State.clients.length);
    } catch (e) {
      Ui.syncStatus('error');
      notify('Ошибка загрузки данных: ' + e.message, 'error');
    }
  },

  // Фоновый поллинг каждые 30 сек
  startPolling() {
    this._pollInterval = setInterval(async () => {
      if (State.syncing) return;
      try {
        const [ordRes, finRes] = await Promise.all([
          Api.orders.list(),
          Api.finance.list(),
        ]);
        if (ordRes.ok) {
          const incoming = JSON.stringify(ordRes.data);
          const current  = JSON.stringify(State.orders);
          if (incoming !== current) {
            State.set('orders', ordRes.data || []);
            App.renderCurrent();
            App.updateBadges();
          }
        }
        if (finRes.ok) State.set('finance', finRes.data || []);
        Ui.syncStatus('ok');
      } catch { Ui.syncStatus('error'); }
    }, 30000);
  },

  stopPolling() {
    if (this._pollInterval) clearInterval(this._pollInterval);
  },
};

// ════════════════════════════════════════════════════════════
//  APP — навигация и основной контроллер
// ════════════════════════════════════════════════════════════
const App = {

  currentPage: 'dashboard',

  showPage(name, btn) {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));

    const page = document.getElementById('page-' + name);
    if (page) page.classList.add('active');
    if (btn)  btn.classList.add('active');

    this.currentPage = name;

    const renders = {
      dashboard:  () => Dashboard.refresh(),
      orders:     () => Kanban.render(),
      finance:    () => Finance.renderTable(),
      stats:      () => Stats.render(),
      accounting: () => Accounting.render(),
      clients:    () => Clients.render(),
      notes:      () => Notes.render(),
      warehouse:  () => Warehouse.render(),
      calendar:   () => Calendar.render(),
      settings:   () => Settings.load(),
    };

    if (renders[name]) {
      renders[name]();
      return;
    }

    // Внешний модуль
    const mod = CRM._modules[name];
    if (mod && typeof mod.render === 'function') {
      Promise.resolve().then(() => mod.render())
        .catch(e => notify('Ошибка модуля: ' + e.message, 'error'));
    }
  },

  renderCurrent() {
    const renders = {
      dashboard:  () => Dashboard.refresh(),
      orders:     () => Kanban.render(),
      finance:    () => Finance.renderTable(),
      stats:      () => Stats.render(),
      accounting: () => Accounting.render(),
      clients:    () => Clients.render(),
      notes:      () => Notes.render(),
      warehouse:  () => Warehouse.render(),
      calendar:   () => Calendar.render(),
    };
    if (renders[this.currentPage]) renders[this.currentPage]();
  },

  updateBadges() {
    // Заказы
    const active = State.orders.filter(o =>
      o.status === 'new' || o.status === 'work'
    ).length;
    const ob = document.getElementById('ordersNavBadge');
    if (ob) { ob.textContent = active; ob.style.display = active > 0 ? '' : 'none'; }

    // Заметки
    const urgent = State.notes.filter(n =>
      n.priority === 'urgent' || n.priority === 'important'
    ).length;
    const nb = document.getElementById('notesNavBadge');
    if (nb) nb.style.display = urgent > 0 ? '' : 'none';

    // Склад
    const low = State.warehouse.filter(w =>
      parseFloat(w.qty) <= parseFloat(w.min_qty)
    ).length;
    const wb = document.getElementById('warehouseLowBadge');
    if (wb) wb.style.display = low > 0 ? '' : 'none';
  },
};

// Глобальные обёртки для onclick в HTML
window.showPage = (n, b) => App.showPage(n, b);

// ════════════════════════════════════════════════════════════
//  UI — вспомогательные функции интерфейса
// ════════════════════════════════════════════════════════════
const Ui = {

  syncStatus(status) {
    const dot  = document.getElementById('syncDot');
    const text = document.getElementById('syncText');
    const map = {
      loading: ['var(--accent4)', '⟳ Загрузка...'],
      saving:  ['var(--accent4)', '⟳ Сохранение...'],
      ok:      ['var(--accent3)', '● Онлайн'],
      error:   ['var(--danger)',  '⚠ Ошибка'],
    };
    const [color, label] = map[status] || ['var(--text-muted)', ''];
    if (dot)  dot.style.background = color;
    if (text) { text.textContent = label; text.style.color = color; }
  },

  updateDbInfo() {
    Api.get('db/info').then(res => {
      if (!res.ok) return;
      const d = res.data || {};
      const el = id => document.getElementById(id);
      if (el('dbVersion'))    el('dbVersion').textContent    = d.version    || '—';
      if (el('dbSize'))       el('dbSize').textContent       = d.size_mb ? d.size_mb + ' МБ' : '—';
      if (el('dbLastUpdate')) el('dbLastUpdate').textContent = d.updated_at || '—';
      if (el('dbSizeInfo'))   el('dbSizeInfo').textContent   = d.size_kb ? `БД: ${d.size_kb} КБ` : '';
    }).catch(() => {});
  },
};

// ════════════════════════════════════════════════════════════
//  МОДАЛКИ
// ════════════════════════════════════════════════════════════
function openModal(id) {
  const m = document.getElementById(id);
  if (!m) return;
  m.classList.add('open');
  if (id === 'orderModal')  Order.initModal();
  if (id === 'incomeModal')  { const el = document.getElementById('inc_date'); if (el) el.value = nowDTLocal(); }
  if (id === 'expenseModal') { const el = document.getElementById('exp_date'); if (el) el.value = nowDTLocal(); }
}

function closeModal(id) {
  const m = document.getElementById(id);
  if (m) m.classList.remove('open');
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => {
      if (e.target === o) o.classList.remove('open');
    });
  });
});

// ════════════════════════════════════════════════════════════
//  ORDER MODULE
// ════════════════════════════════════════════════════════════
const Order = {

  currentTab:   'photo',
  editingId:    null,
  currentFiles: [],

  // ── Инициализация модала ─────────────────────────────────
  initModal() {
    if (this.editingId) return; // редактирование — не сбрасываем

    const num = 'ORD-' + String(
      (State.orders.length + 1)
    ).padStart(5, '0');

    const fields = [
      'ord_total','ord_prepay','ord_comment',
      'ord_client','ord_phone','ord_manager',
    ];
    fields.forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });

    const set = (id, v) => { const e = document.getElementById(id); if (e) e.value = v; };
    set('ord_num',  num);
    set('ord_date', nowDTLocal());
    set('ord_deadline', '');

    const disp = document.getElementById('ordTotalDisplay');
    if (disp) disp.textContent = '0 ₽';

    this.currentFiles = [];
    this.currentTab   = 'photo';

    // Сброс чекбоксов
    document.querySelectorAll('.checkbox-item.checked').forEach(el => {
      el.classList.remove('checked');
      const dot   = el.querySelector('.checkbox-dot');
      const input = el.querySelector('input');
      if (dot)   dot.textContent = '';
      if (input) input.checked   = false;
    });

    // Сброс размеров
    document.querySelectorAll('.size-btn.selected').forEach(b => b.classList.remove('selected'));

    switchServiceTab('photo', document.querySelector('.order-service-tab'));

    // Подсказки клиентов
    const dl = document.getElementById('clientsList');
    if (dl) dl.innerHTML = State.clients
      .map(c => `<option value="${escHtml(c.name)}" data-phone="${escHtml(c.phone || '')}">`)
      .join('');

    // Заголовок модала
    const title = document.getElementById('orderModalTitle');
    if (title) title.textContent = 'Создание заказа';

    document.getElementById('ord_edit_id').value = '';
  },

  // ── Сохранить заказ ──────────────────────────────────────
  async save() {
    const editId  = document.getElementById('ord_edit_id')?.value || '';
    const num     = document.getElementById('ord_num')?.value     || '';
    const client  = document.getElementById('ord_client')?.value.trim() || 'Без имени';
    const phone   = document.getElementById('ord_phone')?.value   || '';
    const manager = document.getElementById('ord_manager')?.value || '';
    const date    = document.getElementById('ord_date')?.value    || nowDTLocal();
    const deadline= document.getElementById('ord_deadline')?.value|| '';
    const status  = document.getElementById('ord_status')?.value  || 'new';
    const payment = document.getElementById('ord_payment')?.value || 'Наличные';
    const comment = document.getElementById('ord_comment')?.value || '';
    const bizcat  = document.getElementById('ord_bizcat')?.value  || '';
    const total   = parseFloat(document.getElementById('ord_total')?.value)  || 0;
    const prepay  = parseFloat(document.getElementById('ord_prepay')?.value) || 0;

    if (total <= 0) { notify('Укажите сумму заказа', 'error'); return; }

    // Параметры по типу услуги
    const extra   = this._collectExtra();
    const options = this._collectChecked();
    const size    = document.querySelector('.size-btn.selected')?.textContent.trim() || '';

    const orderData = {
      num, client, phone, manager, date, deadline,
      service:      this.currentTab,
      service_label: getServiceLabel(this.currentTab),
      size, status, payment, comment, bizcat,
      total, prepay,
      options:  JSON.stringify(options),
      extra:    JSON.stringify(extra),
      files:    '[]',
    };

    // Загрузка файлов если есть
    if (this.currentFiles.length) {
      notify('⏳ Загружаем файлы...', 'info');
      const uploaded = await this._uploadFiles(editId || Date.now());
      orderData.files = JSON.stringify(uploaded);
    }

    Ui.syncStatus('saving');

    let res;
    if (editId) {
      res = await Api.orders.update(editId, orderData);
    } else {
      res = await Api.orders.create(orderData);
    }

    if (!res || !res.ok) {
      notify('Ошибка сохранения заказа: ' + (res?.error || ''), 'error');
      Ui.syncStatus('error');
      return;
    }

    const savedOrder = res.data || { ...orderData, id: res.id };

    // ── ФИНАНСОВАЯ ЛОГИКА ────────────────────────────────
    if (!editId) {
      await this._handleFinance(savedOrder, total, prepay, payment, client, num, date);
    }

    // Обновить State
    if (editId) {
      const idx = State.orders.findIndex(o => String(o.id) === String(editId));
      if (idx !== -1) State.orders[idx] = savedOrder;
      notify(`Заказ ${num} обновлён`, 'success');
    } else {
      State.orders.unshift(savedOrder);
      notify(`Заказ ${num} создан`, 'success');
    }

    this.editingId    = null;
    this.currentFiles = [];

    closeModal('orderModal');
    Kanban.render();
    Dashboard.refresh();
    App.updateBadges();
    Ui.syncStatus('ok');

    // Уведомления
    if (!editId) Api.notify('order_new', savedOrder).catch(() => {});
  },

  // ── Финансовая логика при создании заказа ────────────────
  async _handleFinance(order, total, prepay, payment, client, num, date) {
    const label = order.service_label || 'Заказ';
    const desc  = `Заказ ${num} — ${client}`;

    if (prepay > 0 && prepay < total) {
      // Частичная оплата: записываем предоплату
      await Api.finance.create({
        type:     'income',
        date,
        category: label,
        description: desc + ' (предоплата)',
        amount:   prepay,
        method:   payment,
        client,
        order_id: order.id,
      });
      notify(`💰 Предоплата ${fmt(prepay)} записана в финансы`, 'info');

    } else if (prepay >= total || payment !== 'Предоплата' && prepay === 0 && total > 0) {
      // Полная оплата при создании
      if (payment !== 'В кредит' && payment !== 'Безнал (счёт)') {
        await Api.finance.create({
          type:     'income',
          date,
          category: label,
          description: desc,
          amount:   total,
          method:   payment,
          client,
          order_id: order.id,
        });
        notify(`💰 Оплата ${fmt(total)} записана в финансы`, 'info');
      }
    }
    // В кредит / Безнал (счёт) — финансы не записываем автоматически
  },

  // ── Редактировать заказ ──────────────────────────────────
  edit(id) {
    const order = State.order(id);
    if (!order) { notify('Заказ не найден', 'error'); return; }

    this.editingId = id;
    openModal('orderModal');

    setTimeout(() => {
      const set = (elId, val) => {
        const e = document.getElementById(elId);
        if (e) e.value = val ?? '';
      };

      set('ord_edit_id',  order.id);
      set('ord_num',      order.num);
      set('ord_date',     order.date);
      set('ord_deadline', order.deadline || '');
      set('ord_client',   order.client);
      set('ord_phone',    order.phone || '');
      set('ord_manager',  order.manager || '');
      set('ord_status',   order.status);
      set('ord_payment',  order.payment || 'Наличные');
      set('ord_comment',  order.comment || '');
      set('ord_total',    order.total);
      set('ord_prepay',   order.prepay || 0);
      set('ord_bizcat',   order.bizcat || '');

      updateTotalDisplay();

      const btn = document.querySelector(
        `.order-service-tab[onclick*="'${order.service}'"]`
      );
      if (btn) switchServiceTab(order.service, btn);

      const title = document.getElementById('orderModalTitle');
      if (title) title.textContent = `Редактирование ${order.num}`;

      this.currentFiles = [];
    }, 100);
  },

  // ── Удалить заказ ────────────────────────────────────────
  async delete(id) {
    if (!confirm('Удалить заказ?')) return;
    const res = await Api.orders.remove(id);
    if (!res.ok) { notify('Ошибка удаления', 'error'); return; }
    State.orders = State.orders.filter(o => String(o.id) !== String(id));
    Kanban.render();
    Dashboard.refresh();
    App.updateBadges();
    closeOrderDetail();
    notify('Заказ удалён', 'info');
  },

  // ── Изменить статус ──────────────────────────────────────
  async setStatus(id, newStatus) {
    const order = State.order(id);
    if (!order) return;

    const res = await Api.orders.update(id, { status: newStatus });
    if (!res.ok) { notify('Ошибка смены статуса', 'error'); return; }

    order.status = newStatus;
    Kanban.render();
    App.updateBadges();

    // При выдаче заказа — записать остаток оплаты
    if (newStatus === 'done') {
      await this._handleDonePayment(order);
    }

    notify(`Заказ ${order.num} → ${KB_STATUS_LABELS[newStatus]}`, 'success');
    Api.notify('order_status', order).catch(() => {});
  },

  // ── При выдаче заказа — записать остаток ─────────────────
  async _handleDonePayment(order) {
    const total  = parseFloat(order.total)  || 0;
    const prepay = parseFloat(order.prepay) || 0;
    const remain = total - prepay;

    if (remain > 0 && order.payment !== 'В кредит') {
      // Спрашиваем подтверждение
      if (confirm(`Записать получение остатка ${fmt(remain)} в финансы?`)) {
        await Api.finance.create({
          type:        'income',
          date:        nowDTLocal(),
          category:    order.service_label || 'Заказ',
          description: `Остаток по заказу ${order.num} — ${order.client}`,
          amount:      remain,
          method:      order.payment,
          client:      order.client,
          order_id:    order.id,
        });
        // Обновить finance в State
        const finRes = await Api.finance.list();
        if (finRes.ok) State.set('finance', finRes.data || []);
        notify(`💰 Остаток ${fmt(remain)} записан`, 'success');
      }
    }
  },

  // ── Собрать параметры услуги ─────────────────────────────
  _collectExtra() {
    const tab = this.currentTab;
    const v   = id => document.getElementById(id)?.value || '';
    const map = {
      photo:    { photo_size: document.querySelector('#sizeMatrix-photo .size-btn.selected')?.textContent.trim() || '', photo_qty: v('photo_qty'), photo_material: v('photo_material'), photo_price: v('photo_price') },
      copy:     { copy_size: document.querySelector('#stab-copy .size-btn.selected')?.textContent.trim() || '', copy_qty: v('copy_qty'), copy_sides: v('copy_sides'), copy_price: v('copy_price') },
      banner:   { ban_w: v('ban_w'), ban_h: v('ban_h'), ban_area: v('ban_area'), ban_price: v('ban_price'), ban_qty: v('ban_qty') },
      wide:     { wide_w: v('wide_w'), wide_h: v('wide_h'), wide_area: v('wide_area'), wide_price: v('wide_price') },
      business: { biz_qty: v('biz_qty'), biz_size: v('biz_size'), biz_price: v('biz_price') },
      design:   { des_revisions: v('des_revisions'), des_price: v('des_price'), des_format: v('des_format') },
      promo:    { promo_qty: v('promo_qty'), promo_price: v('promo_price') },
      other:    { other_desc: v('other_desc'), other_qty: v('other_qty'), other_price: v('other_price') },
    };
    return map[tab] || {};
  },

  _collectChecked() {
    const items = [];
    document.querySelector('.service-tab-content.active')
      ?.querySelectorAll('.checkbox-item.checked')
      .forEach(c => items.push(c.textContent.trim().replace('✓', '').trim()));
    return items;
  },

  // ── Загрузка файлов ──────────────────────────────────────
  async _uploadFiles(orderId) {
    const uploaded = [];
    for (const file of this.currentFiles) {
      const fd = new FormData();
      fd.append('file', file);
      fd.append('order_id', orderId);
      try {
        const res = await Api.upload(fd);
        if (res.ok) uploaded.push({ name: file.name, size: file.size, type: file.type, url: res.url, filename: res.filename });
        else uploaded.push({ name: file.name, size: file.size, type: file.type, url: '' });
      } catch (e) {
        uploaded.push({ name: file.name, size: file.size, type: file.type, url: '' });
      }
    }
    return uploaded;
  },
};

// Глобальные обёртки
window.saveOrder  = () => Order.save();
window.editOrder  = (id) => Order.edit(id);
window.deleteOrder = (id) => Order.delete(id);
window.editOrderKb = (e, id) => { if (e) e.stopPropagation(); Order.edit(id); };
window.deleteOrderKb = (id) => Order.delete(id);

// ════════════════════════════════════════════════════════════
//  KANBAN
// ════════════════════════════════════════════════════════════
const KB_STATUSES = ['new','work','ready','done','cancel'];
const KB_SERVICE_LABELS = {
  photo:'📸 Фото', copy:'🖨️ Копи', banner:'🏳️ Баннер',
  design:'🎨 Дизайн', business:'💼 Бизнес',
  wide:'🖼️ Широкий', promo:'🎁 Сувенирка', other:'⚙️ Прочее',
};
const KB_STATUS_LABELS = {
  new:'🆕 Новый', work:'⚙️ В работе',
  ready:'✅ Готов', done:'📦 Выдан', cancel:'❌ Отменён',
};
let draggedOrderId = null;

const Kanban = {

  render() {
    const search = (document.getElementById('orderSearch')?.value || '').toLowerCase();
    const svcF   = document.getElementById('orderServiceFilter')?.value || '';

    let orders = [...State.orders].filter(o => {
      const ms = !search ||
        (o.num    || '').toLowerCase().includes(search) ||
        (o.client || '').toLowerCase().includes(search) ||
        (o.comment|| '').toLowerCase().includes(search);
      const mv = !svcF || o.service === svcF;
      return ms && mv;
    }).sort((a, b) => new Date(b.date || 0) - new Date(a.date || 0));

    // Очистить колонки
    KB_STATUSES.forEach(st => {
      const col = document.getElementById('kbCol_' + st);
      if (col) col.innerHTML = '';
    });

    const counts = Object.fromEntries(KB_STATUSES.map(s => [s, 0]));
    let totalSum = 0;

    orders.forEach(order => {
      const st  = order.status || 'new';
      const col = document.getElementById('kbCol_' + st);
      if (!col) return;
      counts[st]++;
      totalSum += Number(order.total) || 0;
      col.appendChild(this._buildCard(order));
    });

    // Пустые состояния
    KB_STATUSES.forEach(st => {
      const col = document.getElementById('kbCol_' + st);
      if (col && col.children.length === 0) {
        col.innerHTML = '<div class="kanban-empty">Нет заказов</div>';
      }
    });

    // Бейджи
    KB_STATUSES.forEach(st => {
      const badge = document.getElementById('kbBadge_' + st);
      const pill  = document.getElementById('kbCount_' + st);
      if (badge) badge.textContent = counts[st] || 0;
      if (pill)  pill.textContent  = counts[st] || 0;
    });

    const totalEl = document.getElementById('kbTotalSum');
    if (totalEl) totalEl.textContent = fmt(totalSum);
  },

  _buildCard(order) {
    const card = document.createElement('div');
    card.className = 'kb-card';
    card.draggable  = true;
    card.dataset.id = order.id;
    card.dataset.status = order.status || 'new';

    const svc    = order.service || 'other';
    const svcLbl = KB_SERVICE_LABELS[svc] || svc;
    const st     = order.status || 'new';

    // Дедлайн
    let deadlineBadge = '';
    if (order.deadline) {
      const diff = new Date(order.deadline) - new Date();
      const h    = diff / 3600000;
      if      (h < 0)  deadlineBadge = `<span class="kb-deadline-badge kb-deadline-over">⚠ Просрочен</span>`;
      else if (h < 24) deadlineBadge = `<span class="kb-deadline-badge kb-deadline-warning">⏰ ${Math.ceil(h)}ч</span>`;
      else             deadlineBadge = `<span class="kb-deadline-badge kb-deadline-ok">📅 ${Math.ceil(h/24)}д</span>`;
    }

    // Финансовый индикатор предоплаты
    const total   = parseFloat(order.total)  || 0;
    const prepay  = parseFloat(order.prepay) || 0;
    const paid    = prepay > 0 ? prepay : (order.payment !== 'В кредит' && order.payment !== 'Безнал (счёт)' ? total : 0);
    const paidPct = total > 0 ? Math.min(100, Math.round((paid / total) * 100)) : 0;
    const paidColor = paidPct === 100 ? 'var(--accent3)' : paidPct > 0 ? 'var(--accent4)' : 'var(--danger)';

    const extra = this._parseJSON(order.extra);
    const desc  = this._buildDesc(order, extra);

    card.innerHTML = `
      <div class="kb-card-actions">
        <button class="kb-action-btn" onclick="openOrderDetail(event,'${order.id}')" title="Просмотр">👁</button>
        <button class="kb-action-btn" onclick="toggleKbStatusMenu(event,'${order.id}')" title="Статус">⇄</button>
        <button class="kb-action-btn" onclick="editOrderKb(event,'${order.id}')" title="Редактировать">✏</button>
      </div>
      <div class="kb-status-menu" id="kbMenu_${order.id}">
        ${KB_STATUSES.map(s => `
          <div class="kb-status-opt" onclick="changeKbOrderStatus('${order.id}','${s}',event)">
            ${KB_STATUS_LABELS[s]}
          </div>`).join('')}
      </div>
      <div class="kb-card-head">
        <span class="kb-card-num">${escHtml(order.num || '#—')}</span>
        <span class="kb-card-service kb-svc-${svc}">${svcLbl}</span>
      </div>
      <div class="kb-card-client">${escHtml(order.client || '👤 Анонимный')}</div>
      <div class="kb-card-desc">${escHtml(desc)}</div>
      <div style="margin-bottom:8px;">
        <div style="display:flex;justify-content:space-between;font-size:0.65rem;color:var(--text-muted);margin-bottom:3px;">
          <span>${prepay > 0 ? `Предоплата ${fmt(prepay)}` : order.payment}</span>
          <span style="color:${paidColor};">${paidPct}%</span>
        </div>
        <div style="height:3px;background:var(--border);border-radius:2px;overflow:hidden;">
          <div style="height:100%;width:${paidPct}%;background:${paidColor};border-radius:2px;transition:width 0.4s;"></div>
        </div>
      </div>
      <div class="kb-card-foot">
        <span class="kb-card-price">${fmt(order.total)}</span>
        <div class="kb-card-meta">
          <span class="kb-card-date">🕐 ${fmtDateShort(order.date)}</span>
          ${deadlineBadge}
        </div>
      </div>`;

    // Drag
    card.addEventListener('dragstart', e => {
      draggedOrderId = order.id;
      setTimeout(() => card.classList.add('dragging'), 0);
      e.dataTransfer.setData('text/plain', String(order.id));
    });
    card.addEventListener('dragend', () => {
      card.classList.remove('dragging');
      draggedOrderId = null;
    });

    // Click → detail
    card.addEventListener('click', e => {
      if (e.target.closest('.kb-card-actions') || e.target.closest('.kb-status-menu')) return;
      openOrderDetail(e, order.id);
    });

    return card;
  },

  _buildDesc(order, extra) {
    const parts = [];
    const svc   = order.service;
    if (svc === 'photo'   && extra.photo_size)   parts.push(extra.photo_size);
    if (svc === 'copy'    && extra.copy_size)     parts.push(extra.copy_size);
    if (svc === 'banner'  && extra.ban_w)         parts.push(`${extra.ban_w}×${extra.ban_h}м`);
    if (svc === 'wide'    && extra.wide_w)        parts.push(`${extra.wide_w}×${extra.wide_h}см`);
    if (svc === 'other'   && extra.other_desc)    parts.push(extra.other_desc);
    if (order.bizcat)     parts.push(order.bizcat);
    if (order.comment)    parts.push(order.comment.substring(0, 50));
    return parts.join(' · ') || '—';
  },

  _parseJSON(str) {
    if (!str) return {};
    try { return JSON.parse(str); } catch { return {}; }
  },
};

// ── Drag & Drop глобальные функции (вызываются из HTML) ──
window.highlightDrop   = el => el.classList.add('drag-over');
window.unhighlightDrop = el => el.classList.remove('drag-over');
window.dropCard = async (event, newStatus) => {
  event.preventDefault();
  window.unhighlightDrop(event.currentTarget);
  const id = event.dataTransfer.getData('text/plain') || draggedOrderId;
  if (!id) return;
  await Order.setStatus(id, newStatus);
};

window.toggleKbStatusMenu = (e, id) => {
  e.stopPropagation();
  document.querySelectorAll('.kb-status-menu.open').forEach(m => {
    if (m.id !== 'kbMenu_' + id) m.classList.remove('open');
  });
  const menu = document.getElementById('kbMenu_' + id);
  if (menu) menu.classList.toggle('open');
};

window.changeKbOrderStatus = async (id, status, e) => {
  if (e) e.stopPropagation();
  document.querySelectorAll('.kb-status-menu.open').forEach(m => m.classList.remove('open'));
  await Order.setStatus(id, status);
  const overlay = document.getElementById('orderDetailOverlay');
  if (overlay?.classList.contains('open')) openOrderDetail(null, id);
};

document.addEventListener('click', () => {
  document.querySelectorAll('.kb-status-menu.open').forEach(m => m.classList.remove('open'));
});

window.renderKanban = () => Kanban.render();

// ════════════════════════════════════════════════════════════
//  ORDER DETAIL OVERLAY
// ════════════════════════════════════════════════════════════
window.openOrderDetail = function(e, id) {
  if (e) e.stopPropagation();
  const order = State.order(id);
  if (!order) return;

  let overlay = document.getElementById('orderDetailOverlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.className = 'order-detail-overlay';
    overlay.id = 'orderDetailOverlay';
    overlay.innerHTML = '<div class="order-detail-modal" id="orderDetailModal"></div>';
    overlay.addEventListener('click', ev => { if (ev.target === overlay) closeOrderDetail(); });
    document.body.appendChild(overlay);
  }

  const modal = document.getElementById('orderDetailModal');
  const svc   = order.service || 'other';
  const st    = order.status  || 'new';
  const extra = (() => { try { return JSON.parse(order.extra || '{}'); } catch { return {}; } })();
  const opts  = (() => { try { return JSON.parse(order.options || '[]'); } catch { return []; } })();

  const svcIcon = { photo:'📸', copy:'🖨️', banner:'🏳️', design:'🎨', business:'💼', wide:'🖼️', promo:'🎁', other:'⚙️' }[svc] || '📋';
  const iconBg  = { new:'linear-gradient(135deg,#6366f1,#a78bfa)', work:'linear-gradient(135deg,#f59e0b,#fbbf24)', ready:'linear-gradient(135deg,#10b981,#34d399)', done:'linear-gradient(135deg,#06b6d4,#22d3ee)', cancel:'linear-gradient(135deg,#ef4444,#f87171)' }[st] || '';

  const total   = parseFloat(order.total)  || 0;
  const prepay  = parseFloat(order.prepay) || 0;
  const remain  = total - prepay;

  const chips = [
    ...Object.entries(extra).filter(([,v]) => v).map(([k,v]) => `${k.replace(/_/g,' ')}: ${escHtml(String(v))}`),
    ...opts,
  ].map(p => `<span class="od-chip">${escHtml(p)}</span>`).join('');

  const stepsHtml = KB_STATUSES.map(s => `
    <div class="od-status-step ${st === s ? 'active-' + s : ''}"
         onclick="changeKbOrderStatus('${order.id}','${s}',event)">
      ${KB_STATUS_LABELS[s]}
    </div>`).join('');

  modal.innerHTML = `
    <div class="od-header">
      <div class="od-icon-wrap" style="background:${iconBg};">${svcIcon}</div>
      <div class="od-titles">
        <div class="od-order-num">Заказ ${escHtml(order.num || '#—')}</div>
        <div class="od-client-name">${escHtml(order.client || 'Анонимный клиент')}</div>
        <div class="od-sub">${KB_SERVICE_LABELS[svc] || svc} • ${formatDate(order.date)}</div>
      </div>
      <button class="od-close" onclick="closeOrderDetail()">✕</button>
    </div>
    <div class="od-body">
      <div class="od-section-title">Статус заказа</div>
      <div class="od-status-bar">${stepsHtml}</div>

      <div class="od-grid-3">
        <div class="od-info-block">
          <div class="od-info-label">Сумма заказа</div>
          <div class="od-info-val price">${fmt(total)}</div>
        </div>
        <div class="od-info-block">
          <div class="od-info-label">Предоплата</div>
          <div class="od-info-val" style="color:${prepay > 0 ? 'var(--accent4)' : 'var(--text-muted)'};">${prepay > 0 ? fmt(prepay) : '—'}</div>
        </div>
        <div class="od-info-block">
          <div class="od-info-label">Остаток к оплате</div>
          <div class="od-info-val" style="color:${remain > 0 ? 'var(--danger)' : 'var(--accent3)'};">${remain > 0 ? fmt(remain) : '✅ Оплачено'}</div>
        </div>
      </div>

      <div class="od-grid">
        <div class="od-info-block">
          <div class="od-info-label">📞 Телефон</div>
          <div class="od-info-val phone">${escHtml(order.phone || '—')}</div>
        </div>
        <div class="od-info-block">
          <div class="od-info-label">💳 Способ оплаты</div>
          <div class="od-info-val">${escHtml(order.payment || '—')}</div>
        </div>
        <div class="od-info-block">
          <div class="od-info-label">👔 Менеджер</div>
          <div class="od-info-val">${escHtml(order.manager || '—')}</div>
        </div>
        <div class="od-info-block">
          <div class="od-info-label">🕐 Принят</div>
          <div class="od-info-val">${formatDate(order.date)}</div>
        </div>
        <div class="od-info-block">
          <div class="od-info-label">⏰ Дедлайн</div>
          <div class="od-info-val">${order.deadline ? formatDate(order.deadline) : '—'}</div>
        </div>
        <div class="od-info-block">
          <div class="od-info-label">🏷️ Категория</div>
          <div class="od-info-val">${escHtml(order.bizcat || '—')}</div>
        </div>
      </div>

      ${chips ? `<div class="od-section-title">Параметры</div><div class="od-params-chips">${chips}</div>` : ''}
      ${order.comment ? `<div class="od-comment-box"><strong>💬 Комментарий:</strong> ${escHtml(order.comment)}</div>` : ''}
    </div>
    <div class="od-actions">
      <button class="od-btn od-btn-edit"   onclick="editOrderKb(event,'${order.id}')">✏️ Редактировать</button>
      <button class="od-btn od-btn-print"  onclick="printOrderForm('client')">🖨️ Чек</button>
      ${st !== 'done'   ? `<button class="od-btn od-btn-done"  onclick="changeKbOrderStatus('${order.id}','done',event)">✅ Выдать</button>` : ''}
      ${st !== 'work'   ? `<button class="od-btn od-btn-edit"  onclick="changeKbOrderStatus('${order.id}','work',event)" style="background:rgba(245,158,11,0.2);border-color:rgba(245,158,11,0.4);color:#fbbf24;">⚙️ В работу</button>` : ''}
      ${st !== 'ready'  ? `<button class="od-btn od-btn-done"  onclick="changeKbOrderStatus('${order.id}','ready',event)" style="background:rgba(16,185,129,0.15);border-color:rgba(16,185,129,0.3);color:#34d399;">✅ Готов</button>` : ''}
      <button class="od-btn od-btn-delete" onclick="deleteOrderKb('${order.id}')">🗑️ Удалить</button>
    </div>`;

  requestAnimationFrame(() => overlay.classList.add('open'));
};

window.closeOrderDetail = function() {
  document.getElementById('orderDetailOverlay')?.classList.remove('open');
};

// ════════════════════════════════════════════════════════════
//  FINANCE MODULE
// ════════════════════════════════════════════════════════════
const Finance = {

  async saveIncome() {
    const amount = parseFloat(document.getElementById('inc_amount')?.value);
    if (!amount || amount <= 0) { notify('Укажите сумму', 'error'); return; }

    const data = {
      type:        'income',
      date:        document.getElementById('inc_date')?.value || nowDTLocal(),
      category:    document.getElementById('inc_cat')?.value || '',
      description: document.getElementById('inc_desc')?.value || '',
      amount,
      method:      document.getElementById('inc_method')?.value || '',
      client:      document.getElementById('inc_client')?.value || '',
    };

    const res = await Api.finance.create(data);
    if (!res.ok) { notify('Ошибка записи дохода', 'error'); return; }

    State.finance.unshift(res.data || { ...data, id: res.id });
    closeModal('incomeModal');
    notify(`💰 Доход ${fmt(amount)} записан`, 'success');
    Dashboard.refresh();
    if (App.currentPage === 'finance') this.renderTable();
  },

  async saveExpense() {
    const amount = parseFloat(document.getElementById('exp_amount')?.value);
    if (!amount || amount <= 0) { notify('Укажите сумму', 'error'); return; }

    const data = {
      type:        'expense',
      date:        document.getElementById('exp_date')?.value || nowDTLocal(),
      category:    document.getElementById('exp_cat')?.value || '',
      description: document.getElementById('exp_desc')?.value || '',
      amount,
      method:      document.getElementById('exp_method')?.value || '',
    };

    const res = await Api.finance.create(data);
    if (!res.ok) { notify('Ошибка записи расхода', 'error'); return; }

    State.finance.unshift(res.data || { ...data, id: res.id });
    closeModal('expenseModal');
    notify(`📤 Расход ${fmt(amount)} записан`, 'error');
    Dashboard.refresh();
    if (App.currentPage === 'finance') this.renderTable();
  },

  async delete(id) {
    if (!confirm('Удалить запись?')) return;
    const res = await Api.finance.remove(id);
    if (!res.ok) { notify('Ошибка удаления', 'error'); return; }
    State.finance = State.finance.filter(f => String(f.id) !== String(id));
    this.renderTable();
    Dashboard.refresh();
    notify('Запись удалена', 'info');
  },

  renderTable() {
    const search = (document.getElementById('finSearch')?.value || '').toLowerCase();
    const type   = document.getElementById('finTypeFilter')?.value || '';
    const month  = document.getElementById('finMonthFilter')?.value || '';

    const now = new Date();
    let items = [...State.finance];

    if (search) items = items.filter(i =>
      (i.description || i.desc || '').toLowerCase().includes(search) ||
      (i.category || '').toLowerCase().includes(search)
    );
    if (type)   items = items.filter(i => i.type === type);
    if (month)  items = items.filter(i => (i.date || '').startsWith(month));

    // Итоги месяца
    const curMonth = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}`;
    const mItems = State.finance.filter(i => (i.date || '').startsWith(curMonth));
    const income  = mItems.filter(i => i.type === 'income') .reduce((a,b) => a + (b.amount||0), 0);
    const expense = mItems.filter(i => i.type === 'expense').reduce((a,b) => a + (b.amount||0), 0);

    const s = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
    s('finIncomeTotal',  fmt(income));
    s('finExpenseTotal', fmt(expense));
    s('finProfitTotal',  fmt(income - expense));

    const tbody = document.getElementById('financeTableBody');
    if (!tbody) return;

    if (!items.length) {
      tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state"><div class="icon">💰</div><div class="title">Операций нет</div></div></td></tr>`;
      return;
    }

    tbody.innerHTML = items.map(i => `
      <tr>
        <td>${formatDate(i.date)}</td>
        <td><span class="badge ${i.type === 'income' ? 'badge-done' : 'badge-cancel'}">
          ${i.type === 'income' ? '↑ Доход' : '↓ Расход'}
        </span></td>
        <td>${escHtml(i.category || '—')}</td>
        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escHtml(i.description || i.desc || '')}">
          ${escHtml(i.description || i.desc || '—')}
        </td>
        <td style="font-weight:700;color:${i.type === 'income' ? 'var(--accent3)' : 'var(--danger)'};">
          ${i.type === 'income' ? '+' : '−'}${fmt(i.amount)}
        </td>
        <td>${escHtml(i.method || '—')}</td>
        <td>
          <button class="btn btn-danger btn-xs" onclick="Finance.delete('${i.id}')">🗑️</button>
        </td>
      </tr>`).join('');
  },
};

window.saveIncome  = () => Finance.saveIncome();
window.saveExpense = () => Finance.saveExpense();
window.deleteFinance = id => Finance.delete(id);
window.renderFinanceTable = () => Finance.renderTable();

// ════════════════════════════════════════════════════════════
//  DASHBOARD
// ════════════════════════════════════════════════════════════
const Dashboard = {

  async refresh() {
    // Загружаем актуальную статистику с сервера
    const now    = new Date();
    const month  = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}`;
    const today  = now.toDateString();

    const statsRes = await Api.stats({ month }).catch(() => null);
    const stats = statsRes?.ok ? statsRes : null;

    // KPI
    const ordersToday   = State.orders.filter(o => new Date(o.date).toDateString() === today).length;
    const finance       = State.finance;
    const incMonth      = finance.filter(f => f.type==='income'  && (f.date||'').startsWith(month)).reduce((a,b)=>a+(b.amount||0),0);
    const expMonth      = finance.filter(f => f.type==='expense' && (f.date||'').startsWith(month)).reduce((a,b)=>a+(b.amount||0),0);
    const incToday      = finance.filter(f => f.type==='income'  && new Date(f.date).toDateString()===today).reduce((a,b)=>a+(b.amount||0),0);
    const expToday      = finance.filter(f => f.type==='expense' && new Date(f.date).toDateString()===today).reduce((a,b)=>a+(b.amount||0),0);
    const profit        = incMonth - expMonth;

    const s = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
    s('kpiOrdersToday',  ordersToday);
    s('kpiIncomeMonth',  fmt(incMonth));
    s('kpiExpenseMonth', fmt(expMonth));
    s('kpiProfitMonth',  fmt(profit));
    s('kpiIncomeToday',  'сегодня: ' + fmt(incToday));
    s('kpiExpenseToday', 'сегодня: ' + fmt(expToday));
    s('kpiProfitStatus', profit >= 0 ? '📈 Прибыльно' : '📉 Убыток');

    // Последние заказы
    const ro = document.getElementById('dashRecentOrders');
    if (ro) {
      const recent = State.orders.slice(0, 5);
      ro.innerHTML = recent.length
        ? `<div style="display:flex;flex-direction:column;gap:6px;">
           ${recent.map(o => `
             <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;
                         background:var(--bg-dark);border-radius:10px;cursor:pointer;border:1px solid var(--border);transition:all 0.2s;"
                  onclick="openOrderDetail(event,'${o.id}')" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
               <div style="display:flex;align-items:center;gap:10px;">
                 <span style="font-size:0.75rem;font-weight:700;color:var(--accent2);">${escHtml(o.num)}</span>
                 <span style="font-size:0.82rem;font-weight:600;">${escHtml(o.client)}</span>
                 <span style="font-size:0.72rem;color:var(--text-muted);">${escHtml(o.service_label || '')}</span>
               </div>
               <div style="display:flex;align-items:center;gap:8px;">
                 <span style="font-weight:700;color:var(--accent2);">${fmt(o.total)}</span>
                 <span class="badge badge-${o.status}">${KB_STATUS_LABELS[o.status] || o.status}</span>
               </div>
             </div>`).join('')}
           </div>`
        : `<div class="empty-state"><div class="icon">📋</div><div class="title">Заказов пока нет</div><div class="desc">Нажмите «Внести заказ»</div></div>`;
    }

    this._renderExtended(today, incToday, expToday, finance);
    App.updateBadges();
  },

  _renderExtended(today, sumInc, sumExp, finance) {
    const now    = new Date();
    const profit = sumInc - sumExp;
    const days   = ['Вс','Пн','Вт','Ср','Чт','Пт','Сб'];
    const months = ['января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'];

    const dateEl = document.getElementById('dashTodayDate');
    if (dateEl) dateEl.textContent = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]}`;

    const s = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
    s('dashTodayIncome',  fmt(sumInc));
    s('dashTodayExpense', fmt(sumExp));
    s('dashTodayProfit',  fmt(profit));

    // Прогресс-бар
    const total  = sumInc + sumExp;
    const pct    = total > 0 ? Math.round((sumInc / total) * 100) : 0;
    const barEl  = document.getElementById('dashTodayBar');
    const ratioEl= document.getElementById('dashTodayRatio');
    if (barEl)   { barEl.style.width = pct + '%'; barEl.style.background = pct >= 60 ? 'linear-gradient(to right,var(--accent3),var(--accent2))' : pct >= 40 ? 'linear-gradient(to right,var(--accent4),var(--accent2))' : 'linear-gradient(to right,var(--danger),var(--accent4))'; }
    if (ratioEl) ratioEl.textContent = total > 0 ? `${pct}% доход` : '—';

    // Последние 4 операции
    const rfEl = document.getElementById('dashRecentFinance');
    if (rfEl) {
      const last4 = [...finance].slice(0, 4);
      rfEl.innerHTML = last4.length
        ? last4.map(f => `
          <div style="display:flex;align-items:center;justify-content:space-between;padding:5px 8px;background:var(--bg-dark);border-radius:8px;">
            <div style="display:flex;align-items:center;gap:8px;">
              <span>${f.type === 'income' ? '💚' : '🔴'}</span>
              <span style="font-size:0.78rem;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escHtml(f.category || f.description || '—')}</span>
              <span style="font-size:0.65rem;color:var(--text-muted);">${formatDate(f.date)}</span>
            </div>
            <span style="font-weight:700;color:${f.type === 'income' ? 'var(--accent3)' : 'var(--danger)'};">${f.type === 'income' ? '+' : '−'}${fmt(f.amount)}</span>
          </div>`).join('')
        : '<div style="text-align:center;padding:12px;color:var(--text-muted);font-size:0.78rem;">Операций пока нет</div>';
    }

    // Почасовой график
    const hourlyEl = document.getElementById('dashHourlyChart');
    if (hourlyEl) {
      const hours = Array(24).fill(0);
      const todayInc = finance.filter(f => f.type==='income' && new Date(f.date).toDateString()===today);
      todayInc.forEach(f => { hours[new Date(f.date).getHours()] += f.amount || 0; });
      const bars = [];
      for (let h = 0; h < 24; h += 2) bars.push({ h, val: hours[h] + (hours[h+1]||0) });
      const maxBar = Math.max(...bars.map(b => b.val), 1);
      const nowH   = now.getHours();

      hourlyEl.innerHTML = bars.map(({ h, val }) => {
        const pct   = Math.max(4, Math.round((val / maxBar) * 100));
        const isNow = h <= nowH && nowH < h + 2;
        return `<div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;gap:2px;">
          ${val > 0 ? `<div style="font-size:0.55rem;color:var(--accent3);white-space:nowrap;">${val>=1000?Math.round(val/1000)+'к':Math.round(val)}</div>` : ''}
          <div style="width:100%;height:${pct}%;background:${isNow?'var(--accent2)':'rgba(6,182,212,0.4)'};border-radius:2px 2px 0 0;transition:height 0.4s;"></div>
        </div>`;
      }).join('');
    }

    // Топ категорий
    const topEl = document.getElementById('dashTopIncome');
    if (topEl) {
      const todayInc = finance.filter(f => f.type==='income' && new Date(f.date).toDateString()===today);
      const cats = {};
      todayInc.forEach(f => { cats[f.category||'Прочее'] = (cats[f.category||'Прочее']||0) + (f.amount||0); });
      const sorted = Object.entries(cats).sort((a,b) => b[1]-a[1]).slice(0, 5);
      const maxV   = sorted[0]?.[1] || 1;
      const colors = ['#10b981','#06b6d4','#7c3aed','#f59e0b','#ef4444'];
      topEl.innerHTML = sorted.length
        ? sorted.map(([cat, val], i) => `
          <div>
            <div style="display:flex;justify-content:space-between;font-size:0.75rem;margin-bottom:3px;">
              <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:120px;">${escHtml(cat)}</span>
              <span style="font-weight:700;color:${colors[i]};">${fmt(val)}</span>
            </div>
            <div style="height:4px;background:var(--border);border-radius:2px;"><div style="height:100%;width:${Math.round((val/maxV)*100)}%;background:${colors[i]};border-radius:2px;"></div></div>
          </div>`).join('')
        : '<div style="color:var(--text-muted);font-size:0.72rem;text-align:center;padding:16px 0;">Нет доходов сегодня</div>';
    }

    // Итог дня
    const emojiEl = document.getElementById('dashDayEmoji');
    const labelEl = document.getElementById('dashDayLabel');
    if (emojiEl && labelEl) {
      const cases = [
        [sumInc === 0 && sumExp === 0, '😴', 'День не начат',    'var(--text-muted)'],
        [profit > 10000,              '🤑', 'Отличный день!',    'var(--accent3)'],
        [profit > 5000,               '😊', 'Хороший день',      'var(--accent3)'],
        [profit > 1000,               '🙂', 'Небольшой плюс',    'var(--accent2)'],
        [profit === 0 && sumInc > 0,  '😐', 'В ноль',           'var(--accent4)'],
        [profit < 0,                  '😟', 'Расходы > доходов', 'var(--danger)'],
      ];
      const [, emoji, label, color] = cases.find(([cond]) => cond) || ['','🙂','Работаем','var(--text)'];
      emojiEl.textContent = emoji;
      labelEl.textContent = label;
      labelEl.style.color = color;
    }

    // Мини-статы
    const todayOrders = State.orders.filter(o => new Date(o.date).toDateString() === today);
    const todayFin    = finance.filter(f => new Date(f.date).toDateString() === today);
    const avgCheck    = todayOrders.length ? Math.round(todayOrders.reduce((a,b) => a+(b.total||0),0) / todayOrders.length) : 0;
    s('dashTodayOpsCount',    todayFin.length);
    s('dashTodayOrdersCount', todayOrders.length);
    s('dashTodayAvgCheck',    fmt(avgCheck));
  },
};

window.refreshDashboard = () => Dashboard.refresh();

// ════════════════════════════════════════════════════════════
//  CLIENTS MODULE
// ════════════════════════════════════════════════════════════
const Clients = {

  async save() {
    const name = document.getElementById('cli_name')?.value.trim();
    if (!name) { notify('Введите имя клиента', 'error'); return; }

    const editId = document.getElementById('cli_edit_id')?.value || '';
    const data = {
      name,
      type:     document.getElementById('cli_type')?.value     || '',
      phone:    document.getElementById('cli_phone')?.value    || '',
      email:    document.getElementById('cli_email')?.value    || '',
      address:  document.getElementById('cli_address')?.value  || '',
      inn:      document.getElementById('cli_inn')?.value      || '',
      discount: parseFloat(document.getElementById('cli_discount')?.value) || 0,
      notes:    document.getElementById('cli_notes')?.value    || '',
    };

    let res;
    if (editId) {
      res = await Api.clients.update(editId, data);
    } else {
      res = await Api.clients.create(data);
    }

    if (!res.ok) { notify('Ошибка сохранения клиента', 'error'); return; }

    if (editId) {
      const idx = State.clients.findIndex(c => String(c.id) === String(editId));
      if (idx !== -1) State.clients[idx] = { ...State.clients[idx], ...data };
      notify('Клиент обновлён', 'success');
    } else {
      State.clients.unshift(res.data || { ...data, id: res.id });
      notify(`Клиент ${name} добавлен`, 'success');
    }

    closeModal('clientModal');
    this.render();
  },

  async delete(id) {
    if (!confirm('Удалить клиента?')) return;
    const res = await Api.clients.remove(id);
    if (!res.ok) { notify('Ошибка удаления', 'error'); return; }
    State.clients = State.clients.filter(c => String(c.id) !== String(id));
    this.render();
    notify('Клиент удалён', 'info');
  },

  render() {
    const search  = (document.getElementById('clientSearch')?.value || '').toLowerCase();
    let clients   = [...State.clients];
    if (search) clients = clients.filter(c =>
      (c.name  || '').toLowerCase().includes(search) ||
      (c.phone || '').includes(search) ||
      (c.email || '').toLowerCase().includes(search)
    );

    const grid = document.getElementById('clientsGrid');
    if (!grid) return;

    if (!clients.length) {
      grid.innerHTML = `<div class="empty-state card" style="grid-column:1/-1;"><div class="icon">👥</div><div class="title">Клиентов нет</div></div>`;
      return;
    }

    const orderCount = name => State.orders.filter(o => o.client === name).length;
    const totalSpent = name => State.orders
      .filter(o => o.client === name && o.status === 'done')
      .reduce((a,b) => a + (b.total||0), 0);

    grid.innerHTML = clients.map(c => `
      <div class="card">
        <div style="display:flex;gap:12px;align-items:center;margin-bottom:12px;">
          <div class="client-avatar">${(c.name||'?').charAt(0).toUpperCase()}</div>
          <div style="flex:1;min-width:0;">
            <div style="font-weight:700;">${escHtml(c.name)}</div>
            <div class="text-xs text-muted">${escHtml(c.type || '')}</div>
          </div>
          ${c.discount > 0 ? `<span class="badge badge-work">−${c.discount}%</span>` : ''}
        </div>
        ${c.phone ? `<div class="text-xs text-muted mb-8">📞 ${escHtml(c.phone)}</div>` : ''}
        ${c.email ? `<div class="text-xs text-muted mb-8">✉️ ${escHtml(c.email)}</div>` : ''}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:12px 0;padding:10px;background:var(--bg-dark);border-radius:8px;">
          <div style="text-align:center;">
            <div style="font-weight:800;color:var(--accent2);">${orderCount(c.name)}</div>
            <div class="text-xs text-muted">заказов</div>
          </div>
          <div style="text-align:center;">
            <div style="font-weight:800;color:var(--accent3);">${fmt(totalSpent(c.name))}</div>
            <div class="text-xs text-muted">потрачено</div>
          </div>
        </div>
        <div style="display:flex;gap:6px;">
          <button class="btn btn-danger btn-xs" style="flex:1;" onclick="Clients.delete('${c.id}')">🗑️</button>
          <button class="btn btn-primary btn-xs" style="flex:2;" onclick="newOrderForClient('${escHtml(c.name)}','${escHtml(c.phone||'')}')">+ Заказ</button>
        </div>
      </div>`).join('');
  },
};

window.saveClient  = () => Clients.save();
window.deleteClient = id => Clients.delete(id);
window.renderClients = () => Clients.render();
window.newOrderForClient = (name, phone) => {
  openModal('orderModal');
  setTimeout(() => {
    const cn = document.getElementById('ord_client');
    const cp = document.getElementById('ord_phone');
    if (cn) cn.value = name;
    if (cp) cp.value = phone;
  }, 100);
};

// ════════════════════════════════════════════════════════════
//  NOTES MODULE
// ════════════════════════════════════════════════════════════
const Notes = {

  async save() {
    const title = document.getElementById('note_title')?.value.trim() || '';
    const body  = document.getElementById('note_body')?.value.trim()  || '';
    if (!title && !body) { notify('Введите текст заметки', 'error'); return; }

    const data = {
      title:    title || 'Без заголовка',
      body,
      priority: document.getElementById('note_priority')?.value || 'normal',
      shift:    document.getElementById('note_shift')?.value    || '',
    };

    const res = await Api.notes.create(data);
    if (!res.ok) { notify('Ошибка сохранения заметки', 'error'); return; }

    State.notes.unshift(res.data || { ...data, id: res.id });
    closeModal('noteModal');
    this.render();
    App.updateBadges();
    notify('Заметка сохранена', 'success');
  },

  async delete(id) {
    if (!confirm('Удалить заметку?')) return;
    const res = await Api.notes.remove(id);
    if (!res.ok) { notify('Ошибка', 'error'); return; }
    State.notes = State.notes.filter(n => String(n.id) !== String(id));
    this.render();
    App.updateBadges();
  },

  render() {
    const grid = document.getElementById('notesGrid');
    if (!grid) return;

    if (!State.notes.length) {
      grid.innerHTML = `<div class="empty-state card" style="grid-column:1/-1;"><div class="icon">📝</div><div class="title">Заметок нет</div></div>`;
      return;
    }

    const labels = { normal:'Обычная', info:'Информация', important:'⚠️ Важная', urgent:'🚨 Срочно!' };
    const colors = { normal:'var(--text-muted)', info:'var(--accent2)', important:'var(--accent4)', urgent:'var(--danger)' };

    grid.innerHTML = State.notes.map(n => `
      <div class="note-card ${n.priority}">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:6px;">
          <div class="note-title">${escHtml(n.title)}</div>
          <span style="font-size:0.65rem;padding:2px 8px;border-radius:20px;border:1px solid ${colors[n.priority]||'var(--border)'};color:${colors[n.priority]||'var(--text-muted)'};">
            ${labels[n.priority]||n.priority}
          </span>
        </div>
        <div class="note-body">${escHtml(n.body || '')}</div>
        <div class="note-meta">
          <span>🕐 ${formatDate(n.created_at||n.created)}</span>
          ${n.shift ? `<span>👤 ${escHtml(n.shift)}</span>` : ''}
          <button class="btn btn-danger btn-xs" style="margin-left:auto;" onclick="Notes.delete('${n.id}')">🗑️</button>
        </div>
      </div>`).join('');
  },
};

window.saveNote     = () => Notes.save();
window.renderNotes  = () => Notes.render();

// ════════════════════════════════════════════════════════════
//  WAREHOUSE MODULE
// ════════════════════════════════════════════════════════════
const Warehouse = {

  async load() {
    const res = await Api.warehouse.list();
    if (res.ok) State.set('warehouse', res.data || []);
  },

  async render() {
    await this.load();

    const search = (document.getElementById('whSearch')?.value   || '').toLowerCase();
    const cat    = document.getElementById('whCatFilter')?.value  || '';
    const status = document.getElementById('whStatusFilter')?.value || '';

    const all   = State.warehouse;
    let   items = [...all];

    if (search) items = items.filter(i => (i.name||'').toLowerCase().includes(search));
    if (cat)    items = items.filter(i => i.category === cat);

    items = items.map(i => ({ ...i, isLow: parseFloat(i.qty) <= parseFloat(i.min_qty) }));

    if (status === 'low') items = items.filter(i =>  i.isLow);
    if (status === 'ok')  items = items.filter(i => !i.isLow);

    const low = all.filter(i => parseFloat(i.qty) <= parseFloat(i.min_qty)).length;
    const sum = all.reduce((a,b) => a + (parseFloat(b.qty)||0) * (parseFloat(b.price)||0), 0);

    const s = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
    s('wh_total', all.length);
    s('wh_low',   low);
    s('wh_ok',    all.length - low);
    s('wh_sum',   fmt(sum));

    const badge = document.getElementById('warehouseLowBadge');
    if (badge) badge.style.display = low > 0 ? '' : 'none';

    const tbody = document.getElementById('warehouseTableBody');
    if (!tbody) return;

    if (!items.length) {
      tbody.innerHTML = `<tr><td colspan="8"><div class="empty-state"><div class="icon">📦</div><div class="title">Склад пуст</div></div></td></tr>`;
      return;
    }

    tbody.innerHTML = items.map(i => `
      <tr>
        <td style="font-weight:600;">${escHtml(i.name)}</td>
        <td><span class="badge badge-new">${escHtml(i.category)}</span></td>
        <td style="font-weight:700;color:${i.isLow ? 'var(--danger)' : 'var(--accent3)'};">${i.qty} ${escHtml(i.unit)}</td>
        <td class="text-muted">${i.min_qty} ${escHtml(i.unit)}</td>
        <td>${i.isLow ? '<span class="badge badge-cancel">⚠️ Мало</span>' : '<span class="badge badge-done">✅ Норма</span>'}</td>
        <td>${fmt(i.price)}</td>
        <td style="font-weight:700;">${fmt(parseFloat(i.qty) * parseFloat(i.price))}</td>
        <td>
          <div style="display:flex;gap:4px;">
            <button class="btn btn-success btn-xs" onclick="openWhAction('restock','${i.id}','${escHtml(i.name)}',${i.qty})" title="Пополнить">+</button>
            <button class="btn btn-secondary btn-xs" onclick="openWhAction('deduct','${i.id}','${escHtml(i.name)}',${i.qty})" title="Списать">−</button>
            <button class="btn btn-danger btn-xs" onclick="Warehouse.delete('${i.id}')" title="Удалить">🗑️</button>
          </div>
        </td>
      </tr>`).join('');
  },

  async add() {
    const name = document.getElementById('wh_name')?.value.trim();
    if (!name) { notify('Введите наименование', 'error'); return; }

    const data = {
      name,
      category: document.getElementById('wh_cat')?.value    || 'Прочее',
      unit:     document.getElementById('wh_unit')?.value   || 'шт',
      qty:      parseFloat(document.getElementById('wh_qty')?.value)    || 0,
      min_qty:  parseFloat(document.getElementById('wh_minqty')?.value) || 0,
      price:    parseFloat(document.getElementById('wh_price')?.value)  || 0,
    };

    const res = await Api.warehouse.create(data);
    if (!res.ok) { notify('Ошибка добавления', 'error'); return; }

    notify(`✅ ${name} добавлен`, 'success');
    closeModal('warehouseAddModal');
    this.render();
  },

  async action(type, id, qty) {
    const res = await Api.warehouse[type]({ id, qty });
    if (!res.ok) { notify('Ошибка: ' + (res.error || ''), 'error'); return; }
    if (res.alert) notify('⚠️ ' + res.alert, 'error');
    else notify(type === 'restock' ? '✅ Пополнено' : '✅ Списано', 'success');
    closeModal('warehouseActionModal');
    this.render();
    App.updateBadges();
  },

  async delete(id) {
    if (!confirm('Удалить позицию?')) return;
    const res = await Api.warehouse.remove(id);
    if (!res.ok) { notify('Ошибка', 'error'); return; }
    this.render();
    notify('Позиция удалена', 'info');
  },

  async showMovements() {
    const res = await Api.warehouse.history();
    const movs = res.data || [];
    if (!movs.length) { notify('История движений пуста', 'info'); return; }
    const text = movs.slice(0, 20).map(m =>
      `${formatDate(m.created_at)} | ${m.type === 'restock' ? '▲' : '▼'} ${m.name} — ${m.qty} ${m.unit}`
    ).join('\n');
    alert('📋 Последние движения:\n\n' + text);
  },
};

window.saveWarehouseItem     = () => Warehouse.add();
window.renderWarehouse       = () => Warehouse.render();
window.showWarehouseMovements= () => Warehouse.showMovements();
window.deleteWarehouseItem   = id => Warehouse.delete(id);
window.openWhAction = (type, id, name, currentQty) => {
  const set = (elId, v) => { const e = document.getElementById(elId); if (e) e.value = v; };
  set('wh_action_id',   id);
  set('wh_action_type', type);
  set('wh_action_qty',  '');
  const nm  = document.getElementById('whActionItemName');
  const cur = document.getElementById('whActionCurrentQty');
  const tit = document.getElementById('whActionTitle');
  if (nm)  nm.textContent  = name;
  if (cur) cur.textContent = currentQty;
  if (tit) tit.textContent = type === 'restock' ? '📥 Пополнить склад' : '📤 Списать со склада';
  openModal('warehouseActionModal');
};
window.executeWarehouseAction = () => {
  const id   = document.getElementById('wh_action_id')?.value   || '';
  const type = document.getElementById('wh_action_type')?.value || '';
  const qty  = parseFloat(document.getElementById('wh_action_qty')?.value);
  if (!qty || qty <= 0) { notify('Введите количество', 'error'); return; }
  Warehouse.action(type, id, qty);
};

// ════════════════════════════════════════════════════════════
//  CALENDAR MODULE
// ════════════════════════════════════════════════════════════
const Calendar = {

  currentDate: new Date(),

  async render() {
    const year  = this.currentDate.getFullYear();
    const month = this.currentDate.getMonth();
    const from  = `${year}-${String(month+1).padStart(2,'0')}-01`;
    const to    = `${year}-${String(month+1).padStart(2,'0')}-${new Date(year, month+1, 0).getDate()}`;

    const label = document.getElementById('calMonthLabel');
    if (label) label.textContent = this.currentDate.toLocaleDateString('ru', { month: 'long', year: 'numeric' });

    const res = await Api.calendar.list({ from, to });
    let events = res.ok ? (res.data || []) : [];

    // Добавить дедлайны из заказов
    State.orders.forEach(o => {
      if (!o.deadline || o.status === 'done' || o.status === 'cancel') return;
      const d = o.deadline.slice(0, 10);
      if (d >= from && d <= to) {
        events.push({ id: 'ord_'+o.id, title: `${o.num} — ${o.client}`, date: d, type: 'deadline', color: '#ef4444' });
      }
    });

    const grid = document.getElementById('calendarGrid');
    if (!grid) return;

    const dayNames    = ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];
    const firstDay    = new Date(year, month, 1).getDay();
    const offset      = firstDay === 0 ? 6 : firstDay - 1;
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const today       = new Date().toDateString();

    let html = dayNames.map(d => `
      <div style="padding:8px;text-align:center;font-size:0.72rem;font-weight:700;color:var(--text-muted);background:var(--bg-card2);border-bottom:1px solid var(--border);">${d}</div>
    `).join('');

    for (let i = 0; i < offset; i++) {
      html += `<div style="min-height:80px;border:1px solid var(--border);background:var(--bg-dark);opacity:0.3;"></div>`;
    }

    for (let d = 1; d <= daysInMonth; d++) {
      const dateStr   = `${year}-${String(month+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
      const dayEvents = events.filter(e => e.date === dateStr);
      const isToday   = new Date(year, month, d).toDateString() === today;

      html += `
        <div style="min-height:80px;border:1px solid var(--border);padding:6px;background:${isToday?'rgba(124,58,237,0.1)':'var(--bg-card)'};cursor:pointer;"
             onclick="quickAddCalEvent('${dateStr}')">
          <div style="font-size:0.82rem;font-weight:${isToday?'900':'600'};color:${isToday?'var(--accent2)':'var(--text)'};margin-bottom:4px;">${d}${isToday?' ←':''}</div>
          ${dayEvents.map(e => `
            <div style="font-size:0.65rem;padding:2px 4px;border-radius:4px;background:${e.color||'var(--accent)'}22;color:${e.color||'var(--accent)'};border-left:2px solid ${e.color||'var(--accent)'};margin-bottom:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                 title="${escHtml(e.title)}">${escHtml(e.title)}</div>
          `).join('')}
        </div>`;
    }

    grid.innerHTML = html;
  },

  prev() { this.currentDate.setMonth(this.currentDate.getMonth() - 1); this.render(); },
  next() { this.currentDate.setMonth(this.currentDate.getMonth() + 1); this.render(); },
};

window.calPrevMonth      = () => Calendar.prev();
window.calNextMonth      = () => Calendar.next();
window.renderCalendar    = () => Calendar.render();
window.quickAddCalEvent  = date => {
  const set = (id, v) => { const e = document.getElementById(id); if (e) e.value = v; };
  set('cal_date', date); set('cal_time', ''); set('cal_title', '');
  openModal('calEventModal');
};
window.saveCalEvent = async () => {
  const title = document.getElementById('cal_title')?.value.trim();
  if (!title) { notify('Введите заголовок', 'error'); return; }
  const data = {
    title,
    date:  document.getElementById('cal_date')?.value  || '',
    time:  document.getElementById('cal_time')?.value  || '',
    type:  document.getElementById('cal_type')?.value  || 'task',
    color: document.getElementById('cal_color')?.value || '#7c3aed',
    note:  document.getElementById('cal_note')?.value  || '',
  };
  const res = await Api.calendar.create(data);
  if (!res.ok) { notify('Ошибка', 'error'); return; }
  closeModal('calEventModal');
  Calendar.render();
  notify('✅ Задача добавлена', 'success');
};

// ════════════════════════════════════════════════════════════
//  STATS MODULE
// ════════════════════════════════════════════════════════════
const Stats = {
  render() {
    const period = document.getElementById('statsPeriod')?.value || 'month';
    const now    = new Date();
    let orders   = [...State.orders];

    if (period === 'month') orders = orders.filter(o => {
      const d = new Date(o.date);
      return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
    });
    if (period === 'week') {
      const weekAgo = new Date(now - 7 * 86400000);
      orders = orders.filter(o => new Date(o.date) >= weekAgo);
    }

    const s = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
    s('statTotalOrders', orders.length);
    s('statDoneOrders',  orders.filter(o => o.status === 'done').length);
    s('statClients',     State.clients.length);
    s('statAvgCheck',    fmt(orders.length ? Math.round(orders.reduce((a,b) => a+(b.total||0),0)/orders.length) : 0));

    const byService = {};
    orders.forEach(o => { byService[o.service_label||'Прочее'] = (byService[o.service_label||'Прочее']||0)+1; });
    this._renderBarChart('statsByService', byService, '#7c3aed');

    const byBiz = {};
    orders.forEach(o => { const k = o.bizcat||'Не указано'; byBiz[k]=(byBiz[k]||0)+1; });
    this._renderBarChart('statsByCategory', byBiz, '#06b6d4');
    this._renderServiceBars(orders);
  },

  _renderBarChart(id, data, color) {
    const el = document.getElementById(id);
    if (!el) return;
    const entries = Object.entries(data).sort((a,b)=>b[1]-a[1]).slice(0,8);
    if (!entries.length) { el.innerHTML = '<div class="text-muted text-sm">Нет данных</div>'; return; }
    const max = Math.max(...entries.map(e=>e[1]));
    el.innerHTML = entries.map(([k,v]) => `
      <div style="margin-bottom:8px;">
        <div style="display:flex;justify-content:space-between;font-size:0.75rem;margin-bottom:4px;">
          <span>${escHtml(k)}</span><span style="font-weight:700;">${v}</span>
        </div>
        <div class="progress-bar"><div class="progress-fill" style="width:${Math.round((v/max)*100)}%;background:${color};"></div></div>
      </div>`).join('');
  },

  _renderServiceBars(orders) {
    const el = document.getElementById('statsServiceBars');
    if (!el) return;
    const data = {};
    orders.forEach(o => { data[o.service_label||'Прочее']=(data[o.service_label||'Прочее']||0)+1; });
    const entries = Object.entries(data).sort((a,b)=>b[1]-a[1]).slice(0,8);
    if (!entries.length) { el.innerHTML = '<div class="text-muted text-sm">Нет данных</div>'; return; }
    const max    = Math.max(...entries.map(e=>e[1]));
    const colors = ['#7c3aed','#06b6d4','#10b981','#f59e0b','#ef4444','#8b5cf6','#0ea5e9','#14b8a6'];
    el.innerHTML = `<div style="display:flex;align-items:flex-end;gap:12px;height:100px;padding:0 4px;">
      ${entries.map(([k,v],i) => `
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;">
          <div style="font-size:0.65rem;font-weight:700;color:${colors[i]};">${v}</div>
          <div style="width:100%;height:${Math.round((v/max)*80)}px;background:${colors[i]};border-radius:4px 4px 0 0;transition:height 0.4s;"></div>
          <div style="font-size:0.6rem;color:var(--text-muted);text-align:center;">${escHtml(k.split(' ')[0])}</div>
        </div>`).join('')}
    </div>`;
  },
};

window.renderStats = () => Stats.render();

// ════════════════════════════════════════════════════════════
//  ACCOUNTING MODULE
// ════════════════════════════════════════════════════════════
const Accounting = {
  render() {
    const months = {};
    State.finance.forEach(f => {
      const d   = new Date(f.date);
      const key = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
      if (!months[key]) months[key] = { income:0, expense:0 };
      if (f.type === 'income')  months[key].income  += f.amount||0;
      else                      months[key].expense += f.amount||0;
    });

    const ordersByMonth = {};
    State.orders.forEach(o => {
      const d   = new Date(o.date);
      const key = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
      ordersByMonth[key] = (ordersByMonth[key]||0) + 1;
    });

    const MONTHS = ['Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'];
    const tbody  = document.getElementById('accountingTable');
    if (!tbody) return;
    const arr = Object.entries(months).sort((a,b) => b[0].localeCompare(a[0]));

    tbody.innerHTML = !arr.length
      ? `<tr><td colspan="6"><div class="empty-state"><div class="icon">📊</div><div class="title">Нет данных</div></div></td></tr>`
      : arr.map(([k,v]) => {
          const profit = v.income - v.expense;
          const margin = v.income > 0 ? ((profit/v.income)*100).toFixed(1) : '0';
          const [yr, mn] = k.split('-');
          return `
            <tr>
              <td>${MONTHS[parseInt(mn)-1]} ${yr}</td>
              <td style="color:var(--accent3);font-weight:700;">${fmt(v.income)}</td>
              <td style="color:var(--danger);font-weight:700;">${fmt(v.expense)}</td>
              <td style="color:${profit>=0?'var(--accent2)':'var(--danger)'};font-weight:700;">${fmt(profit)}</td>
              <td>${ordersByMonth[k]||0}</td>
              <td><span class="badge ${profit>=0?'badge-done':'badge-cancel'}">${margin}%</span></td>
            </tr>`;
        }).join('');

    const expByCat = {}, incByCat = {};
    State.finance.forEach(f => {
      const cat = f.category || 'Прочее';
      if (f.type === 'expense') expByCat[cat] = (expByCat[cat]||0) + (f.amount||0);
      else                      incByCat[cat] = (incByCat[cat]||0) + (f.amount||0);
    });
    Stats._renderBarChart('expenseByCategory', expByCat, '#ef4444');
    Stats._renderBarChart('incomeByCategory',  incByCat, '#10b981');
  },
};

window.renderAccounting = () => Accounting.render();

// ════════════════════════════════════════════════════════════
//  SETTINGS MODULE
// ════════════════════════════════════════════════════════════
const Settings = {

  async load() {
    const res = await Api.settings.get();
    if (res.ok) State.set('settings', res.data || {});

    const s = State.settings;
    const fields = {
      setCompany:'company', setInn:'inn', setOgrn:'ogrn',
      setAddress:'address', setPhone:'phone', setEmail:'email',
      setWebsite:'website', setBankAcc:'bankAcc', setBik:'bik',
      setBankName:'bankName', setKorAcc:'korAcc', setKpp:'kpp',
      setReceiptHeader:'receiptHeader', setReceiptFooter:'receiptFooter',
      setSignatory:'signatory', setSignatoryTitle:'signatoryTitle',
      setVat:'vat', setCurrency:'currency',
      setApiKey:'apiKey', setApiModel:'apiModel',
      setTgToken:'tgToken', setTgBossId:'tgBossId',
    };
    Object.entries(fields).forEach(([id, key]) => {
      const el = document.getElementById(id);
      if (el) el.value = s[key] || '';
    });

    Ui.updateDbInfo();
    this._renderModulesGrid();
  },

  async save() {
    const fields = {
      setCompany:'company', setInn:'inn', setOgrn:'ogrn',
      setAddress:'address', setPhone:'phone', setEmail:'email',
      setWebsite:'website', setBankAcc:'bankAcc', setBik:'bik',
      setBankName:'bankName', setKorAcc:'korAcc', setKpp:'kpp',
      setReceiptHeader:'receiptHeader', setReceiptFooter:'receiptFooter',
      setSignatory:'signatory', setSignatoryTitle:'signatoryTitle',
      setVat:'vat', setCurrency:'currency',
      setApiKey:'apiKey', setApiModel:'apiModel',
      setTgToken:'tgToken', setTgBossId:'tgBossId',
    };

    const data = {};
    Object.entries(fields).forEach(([id, key]) => {
      const el = document.getElementById(id);
      if (el) data[key] = el.value;
    });

    const res = await Api.settings.save(data);
    if (!res.ok) { notify('Ошибка сохранения настроек', 'error'); return; }

    State.set('settings', { ...State.settings, ...data });
    notify('✅ Настройки сохранены', 'success');
  },

  _renderModulesGrid() {
    const grid = document.getElementById('modulesGrid');
    if (!grid) return;
    const mods = Object.values(CRM._modules || {});
    if (!mods.length) {
      grid.innerHTML = '<div class="text-muted text-sm" style="grid-column:1/-1;padding:12px;">Нет подключённых модулей</div>';
      return;
    }
    grid.innerHTML = mods.map(m => `
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px;background:var(--bg-dark);border-radius:10px;border:1px solid var(--border);">
        <div style="display:flex;gap:10px;align-items:center;">
          <div style="font-size:1.4rem;">${m.icon || '🔧'}</div>
          <div>
            <div style="font-weight:700;font-size:0.85rem;">${m.name}</div>
            <div class="text-xs text-muted">${m.id}</div>
          </div>
        </div>
        <span class="badge badge-done">Активен</span>
      </div>`).join('');
  },
};

window.loadSettings  = () => Settings.load();
window.saveSettings  = () => Settings.save();
window.renderModulesGrid = () => Settings._renderModulesGrid();

// ════════════════════════════════════════════════════════════
//  CRM FRAMEWORK — регистрация внешних модулей
// ════════════════════════════════════════════════════════════
window.CRM = {
  _modules: {},

  registerModule(cfg) {
    this._modules[cfg.id] = cfg;
    this._injectPage(cfg);
    this._injectNav(cfg);
    console.log(`✅ Модуль «${cfg.name}» зарегистрирован`);
  },

  _injectPage(cfg) {
    if (document.getElementById('page-' + cfg.id)) return;
    const div = document.createElement('div');
    div.className = 'page';
    div.id = 'page-' + cfg.id;
    div.innerHTML = cfg.page || '';
    const main = document.getElementById('mainContent');
    if (main) main.appendChild(div);
  },

  _injectNav(cfg) {
    const section = document.getElementById('modules-sidebar-section');
    if (!section) return;
    if (document.getElementById('nav-' + cfg.id)) return;
    const btn = document.createElement('button');
    btn.className = 'nav-btn';
    btn.id = 'nav-' + cfg.id;
    btn.innerHTML = `<span style="font-size:1rem;">${cfg.icon||'🔧'}</span> ${cfg.name}`;
    btn.onclick = () => App.showPage(cfg.id, btn);
    section.appendChild(btn);
  },

  api: (module, action, body, params) =>
    Api.module(module, action, body, params),
};

// ════════════════════════════════════════════════════════════
//  EXPORT / IMPORT / CLEAR DB
// ════════════════════════════════════════════════════════════
window.exportDB = () => {
  const data = { orders: State.orders, finance: State.finance, clients: State.clients, settings: State.settings, notes: State.notes };
  const blob  = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
  const url   = URL.createObjectURL(blob);
  const a     = document.createElement('a');
  a.href = url; a.download = `printcrm_${new Date().toISOString().slice(0,10)}.json`; a.click();
  URL.revokeObjectURL(url);
  notify('База экспортирована', 'success');
};

window.importDB = () => document.getElementById('importFile')?.click();

window.loadImportFile = async (e) => {
  const file = e.target.files[0];
  if (!file) return;
  try {
    const text = await file.text();
    const data = JSON.parse(text);
    if (!confirm('Загрузить базу? Текущие данные будут заменены.')) return;
    const res = await Api.post('import', data);
    if (res.ok) { notify('База импортирована', 'success'); await Sync.loadAll(); }
    else notify('Ошибка импорта: ' + (res.error||''), 'error');
  } catch { notify('Ошибка: неверный файл', 'error'); }
};

window.clearDB = async () => {
  if (!confirm('УДАЛИТЬ ВСЕ ДАННЫЕ?')) return;
  if (!confirm('Вы точно уверены?')) return;
  const res = await Api.post('db/clear', {});
  if (res.ok) { await Sync.loadAll(); notify('База очищена', 'error'); }
  else notify('Ошибка очистки', 'error');
};

// ════════════════════════════════════════════════════════════
//  PRINT
// ════════════════════════════════════════════════════════════
window.printOrderForm = (forWhom) => {
  const s       = State.settings || {};
  const num     = document.getElementById('ord_num')?.value     || '';
  const client  = document.getElementById('ord_client')?.value  || 'Без имени';
  const phone   = document.getElementById('ord_phone')?.value   || '';
  const manager = document.getElementById('ord_manager')?.value || '';
  const date    = document.getElementById('ord_date')?.value    || '';
  const deadline= document.getElementById('ord_deadline')?.value|| '';
  const total   = parseFloat(document.getElementById('ord_total')?.value)  || 0;
  const prepay  = parseFloat(document.getElementById('ord_prepay')?.value) || 0;
  const comment = document.getElementById('ord_comment')?.value || '';
  const payment = document.getElementById('ord_payment')?.value || '';
  const service = getServiceLabel(Order.currentTab);
  const size    = document.querySelector('.size-btn.selected')?.textContent.trim() || '';
  const remain  = total - prepay;
  const isMan   = forWhom === 'manager';

  const checked = [];
  document.querySelector('.service-tab-content.active')
    ?.querySelectorAll('.checkbox-item.checked')
    .forEach(c => checked.push(c.textContent.trim().replace('✓','').trim()));

  const html = `
    <style>
      body{font-family:Arial,sans-serif;font-size:12px;color:#000;}
      .print-header{text-align:center;margin-bottom:16px;border-bottom:2px solid #000;padding-bottom:12px;}
      .print-title{font-size:18px;font-weight:bold;margin:10px 0;}
      table{width:100%;border-collapse:collapse;margin:12px 0;}
      td,th{border:1px solid #999;padding:6px 8px;font-size:11px;}
      .total-block{border:2px solid #000;padding:12px;margin:12px 0;font-size:14px;}
      .footer{text-align:center;margin-top:20px;font-size:10px;color:#666;}
      .sign-block{display:flex;justify-content:space-between;margin-top:24px;}
    </style>
    <div class="print-header">
      <div style="font-size:16px;font-weight:bold;">${escHtml(s.company||'Фотокопицентр')}</div>
      ${s.address ? `<div>${escHtml(s.address)}</div>` : ''}
      ${s.phone   ? `<div>Тел: ${escHtml(s.phone)}</div>` : ''}
      ${s.inn     ? `<div>ИНН: ${escHtml(s.inn)}</div>` : ''}
    </div>
    <div class="print-title">${isMan ? 'БЛАНК МЕНЕДЖЕРА' : 'КВИТАНЦИЯ КЛИЕНТА'} — ЗАКАЗ № ${escHtml(num)}</div>
    <table>
      <tr><th>Дата приёма</th><td>${formatDate(date)}</td><th>Срок выдачи</th><td>${deadline ? formatDate(deadline) : '—'}</td></tr>
      <tr><th>Клиент</th><td>${escHtml(client)}</td><th>Телефон</th><td>${escHtml(phone)}</td></tr>
      <tr><th>Вид услуги</th><td>${escHtml(service)}</td><th>Размер/формат</th><td>${escHtml(size)||'—'}</td></tr>
      <tr><th>Менеджер</th><td>${escHtml(manager)}</td><th>Способ оплаты</th><td>${escHtml(payment)}</td></tr>
    </table>
    ${checked.length ? `<div style="margin:8px 0;"><b>Параметры:</b> ${checked.map(c=>`✓ ${escHtml(c)}`).join(', ')}</div>` : ''}
    ${comment ? `<div style="margin:8px 0;padding:8px;border:1px solid #ccc;"><b>Комментарий:</b> ${escHtml(comment)}</div>` : ''}
    <div class="total-block">
      <table style="border:none;">
        <tr><td style="border:none;"><b>ИТОГО:</b></td><td style="border:none;font-size:16px;font-weight:bold;">${fmt(total)}</td></tr>
        ${prepay > 0 ? `<tr><td style="border:none;">Предоплата:</td><td style="border:none;">${fmt(prepay)}</td></tr>` : ''}
        <tr><td style="border:none;"><b>К ДОПЛАТЕ:</b></td><td style="border:none;font-size:16px;font-weight:bold;color:${remain > 0 ? 'red' : 'green'};">${remain > 0 ? fmt(remain) : '✅ Оплачено'}</td></tr>
      </table>
    </div>
    ${s.receiptHeader ? `<div style="text-align:center;font-style:italic;">${escHtml(s.receiptHeader)}</div>` : ''}
    <div class="sign-block">
      <div>${escHtml(s.signatoryTitle||'Менеджер')}: ${escHtml(s.signatory||'_______________')}</div>
      <div>Клиент: _______________</div>
    </div>
    ${s.receiptFooter ? `<div class="footer">${escHtml(s.receiptFooter)}</div>` : ''}`;

  const pa = document.getElementById('printArea');
  if (pa) {
    pa.innerHTML = html;
    pa.style.display = 'block';
    window.print();
    setTimeout(() => { pa.style.display = 'none'; }, 1000);
  }
};

// ════════════════════════════════════════════════════════════
//  DEEPSEEK AI CHAT
// ════════════════════════════════════════════════════════════
let chatHistory = [];

async function sendChatMessage() {
  const input = document.getElementById('chatInput');
  const text  = input?.value.trim();
  if (!text) return;
  if (input) { input.value = ''; input.style.height = 'auto'; }
  await _processChatMessage(text);
}

window.sendQuickChat = async (text) => {
  await _processChatMessage(text);
};

async function _processChatMessage(text) {
  const msgs = document.getElementById('chatMessages');
  if (!msgs) return;

  _appendChatMsg(msgs, 'user', text);
  chatHistory.push({ role: 'user', content: text });

  const typingId = _appendTyping(msgs);

  try {
    const apiKey = State.settings.apiKey || '';
    const model  = State.settings.apiModel || 'deepseek-chat';
    if (!apiKey) {
      _removeTyping(typingId, msgs);
      _appendChatMsg(msgs, 'ai', '⚠️ Добавьте API ключ DeepSeek в Настройках.');
      return;
    }

    const res = await fetch('https://api.deepseek.com/v1/chat/completions', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${apiKey}` },
      body: JSON.stringify({
        model,
        messages: [
          { role: 'system', content: 'Ты Валера — эксперт по типографии и печатному бизнесу. Помогаешь управлять фотокопицентром. Отвечаешь кратко, по-русски, по делу.' },
          ...chatHistory.slice(-10),
        ],
        max_tokens: 1000,
      }),
    });

    const data = await res.json();
    _removeTyping(typingId, msgs);

    const reply = data.choices?.[0]?.message?.content || '⚠️ Нет ответа от AI';
    chatHistory.push({ role: 'assistant', content: reply });
    _appendChatMsg(msgs, 'ai', reply);

  } catch (e) {
    _removeTyping(typingId, msgs);
    _appendChatMsg(msgs, 'ai', '❌ Ошибка: ' + e.message);
  }
}

function _appendChatMsg(container, type, text) {
  const div = document.createElement('div');
  div.className = `chat-msg ${type}`;
  const time = new Date().toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
  div.innerHTML = `
    <div class="chat-avatar">${type === 'ai' ? '🤖' : '👤'}</div>
    <div>
      <div class="chat-bubble">${text.replace(/\n/g, '<br>')}</div>
      <div class="chat-time">${time}</div>
    </div>`;
  container.appendChild(div);
  container.scrollTop = container.scrollHeight;
}

function _appendTyping(container) {
  const id  = 'typing_' + Date.now();
  const div = document.createElement('div');
  div.className = 'chat-msg ai';
  div.id = id;
  div.innerHTML = `<div class="chat-avatar">🤖</div><div class="chat-bubble"><div class="typing-indicator"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div></div>`;
  container.appendChild(div);
  container.scrollTop = container.scrollHeight;
  return id;
}

function _removeTyping(id, container) {
  document.getElementById(id)?.remove();
}

window.autoResizeTextarea = el => {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 120) + 'px';
};

async function testApiKey() {
  const key = document.getElementById('setApiKey')?.value;
  if (!key) { notify('Введите API ключ', 'error'); return; }
  notify('Проверяю...', 'info');
  try {
    const res = await fetch('https://api.deepseek.com/v1/chat/completions', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${key}` },
      body: JSON.stringify({ model: 'deepseek-chat', messages: [{ role: 'user', content: 'ping' }], max_tokens: 5 }),
    });
    const data = await res.json();
    if (data.choices) notify('✅ API ключ работает!', 'success');
    else notify('❌ Ключ не работает: ' + JSON.stringify(data), 'error');
  } catch (e) { notify('❌ Ошибка: ' + e.message, 'error'); }
}

window.testApiKey = testApiKey;

window.testTelegram = async () => {
  const res = await Api.notify('test', { message: '🔔 Тест уведомление из PrintCRM' });
  if (res.ok) notify('📤 Тест отправлен в Telegram', 'success');
  else notify('❌ Ошибка: ' + (res.error||''), 'error');
};

// ════════════════════════════════════════════════════════════
//  ВСПОМОГАТЕЛЬНЫЕ УТИЛИТЫ
// ════════════════════════════════════════════════════════════
function nowDTLocal() {
  const n = new Date(), p = v => String(v).padStart(2,'0');
  return `${n.getFullYear()}-${p(n.getMonth()+1)}-${p(n.getDate())}T${p(n.getHours())}:${p(n.getMinutes())}`;
}

function formatDate(str) {
  if (!str) return '—';
  try {
    const d = new Date(str);
    const base = d.toLocaleDateString('ru-RU', { day:'2-digit', month:'2-digit', year:'numeric' });
    return str.includes('T') ? base + ' ' + d.toLocaleTimeString('ru-RU', { hour:'2-digit', minute:'2-digit' }) : base;
  } catch { return str; }
}

function fmt(val) {
  const cur = State.settings?.currency || '₽';
  return (parseFloat(val)||0).toLocaleString('ru-RU') + ' ' + cur;
}

function fmtDateShort(iso) {
  if (!iso) return '—';
  const d = new Date(iso);
  return d.toLocaleString('ru-RU', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' });
}

function escHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Совместимость со старыми вызовами
window.escapeHtml  = escHtml;
window.formatDate  = formatDate;
window.formatMoney = fmt;
window.formatSize  = (bytes) => {
  if (!bytes) return '0 Б';
  if (bytes < 1024)    return bytes + ' Б';
  if (bytes < 1048576) return (bytes/1024).toFixed(1) + ' КБ';
  return (bytes/1048576).toFixed(1) + ' МБ';
};

function getServiceLabel(tab) {
  return { photo:'Фотопечать', copy:'Копирование/Распечатка', banner:'Баннерная печать', design:'Дизайн', business:'Бизнес-полиграфия', wide:'Широкоформатная печать', promo:'Сувенирная продукция', other:'Прочее' }[tab] || tab;
}

function getStatusBadge(status) {
  return `<span class="badge badge-${status}">${KB_STATUS_LABELS[status]||status}</span>`;
}

// Формы — глобальные обёртки
window.switchServiceTab = (tab, btn) => {
  Order.currentTab = tab;
  document.querySelectorAll('.order-service-tab').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  document.querySelectorAll('.service-tab-content').forEach(t => t.classList.remove('active'));
  const tabEl = document.getElementById('stab-' + tab);
  if (tabEl) tabEl.classList.add('active');
};

window.toggleCheck = label => {
  label.classList.toggle('checked');
  const dot   = label.querySelector('.checkbox-dot');
  const input = label.querySelector('input');
  const on    = label.classList.contains('checked');
  if (dot)   dot.textContent = on ? '✓' : '';
  if (input) input.checked   = on;
};

window.selectSize = (btn, type) => {
  const matrix = btn.closest('.size-matrix') || document.getElementById('sizeMatrix-' + type);
  if (matrix) matrix.querySelectorAll('.size-btn').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  if (type === 'banner') {
    const m = btn.textContent.trim().match(/([\d.]+)×([\d.]+)/);
    if (m) {
      const bw = document.getElementById('ban_w');
      const bh = document.getElementById('ban_h');
      if (bw) bw.value = m[1];
      if (bh) bh.value = m[2];
      calcBannerArea();
    }
  }
};

window.calcBannerArea = () => {
  const w    = parseFloat(document.getElementById('ban_w')?.value)     || 0;
  const h    = parseFloat(document.getElementById('ban_h')?.value)     || 0;
  const p    = parseFloat(document.getElementById('ban_price')?.value) || 0;
  const q    = parseInt(document.getElementById('ban_qty')?.value)     || 1;
  const area = (w * h).toFixed(2);
  const aEl  = document.getElementById('ban_area');
  const tEl  = document.getElementById('ord_total');
  if (aEl) aEl.value = area;
  if (tEl) tEl.value = (area * p * q).toFixed(0);
  updateTotalDisplay();
};

window.calcWideArea = () => {
  const w    = (parseFloat(document.getElementById('wide_w')?.value) || 0) / 100;
  const h    = (parseFloat(document.getElementById('wide_h')?.value) || 0) / 100;
  const p    = parseFloat(document.getElementById('wide_price')?.value) || 0;
  const area = (w * h).toFixed(4);
  const aEl  = document.getElementById('wide_area');
  const tEl  = document.getElementById('ord_total');
  if (aEl) aEl.value = parseFloat(area).toFixed(2);
  if (tEl) tEl.value = (parseFloat(area) * p).toFixed(0);
  updateTotalDisplay();
};

window.calcTotal = () => {
  const fields = {
    photo:    ['photo_qty',  'photo_price'],
    copy:     ['copy_qty',   'copy_price'],
    design:   [null,         'des_price'],
    business: ['biz_qty',    'biz_price'],
    promo:    ['promo_qty',  'promo_price'],
    other:    ['other_qty',  'other_price'],
  };
  const pair  = fields[Order.currentTab];
  if (!pair) return;
  const qty   = pair[0] ? (parseInt(document.getElementById(pair[0])?.value) || 0) : 1;
  const price = parseFloat(document.getElementById(pair[1])?.value) || 0;
  const total = qty * price;
  if (total > 0) {
    const tEl = document.getElementById('ord_total');
    if (tEl) tEl.value = total.toFixed(0);
    updateTotalDisplay();
  }
};

window.updateTotalDisplay = () => {
  const val = parseFloat(document.getElementById('ord_total')?.value) || 0;
  const el  = document.getElementById('ordTotalDisplay');
  if (el) el.textContent = fmt(val);
};

// ════════════════════════════════════════════════════════════
//  ЧАСЫ
// ════════════════════════════════════════════════════════════
function updateClock() {
  const n  = new Date(), p = v => String(v).padStart(2,'0');
  const days   = ['Вс','Пн','Вт','Ср','Чт','Пт','Сб'];
  const months = ['янв','фев','мар','апр','май','июн','июл','авг','сен','окт','ноя','дек'];
  const tEl = document.getElementById('clockTime');
  const dEl = document.getElementById('clockDate');
  if (tEl) tEl.textContent = `${p(n.getHours())}:${p(n.getMinutes())}:${p(n.getSeconds())}`;
  if (dEl) dEl.textContent = `${days[n.getDay()]}, ${n.getDate()} ${months[n.getMonth()]}`;
}
setInterval(updateClock, 1000);
updateClock();

// ════════════════════════════════════════════════════════════
//  УВЕДОМЛЕНИЯ
// ════════════════════════════════════════════════════════════
window.notify = (msg, type = 'info') => {
  const icons  = { success:'✅', error:'❌', info:'💡' };
  const stack  = document.getElementById('notifStack');
  if (!stack) return;
  const el = document.createElement('div');
  el.className = `notification ${type}`;
  el.innerHTML = `<span>${icons[type]||'ℹ'}</span><span>${msg}</span>`;
  stack.appendChild(el);
  setTimeout(() => {
    el.style.cssText += 'opacity:0;transform:translateX(20px);transition:all 0.3s;';
    setTimeout(() => el.remove(), 300);
  }, 3500);
};

// ════════════════════════════════════════════════════════════
//  BEFOREUNLOAD
// ════════════════════════════════════════════════════════════
window.addEventListener('beforeunload', () => {
  Sync.stopPolling();
});

// ════════════════════════════════════════════════════════════
//  СТАРТ ПРИЛОЖЕНИЯ
// ════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', async () => {
  // Установить месяц в фильтр финансов
  const monthFilter = document.getElementById('finMonthFilter');
  if (monthFilter) {
    const now = new Date();
    monthFilter.value = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}`;
  }

  // Загрузить данные
  await Sync.loadAll();

  // Запустить поллинг
  Sync.startPolling();

  // Показать дашборд
  App.showPage('dashboard', document.getElementById('nav-dashboard'));

  console.log('🚀 PrintCRM v3.0 запущен');
});