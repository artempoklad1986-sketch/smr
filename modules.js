// ============================================================
//  modules.js — модульная система v4.2
//  ✅ Артемий — уведомления клиентам в МАКс
//  ✅ ВКонтакте Бот — уведомления клиентам в ВК
// ============================================================

function _waitForConfig(cb, attempts) {
  attempts = attempts || 0;
  if (typeof API_URL !== 'undefined' && typeof API_KEY !== 'undefined') {
    cb();
    return;
  }
  if (attempts > 40) {
    console.error('modules.js: API_URL/API_KEY не найдены после ожидания');
    return;
  }
  setTimeout(function() { _waitForConfig(cb, attempts + 1); }, 150);
}

var MODULES = {
  warehouse: {
    enabled: false,
    icon:    '📦',
    name:    'Склад и материалы',
    desc:    'Бумага, чернила, расходники'
  },
  staff: {
    enabled: false,
    icon:    '👥',
    name:    'Сотрудники и смены',
    desc:    'PIN авторизация, табель'
  },
  telegram: {
    enabled: false,
    icon:    '📱',
    name:    'Telegram уведомления',
    desc:    'Клиентам и директору'
  },
  calendar: {
    enabled: false,
    icon:    '📅',
    name:    'Календарь производства',
    desc:    'Дедлайны и задачи'
  },
  artemiy_bot: {
    enabled: true,
    icon:    '🤖',
    name:    'Артемий — клиентский бот',
    desc:    'Уведомления клиентам в МАКс'
  },
  vk_bot: {
    enabled: true,
    icon:    '💙',
    name:    'ВКонтакте Бот',
    desc:    'Уведомления и заказы через ВКонтакте'
  },
};

function loadModuleSettings() {
  CRM.api('settings', 'get')
    .then(function(res) {
      var mods = (res && res.data && res.data.modules) ? res.data.modules : {};
      Object.keys(MODULES).forEach(function(name) {
        if (mods[name] !== undefined) {
          MODULES[name].enabled = !!mods[name];
        }
      });
      renderModulesGrid();
    })
    .catch(function() {
      renderModulesGrid();
    });
}

function toggleModule(name, enabled) {
  if (!MODULES[name]) return;
  MODULES[name].enabled = enabled;
  CRM.api('settings', 'set', { modules: _buildModulesMap() })
    .then(function() {
      renderModulesGrid();
      notify(
        'Модуль «' + MODULES[name].name + '» ' + (enabled ? 'включён ✅' : 'выключен'),
        enabled ? 'success' : 'info'
      );
    })
    .catch(function() {
      notify('Ошибка сохранения модуля', 'error');
    });
}

function _buildModulesMap() {
  var map = {};
  Object.keys(MODULES).forEach(function(k) { map[k] = MODULES[k].enabled; });
  return map;
}

function renderModulesGrid() {
  var grid = document.getElementById('modulesGrid');
  if (!grid) return;

  var cardsHTML = Object.keys(MODULES).map(function(id) {
    var m = MODULES[id];
    return (
      '<div style="display:flex;align-items:center;justify-content:space-between;padding:14px;' +
      'background:var(--bg-dark);border-radius:12px;border:1px solid var(--border);">' +
        '<div style="display:flex;gap:10px;align-items:center;">' +
          '<div style="font-size:1.5rem;">' + m.icon + '</div>' +
          '<div>' +
            '<div style="font-weight:700;font-size:0.9rem;">' + m.name + '</div>' +
            '<div class="text-xs text-muted">' + m.desc + '</div>' +
            '<button class="btn btn-secondary btn-xs mt-8" ' +
              'onclick="testModule(\'' + id + '\')">🧪 Проверить</button>' +
          '</div>' +
        '</div>' +
        '<div id="toggle-' + id + '" ' +
          'class="toggle-switch ' + (m.enabled ? 'on' : '') + '" ' +
          'onclick="toggleModule(\'' + id + '\',' + (!m.enabled) + ')">' +
          '<div class="toggle-thumb"></div>' +
        '</div>' +
      '</div>'
    );
  }).join('');

  CRM.api('settings', 'get').then(function(res) {
    var s = (res && res.data) ? res.data : {};

    grid.innerHTML = cardsHTML +

      '<div style="padding:14px;background:var(--bg-dark);border-radius:12px;' +
      'border:1px solid var(--border);grid-column:1/-1;">' +
        '<div style="font-weight:700;font-size:0.85rem;margin-bottom:8px;">📱 Telegram настройки</div>' +
        '<div style="display:flex;gap:8px;flex-wrap:wrap;">' +
          '<input class="form-input" id="set_tgToken" placeholder="Bot Token" ' +
            'style="flex:1;min-width:200px;font-size:0.8rem;" ' +
            'value="' + _esc(s.tgToken || '') + '">' +
          '<input class="form-input" id="set_tgBossId" placeholder="Chat ID директора" ' +
            'style="flex:1;min-width:160px;font-size:0.8rem;" ' +
            'value="' + _esc(s.tgBossId || '') + '">' +
          '<button class="btn btn-secondary btn-sm" ' +
            'onclick="saveTelegramSettings()">💾 Сохранить</button>' +
        '</div>' +
      '</div>' +

      '<div style="padding:12px;background:var(--bg-dark);border-radius:10px;' +
      'border:1px solid var(--border);grid-column:1/-1;">' +
        '<div class="text-xs text-muted" style="margin-bottom:8px;font-weight:700;' +
          'text-transform:uppercase;letter-spacing:1px;">Статус подключения</div>' +
        '<div id="modulesStatusList" style="display:flex;gap:8px;flex-wrap:wrap;"></div>' +
      '</div>';
  });
}

function saveTelegramSettings() {
  var tgT = document.getElementById('set_tgToken');
  var tgB = document.getElementById('set_tgBossId');
  CRM.api('settings', 'set', {
    tgToken:  tgT ? tgT.value.trim() : '',
    tgBossId: tgB ? tgB.value.trim() : '',
  }).then(function() {
    notify('Telegram настройки сохранены', 'success');
  }).catch(function() {
    notify('Ошибка сохранения Telegram настроек', 'error');
  });
}

function testModule(name) {
  notify('Проверяю модуль ' + name + '...', 'info');
  CRM.api(name, 'list')
    .then(function(json) {
      if (json && (json.ok !== undefined || json.data !== undefined)) {
        notify('✅ Модуль «' + name + '» работает!', 'success');
      } else {
        notify('❌ Модуль «' + name + '»: нет ответа', 'error');
      }
      updateModulesStatus();
    })
    .catch(function(e) {
      notify('❌ Модуль «' + name + '» недоступен: ' + e.message, 'error');
    });
}

function updateModulesStatus() {
  var list = document.getElementById('modulesStatusList');
  if (!list) return;

  var promises = Object.keys(MODULES).map(function(name) {
    return CRM.api(name, 'list')
      .then(function(j) {
        return { name: name, ok: !!(j && (j.ok !== undefined || j.data !== undefined)) };
      })
      .catch(function() {
        return { name: name, ok: false };
      });
  });

  Promise.all(promises).then(function(results) {
    list.innerHTML = results.map(function(r) {
      var enabled  = MODULES[r.name] && MODULES[r.name].enabled;
      var colorOk  = r.ok ? 'rgba(16,185,129,0.3)' : 'rgba(239,68,68,0.3)';
      var bgOk     = r.ok ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)';
      var dotColor = r.ok ? 'var(--accent3)' : 'var(--danger)';
      return (
        '<div style="display:flex;align-items:center;gap:6px;padding:4px 10px;' +
          'border:1px solid ' + colorOk + ';background:' + bgOk + ';' +
          'border-radius:8px;font-size:0.75rem;">' +
          '<span style="color:' + dotColor + ';">' + (r.ok ? '●' : '○') + '</span>' +
          '<span style="font-weight:600;">' + r.name + '</span>' +
          '<span style="color:var(--text-muted);">' + (r.ok ? 'OK' : 'Нет') + '</span>' +
          (enabled ? '<span style="color:var(--accent2);">вкл</span>' : '') +
        '</div>'
      );
    }).join('');
  });
}

/* ============================================================
   CRM ENGINE
============================================================ */
var CRM = {

  modules: {},
  _cache:  null,

  _getCache: function() {
    if (typeof getDB === 'function') return getDB();
    if (!this._cache) {
      this._cache = {
        orders: [], finance: [], clients: [], notes: [],
        warehouse: [], calEvents: [], staff: [], staffLog: [],
        salary: { records: [], employees: [], shifts: [] },
        settings: {}
      };
    }
    return this._cache;
  },

  _saveCache: function(db) {
    if (typeof saveDB === 'function') saveDB(db);
    else this._cache = db;
  },

  api: function(module, action, body, params) {
    body   = body   || null;
    params = params || {};
    var local = this._handleLocal(module, action, body, params);
    if (local !== null) return Promise.resolve(local);
    return this._fetchServer(module, action, body, params);
  },

  _fetchServer: function(module, action, body, params) {
    var qs = new URLSearchParams(
      Object.assign({ module: module, action: action, key: API_KEY }, params)
    ).toString();

    return fetch(API_URL + '?' + qs, {
      method:  body ? 'POST' : 'GET',
      headers: (typeof apiHeaders !== 'undefined') ? apiHeaders : { 'Content-Type': 'application/json' },
      body:    body ? JSON.stringify(body) : undefined,
    })
    .then(function(r) {
      var status = r.status;
      return r.text().then(function(raw) {
        if (!raw || raw.trim() === '' || raw.trim() === 'null') {
          return { ok: false, data: [], error: 'empty' };
        }
        if (status >= 400) {
          console.warn('CRM.api(' + module + ',' + action + ') HTTP ' + status);
          return { ok: false, data: [], error: 'HTTP ' + status };
        }
        var start = -1;
        for (var i = 0; i < raw.length; i++) {
          if (raw[i] === '{' || raw[i] === '[') { start = i; break; }
        }
        if (start === -1) {
          console.warn('CRM.api(' + module + '/' + action + ') не JSON:', raw.slice(0, 200));
          return { ok: false, data: [], error: 'not json' };
        }
        var opener = raw[start];
        var closer = opener === '{' ? '}' : ']';
        var depth  = 0;
        var inStr  = false;
        var escape = false;
        var end    = -1;
        for (var j = start; j < raw.length; j++) {
          var ch = raw[j];
          if (escape)               { escape = false; continue; }
          if (ch === '\\' && inStr) { escape = true;  continue; }
          if (ch === '"')           { inStr = !inStr;  continue; }
          if (inStr)                continue;
          if (ch === opener)        depth++;
          if (ch === closer) {
            depth--;
            if (depth === 0) { end = j; break; }
          }
        }
        if (end === -1) {
          console.warn('CRM.api(' + module + '/' + action + ') незакрытый JSON');
          return { ok: false, data: [], error: 'unclosed json' };
        }
        try {
          return JSON.parse(raw.slice(start, end + 1));
        } catch(e) {
          console.warn('CRM.api(' + module + '/' + action + ') парсинг:', e.message);
          return { ok: false, data: [], error: e.message };
        }
      });
    })
    .catch(function(e) {
      console.warn('CRM.api(' + module + '/' + action + ') сервер недоступен:', e.message);
      return { ok: false, data: [], error: e.message };
    });
  },

  _handleLocal: function(module, action, body, params) {
    var db   = this._getCache();
    var self = this;

    /* ---- WAREHOUSE ---- */
    if (module === 'warehouse') {
      if (action === 'list') {
        var items = (db.warehouse || []).map(function(i) {
          return Object.assign({}, i, { isLow: parseFloat(i.qty) <= parseFloat(i.minQty) });
        });
        return { ok: true, data: items };
      }
      if (action === 'add' && body) {
        if (!db.warehouse) db.warehouse = [];
        var item = {
          id:        'wh_' + Date.now(),
          name:      body.name      || '',
          category:  body.category  || 'Прочее',
          unit:      body.unit      || 'шт',
          qty:       parseFloat(body.qty)    || 0,
          minQty:    parseFloat(body.minQty) || 0,
          price:     parseFloat(body.price)  || 0,
          added:     new Date().toISOString(),
          movements: []
        };
        db.warehouse.push(item);
        self._saveCache(db);
        self._syncDB(db);
        return { ok: true, id: item.id };
      }
      if (action === 'restock' && body) {
        var wItem = (db.warehouse || []).find(function(i) { return i.id === body.id; });
        if (!wItem) return { ok: false, error: 'Не найдено' };
        wItem.qty = parseFloat(wItem.qty) + parseFloat(body.qty);
        if (!wItem.movements) wItem.movements = [];
        wItem.movements.push({ type: 'restock', qty: body.qty, date: new Date().toISOString() });
        self._saveCache(db);
        self._syncDB(db);
        return { ok: true };
      }
      if (action === 'deduct' && body) {
        var dItem = (db.warehouse || []).find(function(i) { return i.id === body.id; });
        if (!dItem) return { ok: false, error: 'Не найдено' };
        if (parseFloat(dItem.qty) < parseFloat(body.qty)) return { ok: false, error: 'Недостаточно остатка' };
        dItem.qty = parseFloat(dItem.qty) - parseFloat(body.qty);
        if (!dItem.movements) dItem.movements = [];
        dItem.movements.push({ type: 'deduct', qty: body.qty, date: new Date().toISOString() });
        var isLow = parseFloat(dItem.qty) <= parseFloat(dItem.minQty);
        self._saveCache(db);
        self._syncDB(db);
        return { ok: true, alert: isLow ? '«' + dItem.name + '» заканчивается! Остаток: ' + dItem.qty : null };
      }
      if (action === 'delete') {
        var delId = (body && body.id) || params.id;
        db.warehouse = (db.warehouse || []).filter(function(i) { return i.id !== delId; });
        self._saveCache(db);
        self._syncDB(db);
        return { ok: true };
      }
      if (action === 'movements') {
        var movs = [];
        (db.warehouse || []).forEach(function(itm) {
          (itm.movements || []).forEach(function(m) {
            movs.push({ date: m.date, type: m.type, name: itm.name, qty: m.qty, unit: itm.unit });
          });
        });
        movs.sort(function(a, b) { return new Date(b.date) - new Date(a.date); });
        return { ok: true, data: movs };
      }
    }

    /* ---- STAFF ---- */
    if (module === 'staff') {
      if (action === 'list') {
        var safeStaff = (db.staff || []).map(function(s) {
          return { id: s.id, name: s.name, role: s.role, phone: s.phone, color: s.color, created: s.created };
        });
        return { ok: true, data: safeStaff };
      }
      if (action === 'add' && body) {
        if (!body.name) return { ok: false, error: 'Нет имени' };
        if (!body.pin || body.pin.length < 4) return { ok: false, error: 'PIN минимум 4 цифры' };
        if (!db.staff) db.staff = [];
        var pinHash = _hashPin(body.pin, body.name);
        var member  = {
          id:      'st_' + Date.now(),
          name:    body.name  || '',
          pinHash: pinHash,
          role:    body.role  || 'Менеджер',
          phone:   body.phone || '',
          color:   body.color || '#7c3aed',
          created: new Date().toISOString()
        };
        db.staff.push(member);
        if (!db.staffLog) db.staffLog = [];
        db.staffLog.unshift({ date: new Date().toISOString(), staffName: body.name, action: 'Добавлен', data: body.role || '' });
        self._saveCache(db);
        self._syncDB(db);
        return { ok: true, id: member.id };
      }
      if (action === 'delete') {
        var stDelId  = (body && body.id) || params.id;
        var stMember = (db.staff || []).find(function(s) { return s.id === stDelId; });
        db.staff = (db.staff || []).filter(function(s) { return s.id !== stDelId; });
        if (stMember) {
          if (!db.staffLog) db.staffLog = [];
          db.staffLog.unshift({ date: new Date().toISOString(), staffName: stMember.name, action: 'Удалён', data: '' });
        }
        self._saveCache(db);
        self._syncDB(db);
        return { ok: true };
      }
      if (action === 'log') {
        return { ok: true, data: db.staffLog || [] };
      }
      if (action === 'verify' && body) {
        var vMember = (db.staff || []).find(function(s) { return s.name === body.name; });
        if (!vMember) return { ok: false, error: 'Не найден' };
        var vHash = _hashPin(body.pin, body.name);
        if (vHash === vMember.pinHash) {
          return { ok: true, staff: { id: vMember.id, name: vMember.name, role: vMember.role, color: vMember.color } };
        }
        return { ok: false, error: 'Неверный PIN' };
      }
    }

    /* ---- CALENDAR ---- */
    if (module === 'calendar') {
      if (action === 'list') {
        var events = db.calEvents || [];
        if (params.from && params.to) {
          events = events.filter(function(e) { return e.date >= params.from && e.date <= params.to; });
        }
        return { ok: true, data: events };
      }
      if (action === 'add' && body) {
        if (!db.calEvents) db.calEvents = [];
        var calEvent = {
          id:    'cal_' + Date.now(),
          title: body.title || '',
          date:  body.date  || '',
          time:  body.time  || '',
          type:  body.type  || 'task',
          color: body.color || '#7c3aed',
          note:  body.note  || ''
        };
        db.calEvents.push(calEvent);
        self._saveCache(db);
        self._syncDB(db);
        return { ok: true, id: calEvent.id };
      }
      if (action === 'delete') {
        var calDelId = (body && body.id) || params.id;
        db.calEvents = (db.calEvents || []).filter(function(e) { return e.id !== calDelId; });
        self._saveCache(db);
        self._syncDB(db);
        return { ok: true };
      }
    }

    /* ---- ORDERS ---- */
    if (module === 'orders') {
      if (action === 'list') {
        var orders = db.orders || [];
        if (params.status) orders = orders.filter(function(o) { return o.status === params.status; });
        if (params.client) orders = orders.filter(function(o) { return o.client === params.client; });
        return { ok: true, data: orders };
      }
      if (action === 'get' && params.id) {
        var foundOrder = (db.orders || []).find(function(o) { return String(o.id) === String(params.id); });
        return foundOrder ? { ok: true, data: foundOrder } : { ok: false, error: 'Не найдено' };
      }
      if (action === 'updateStatus' && body) {
        var updOrder = (db.orders || []).find(function(o) { return o.id === body.id; });
        if (!updOrder) return { ok: false, error: 'Не найдено' };
        updOrder.status = body.status;
        self._saveCache(db);
        self._syncDB(db);
        return { ok: true };
      }
    }

    /* ---- CLIENTS ---- */
    if (module === 'clients') {
      if (action === 'list') return { ok: true, data: db.clients || [] };
      if (action === 'search' && params.q) {
        var q = params.q.toLowerCase();
        var found = (db.clients || []).filter(function(c) {
          return c.name.toLowerCase().includes(q) || (c.phone || '').includes(q);
        });
        return { ok: true, data: found };
      }
      if (action === 'get' && params.id) {
        var foundClient = (db.clients || []).find(function(c) { return String(c.id) === String(params.id); });
        return foundClient ? { ok: true, data: foundClient } : { ok: false, error: 'Не найдено' };
      }
    }

    /* ---- FINANCE ---- */
    if (module === 'finance') {
      if (action === 'list') {
        var fin = db.finance || [];
        if (params.type) fin = fin.filter(function(f) { return f.type === params.type; });
        return { ok: true, data: fin };
      }
    }

    /* ---- SETTINGS ---- */
    if (module === 'settings') {
      if (action === 'get') return { ok: true, data: db.settings || {} };
      if (action === 'set' && body) {
        db.settings = Object.assign({}, db.settings || {}, body);
        self._saveCache(db);
        return self._fetchServer('settings', 'set', body, {});
      }
    }

    /* ---- TELEGRAM ---- */
    if (module === 'telegram') {
      if (action === 'list') {
        var ts = db.settings || {};
        return { ok: true, data: { configured: !!(ts.tgToken && ts.tgBossId) } };
      }
      if (action === 'send' && body && body.text) {
        _sendTelegramInternal(body.text);
        return { ok: true };
      }
    }

    /* ---- ARTEMIY_BOT ---- */
    if (module === 'artemiy_bot') {
      if (action === 'list') {
        return { ok: true, data: { enabled: MODULES.artemiy_bot.enabled } };
      }
    }

    /* ---- VK_BOT ---- */
    if (module === 'vk_bot') {
      if (action === 'list') {
        return { ok: true, data: { enabled: MODULES.vk_bot.enabled } };
      }
    }

    /* ---- DEBTS — только сервер ---- */
    if (module === 'debts') return null;

    return null;
  },

  _syncTimer: null,

  _syncDB: function(db) {
    var self = this;
    clearTimeout(self._syncTimer);
    self._syncTimer = setTimeout(function() {
      fetch(API_URL + '?action=db&key=' + encodeURIComponent(API_KEY), {
        method:  'POST',
        headers: (typeof apiHeaders !== 'undefined') ? apiHeaders : { 'Content-Type': 'application/json' },
        body:    JSON.stringify(db)
      })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        if (!res || !res.ok) console.warn('modules._syncDB: сервер вернул ошибку', res);
      })
      .catch(function(e) {
        console.warn('modules._syncDB: ошибка синхронизации:', e.message);
      });
    }, 800);
  },

  getData: function(module, params) {
    return this.api(module, 'list', null, params || {}).then(function(res) {
      return (res && res.data) ? res.data : [];
    });
  },

  save:   function(module, body) { return this.api(module, 'add', body); },
  remove: function(module, id)   { return this.api(module, 'delete', { id: id }); },

  notify: function(msg, type) {
    if (typeof window.notify === 'function') window.notify(msg, type || 'info');
  },

  formatMoney: function(val) {
    if (typeof window.formatMoney === 'function') return window.formatMoney(val);
    return (parseFloat(val) || 0).toLocaleString('ru-RU') + ' ₽';
  },

  formatDate: function(str) {
    if (typeof window.formatDate === 'function') return window.formatDate(str);
    if (!str) return '—';
    try { return new Date(str).toLocaleDateString('ru-RU'); } catch(e) { return str; }
  },

  getSettings: function() { return this._getCache().settings || {}; },

  openModal:  function(id) { if (typeof window.openModal  === 'function') window.openModal(id);  },
  closeModal: function(id) { if (typeof window.closeModal === 'function') window.closeModal(id); },

  registerModule: function(config) {
    this.modules[config.id] = config;
    this._addToSidebar(config);
    this._addPage(config);
    console.log('✅ Модуль «' + config.name + '» зарегистрирован');
  },

  _addToSidebar: function(config) {
    var section = document.getElementById('modules-sidebar-section');
    if (!section) {
      section = document.createElement('div');
      section.id = 'modules-sidebar-section';
      section.innerHTML = '<div class="sidebar-section-label">Доп. модули</div>';
      var sidebar = document.querySelector('.sidebar');
      if (sidebar) sidebar.appendChild(section);
    }
    if (document.getElementById('nav-' + config.id)) return;
    var btn       = document.createElement('button');
    btn.className = 'nav-btn';
    btn.id        = 'nav-' + config.id;
    var self      = this;
    btn.onclick   = function() { self.showModulePage(config.id, btn); };
    btn.innerHTML = '<span style="font-size:1rem;">' + (config.icon || '🔧') + '</span> ' + config.name;
    section.appendChild(btn);
  },

  _addPage: function(config) {
    var existing = document.getElementById('page-' + config.id);
    if (existing) {
      if (!existing.innerHTML.trim()) {
        existing.innerHTML = config.page || this._defaultPageHTML(config);
      }
      return;
    }
    var main = document.querySelector('.main');
    if (!main) return;
    var page       = document.createElement('div');
    page.className = 'page';
    page.id        = 'page-' + config.id;
    page.innerHTML = config.page || this._defaultPageHTML(config);
    main.appendChild(page);
  },

  _defaultPageHTML: function(config) {
    return (
      '<div class="page-header">' +
        '<div class="page-title">' + (config.icon || '🔧') + ' ' + config.name + '</div>' +
      '</div>' +
      '<div class="card"><div class="empty-state">' +
        '<div class="icon">' + (config.icon || '🔧') + '</div>' +
        '<div class="title">Модуль «' + config.name + '» загружен</div>' +
      '</div></div>'
    );
  },

  showModulePage: function(id, btn) {
    document.querySelectorAll('.page').forEach(function(p) {
      p.classList.remove('active');
    });
    document.querySelectorAll('.nav-btn').forEach(function(b) {
      b.classList.remove('active');
    });
    var page = document.getElementById('page-' + id);
    if (!page) {
      var mod = this.modules[id];
      if (mod) {
        this._addPage(mod);
        page = document.getElementById('page-' + id);
      }
    }
    if (page) page.classList.add('active');
    if (btn)  btn.classList.add('active');
    var mod = this.modules[id];
    if (mod && typeof mod.render === 'function') {
      Promise.resolve()
        .then(function() { return mod.render(); })
        .catch(function(e) {
          console.error('Ошибка рендера модуля ' + id + ':', e);
          if (typeof window.notify === 'function') {
            window.notify('Ошибка модуля «' + id + '»: ' + e.message, 'error');
          }
        });
    }
  },

  loadAll: function() {
    return fetch(
      API_URL + '?action=registry&key=' + encodeURIComponent(API_KEY),
      { headers: (typeof apiHeaders !== 'undefined') ? apiHeaders : {} }
    )
    .then(function(res) { return res.text(); })
    .then(function(raw) {
      if (!raw || raw.trim() === 'null' || raw.trim() === '') return;
     // ПРАВИЛЬНО
var match = raw.match(/(\{[\s\S]*\}|$$[\s\S]*$$)/);
      if (!match) return;
      var json;
      try { json = JSON.parse(match[0]); } catch(e) { return; }
      var list  = json.modules || [];
      var chain = Promise.resolve();
      list.forEach(function(mod) {
        chain = chain.then(function() { return CRM._loadModuleJS(mod.id); });
      });
      return chain.then(function() {
        if (list.length) console.log('🚀 Загружено доп. модулей: ' + list.length);
      });
    })
    .catch(function(e) {
      console.info('Registry не настроен (это нормально):', e.message);
    });
  },

  _loadModuleJS: function(moduleId) {
    return fetch(
      API_URL + '?module=' + encodeURIComponent(moduleId) +
      '&action=__getjs__&key=' + encodeURIComponent(API_KEY)
    )
    .then(function(res) {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return res.text();
    })
    .then(function(text) {
      var m = text.match(/<script>([\s\S]*?)<\/script>/i);
      if (m && m[1]) {
        var script         = document.createElement('script');
        script.textContent = m[1];
        document.head.appendChild(script);
      }
    })
    .catch(function(e) {
      console.warn('Ошибка загрузки JS модуля ' + moduleId + ':', e.message);
    });
  }
};

/* ============================================================
   ХЕШИРОВАНИЕ PIN
============================================================ */
function _hashPin(pin, name) {
  var str  = String(pin) + '::' + String(name).slice(0, 4).toLowerCase();
  var hash = 0;
  for (var i = 0; i < str.length; i++) {
    hash = ((hash << 5) - hash) + str.charCodeAt(i);
    hash = hash & hash;
  }
  return 'h' + Math.abs(hash).toString(16);
}

/* ============================================================
   ESCAPE HTML
============================================================ */
function _esc(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

function escapeHtml(str) { return _esc(str); }

function formatSize(bytes) {
  if (!bytes) return '0 Б';
  if (bytes < 1024)    return bytes + ' Б';
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' КБ';
  return (bytes / 1048576).toFixed(1) + ' МБ';
}

/* ============================================================
   МОДАЛ ДЕТАЛЕЙ ЗАКАЗА
============================================================ */
function _ensureOrderDetailModal() {
  if (document.getElementById('orderDetailModal')) return;
  var div = document.createElement('div');
  div.innerHTML =
    '<div id="orderDetailModal" class="modal-overlay" style="display:none;">' +
      '<div class="modal" style="max-width:700px;max-height:90vh;overflow-y:auto;">' +
        '<div class="modal-header">' +
          '<div class="modal-title" id="orderDetailTitle">Детали заказа</div>' +
          '<button class="modal-close" onclick="closeModal(\'orderDetailModal\')">×</button>' +
        '</div>' +
        '<div class="modal-body" id="orderDetailBody"><div class="loading">Загрузка...</div></div>' +
        '<div class="modal-footer">' +
          '<button class="btn btn-secondary" onclick="closeModal(\'orderDetailModal\')">Закрыть</button>' +
        '</div>' +
      '</div>' +
    '</div>';
  document.body.appendChild(div.firstChild);
}

function _renderOrderDetailBody(order) {
  var body = document.getElementById('orderDetailBody');
  if (!body) return;

  var statusLabels = { new:'Новый', work:'В работе', ready:'Готов', done:'Выдан', cancel:'Отменён' };
  var statusColors = {
    new:'var(--accent)', work:'var(--accent2)', ready:'var(--accent3)',
    done:'var(--success)', cancel:'var(--danger)'
  };

  var filesHTML = '';
  if (order.files && order.files.length) {
    filesHTML =
      '<div style="margin-top:16px;">' +
        '<div style="font-weight:700;margin-bottom:8px;">📎 Файлы заказа (' + order.files.length + '):</div>' +
        '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:8px;">';
    for (var i = 0; i < order.files.length; i++) {
      var file    = order.files[i];
      var isImage = file.type && file.type.startsWith('image/');
      var fileUrl = file.url || 'https://принтсс.рф/uploads/' + (file.name || file.filename || '');
      filesHTML += '<div style="border:1px solid var(--border);border-radius:8px;overflow:hidden;background:var(--bg-dark);">';
      if (isImage && fileUrl) {
        filesHTML +=
          '<div style="aspect-ratio:1;background:#1a1a1a;display:flex;align-items:center;justify-content:center;">' +
            '<img src="' + fileUrl + '" style="max-width:100%;max-height:100%;object-fit:contain;" onerror="this.style.display=\'none\'">' +
          '</div>';
      } else {
        filesHTML += '<div style="aspect-ratio:1;display:flex;align-items:center;justify-content:center;font-size:2rem;">📄</div>';
      }
      filesHTML +=
          '<div style="padding:6px;font-size:0.7rem;text-align:center;">' +
            '<div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + _esc(file.name || 'файл') + '">' + _esc(file.name || 'файл') + '</div>' +
            '<div style="font-size:0.6rem;color:var(--text-muted);">' + formatSize(file.size) + '</div>' +
            (fileUrl ? '<a href="' + fileUrl + '" target="_blank" class="btn btn-primary btn-xs" style="margin-top:4px;display:inline-block;">⬇ Скачать</a>' : '') +
          '</div>' +
        '</div>';
    }
    filesHTML +=
        '</div>' +
        '<button class="btn btn-primary btn-sm" style="margin-top:12px;" ' +
          'onclick="downloadAllOrderFiles(' + order.id + ')">📦 Скачать все (ZIP)</button>' +
      '</div>';
  } else {
    filesHTML = '<div style="margin-top:16px;"><div style="font-weight:700;">📎 Файлы:</div><div class="text-muted">Нет приложенных файлов</div></div>';
  }

  var fmtMoney = typeof formatMoney === 'function' ? formatMoney : CRM.formatMoney.bind(CRM);
  var fmtDate  = typeof formatDate  === 'function' ? formatDate  : CRM.formatDate.bind(CRM);

  body.innerHTML =
    '<div style="max-height:70vh;overflow-y:auto;padding-right:8px;">' +
      '<div style="margin-bottom:10px;"><span style="font-weight:600;width:100px;display:inline-block;">Номер:</span>'     + _esc(order.num || order.id) + '</div>' +
      '<div style="margin-bottom:10px;"><span style="font-weight:600;width:100px;display:inline-block;">Клиент:</span>'    + _esc(order.client) + '</div>' +
      '<div style="margin-bottom:10px;"><span style="font-weight:600;width:100px;display:inline-block;">Телефон:</span>'   + (order.phone || '—') + '</div>' +
      '<div style="margin-bottom:10px;"><span style="font-weight:600;width:100px;display:inline-block;">Услуга:</span>'    + _esc(order.serviceLabel || order.service || '—') + '</div>' +
      '<div style="margin-bottom:10px;"><span style="font-weight:600;width:100px;display:inline-block;">Размер:</span>'    + _esc(order.size || '—') + '</div>' +
      '<div style="margin-bottom:10px;"><span style="font-weight:600;width:100px;display:inline-block;">Статус:</span>' +
        '<span style="background:' + (statusColors[order.status] || 'var(--text-muted)') + ';padding:2px 8px;border-radius:12px;font-size:0.75rem;">' +
          (statusLabels[order.status] || order.status) +
        '</span>' +
      '</div>' +
      '<div style="margin-bottom:10px;"><span style="font-weight:600;width:100px;display:inline-block;">Сумма:</span>'     + fmtMoney(order.total) + '</div>' +
      (order.deadline ? '<div style="margin-bottom:10px;"><span style="font-weight:600;width:100px;display:inline-block;">Дедлайн:</span>'     + fmtDate(order.deadline) + '</div>' : '') +
      (order.comment  ? '<div style="margin-bottom:10px;"><span style="font-weight:600;width:100px;display:inline-block;">Комментарий:</span>' + _esc(order.comment)      + '</div>' : '') +
      filesHTML +
    '</div>';
}

window.openOrderDetails = function(id) {
  var db    = CRM._getCache();
  var order = (db.orders || []).find(function(o) { return String(o.id) === String(id); });
  if (order) {
    _ensureOrderDetailModal();
    _renderOrderDetailBody(order);
    if (typeof openModal === 'function') openModal('orderDetailModal');
    return;
  }
  if (typeof notify === 'function') notify('Заказ не найден', 'error');
};

/* ============================================================
   СКАЧИВАНИЕ ФАЙЛОВ В ZIP
============================================================ */
window.downloadAllOrderFiles = async function(orderId) {
  var db    = CRM._getCache();
  var order = (db.orders || []).find(function(o) { return o.id === orderId; });
  if (!order || !order.files || !order.files.length) {
    if (typeof notify === 'function') notify('Нет файлов для скачивания', 'error');
    return;
  }
  if (typeof notify === 'function') notify('⏳ Создаю архив...', 'info');
  try {
    if (typeof JSZip === 'undefined') {
      await new Promise(function(resolve, reject) {
        var script  = document.createElement('script');
        script.src  = 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js';
        script.onload  = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
      });
    }
    var zip    = new JSZip();
    var loaded = 0;
    for (var i = 0; i < order.files.length; i++) {
      var file    = order.files[i];
      var fileUrl = file.url || 'https://принтсс.рф/uploads/' + (file.name || file.filename || '');
      if (!fileUrl) continue;
      try {
        var response = await fetch(fileUrl);
        if (response.ok) { zip.file(file.name || ('file_' + i), await response.blob()); loaded++; }
      } catch(e) { console.warn('Ошибка загрузки файла:', file.name, e); }
    }
    var content   = await zip.generateAsync({ type: 'blob' });
    var link      = document.createElement('a');
    link.href     = URL.createObjectURL(content);
    link.download = 'order_' + (order.num || order.id) + '_files.zip';
    link.click();
    URL.revokeObjectURL(link.href);
    if (typeof notify === 'function') notify('✅ Архив: ' + loaded + ' файлов', 'success');
  } catch(e) {
    console.error('Ошибка ZIP:', e);
    if (typeof notify === 'function') notify('❌ Ошибка: ' + e.message, 'error');
  }
};

/* ============================================================
   СКЛАД
============================================================ */
function renderWarehouse() {
  var search = document.getElementById('whSearch')       ? document.getElementById('whSearch').value.toLowerCase()  : '';
  var cat    = document.getElementById('whCatFilter')    ? document.getElementById('whCatFilter').value             : '';
  var status = document.getElementById('whStatusFilter') ? document.getElementById('whStatusFilter').value          : '';

  CRM.api('warehouse', 'list').then(function(res) {
    var all   = res.data || [];
    var items = all.slice();
    if (search) items = items.filter(function(i) { return i.name.toLowerCase().includes(search); });
    if (cat)    items = items.filter(function(i) { return i.category === cat; });
    if (status === 'low') items = items.filter(function(i) { return  i.isLow; });
    if (status === 'ok')  items = items.filter(function(i) { return !i.isLow; });

    var low = all.filter(function(i) { return i.isLow; }).length;
    var sum = all.reduce(function(a, b) { return a + (parseFloat(b.qty)||0) * (parseFloat(b.price)||0); }, 0);

    var setEl = function(id, v) { var el = document.getElementById(id); if (el) el.textContent = v; };
    setEl('wh_total', all.length);
    setEl('wh_low',   low);
    setEl('wh_ok',    all.length - low);
    setEl('wh_sum',   CRM.formatMoney(sum));

    var badge = document.getElementById('warehouseLowBadge');
    if (badge) badge.style.display = low > 0 ? '' : 'none';

    var tbody = document.getElementById('warehouseTableBody');
    if (!tbody) return;

    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="8"><div class="empty-state"><div class="icon">📦</div><div class="title">Склад пуст / ничего не найдено</div></div></td></tr>';
      return;
    }

    tbody.innerHTML = items.map(function(i) {
      var safeName = _esc(i.name);
      var safeId   = _esc(i.id);
      return (
        '<tr>' +
          '<td style="font-weight:600;">' + safeName + '</td>' +
          '<td><span class="badge badge-new">' + _esc(i.category) + '</span></td>' +
          '<td style="font-weight:700;color:' + (i.isLow ? 'var(--danger)' : 'var(--accent3)') + ';">' + i.qty + ' ' + i.unit + '</td>' +
          '<td class="text-muted">' + i.minQty + ' ' + i.unit + '</td>' +
          '<td>' + (i.isLow ? '<span class="badge badge-cancel">⚠️ Мало</span>' : '<span class="badge badge-done">✅ Норма</span>') + '</td>' +
          '<td>' + CRM.formatMoney(i.price) + '</td>' +
          '<td style="font-weight:700;">' + CRM.formatMoney(parseFloat(i.qty) * parseFloat(i.price)) + '</td>' +
          '<td><div style="display:flex;gap:4px;">' +
            '<button class="btn btn-success btn-xs" onclick="openWhAction(\'restock\',\'' + safeId + '\',\'' + i.name.replace(/\\/g,'\\\\').replace(/'/g,"\\'") + '\',' + i.qty + ')" title="Пополнить">+</button>' +
            '<button class="btn btn-secondary btn-xs" onclick="openWhAction(\'deduct\',\'' + safeId + '\',\'' + i.name.replace(/\\/g,'\\\\').replace(/'/g,"\\'") + '\',' + i.qty + ')" title="Списать">−</button>' +
            '<button class="btn btn-danger btn-xs" onclick="deleteWarehouseItem(\'' + safeId + '\')" title="Удалить">🗑️</button>' +
          '</div></td>' +
        '</tr>'
      );
    }).join('');
  });
}

function saveWarehouseItem() {
  var name = document.getElementById('wh_name') ? document.getElementById('wh_name').value.trim() : '';
  if (!name) { notify('Введите наименование', 'error'); return; }
  CRM.api('warehouse', 'add', {
    name:     name,
    category: document.getElementById('wh_cat')    ? document.getElementById('wh_cat').value    : 'Прочее',
    unit:     document.getElementById('wh_unit')   ? document.getElementById('wh_unit').value   : 'шт',
    qty:      document.getElementById('wh_qty')    ? document.getElementById('wh_qty').value    : 0,
    minQty:   document.getElementById('wh_minqty') ? document.getElementById('wh_minqty').value : 0,
    price:    document.getElementById('wh_price')  ? document.getElementById('wh_price').value  : 0
  }).then(function(res) {
    if (res && res.ok) {
      notify('✅ Позиция добавлена: ' + name, 'success');
      if (typeof closeModal === 'function') closeModal('warehouseAddModal');
      renderWarehouse();
    } else {
      notify('Ошибка: ' + (res && res.error ? res.error : 'неизвестно'), 'error');
    }
  });
}

function openWhAction(type, id, name, currentQty) {
  var elId  = document.getElementById('wh_action_id');
  var elTyp = document.getElementById('wh_action_type');
  var elQty = document.getElementById('wh_action_qty');
  var elNm  = document.getElementById('whActionItemName');
  var elCur = document.getElementById('whActionCurrentQty');
  var elTit = document.getElementById('whActionTitle');
  if (elId)  elId.value        = id;
  if (elTyp) elTyp.value       = type;
  if (elQty) elQty.value       = '';
  if (elNm)  elNm.textContent  = name;
  if (elCur) elCur.textContent = currentQty;
  if (elTit) elTit.textContent = type === 'restock' ? '📥 Пополнить склад' : '📤 Списать со склада';
  if (typeof openModal === 'function') openModal('warehouseActionModal');
}

function executeWarehouseAction() {
  var id   = document.getElementById('wh_action_id')   ? document.getElementById('wh_action_id').value   : '';
  var type = document.getElementById('wh_action_type') ? document.getElementById('wh_action_type').value : '';
  var qty  = parseFloat(document.getElementById('wh_action_qty') ? document.getElementById('wh_action_qty').value : 0);
  if (!qty || qty <= 0) { notify('Введите количество', 'error'); return; }
  CRM.api('warehouse', type, { id: id, qty: qty }).then(function(res) {
    if (!res || !res.ok) { notify('Ошибка: ' + (res && res.error ? res.error : 'неизвестно'), 'error'); return; }
    if (res.alert) notify('⚠️ ' + res.alert, 'error');
    else notify(type === 'restock' ? '✅ Пополнено' : '✅ Списано', 'success');
    if (typeof closeModal === 'function') closeModal('warehouseActionModal');
    renderWarehouse();
  });
}

function deleteWarehouseItem(id) {
  if (!confirm('Удалить позицию?')) return;
  CRM.api('warehouse', 'delete', { id: id }).then(function() {
    renderWarehouse();
    notify('Позиция удалена', 'info');
  });
}

function showWarehouseMovements() {
  CRM.api('warehouse', 'movements').then(function(res) {
    var movs = res.data || [];
    if (!movs.length) { notify('История движений пуста', 'info'); return; }
    var text = movs.slice(0, 20).map(function(m) {
      return CRM.formatDate(m.date) + ' | ' + (m.type === 'restock' ? '▲' : '▼') + ' ' + m.name + ' — ' + m.qty + ' ' + m.unit;
    }).join('\n');
    alert('📋 Последние 20 движений:\n\n' + text);
  });
}

/* ============================================================
   СОТРУДНИКИ
============================================================ */
function renderStaff() {
  CRM.api('staff', 'list').then(function(res) {
    var staff = res.data || [];
    var grid  = document.getElementById('staffGrid');
    if (!grid) return;
    if (!staff.length) {
      grid.innerHTML = '<div class="empty-state card" style="grid-column:1/-1;"><div class="icon">👥</div><div class="title">Нет сотрудников</div></div>';
    } else {
      grid.innerHTML = staff.map(function(s) {
        return (
          '<div class="card">' +
            '<div style="display:flex;gap:12px;align-items:center;margin-bottom:12px;">' +
              '<div style="width:44px;height:44px;border-radius:12px;background:' + (s.color || 'var(--accent)') + ';' +
                'display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.1rem;">' +
                _esc(s.name.charAt(0)) +
              '</div>' +
              '<div>' +
                '<div style="font-weight:700;">' + _esc(s.name) + '</div>' +
                '<div class="text-xs text-muted">' + _esc(s.role) + '</div>' +
                (s.phone ? '<div class="text-xs text-muted">📞 ' + _esc(s.phone) + '</div>' : '') +
              '</div>' +
            '</div>' +
            '<div style="display:flex;gap:6px;margin-top:10px;">' +
              '<button class="btn btn-danger btn-xs" style="flex:1;" onclick="deleteStaff(\'' + _esc(s.id) + '\')">🗑️ Удалить</button>' +
            '</div>' +
          '</div>'
        );
      }).join('');
    }
    renderStaffLog();
  });
}

function renderStaffLog() {
  CRM.api('staff', 'log').then(function(res) {
    var logs  = res.data || [];
    var tbody = document.getElementById('staffLogBody');
    if (!tbody) return;
    if (!logs.length) {
      tbody.innerHTML = '<tr><td colspan="4"><div class="empty-state"><div class="icon">📋</div><div class="title">Лог пуст</div></div></td></tr>';
      return;
    }
    tbody.innerHTML = logs.slice(0, 20).map(function(l) {
      return '<tr><td>' + CRM.formatDate(l.date) + '</td><td>' + _esc(l.staffName) + '</td><td>' + _esc(l.action) + '</td><td>' + _esc(l.data || '') + '</td></tr>';
    }).join('');
  });
}

function saveStaff() {
  var name = document.getElementById('st_name') ? document.getElementById('st_name').value.trim() : '';
  var pin  = document.getElementById('st_pin')  ? document.getElementById('st_pin').value.trim()  : '';
  if (!name) { notify('Введите имя', 'error'); return; }
  if (pin.length < 4) { notify('PIN — минимум 4 цифры', 'error'); return; }
  if (!/^\d+$/.test(pin)) { notify('PIN должен состоять только из цифр', 'error'); return; }
  CRM.api('staff', 'add', {
    name:  name,
    pin:   pin,
    role:  document.getElementById('st_role')  ? document.getElementById('st_role').value  : 'Менеджер',
    phone: document.getElementById('st_phone') ? document.getElementById('st_phone').value : '',
    color: document.getElementById('st_color') ? document.getElementById('st_color').value : '#7c3aed'
  }).then(function(res) {
    if (res && res.ok) {
      notify('✅ Сотрудник добавлен: ' + name, 'success');
      if (typeof closeModal === 'function') closeModal('staffAddModal');
      renderStaff();
    } else {
      notify('Ошибка: ' + (res && res.error ? res.error : 'неизвестно'), 'error');
    }
  });
}

function deleteStaff(id) {
  if (!confirm('Удалить сотрудника?')) return;
  CRM.api('staff', 'delete', { id: id }).then(function() {
    renderStaff();
    notify('Сотрудник удалён', 'info');
  });
}

/* ============================================================
   КАЛЕНДАРЬ
============================================================ */
var calCurrentDate = new Date();

function renderCalendar() {
  var year  = calCurrentDate.getFullYear();
  var month = calCurrentDate.getMonth();
  var label = calCurrentDate.toLocaleDateString('ru', { month: 'long', year: 'numeric' });
  var calLbl = document.getElementById('calMonthLabel');
  if (calLbl) calLbl.textContent = label;

  var from = year + '-' + String(month + 1).padStart(2, '0') + '-01';
  var to   = year + '-' + String(month + 1).padStart(2, '0') + '-' + new Date(year, month + 1, 0).getDate();

  CRM.api('calendar', 'list', null, { from: from, to: to }).then(function(res) {
    var events = res.data || [];
    var db     = CRM._getCache();

    (db.orders || []).forEach(function(o) {
      if (!o.deadline || o.status === 'done' || o.status === 'cancel') return;
      var d = o.deadline.slice(0, 10);
      if (d >= from && d <= to) {
        events.push({ id: 'loc_' + o.id, title: (o.num || '') + ' — ' + o.client, date: d, type: 'deadline', color: '#ef4444' });
      }
    });

    var grid = document.getElementById('calendarGrid');
    if (!grid) return;

    var dayNames    = ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];
    var firstDay    = new Date(year, month, 1).getDay();
    var offset      = firstDay === 0 ? 6 : firstDay - 1;
    var daysInMonth = new Date(year, month + 1, 0).getDate();
    var today       = new Date().toDateString();

    var html = dayNames.map(function(d) {
      return '<div style="padding:8px;text-align:center;font-size:0.72rem;font-weight:700;color:var(--text-muted);background:var(--bg-card2);border-bottom:1px solid var(--border);">' + d + '</div>';
    }).join('');

    for (var i = 0; i < offset; i++) {
      html += '<div style="min-height:80px;border:1px solid var(--border);background:var(--bg-dark);opacity:0.3;"></div>';
    }

    for (var d = 1; d <= daysInMonth; d++) {
      var dateStr   = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
      var dayEvents = events.filter(function(e) { return e.date === dateStr; });
      var isToday   = new Date(year, month, d).toDateString() === today;
      html +=
        '<div style="min-height:80px;border:1px solid var(--border);padding:6px;' +
          'background:' + (isToday ? 'rgba(124,58,237,0.1)' : 'var(--bg-card)') + ';cursor:pointer;"' +
          ' onclick="quickAddCalEvent(\'' + dateStr + '\')">' +
          '<div style="font-size:0.82rem;font-weight:' + (isToday ? '900' : '600') + ';color:' + (isToday ? 'var(--accent2)' : 'var(--text)') + ';margin-bottom:4px;">' +
            d + (isToday ? ' ←' : '') +
          '</div>' +
          dayEvents.map(function(e) {
            return '<div style="font-size:0.65rem;padding:2px 4px;border-radius:4px;background:' + (e.color||'var(--accent)') + '22;color:' + (e.color||'var(--accent)') + ';border-left:2px solid ' + (e.color||'var(--accent)') + ';margin-bottom:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + _esc(e.title) + '">' + _esc(e.title) + '</div>';
          }).join('') +
        '</div>';
    }
    grid.innerHTML = html;
  });
}

function calPrevMonth() { calCurrentDate.setMonth(calCurrentDate.getMonth() - 1); renderCalendar(); }
function calNextMonth() { calCurrentDate.setMonth(calCurrentDate.getMonth() + 1); renderCalendar(); }

function quickAddCalEvent(date) {
  var elD = document.getElementById('cal_date');
  var elT = document.getElementById('cal_time');
  var elN = document.getElementById('cal_title');
  if (elD) elD.value = date;
  if (elT) elT.value = '';
  if (elN) elN.value = '';
  if (typeof openModal === 'function') openModal('calEventModal');
}

function saveCalEvent() {
  var title = document.getElementById('cal_title') ? document.getElementById('cal_title').value.trim() : '';
  if (!title) { notify('Введите заголовок', 'error'); return; }
  CRM.api('calendar', 'add', {
    title: title,
    date:  document.getElementById('cal_date')  ? document.getElementById('cal_date').value  : '',
    time:  document.getElementById('cal_time')  ? document.getElementById('cal_time').value  : '',
    type:  document.getElementById('cal_type')  ? document.getElementById('cal_type').value  : 'task',
    color: document.getElementById('cal_color') ? document.getElementById('cal_color').value : '#7c3aed',
    note:  document.getElementById('cal_note')  ? document.getElementById('cal_note').value  : ''
  }).then(function(res) {
    if (res && res.ok) {
      notify('✅ Задача добавлена', 'success');
      if (typeof closeModal === 'function') closeModal('calEventModal');
      renderCalendar();
    } else {
      notify('Ошибка: ' + (res && res.error ? res.error : 'неизвестно'), 'error');
    }
  });
}

function deleteCalEvent(id) {
  if (!confirm('Удалить задачу?')) return;
  CRM.api('calendar', 'delete', { id: id }).then(function() { renderCalendar(); });
}

/* ============================================================
   TELEGRAM
============================================================ */
function _sendTelegramInternal(text) {
  CRM.api('settings', 'get').then(function(res) {
    var s      = (res && res.data) ? res.data : {};
    var token  = s.tgToken;
    var chatId = s.tgBossId;
    if (!token || !chatId) return;
    if (!MODULES.telegram || !MODULES.telegram.enabled) return;
    fetch('https://api.telegram.org/bot' + token + '/sendMessage', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ chat_id: chatId, text: text, parse_mode: 'HTML' })
    }).catch(function(e) { console.warn('Telegram ошибка:', e.message); });
  });
}

function sendTelegram(text) { _sendTelegramInternal(text); }

function notifyTelegramNewOrder(order) {
  if (!MODULES.telegram || !MODULES.telegram.enabled) return;
  CRM.api('settings', 'get').then(function(res) {
    var s   = (res && res.data) ? res.data : {};
    var msg =
      '📋 <b>Новый заказ ' + (order.num || '') + '</b>\n' +
      '👤 Клиент: ' + (order.client || '') + '\n' +
      '📞 Тел: ' + (order.phone || '—') + '\n' +
      '🖨 Услуга: ' + (order.serviceLabel || '') + '\n' +
      '💰 Сумма: ' + CRM.formatMoney(order.total) + '\n' +
      '🏢 ' + (s.company || 'Типография');
    _sendTelegramInternal(msg);
  });
}

function notifyTelegramStatusChange(order) {
  if (!MODULES.telegram || !MODULES.telegram.enabled) return;
  var labels = { new:'Новый', work:'В работе', ready:'✅ Готов', done:'📦 Выдан', cancel:'❌ Отменён' };
  _sendTelegramInternal(
    '🔄 <b>Заказ ' + (order.num || '') + ' — статус изменён</b>\n' +
    '📌 Статус: ' + (labels[order.status] || order.status) + '\n' +
    '👤 Клиент: ' + (order.client || '')
  );
}

function notifyTelegramLowStock(item) {
  if (!MODULES.telegram || !MODULES.telegram.enabled) return;
  _sendTelegramInternal(
    '⚠️ <b>Заканчивается на складе</b>\n' +
    '📦 ' + item.name + '\n' +
    '📉 Остаток: ' + item.qty + ' ' + item.unit + ' (мин: ' + item.minQty + ' ' + item.unit + ')'
  );
}

/* ============================================================
   ПАТЧИ — Telegram
============================================================ */
function _patchAppFunctions() {
  var _origSave = window.saveOrder;
  if (typeof _origSave === 'function') {
    window.saveOrder = function() {
      _origSave.apply(this, arguments);
      var db    = CRM._getCache();
      var order = db.orders && db.orders[0];
      if (order) notifyTelegramNewOrder(order);
    };
  }

  var _origStatus = window.changeOrderStatus;
  if (typeof _origStatus === 'function') {
    window.changeOrderStatus = function(id) {
      _origStatus.apply(this, arguments);
      var db    = CRM._getCache();
      var order = (db.orders || []).find(function(o) { return o.id === id; });
      if (order) notifyTelegramStatusChange(order);
    };
  }

  var _origWh = window.executeWarehouseAction;
  if (typeof _origWh === 'function') {
    window.executeWarehouseAction = function() {
      _origWh.apply(this, arguments);
      var db = CRM._getCache();
      (db.warehouse || []).forEach(function(item) {
        if (parseFloat(item.qty) <= parseFloat(item.minQty)) notifyTelegramLowStock(item);
      });
    };
  }
}

/* ============================================================
   МАКс уведомления владельцу
============================================================ */
var NOTIFY_URL    = '/bot/crm_notify.php';
var NOTIFY_SECRET = 'crm2025notify';

function crmNotify(event, data) {
  fetch(NOTIFY_URL + '?key=' + NOTIFY_SECRET + '&event=' + event, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ event: event, data: data || {} })
  })
  .then(function(r) { return r.json(); })
  .then(function(j) { console.log('notify [' + event + ']:', j.ok ? 'OK' : 'FAIL'); })
  .catch(function(e) { console.warn('notify error [' + event + ']:', e.message); });
}

/* ============================================================
   АРТЕМИЙ — уведомления клиентам в МАКс
============================================================ */
function artemiyNotify(event, data) {
  if (!data || !data.phone) return;
  if (!MODULES.artemiy_bot || !MODULES.artemiy_bot.enabled) return;
  fetch('/bot/artemiy.php?key=artemiy2025notify&event=' + event, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ data: data })
  })
  .then(function(r) { return r.json(); })
  .then(function(j) { console.log('artemiy [' + event + '] → ' + data.phone + ':', j.ok ? 'OK' : 'NOT_LINKED'); })
  .catch(function(e) { console.warn('artemiy error:', e.message); });
}

/* ============================================================
   ВКонтакте — уведомления клиентам
============================================================ */
function vkNotify(event, data) {
  if (!data || !data.phone) return;
  if (!MODULES.vk_bot || !MODULES.vk_bot.enabled) return;
  fetch('/bot/vk.php?key=vk2025notify&event=' + event, {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ data: data })
  })
  .then(function(r) { return r.json(); })
  .then(function(j) { console.log('vk [' + event + '] → ' + data.phone + ':', j.ok ? 'OK' : 'FAIL'); })
  .catch(function(e) { console.warn('vk error:', e.message); });
}

/* ============================================================
   ПАТЧИ — МАКс + Артемий + ВК
============================================================ */
function _patchCRMNotify() {

  /* ── ЗАКАЗЫ — создание ── */
  var _origSaveOrder = window.saveOrder;
  if (typeof _origSaveOrder === 'function') {
    window.saveOrder = function() {
      _origSaveOrder.apply(this, arguments);
      setTimeout(function() {
        var db    = CRM._getCache();
        var order = db.orders && db.orders[0];
        if (order) {
          crmNotify('order_new', order);
          artemiyNotify('order_new', order);
          vkNotify('order_new', order);
        }
      }, 500);
    };
  }

  /* ── ЗАКАЗЫ — смена статуса ── */
  var _origChangeStatus = window.changeOrderStatus;
  if (typeof _origChangeStatus === 'function') {
    window.changeOrderStatus = function(id, status) {
      _origChangeStatus.apply(this, arguments);
      setTimeout(function() {
        var db    = CRM._getCache();
        var order = (db.orders || []).find(function(o) { return o.id === id; });
        if (order) {
          crmNotify('order_status', order);
          if (order.status === 'done') crmNotify('order_done', order);
          artemiyNotify('order_status', order);
          vkNotify('order_status', order);
        }
      }, 300);
    };
  }

  /* ── ЗАКАЗЫ — перетаскивание карточки (Канбан) ── */
  var _origDropCard = window.dropCard;
  if (typeof _origDropCard === 'function') {
    window.dropCard = function(event, newStatus) {
      var id = event.dataTransfer.getData('text/plain') || window.draggedOrderId;
      _origDropCard.apply(this, arguments);
      setTimeout(function() {
        var db    = CRM._getCache();
        var order = (db.orders || []).find(function(o) { return String(o.id) === String(id); });
        if (order) {
          crmNotify('order_status', order);
          if (order.status === 'done') crmNotify('order_done', order);
          artemiyNotify('order_status', order);
          vkNotify('order_status', order);
        }
      }, 400);
    };
  }

  /* ── ЗАКАЗЫ — смена статуса через меню (Канбан) ── */
  var _origKbStatus = window.changeKbOrderStatus;
  if (typeof _origKbStatus === 'function') {
    window.changeKbOrderStatus = function(id, newStatus, e) {
      _origKbStatus.apply(this, arguments);
      setTimeout(function() {
        var db    = CRM._getCache();
        var order = (db.orders || []).find(function(o) { return String(o.id) === String(id); });
        if (order) {
          crmNotify('order_status', order);
          if (order.status === 'done') crmNotify('order_done', order);
          artemiyNotify('order_status', order);
          vkNotify('order_status', order);
        }
      }, 400);
    };
  }

  /* ── ФИНАНСЫ — доход ── */
  var _origIncome = window.saveIncome;
  if (typeof _origIncome === 'function') {
    window.saveIncome = function() {
      _origIncome.apply(this, arguments);
      setTimeout(function() {
        var db   = CRM._getCache();
        var fin  = db.finance || [];
        var last = fin[0];
        if (last && last.type === 'income') crmNotify('finance_income', last);
      }, 500);
    };
  }

  /* ── ФИНАНСЫ — расход ── */
  var _origExpense = window.saveExpense;
  if (typeof _origExpense === 'function') {
    window.saveExpense = function() {
      _origExpense.apply(this, arguments);
      setTimeout(function() {
        var db   = CRM._getCache();
        var fin  = db.finance || [];
        var last = fin[0];
        if (last && last.type === 'expense') crmNotify('finance_expense', last);
      }, 500);
    };
  }

  /* ── СКЛАД ── */
  var _origWhAction = window.executeWarehouseAction;
  if (typeof _origWhAction === 'function') {
    window.executeWarehouseAction = function() {
      _origWhAction.apply(this, arguments);
      setTimeout(function() {
        var db = CRM._getCache();
        (db.warehouse || []).forEach(function(item) {
          if (parseFloat(item.qty) <= parseFloat(item.minQty)) {
            crmNotify('warehouse_low', item);
          }
        });
      }, 500);
    };
  }

  /* ── ЗАРПЛАТА — перехват CRM.api ── */
  var _origApi = CRM.api.bind(CRM);
  CRM.api = function(module, action, body, params) {
    var result = _origApi(module, action, body, params);
    if (module === 'salary') {
      result.then(function(res) {
        if (!res || !res.ok) return;
        if (action === 'add'            && body) crmNotify('salary_pay',      body);
        if (action === 'addShift'       && body) crmNotify('shift_add',       body);
        if (action === 'deleteShift'         )   crmNotify('shift_delete',    body || {});
        if (action === 'addEmployee'    && body) crmNotify('employee_add',    body);
        if (action === 'updateEmployee' && body) crmNotify('employee_update', body);
        if (action === 'deleteEmployee'      )   crmNotify('employee_delete', body || {});
      });
    }
    return result;
  };
}

/* ============================================================
   ИТОГ ДНЯ
============================================================ */
window.sendDaySummary = function() {
  var db      = CRM._getCache();
  var today   = new Date().toISOString().slice(0, 10);
  var finance = (db.finance || []).filter(function(f) {
    return (f.date || '').slice(0, 10) === today;
  });
  var orders  = (db.orders || []).filter(function(o) {
    return (o.date || '').slice(0, 10) === today;
  });
  var income  = finance.filter(function(f) { return f.type === 'income'; })
                       .reduce(function(a, b) { return a + (b.amount || 0); }, 0);
  var expense = finance.filter(function(f) { return f.type === 'expense'; })
                       .reduce(function(a, b) { return a + (b.amount || 0); }, 0);
  crmNotify('day_summary', {
    orders_count: orders.length,
    orders_done:  orders.filter(function(o) { return o.status === 'done'; }).length,
    income:       income,
    expense:      expense
  });
  if (typeof notify === 'function') notify('📊 Итог дня отправлен в МАКс', 'success');
};

window.testCRMNotify = function() {
  crmNotify('test', {});
  if (typeof notify === 'function') notify('🔔 Тест отправлен — жди сообщение в МАКс', 'info');
};

/* ============================================================
   ИНИЦИАЛИЗАЦИЯ
============================================================ */
function initModules() {
  _waitForConfig(function() {
    loadModuleSettings();
    _patchAppFunctions();
    _patchCRMNotify();
    CRM.loadAll();
    console.log('✅ modules.js v4.2 инициализирован');
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function() { setTimeout(initModules, 500); });
} else {
  setTimeout(initModules, 500);
}