// ============================================================
// PrintCRM v3.0 — js/modules.js
// Реестр и загрузчик внешних модулей
// ============================================================

(function () {
  'use strict';

  // ── Реестр зарегистрированных модулей ─────────────────────
  const _registry = new Map();

  // ── Публичный API ─────────────────────────────────────────
  window.CRM = window.CRM || {};

  /**
   * Зарегистрировать модуль
   * @param {Object} cfg - конфигурация модуля
   * cfg.id       {string}   — уникальный ID
   * cfg.name     {string}   — название (в сайдбаре)
   * cfg.icon     {string}   — эмодзи/иконка
   * cfg.color    {string}   — цвет акцента (#hex)
   * cfg.sidebar  {boolean}  — показывать в меню
   * cfg.render   {Function} — fn(container) — рендер вкладки
   * cfg.onLoad   {Function} — вызывается при первом открытии
   */
  window.CRM.registerModule = function (cfg) {
    if (!cfg || !cfg.id) {
      console.warn('[CRM.modules] registerModule: нет id', cfg);
      return;
    }
    _registry.set(cfg.id, Object.assign({
      name:    cfg.id,
      icon:    '🧩',
      color:   '#7c3aed',
      sidebar: true,
      render:  null,
      onLoad:  null,
    }, cfg));

    console.log('[CRM.modules] зарегистрирован:', cfg.id);
    _dispatchEvent('crm:module:registered', { id: cfg.id });
  };

  /** Получить модуль по ID */
  window.CRM.getModule = function (id) {
    return _registry.get(id) || null;
  };

  /** Получить все модули */
  window.CRM.getModules = function () {
    return Array.from(_registry.values());
  };

  /** Открыть/активировать модуль */
  window.CRM.openModule = function (id) {
    const mod = _registry.get(id);
    if (!mod) {
      console.warn('[CRM.modules] модуль не найден:', id);
      return;
    }
    _activateModule(mod);
  };

  // ── Загрузка модулей с сервера ────────────────────────────
  window.CRM.loadRemoteModules = async function () {
    try {
      const res  = await fetch('/api/registry?key=12345');
      const data = await res.json();
      const list = data.modules || [];

      for (const meta of list) {
        if (_registry.has(meta.id)) continue; // уже загружен
        await _loadRemoteModule(meta);
      }
    } catch (e) {
      console.warn('[CRM.modules] loadRemoteModules error:', e.message);
    }
  };

  // ── Инициализация (вызывается из app.js) ──────────────────
  window.CRM.initModules = function () {
    _renderSidebarItems();
    window.CRM.loadRemoteModules();
  };

  // ════════════════════════════════════════════════════════════
  // ПРИВАТНЫЕ ФУНКЦИИ
  // ════════════════════════════════════════════════════════════

  async function _loadRemoteModule(meta) {
    try {
      const res  = await fetch(`/api/module?module=${meta.id}&action=__getjs__&key=12345`);
      if (!res.ok) return;
      const html = await res.text();
      if (!html.trim()) return;

      // Вставляем HTML/JS модуля в DOM
      const wrap = document.createElement('div');
      wrap.innerHTML = html;

      // Выполняем скрипты
      wrap.querySelectorAll('script').forEach(oldScript => {
        const s = document.createElement('script');
        s.textContent = oldScript.textContent;
        document.head.appendChild(s);
      });

      console.log('[CRM.modules] загружен удалённый модуль:', meta.id);
    } catch (e) {
      console.warn('[CRM.modules] ошибка загрузки модуля', meta.id, e.message);
    }
  }

  function _activateModule(mod) {
    // Ищем или создаём контейнер вкладки
    let container = document.getElementById('module-tab-' + mod.id);

    if (!container) {
      container = document.createElement('div');
      container.id        = 'module-tab-' + mod.id;
      container.className = 'tab-content module-tab';
      container.setAttribute('data-tab', mod.id);

      const main = document.querySelector('.main-content') ||
                   document.querySelector('main')          ||
                   document.body;
      main.appendChild(container);
    }

    // Скрываем все вкладки
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    container.classList.add('active');

    // Обновляем активный пункт меню
    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
    const navItem = document.querySelector(`.nav-item[data-module="${mod.id}"]`);
    if (navItem) navItem.classList.add('active');

    // Первый рендер
    if (!container._rendered) {
      container._rendered = true;
      if (typeof mod.onLoad === 'function') {
        try { mod.onLoad(container); } catch (e) {
          console.error('[CRM.modules] onLoad error:', mod.id, e);
        }
      }
      if (typeof mod.render === 'function') {
        try { mod.render(container); } catch (e) {
          console.error('[CRM.modules] render error:', mod.id, e);
        }
      } else {
        container.innerHTML = `
          <div style="padding:40px;text-align:center;color:#666;">
            <div style="font-size:48px;margin-bottom:12px;">${mod.icon}</div>
            <h3 style="color:#fff;margin-bottom:8px;">${mod.name}</h3>
            <p>Модуль загружен. Функция render() не определена.</p>
          </div>`;
      }
    }

    _dispatchEvent('crm:module:opened', { id: mod.id });
  }

  function _renderSidebarItems() {
    // Добавляем пункты в сайдбар для всех модулей с sidebar:true
    const nav = document.querySelector('.sidebar-nav') ||
                document.querySelector('.nav-menu')    ||
                document.querySelector('nav');
    if (!nav) return;

    _registry.forEach(mod => {
      if (!mod.sidebar) return;
      if (nav.querySelector(`[data-module="${mod.id}"]`)) return; // уже есть

      const item = document.createElement('div');
      item.className       = 'nav-item';
      item.setAttribute('data-module', mod.id);
      item.setAttribute('data-tab',    mod.id);
      item.style.cssText   = `--module-color: ${mod.color}`;
      item.innerHTML       = `<span class="nav-icon">${mod.icon}</span><span>${mod.name}</span>`;
      item.addEventListener('click', () => window.CRM.openModule(mod.id));
      nav.appendChild(item);
    });
  }

  function _dispatchEvent(name, detail) {
    try {
      document.dispatchEvent(new CustomEvent(name, { detail, bubbles: true }));
    } catch (_) {}
  }

  // ── Слушаем регистрацию новых модулей после initModules ──
  document.addEventListener('crm:module:registered', () => {
    _renderSidebarItems();
  });

})();