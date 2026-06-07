<?php
// ============================================================
// PrintCRM v3.0 — index.php
// Единственная точка входа фронтенда
// ============================================================
$config = [
    'api_url'     => '/api/',
    'api_key'     => '12345',
    'version'     => '3.0',
    'app_name'    => 'ПРИНТСС медиа Pro',
    'app_sub'     => 'Фотокопицентр & Типография',
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $config['app_name'] ?> — <?= $config['app_sub'] ?></title>
<link rel="stylesheet" href="styles.css">
</head>
<body>

<!-- ─── CONFIG (до всех скриптов) ─────────────────────────── -->
<script>
  const API_URL     = '<?= $config['api_url'] ?>';
  const API_KEY     = '<?= $config['api_key'] ?>';
  const APP_VERSION = '<?= $config['version'] ?>';
  const apiHeaders  = {
    'Content-Type': 'application/json',
    'X-Api-Key':    API_KEY,
  };
</script>

<!-- ─── NOTIFICATION STACK ────────────────────────────────── -->
<div class="notification-stack" id="notifStack"></div>

<!-- ─── HEADER ───────────────────────────────────────────── -->
<header class="header">
  <div class="header-logo">
    <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
      <rect width="28" height="28" rx="8" fill="url(#lg1)"/>
      <defs><linearGradient id="lg1" x1="0" y1="0" x2="28" y2="28">
        <stop stop-color="#7c3aed"/><stop offset="1" stop-color="#06b6d4"/>
      </linearGradient></defs>
      <path d="M6 8h16M6 14h10M6 20h13" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
      <circle cx="21" cy="19" r="4" fill="#06b6d4" opacity="0.9"/>
      <path d="M19.5 19h3M21 17.5v3" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
    </svg>
    <?= $config['app_name'] ?>
  </div>

  <div class="header-center">
    <button class="btn-quick btn-income"  onclick="openModal('incomeModal')">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
        <polyline points="17 6 23 6 23 12"/>
      </svg>
      Внести доход
    </button>
    <button class="btn-quick btn-expense" onclick="openModal('expenseModal')">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/>
        <polyline points="17 18 23 18 23 12"/>
      </svg>
      Внести расход
    </button>
    <button class="btn-quick btn-order"   onclick="openModal('orderModal')">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <rect x="2" y="3" width="20" height="18" rx="2"/>
        <line x1="8" y1="10" x2="16" y2="10"/>
        <line x1="8" y1="14" x2="14" y2="14"/>
      </svg>
      Внести заказ
    </button>
  </div>

  <div class="header-right">
    <div id="syncIndicator" style="display:flex;align-items:center;gap:6px;font-size:0.72rem;color:var(--text-muted);">
      <span id="syncDot" class="status-dot"></span>
      <span id="syncText">Онлайн</span>
    </div>
    <div class="time-widget">
      <div class="time" id="clockTime">--:--</div>
      <div class="date" id="clockDate">---</div>
    </div>
  </div>
</header>

<!-- ─── LAYOUT ───────────────────────────────────────────── -->
<div class="layout">

<!-- ─── SIDEBAR ──────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-section-label">Навигация</div>

  <button class="nav-btn active" id="nav-dashboard" onclick="showPage('dashboard',this)">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <rect x="3" y="3" width="7" height="7" rx="1"/>
      <rect x="14" y="3" width="7" height="7" rx="1"/>
      <rect x="3" y="14" width="7" height="7" rx="1"/>
      <rect x="14" y="14" width="7" height="7" rx="1"/>
    </svg>
    Дашборд + ИИ
  </button>

  <button class="nav-btn" id="nav-orders" onclick="showPage('orders',this)">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <rect x="2" y="3" width="20" height="18" rx="2"/>
      <polyline points="8 10 12 14 16 10"/>
    </svg>
    Заказы
    <span class="nav-badge" id="ordersNavBadge" style="display:none">0</span>
  </button>

  <button class="nav-btn" id="nav-finance" onclick="showPage('finance',this)">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <line x1="12" y1="1" x2="12" y2="23"/>
      <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
    </svg>
    Доходы / Расходы
  </button>

  <button class="nav-btn" id="nav-stats" onclick="showPage('stats',this)">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
    </svg>
    Статистика
  </button>

  <button class="nav-btn" id="nav-accounting" onclick="showPage('accounting',this)">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
      <polyline points="14 2 14 8 20 8"/>
    </svg>
    Финансовый учёт
  </button>

  <button class="nav-btn" id="nav-clients" onclick="showPage('clients',this)">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
      <circle cx="9" cy="7" r="4"/>
      <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
      <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
    </svg>
    База клиентов
  </button>

  <button class="nav-btn" id="nav-notes" onclick="showPage('notes',this)">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path d="M12 20h9"/>
      <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
    </svg>
    Заметки смены
    <span class="nav-badge" id="notesNavBadge" style="background:var(--accent4);display:none">!</span>
  </button>

  <div class="sidebar-section-label">Система</div>

  <button class="nav-btn" id="nav-warehouse" onclick="showPage('warehouse',this)">
    <span style="font-size:1rem;">📦</span> Склад
    <span class="nav-badge" id="warehouseLowBadge" style="background:var(--danger);display:none">!</span>
  </button>

  <button class="nav-btn" id="nav-calendar" onclick="showPage('calendar',this)">
    <span style="font-size:1rem;">📅</span> Календарь
  </button>

  <button class="nav-btn" id="nav-settings" onclick="showPage('settings',this)">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="3"/>
      <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06
               a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09
               A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83
               l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09
               A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83
               l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09
               a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83
               l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09
               a1.65 1.65 0 0 0-1.51 1z"/>
    </svg>
    Настройки
  </button>

  <!-- Доп. модули — заполняется JS -->
  <div id="modules-sidebar-section"></div>

  <div class="sidebar-footer">
    <div style="font-size:0.68rem;color:var(--text-muted);padding:8px 10px;line-height:1.6;">
      <div style="font-weight:700;color:var(--text);"><?= $config['app_name'] ?></div>
      <div>v<?= $config['version'] ?> • SQLite</div>
      <div id="dbSizeInfo" style="color:var(--accent2);">---</div>
    </div>
  </div>
</aside>

<!-- ─── MAIN ──────────────────────────────────────────────── -->
<main class="main" id="mainContent">

<!-- ══════════════════════════════════════════════════════════
     PAGE: DASHBOARD
══════════════════════════════════════════════════════════ -->
<div class="page active" id="page-dashboard">
  <div class="page-header">
    <div>
      <div class="page-title">Дашборд</div>
      <div class="page-subtitle">Обзор бизнеса и ИИ-ассистент Валера</div>
    </div>
    <button class="btn btn-secondary btn-sm" onclick="refreshDashboard()">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <polyline points="23 4 23 10 17 10"/>
        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
      </svg>
      Обновить
    </button>
  </div>

  <!-- KPI -->
  <div class="grid-4 mb-16">
    <div class="stat-card purple">
      <div class="stat-icon">📋</div>
      <div class="stat-label">Заказы сегодня</div>
      <div class="stat-value purple" id="kpiOrdersToday">—</div>
      <div class="stat-sub">за сутки</div>
    </div>
    <div class="stat-card green">
      <div class="stat-icon">💰</div>
      <div class="stat-label">Доходы (месяц)</div>
      <div class="stat-value green" id="kpiIncomeMonth">—</div>
      <div class="stat-sub" id="kpiIncomeToday">сегодня: —</div>
    </div>
    <div class="stat-card red">
      <div class="stat-icon">📤</div>
      <div class="stat-label">Расходы (месяц)</div>
      <div class="stat-value red" id="kpiExpenseMonth">—</div>
      <div class="stat-sub" id="kpiExpenseToday">сегодня: —</div>
    </div>
    <div class="stat-card cyan">
      <div class="stat-icon">📈</div>
      <div class="stat-label">Прибыль (месяц)</div>
      <div class="stat-value cyan" id="kpiProfitMonth">—</div>
      <div class="stat-sub" id="kpiProfitStatus">расчёт...</div>
    </div>
  </div>

  <!-- ЗАКАЗЫ + ФИНАНСЫ -->
  <div class="grid-2" style="gap:20px;">
    <div class="card">
      <div class="card-title">
        <svg width="14" height="14" fill="none" stroke="var(--accent)" stroke-width="2" viewBox="0 0 24 24">
          <rect x="2" y="3" width="20" height="18" rx="2"/>
          <polyline points="8 10 12 14 16 10"/>
        </svg>
        Последние заказы
      </div>
      <div id="dashRecentOrders">
        <div class="empty-state">
          <div class="icon">📋</div>
          <div class="title">Загрузка...</div>
        </div>
      </div>
    </div>

    <div class="card" style="padding:0;overflow:hidden;">
      <div style="padding:14px 16px 0;display:flex;align-items:center;justify-content:space-between;">
        <div style="display:flex;align-items:center;gap:8px;">
          <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,#065f46,#10b981);display:flex;align-items:center;justify-content:center;font-size:1rem;">💵</div>
          <div>
            <div style="font-weight:700;font-size:0.85rem;">Финансы сегодня</div>
            <div style="font-size:0.7rem;color:var(--text-muted);" id="dashTodayDate">—</div>
          </div>
        </div>
        <div style="display:flex;gap:6px;">
          <button class="btn btn-success btn-xs" onclick="openModal('incomeModal')">+ Доход</button>
          <button class="btn btn-danger btn-xs"  onclick="openModal('expenseModal')">− Расход</button>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0;padding:14px 16px;border-bottom:1px solid var(--border);">
        <div style="text-align:center;padding:8px;border-right:1px solid var(--border);">
          <div style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Доходы</div>
          <div style="font-size:1.3rem;font-weight:800;color:var(--accent3);" id="dashTodayIncome">—</div>
        </div>
        <div style="text-align:center;padding:8px;border-right:1px solid var(--border);">
          <div style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Расходы</div>
          <div style="font-size:1.3rem;font-weight:800;color:var(--danger);" id="dashTodayExpense">—</div>
        </div>
        <div style="text-align:center;padding:8px;">
          <div style="font-size:0.65rem;text-transform:uppercase;letter-spacing:0.5px;color:var(--text-muted);margin-bottom:4px;">Прибыль</div>
          <div style="font-size:1.3rem;font-weight:800;color:var(--accent2);" id="dashTodayProfit">—</div>
        </div>
      </div>
      <div style="padding:10px 16px 6px;">
        <div style="display:flex;justify-content:space-between;font-size:0.68rem;color:var(--text-muted);margin-bottom:4px;">
          <span>Соотношение доход/расход</span>
          <span id="dashTodayRatio">—</span>
        </div>
        <div style="height:6px;background:var(--border);border-radius:3px;overflow:hidden;">
          <div id="dashTodayBar" style="height:100%;border-radius:3px;background:linear-gradient(to right,var(--accent3),var(--accent2));transition:width 0.6s ease;width:0%;"></div>
        </div>
      </div>
      <div style="padding:8px 16px 14px;">
        <div style="font-size:0.68rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">Последние операции</div>
        <div id="dashRecentFinance" style="display:flex;flex-direction:column;gap:5px;">
          <div style="text-align:center;padding:12px;color:var(--text-muted);font-size:0.78rem;">Загрузка...</div>
        </div>
      </div>
    </div>
  </div>

  <!-- СТАТИСТИКА -->
  <div class="mt-24">
    <div class="section-label">📊 Статистика за сегодня</div>
    <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:16px;">
      <div class="card" style="padding:16px;">
        <div class="card-title">
          <svg width="13" height="13" fill="none" stroke="var(--accent3)" stroke-width="2" viewBox="0 0 24 24">
            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
          </svg>
          Доходы по часам (сегодня)
        </div>
        <div id="dashHourlyChart" style="display:flex;align-items:flex-end;gap:3px;height:72px;padding-top:4px;">
          <div style="color:var(--text-muted);font-size:0.72rem;align-self:center;width:100%;text-align:center;">Загрузка...</div>
        </div>
        <div style="display:flex;justify-content:space-between;margin-top:6px;font-size:0.6rem;color:var(--text-muted);">
          <span>00:00</span><span>06:00</span><span>12:00</span><span>18:00</span><span>23:00</span>
        </div>
      </div>
      <div class="card" style="padding:16px;">
        <div class="card-title">
          <svg width="13" height="13" fill="none" stroke="var(--accent4)" stroke-width="2" viewBox="0 0 24 24">
            <path d="M18 20V10M12 20V4M6 20v-6"/>
          </svg>
          Топ доходов сегодня
        </div>
        <div id="dashTopIncome" style="display:flex;flex-direction:column;gap:7px;margin-top:4px;">
          <div style="color:var(--text-muted);font-size:0.72rem;text-align:center;padding:16px 0;">Загрузка...</div>
        </div>
      </div>
      <div class="card" style="padding:16px;display:flex;flex-direction:column;gap:10px;">
        <div class="card-title">
          <svg width="13" height="13" fill="none" stroke="var(--accent2)" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"/>
            <path d="M12 6v6l4 2"/>
          </svg>
          Итог дня
        </div>
        <div style="text-align:center;">
          <div id="dashDayEmoji" style="font-size:2.8rem;line-height:1;">😐</div>
          <div id="dashDayLabel" style="font-size:0.75rem;font-weight:700;color:var(--text-muted);margin-top:4px;">—</div>
        </div>
        <div style="display:flex;flex-direction:column;gap:6px;">
          <div style="display:flex;justify-content:space-between;font-size:0.75rem;padding:5px 8px;background:var(--bg-dark);border-radius:6px;">
            <span style="color:var(--text-muted);">Операций</span>
            <span style="font-weight:700;color:var(--text);" id="dashTodayOpsCount">—</span>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:0.75rem;padding:5px 8px;background:var(--bg-dark);border-radius:6px;">
            <span style="color:var(--text-muted);">Заказов</span>
            <span style="font-weight:700;color:var(--accent2);" id="dashTodayOrdersCount">—</span>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:0.75rem;padding:5px 8px;background:var(--bg-dark);border-radius:6px;">
            <span style="color:var(--text-muted);">Ср. чек</span>
            <span style="font-weight:700;color:var(--accent4);" id="dashTodayAvgCheck">—</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ИИ ВАЛЕРА -->
  <div class="mt-24">
    <div class="section-label">ИИ-ассистент Валера (DeepSeek)</div>
    <div class="card" style="padding:0;overflow:hidden;">
      <div style="display:flex;align-items:center;gap:12px;padding:16px 20px;border-bottom:1px solid var(--border);background:var(--bg-card2);">
        <div style="width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:1.3rem;animation:float 3s ease-in-out infinite;">🤖</div>
        <div>
          <div style="font-weight:700;">Валера — Ваш эксперт по типографии</div>
          <div style="font-size:0.72rem;color:var(--text-muted);">Знает всё о печати, экономике, ценообразовании, форматах</div>
        </div>
        <div style="margin-left:auto;display:flex;align-items:center;gap:6px;">
          <span class="status-dot"></span>
          <span style="font-size:0.75rem;color:var(--accent3);">DeepSeek AI</span>
        </div>
      </div>
      <div style="padding:12px 16px;background:var(--bg-dark);border-bottom:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap;">
        <span style="font-size:0.72rem;color:var(--text-muted);align-self:center;">Быстрые вопросы:</span>
        <button class="ai-quick-btn" onclick="sendQuickChat('Какие форматы баннеров самые популярные?')">📐 Форматы баннеров</button>
        <button class="ai-quick-btn" onclick="sendQuickChat('Как рассчитать себестоимость печати?')">💰 Расчёт цены</button>
        <button class="ai-quick-btn" onclick="sendQuickChat('Дай анализ финансов моей типографии за текущий месяц')">📊 Анализ финансов</button>
        <button class="ai-quick-btn" onclick="sendQuickChat('Какие услуги добавить чтобы увеличить прибыль?')">🚀 Рост бизнеса</button>
        <button class="ai-quick-btn" onclick="sendQuickChat('Стандартные форматы фотопечати')">🖼️ Фото форматы</button>
        <button class="ai-quick-btn" onclick="sendQuickChat('Расскажи анекдот про типографию')">😄 Развлечь меня</button>
      </div>
      <div class="chat-messages" id="chatMessages" style="height:380px;border:none;border-radius:0;"></div>
      <div class="chat-input-area">
        <textarea class="chat-input" id="chatInput"
          placeholder="Спросите Валера... (Enter — отправить, Shift+Enter — новая строка)"
          rows="1"
          onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendChatMessage();}"
          oninput="autoResizeTextarea(this)"></textarea>
        <button class="chat-send-btn" onclick="sendChatMessage()">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <line x1="22" y1="2" x2="11" y2="13"/>
            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
          </svg>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     PAGE: ORDERS (KANBAN)
══════════════════════════════════════════════════════════ -->
<div class="page" id="page-orders">
  <div class="page-header">
    <div>
      <div class="page-title">Заказы</div>
      <div class="page-subtitle">Канбан-доска · перетаскивай карточки между колонками</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
      <div class="search-bar" style="min-width:220px;">
        <svg width="14" height="14" fill="none" stroke="var(--text-muted)" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="8"/>
          <line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" placeholder="Поиск заказа..." id="orderSearch" oninput="renderKanban()">
      </div>
      <select class="form-select" style="width:150px;" id="orderServiceFilter" onchange="renderKanban()">
        <option value="">Все услуги</option>
        <option value="photo">📸 Фото</option>
        <option value="copy">🖨️ Копи</option>
        <option value="banner">🏳️ Баннер</option>
        <option value="design">🎨 Дизайн</option>
        <option value="business">💼 Бизнес</option>
        <option value="wide">🖼️ Широкий</option>
        <option value="promo">🎁 Сувенирка</option>
        <option value="other">⚙️ Прочее</option>
      </select>
      <button class="btn btn-primary btn-sm" onclick="openModal('orderModal')">+ Новый заказ</button>
    </div>
  </div>

  <div class="kanban-stats-bar">
    <div class="kanban-stat-pill"><span class="kanban-stat-dot" style="background:#6366f1;"></span><span id="kbCount_new">0</span>&nbsp;Новых</div>
    <div class="kanban-stat-pill"><span class="kanban-stat-dot" style="background:#f59e0b;"></span><span id="kbCount_work">0</span>&nbsp;В работе</div>
    <div class="kanban-stat-pill"><span class="kanban-stat-dot" style="background:#10b981;"></span><span id="kbCount_ready">0</span>&nbsp;Готовы</div>
    <div class="kanban-stat-pill"><span class="kanban-stat-dot" style="background:#06b6d4;"></span><span id="kbCount_done">0</span>&nbsp;Выданы</div>
    <div class="kanban-stat-pill"><span class="kanban-stat-dot" style="background:#ef4444;"></span><span id="kbCount_cancel">0</span>&nbsp;Отменены</div>
    <div class="kanban-stat-pill" style="margin-left:auto;font-weight:800;">💰 <span id="kbTotalSum">0 ₽</span></div>
  </div>

  <div class="kanban-board" id="kanbanBoard">
    <?php
    $cols = [
      'new'    => ['🆕','Новые',   'new'],
      'work'   => ['⚙️','В работе','work'],
      'ready'  => ['✅','Готовы',  'ready'],
      'done'   => ['📦','Выданы',  'done'],
      'cancel' => ['❌','Отменены','cancel'],
    ];
    foreach ($cols as $status => [$icon, $label, $cls]):
    ?>
    <div class="kanban-col" data-status="<?= $status ?>">
      <div class="kanban-col-header <?= $cls ?>">
        <div class="kanban-col-title">
          <span class="kanban-col-icon"><?= $icon ?></span>
          <?= $label ?>
          <span class="kanban-col-badge" id="kbBadge_<?= $status ?>">0</span>
        </div>
        <?php if ($status === 'new'): ?>
        <button class="kanban-add-btn" onclick="openModal('orderModal')" title="Добавить">+</button>
        <?php endif; ?>
      </div>
      <div class="kanban-cards" id="kbCol_<?= $status ?>"
           ondragover="event.preventDefault();highlightDrop(this)"
           ondragleave="unhighlightDrop(this)"
           ondrop="dropCard(event,'<?= $status ?>')">
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     PAGE: FINANCE
══════════════════════════════════════════════════════════ -->
<div class="page" id="page-finance">
  <div class="page-header">
    <div>
      <div class="page-title">Доходы и Расходы</div>
      <div class="page-subtitle">Журнал финансовых операций</div>
    </div>
    <div style="display:flex;gap:8px;">
      <button class="btn btn-success btn-sm" onclick="openModal('incomeModal')">+ Доход</button>
      <button class="btn btn-danger  btn-sm" onclick="openModal('expenseModal')">− Расход</button>
    </div>
  </div>
  <div class="finance-summary">
    <div class="finance-block profit">
      <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:4px;">ДОХОДЫ (МЕСЯЦ)</div>
      <div style="font-size:1.6rem;font-weight:800;color:var(--accent3);" id="finIncomeTotal">—</div>
    </div>
    <div class="finance-block loss">
      <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:4px;">РАСХОДЫ (МЕСЯЦ)</div>
      <div style="font-size:1.6rem;font-weight:800;color:var(--danger);" id="finExpenseTotal">—</div>
    </div>
    <div class="finance-block balance">
      <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:4px;">ПРИБЫЛЬ (МЕСЯЦ)</div>
      <div style="font-size:1.6rem;font-weight:800;color:var(--accent);" id="finProfitTotal">—</div>
    </div>
  </div>
  <div class="card">
    <div style="display:flex;gap:8px;margin-bottom:16px;align-items:center;">
      <div class="search-bar" style="min-width:220px;">
        <svg width="14" height="14" fill="none" stroke="var(--text-muted)" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="8"/>
          <line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" placeholder="Поиск..." id="finSearch" oninput="renderFinanceTable()">
      </div>
      <select class="form-select" style="width:130px;" id="finTypeFilter" onchange="renderFinanceTable()">
        <option value="">Всё</option>
        <option value="income">Доходы</option>
        <option value="expense">Расходы</option>
      </select>
      <input type="month" class="form-input" id="finMonthFilter" style="width:160px;" onchange="renderFinanceTable()">
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Дата</th><th>Тип</th><th>Категория</th>
            <th>Описание</th><th>Сумма</th><th>Метод</th><th>Удалить</th>
          </tr>
        </thead>
        <tbody id="financeTableBody">
          <tr><td colspan="7">
            <div class="empty-state">
              <div class="icon">💰</div>
              <div class="title">Загрузка операций...</div>
            </div>
          </td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     PAGE: STATS
══════════════════════════════════════════════════════════ -->
<div class="page" id="page-stats">
  <div class="page-header">
    <div>
      <div class="page-title">Статистика</div>
      <div class="page-subtitle">Аналитика продаж и услуг</div>
    </div>
    <select class="form-select" style="width:140px;" id="statsPeriod" onchange="renderStats()">
      <option value="month">Этот месяц</option>
      <option value="week">Эта неделя</option>
      <option value="all">За всё время</option>
    </select>
  </div>
  <div class="grid-4 mb-16">
    <div class="stat-card purple"><div class="stat-icon">📋</div><div class="stat-label">Всего заказов</div><div class="stat-value purple" id="statTotalOrders">—</div></div>
    <div class="stat-card green"><div class="stat-icon">✅</div><div class="stat-label">Выполнено</div><div class="stat-value green" id="statDoneOrders">—</div></div>
    <div class="stat-card cyan"><div class="stat-icon">👥</div><div class="stat-label">Клиентов</div><div class="stat-value cyan" id="statClients">—</div></div>
    <div class="stat-card red"><div class="stat-icon">⚡</div><div class="stat-label">Средний чек</div><div class="stat-value red" id="statAvgCheck">—</div></div>
  </div>
  <div class="grid-2" style="gap:20px;">
    <div class="card"><div class="card-title">По видам услуг</div><div id="statsByService"></div></div>
    <div class="card"><div class="card-title">По категориям</div><div id="statsByCategory"></div></div>
  </div>
  <div class="card mt-16">
    <div class="card-title">Популярные услуги</div>
    <div id="statsServiceBars" style="margin-top:8px;"></div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     PAGE: ACCOUNTING
══════════════════════════════════════════════════════════ -->
<div class="page" id="page-accounting">
  <div class="page-header">
    <div>
      <div class="page-title">Финансовый учёт</div>
      <div class="page-subtitle">Детальная бухгалтерия и отчёты</div>
    </div>
    <button class="btn btn-secondary btn-sm" onclick="window.print()">🖨️ Распечатать</button>
  </div>
  <div class="card mb-16">
    <div class="card-title">Сводный отчёт по месяцам</div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Месяц</th><th>Доходы</th><th>Расходы</th><th>Прибыль</th><th>Заказов</th><th>Маржа</th></tr></thead>
        <tbody id="accountingTable">
          <tr><td colspan="6"><div class="empty-state"><div class="icon">📊</div><div class="title">Загрузка...</div></div></td></tr>
        </tbody>
      </table>
    </div>
  </div>
  <div class="grid-2" style="gap:16px;">
    <div class="card"><div class="card-title">Расходы по категориям</div><div id="expenseByCategory"></div></div>
    <div class="card"><div class="card-title">Доходы по категориям</div><div id="incomeByCategory"></div></div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     PAGE: CLIENTS
══════════════════════════════════════════════════════════ -->
<div class="page" id="page-clients">
  <div class="page-header">
    <div>
      <div class="page-title">База клиентов</div>
      <div class="page-subtitle">CRM — управление клиентами</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
      <div class="search-bar" style="min-width:220px;">
        <svg width="14" height="14" fill="none" stroke="var(--text-muted)" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="8"/>
          <line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" placeholder="Поиск клиента..." id="clientSearch" oninput="renderClients()">
      </div>
      <button class="btn btn-primary btn-sm" onclick="openModal('clientModal')">+ Добавить клиента</button>
    </div>
  </div>
  <div class="grid-auto" id="clientsGrid">
    <div class="empty-state card" style="grid-column:1/-1;">
      <div class="icon">👥</div><div class="title">Загрузка...</div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     PAGE: NOTES
══════════════════════════════════════════════════════════ -->
<div class="page" id="page-notes">
  <div class="page-header">
    <div>
      <div class="page-title">📝 Заметки смены</div>
      <div class="page-subtitle">Важные пометки для следующей смены</div>
    </div>
    <button class="btn btn-primary btn-sm" onclick="openModal('noteModal')">+ Новая заметка</button>
  </div>
  <div class="grid-3" id="notesGrid">
    <div class="empty-state card" style="grid-column:1/-1;">
      <div class="icon">📝</div><div class="title">Загрузка...</div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     PAGE: WAREHOUSE
══════════════════════════════════════════════════════════ -->
<div class="page" id="page-warehouse">
  <div class="page-header">
    <div>
      <div class="page-title">📦 Склад материалов</div>
      <div class="page-subtitle">Остатки бумаги, чернил, расходников</div>
    </div>
    <div style="display:flex;gap:8px;">
      <button class="btn btn-secondary btn-sm" onclick="showWarehouseMovements()">📋 История</button>
      <button class="btn btn-primary    btn-sm" onclick="openModal('warehouseAddModal')">+ Добавить</button>
    </div>
  </div>
  <div class="grid-4 mb-16">
    <div class="stat-card cyan">  <div class="stat-icon">📦</div><div class="stat-label">Всего позиций</div><div class="stat-value cyan"   id="wh_total">—</div></div>
    <div class="stat-card red">   <div class="stat-icon">⚠️</div><div class="stat-label">Заканчивается</div><div class="stat-value red"    id="wh_low">—</div></div>
    <div class="stat-card green"> <div class="stat-icon">✅</div><div class="stat-label">В норме</div>      <div class="stat-value green"  id="wh_ok">—</div></div>
    <div class="stat-card purple"><div class="stat-icon">💰</div><div class="stat-label">Сумма склада</div> <div class="stat-value purple" id="wh_sum">—</div></div>
  </div>
  <div style="display:flex;gap:8px;margin-bottom:16px;align-items:center;">
    <div class="search-bar" style="min-width:220px;">
      <svg width="14" height="14" fill="none" stroke="var(--text-muted)" stroke-width="2" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="8"/>
        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input type="text" placeholder="Поиск..." id="whSearch" oninput="renderWarehouse()">
    </div>
    <select class="form-select" style="width:160px;" id="whCatFilter" onchange="renderWarehouse()">
      <option value="">Все категории</option>
      <option>Бумага</option><option>Чернила / Тонер</option>
      <option>Баннерные материалы</option><option>Плёнки и самоклейка</option>
      <option>Переплётные материалы</option><option>Химия и обслуживание</option>
      <option>Прочее</option>
    </select>
    <select class="form-select" style="width:140px;" id="whStatusFilter" onchange="renderWarehouse()">
      <option value="">Все остатки</option>
      <option value="low">Заканчивается</option>
      <option value="ok">В норме</option>
    </select>
  </div>
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Наименование</th><th>Категория</th><th>Остаток</th><th>Мин.</th><th>Статус</th><th>Цена</th><th>Сумма</th><th>Действия</th></tr>
        </thead>
        <tbody id="warehouseTableBody">
          <tr><td colspan="8"><div class="empty-state"><div class="icon">📦</div><div class="title">Загрузка...</div></div></td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     PAGE: CALENDAR
══════════════════════════════════════════════════════════ -->
<div class="page" id="page-calendar">
  <div class="page-header">
    <div>
      <div class="page-title">📅 Календарь производства</div>
      <div class="page-subtitle">Дедлайны заказов и задачи</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
      <button class="btn btn-secondary btn-sm" onclick="calPrevMonth()">← Назад</button>
      <span id="calMonthLabel" style="font-weight:700;min-width:140px;text-align:center;"></span>
      <button class="btn btn-secondary btn-sm" onclick="calNextMonth()">Вперёд →</button>
      <button class="btn btn-primary   btn-sm" onclick="openModal('calEventModal')">+ Задача</button>
    </div>
  </div>
  <div class="card" style="padding:0;overflow:hidden;">
    <div id="calendarGrid" style="display:grid;grid-template-columns:repeat(7,1fr);"></div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     PAGE: SETTINGS
══════════════════════════════════════════════════════════ -->
<div class="page" id="page-settings">
  <div class="page-header">
    <div>
      <div class="page-title">⚙️ Настройки</div>
      <div class="page-subtitle">Реквизиты, печать, чеки, интеграции</div>
    </div>
    <button class="btn btn-success btn-sm" onclick="saveSettings()">💾 Сохранить всё</button>
  </div>

  <div class="grid-2" style="gap:20px;">
    <div>
      <!-- РЕКВИЗИТЫ -->
      <div class="settings-section">
        <div class="settings-title">🏢 Реквизиты компании</div>
        <div class="form-group"><label class="form-label">Название</label><input class="form-input" id="setCompany" placeholder="ООО «Моя Типография»"></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">ИНН</label><input class="form-input" id="setInn" placeholder="7700000000"></div>
          <div class="form-group"><label class="form-label">ОГРН</label><input class="form-input" id="setOgrn" placeholder="1234567890123"></div>
        </div>
        <div class="form-group"><label class="form-label">Адрес</label><input class="form-input" id="setAddress"></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Телефон</label><input class="form-input" id="setPhone"></div>
          <div class="form-group"><label class="form-label">Email</label><input class="form-input" id="setEmail"></div>
        </div>
        <div class="form-group"><label class="form-label">Сайт</label><input class="form-input" id="setWebsite"></div>
      </div>

      <!-- БАНК -->
      <div class="settings-section">
        <div class="settings-title">🏦 Банковские реквизиты</div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Расчётный счёт</label><input class="form-input" id="setBankAcc"></div>
          <div class="form-group"><label class="form-label">БИК</label><input class="form-input" id="setBik"></div>
        </div>
        <div class="form-group"><label class="form-label">Банк</label><input class="form-input" id="setBankName"></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Кор. счёт</label><input class="form-input" id="setKorAcc"></div>
          <div class="form-group"><label class="form-label">КПП</label><input class="form-input" id="setKpp"></div>
        </div>
      </div>
    </div>

    <div>
      <!-- ПЕЧАТЬ -->
      <div class="settings-section">
        <div class="settings-title">🖨️ Настройки чеков</div>
        <div class="form-group"><label class="form-label">Шапка чека</label><textarea class="form-textarea" id="setReceiptHeader" rows="2"></textarea></div>
        <div class="form-group"><label class="form-label">Подвал чека</label><textarea class="form-textarea" id="setReceiptFooter" rows="2"></textarea></div>
        <div class="form-group"><label class="form-label">ФИО подписанта</label><input class="form-input" id="setSignatory"></div>
        <div class="form-group"><label class="form-label">Должность</label><input class="form-input" id="setSignatoryTitle" placeholder="Менеджер"></div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">НДС</label>
            <select class="form-select" id="setVat">
              <option value="0">Без НДС</option>
              <option value="20">20%</option>
              <option value="10">10%</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Валюта</label>
            <select class="form-select" id="setCurrency">
              <option value="₽">₽ Рубль</option>
              <option value="$">$ Доллар</option>
              <option value="€">€ Евро</option>
            </select>
          </div>
        </div>
      </div>

      <!-- AI -->
      <div class="settings-section">
        <div class="settings-title">🔑 DeepSeek API</div>
        <div class="form-group">
          <label class="form-label">API ключ</label>
          <input class="form-input" id="setApiKey" type="password" placeholder="sk-...">
          <div class="form-hint">🔐 Хранится только на сервере</div>
        </div>
        <div class="form-group">
          <label class="form-label">Модель</label>
          <select class="form-select" id="setApiModel">
            <option value="deepseek-chat">deepseek-chat</option>
            <option value="deepseek-reasoner">deepseek-reasoner</option>
          </select>
        </div>
        <button class="btn btn-secondary btn-sm" onclick="testApiKey()">🧪 Проверить ключ</button>
      </div>

      <!-- TELEGRAM -->
      <div class="settings-section">
        <div class="settings-title">📱 Telegram уведомления</div>
        <div class="form-group">
          <label class="form-label">Bot Token</label>
          <input class="form-input" id="setTgToken" placeholder="1234567890:ABC...">
        </div>
        <div class="form-group">
          <label class="form-label">Chat ID директора</label>
          <input class="form-input" id="setTgBossId" placeholder="123456789">
        </div>
        <button class="btn btn-secondary btn-sm" onclick="testTelegram()">📤 Тест уведомление</button>
      </div>

      <!-- БД -->
      <div class="settings-section">
        <div class="settings-title">🗃️ База данных</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
          <button class="btn btn-secondary btn-sm" onclick="exportDB()">📤 Экспорт JSON</button>
          <button class="btn btn-secondary btn-sm" onclick="importDB()">📥 Импорт JSON</button>
          <button class="btn btn-danger    btn-sm" onclick="clearDB()">🗑️ Очистить всё</button>
        </div>
        <input type="file" id="importFile" accept=".json" style="display:none" onchange="loadImportFile(event)">
        <div id="dbInfo" style="font-size:0.78rem;color:var(--text-muted);padding:10px;background:var(--bg-dark);border-radius:8px;border:1px solid var(--border);">
          <div>Версия БД: <span id="dbVersion" style="color:var(--accent2);">—</span></div>
          <div>Размер: <span id="dbSize" style="color:var(--accent2);">—</span></div>
          <div>Последнее обновление: <span id="dbLastUpdate" style="color:var(--accent2);">—</span></div>
        </div>
      </div>

      <!-- МОДУЛИ -->
      <div class="settings-section">
        <div class="settings-title">🧩 Модули системы</div>
        <div id="modulesGrid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;"></div>
      </div>
    </div>
  </div>
</div>

</main>
</div><!-- /layout -->

<!-- ══════════════════════════════════════════════════════════
     MODALS
══════════════════════════════════════════════════════════ -->

<!-- ORDER MODAL -->
<div class="modal-overlay" id="orderModal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-title">📋 <span id="orderModalTitle">Создание заказа</span></div>
      <button class="modal-close" onclick="closeModal('orderModal')">✕</button>
    </div>
    <input type="hidden" id="ord_edit_id">

    <div class="form-row mb-16">
      <div class="form-group">
        <label class="form-label">№ Заказа</label>
        <input class="form-input" id="ord_num" readonly style="opacity:0.6;">
        <div class="form-hint">💡 Генерируется автоматически</div>
      </div>
      <div class="form-group">
        <label class="form-label">Дата приёма</label>
        <input class="form-input" type="datetime-local" id="ord_date">
      </div>
      <div class="form-group">
        <label class="form-label">Срок выполнения</label>
        <input class="form-input" type="datetime-local" id="ord_deadline">
      </div>
    </div>

    <div class="form-row mb-16">
      <div class="form-group">
        <label class="form-label">Клиент <span style="color:var(--text-muted);font-weight:400;">(необязательно)</span></label>
        <input class="form-input" id="ord_client" placeholder="Иван Иванов" list="clientsList">
        <datalist id="clientsList"></datalist>
      </div>
      <div class="form-group">
        <label class="form-label">Телефон</label>
        <input class="form-input" id="ord_phone" placeholder="+7 (___) ___-__-__">
      </div>
      <div class="form-group">
        <label class="form-label">Менеджер</label>
        <input class="form-input" id="ord_manager" placeholder="Имя менеджера">
      </div>
    </div>

    <div class="section-label">Вид услуги</div>
    <div class="order-service-tabs" id="serviceTabsBar">
      <button class="order-service-tab active" onclick="switchServiceTab('photo',this)">📸 Фото</button>
      <button class="order-service-tab" onclick="switchServiceTab('copy',this)">🖨️ Копи/Хост</button>
      <button class="order-service-tab" onclick="switchServiceTab('banner',this)">🏳️ Баннер</button>
      <button class="order-service-tab" onclick="switchServiceTab('design',this)">🎨 Дизайн</button>
      <button class="order-service-tab" onclick="switchServiceTab('business',this)">💼 Бизнес-печать</button>
      <button class="order-service-tab" onclick="switchServiceTab('wide',this)">🖼️ Широкий формат</button>
      <button class="order-service-tab" onclick="switchServiceTab('promo',this)">🎁 Сувенирка</button>
      <button class="order-service-tab" onclick="switchServiceTab('other',this)">⚙️ Прочее</button>
    </div>

    <!-- SERVICE TABS CONTENT — идентичны оригиналу, сокращаю для читаемости -->
    <div class="service-tab-content active" id="stab-photo">
      <div class="section-label">Фотопечать — параметры</div>
      <div class="form-group">
        <label class="form-label">Формат фото</label>
        <div class="size-matrix" id="sizeMatrix-photo">
          <?php foreach(['10×15','13×18','15×21','20×30','21×30 (А4)','30×40','30×45','40×60','50×70','60×90','70×100','Свой'] as $s): ?>
          <button class="size-btn" onclick="selectSize(this,'photo')"><?= $s ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Кол-во штук</label><input class="form-input" type="number" id="photo_qty" placeholder="1" min="1" oninput="calcTotal()"></div>
        <div class="form-group">
          <label class="form-label">Материал</label>
          <select class="form-select" id="photo_material">
            <option>Глянец</option><option>Матовый</option><option>Холст (Canvas)</option><option>Шёлк</option><option>Металл</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Цена за шт. ₽</label><input class="form-input" type="number" id="photo_price" placeholder="0" oninput="calcTotal()"></div>
      </div>
      <div class="form-group">
        <label class="form-label">Дополнительно</label>
        <div class="checkbox-group">
          <?php foreach(['Ламинация','Обрезка','Рамка','Коллаж','Ретушь','Чёрно-белое','Срочно ×2'] as $opt): ?>
          <label class="checkbox-item" onclick="toggleCheck(this)"><input type="checkbox"><span class="checkbox-dot"></span><?= $opt ?></label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="service-tab-content" id="stab-copy">
      <div class="section-label">Копирование / Распечатка</div>
      <div class="form-group">
        <label class="form-label">Формат бумаги</label>
        <div class="size-matrix">
          <?php foreach(['А6 (105×148)','А5 (148×210)','А4 (210×297)','А3 (297×420)','А2 (420×594)','А1 (594×841)','А0 (841×1189)','Свой'] as $s): ?>
          <button class="size-btn" onclick="selectSize(this,'copy')"><?= $s ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Кол-во листов</label><input class="form-input" type="number" id="copy_qty" placeholder="1" min="1" oninput="calcTotal()"></div>
        <div class="form-group">
          <label class="form-label">Стороны</label>
          <select class="form-select" id="copy_sides"><option>Одностороннее</option><option>Двустороннее</option></select>
        </div>
        <div class="form-group"><label class="form-label">Цена за лист ₽</label><input class="form-input" type="number" id="copy_price" placeholder="0" oninput="calcTotal()"></div>
      </div>
      <div class="form-group">
        <label class="form-label">Параметры</label>
        <div class="checkbox-group">
          <?php foreach(['Цветная','Ч/Б','Плотная бумага','Переплёт пружина','Переплёт клей','Ламинация','Степлер','Срочно ×2'] as $opt): ?>
          <label class="checkbox-item" onclick="toggleCheck(this)"><input type="checkbox"><span class="checkbox-dot"></span><?= $opt ?></label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="service-tab-content" id="stab-banner">
      <div class="section-label">Баннерная печать</div>
      <div class="form-group">
        <label class="form-label">Стандартные размеры (м)</label>
        <div class="size-matrix">
          <?php foreach(['0.5×1','0.6×1.6','1×2','1×3','1×4','1×5','1×10','1.5×3','2×3','2×5','3×6 (Билборд)','4×8','Ситилайт 1.2×1.8','Свой размер'] as $s): ?>
          <button class="size-btn" onclick="selectSize(this,'banner')"><?= $s ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Ширина (м)</label><input class="form-input" type="number" id="ban_w" placeholder="1.0" step="0.1" oninput="calcBannerArea()"></div>
        <div class="form-group"><label class="form-label">Высота (м)</label><input class="form-input" type="number" id="ban_h" placeholder="2.0" step="0.1" oninput="calcBannerArea()"></div>
        <div class="form-group"><label class="form-label">Площадь м²</label><input class="form-input" id="ban_area" readonly style="opacity:0.7;"></div>
        <div class="form-group"><label class="form-label">Цена за м² ₽</label><input class="form-input" type="number" id="ban_price" placeholder="0" oninput="calcBannerArea()"></div>
      </div>
      <div class="form-group"><label class="form-label">Кол-во штук</label><input class="form-input" type="number" id="ban_qty" value="1" min="1" oninput="calcBannerArea()"></div>
      <div class="form-group">
        <label class="form-label">Опции</label>
        <div class="checkbox-group">
          <?php foreach(['Люверсы','Усиленный кант','Подложка пенокартон','Монтаж','Дизайн макета','Баннерная сетка','Frontlit','Backlit','Срочно'] as $opt): ?>
          <label class="checkbox-item" onclick="toggleCheck(this)"><input type="checkbox"><span class="checkbox-dot"></span><?= $opt ?></label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="service-tab-content" id="stab-design">
      <div class="section-label">Дизайн и допечатная подготовка</div>
      <div class="form-group">
        <label class="form-label">Вид работ</label>
        <div class="checkbox-group">
          <?php foreach(['Разработка макета','Правки макета','Логотип','Визитка','Листовка','Буклет','Плакат','Брендбук','Соцсети (пост)','Предпечатная обработка'] as $opt): ?>
          <label class="checkbox-item" onclick="toggleCheck(this)"><input type="checkbox"><span class="checkbox-dot"></span><?= $opt ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Кол-во правок</label><input class="form-input" type="number" id="des_revisions" value="2"></div>
        <div class="form-group"><label class="form-label">Стоимость ₽</label><input class="form-input" type="number" id="des_price" placeholder="0" oninput="calcTotal()"></div>
        <div class="form-group">
          <label class="form-label">Формат файла</label>
          <select class="form-select" id="des_format">
            <option>AI</option><option>CDR</option><option>PSD</option><option>PDF</option><option>PNG</option><option>JPG</option><option>SVG</option>
          </select>
        </div>
      </div>
    </div>

    <div class="service-tab-content" id="stab-business">
      <div class="section-label">Бизнес-печать (полиграфия)</div>
      <div class="form-group">
        <label class="form-label">Вид продукции</label>
        <div class="checkbox-group">
          <?php foreach(['Визитки','Листовки','Буклеты','Брошюры','Каталоги','Плакаты','Наклейки','Бланки','Конверты','Бейджи','Таблички','Самокопирующиеся бланки'] as $opt): ?>
          <label class="checkbox-item" onclick="toggleCheck(this)"><input type="checkbox"><span class="checkbox-dot"></span><?= $opt ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Тираж (шт.)</label><input class="form-input" type="number" id="biz_qty" placeholder="100" oninput="calcTotal()"></div>
        <div class="form-group">
          <label class="form-label">Формат</label>
          <select class="form-select" id="biz_size">
            <option>90×50 (Визитка)</option><option>А6</option><option>А5</option><option>А4</option><option>А3</option><option>Евро (99×210)</option><option>Свой</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Цена за шт. ₽</label><input class="form-input" type="number" id="biz_price" placeholder="0" oninput="calcTotal()"></div>
      </div>
      <div class="form-group">
        <label class="form-label">Дополнительно</label>
        <div class="checkbox-group">
          <?php foreach(['Ламинация глянец','Ламинация матовая','Скругление углов','Тиснение','УФ-лак','Перфорация','Биговка','Срочно'] as $opt): ?>
          <label class="checkbox-item" onclick="toggleCheck(this)"><input type="checkbox"><span class="checkbox-dot"></span><?= $opt ?></label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="service-tab-content" id="stab-wide">
      <div class="section-label">Широкоформатная печать</div>
      <div class="form-group">
        <label class="form-label">Вид продукции</label>
        <div class="checkbox-group">
          <?php foreach(['Фотообои','Печать на холсте','Рулонный баннер (Roll-Up)','Стенд Pop-Up','Наклейки (плёнка)','Витражная плёнка','Пенокартон','ПВХ-плата'] as $opt): ?>
          <label class="checkbox-item" onclick="toggleCheck(this)"><input type="checkbox"><span class="checkbox-dot"></span><?= $opt ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Ширина (см)</label><input class="form-input" type="number" id="wide_w" placeholder="150" oninput="calcWideArea()"></div>
        <div class="form-group"><label class="form-label">Высота (см)</label><input class="form-input" type="number" id="wide_h" placeholder="200" oninput="calcWideArea()"></div>
        <div class="form-group"><label class="form-label">Площадь м²</label><input class="form-input" id="wide_area" readonly style="opacity:0.7;"></div>
        <div class="form-group"><label class="form-label">Цена м² ₽</label><input class="form-input" type="number" id="wide_price" placeholder="0" oninput="calcWideArea()"></div>
      </div>
    </div>

    <div class="service-tab-content" id="stab-promo">
      <div class="section-label">Сувенирная продукция</div>
      <div class="form-group">
        <label class="form-label">Вид продукции</label>
        <div class="checkbox-group">
          <?php foreach(['Кружка с фото','Подушка с фото','Футболка','Холст','Пазл','Фотокнига','Фотомагнит','Брелок','Значок','Пакет с логотипом','Чехол для телефона','Постер'] as $opt): ?>
          <label class="checkbox-item" onclick="toggleCheck(this)"><input type="checkbox"><span class="checkbox-dot"></span><?= $opt ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Кол-во</label><input class="form-input" type="number" id="promo_qty" value="1" min="1" oninput="calcTotal()"></div>
        <div class="form-group"><label class="form-label">Цена за шт. ₽</label><input class="form-input" type="number" id="promo_price" placeholder="0" oninput="calcTotal()"></div>
      </div>
    </div>

    <div class="service-tab-content" id="stab-other">
      <div class="section-label">Прочие услуги</div>
      <div class="form-group"><label class="form-label">Описание</label><textarea class="form-textarea" id="other_desc" placeholder="Опишите услугу..."></textarea></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Кол-во</label><input class="form-input" type="number" id="other_qty" value="1" oninput="calcTotal()"></div>
        <div class="form-group"><label class="form-label">Стоимость ₽</label><input class="form-input" type="number" id="other_price" placeholder="0" oninput="calcTotal()"></div>
      </div>
    </div>

    <hr class="sep">

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Категория бизнеса</label>
        <select class="form-select" id="ord_bizcat">
          <?php foreach(['Частный клиент','Малый бизнес','Корпоративный','Государственный','Образование','Медицина','Ивент / Мероприятие','Строительство / Недвижимость','Торговля / Ритейл','Общепит / Рестораны','Другое'] as $cat): ?>
          <option><?= $cat ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Статус</label>
        <select class="form-select" id="ord_status">
          <option value="new">🆕 Новый</option>
          <option value="work">⚙️ В работе</option>
          <option value="ready">✅ Готов</option>
          <option value="done">📦 Выдан</option>
          <option value="cancel">❌ Отменён</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Способ оплаты</label>
        <select class="form-select" id="ord_payment">
          <option>Наличные</option><option>Карта</option>
          <option>Безнал (счёт)</option><option>QR / СБП</option>
          <option>Предоплата</option><option>В кредит</option>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Комментарий</label>
      <textarea class="form-textarea" id="ord_comment" placeholder="Пожелания клиента..."></textarea>
    </div>

    <!-- ИТОГО -->
    <div style="background:linear-gradient(135deg,rgba(124,58,237,0.15),rgba(6,182,212,0.1));border:1px solid rgba(124,58,237,0.3);border-radius:12px;padding:16px;display:flex;align-items:center;justify-content:space-between;">
      <div>
        <div style="font-size:0.8rem;color:var(--text-muted);">ИТОГО К ОПЛАТЕ</div>
        <div style="font-size:2rem;font-weight:900;color:var(--accent2);" id="ordTotalDisplay">0 ₽</div>
      </div>
      <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-end;">
        <div style="display:flex;align-items:center;gap:8px;">
          <label style="font-size:0.8rem;color:var(--text-muted);">Итоговая сумма ₽</label>
          <input class="form-input" type="number" id="ord_total" style="width:130px;" placeholder="0" oninput="updateTotalDisplay()">
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
          <label style="font-size:0.8rem;color:var(--text-muted);">Предоплата ₽</label>
          <input class="form-input" type="number" id="ord_prepay" style="width:130px;" placeholder="0">
        </div>
      </div>
    </div>

    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('orderModal')">Отмена</button>
      <button class="btn btn-secondary" onclick="printOrderForm('client')">🖨️ Чек клиенту</button>
      <button class="btn btn-secondary" onclick="printOrderForm('manager')">📋 Бланк менеджера</button>
      <button class="btn btn-primary"   onclick="saveOrder()">💾 Сохранить заказ</button>
    </div>
  </div>
</div>

<!-- INCOME MODAL -->
<div class="modal-overlay" id="incomeModal">
  <div class="modal modal-sm">
    <div class="modal-header">
      <div class="modal-title">💰 Внести доход</div>
      <button class="modal-close" onclick="closeModal('incomeModal')">✕</button>
    </div>
    <div class="form-group"><label class="form-label">Дата и время</label><input class="form-input" type="datetime-local" id="inc_date"></div>
    <div class="form-group">
      <label class="form-label">Категория</label>
      <select class="form-select" id="inc_cat">
        <?php foreach(['Фотопечать','Копирование / Распечатка','Баннерная печать','Широкоформатная печать','Дизайн','Бизнес-полиграфия','Сувенирная продукция','Ламинация','Переплёт','Корпоративный заказ','Предоплата','Прочее'] as $c): ?>
        <option><?= $c ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label class="form-label">Описание</label><input class="form-input" id="inc_desc" placeholder="За что получены деньги..."></div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Сумма ₽</label><input class="form-input" type="number" id="inc_amount" placeholder="0"></div>
      <div class="form-group">
        <label class="form-label">Способ оплаты</label>
        <select class="form-select" id="inc_method">
          <option>Наличные</option><option>Карта</option><option>Безнал</option><option>QR / СБП</option>
        </select>
      </div>
    </div>
    <div class="form-group"><label class="form-label">Клиент / Источник</label><input class="form-input" id="inc_client" placeholder="(необязательно)"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('incomeModal')">Отмена</button>
      <button class="btn btn-success"   onclick="saveIncome()">✓ Сохранить доход</button>
    </div>
  </div>
</div>

<!-- EXPENSE MODAL -->
<div class="modal-overlay" id="expenseModal">
  <div class="modal modal-sm">
    <div class="modal-header">
      <div class="modal-title">📤 Внести расход</div>
      <button class="modal-close" onclick="closeModal('expenseModal')">✕</button>
    </div>
    <div class="form-group"><label class="form-label">Дата и время</label><input class="form-input" type="datetime-local" id="exp_date"></div>
    <div class="form-group">
      <label class="form-label">Категория</label>
      <select class="form-select" id="exp_cat">
        <?php foreach(['Расходные материалы (чернила, бумага)','Аренда помещения','Зарплата сотрудникам','Коммунальные услуги','Техобслуживание оборудования','Закупка оборудования','Реклама / Маркетинг','Интернет / Связь','Налоги / Взносы','Транспорт / Доставка','Хоз. нужды','Прочее'] as $c): ?>
        <option><?= $c ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label class="form-label">Описание</label><input class="form-input" id="exp_desc" placeholder="На что потрачено..."></div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Сумма ₽</label><input class="form-input" type="number" id="exp_amount" placeholder="0"></div>
      <div class="form-group">
        <label class="form-label">Способ оплаты</label>
        <select class="form-select" id="exp_method">
          <option>Наличные</option><option>Карта</option><option>Безнал</option><option>QR / СБП</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('expenseModal')">Отмена</button>
      <button class="btn btn-danger"    onclick="saveExpense()">✓ Сохранить расход</button>
    </div>
  </div>
</div>

<!-- CLIENT MODAL -->
<div class="modal-overlay" id="clientModal">
  <div class="modal modal-sm">
    <div class="modal-header">
      <div class="modal-title">👤 <span id="clientModalTitle">Добавить клиента</span></div>
      <button class="modal-close" onclick="closeModal('clientModal')">✕</button>
    </div>
    <input type="hidden" id="cli_edit_id">
    <div class="form-row">
      <div class="form-group"><label class="form-label">Имя / Название</label><input class="form-input" id="cli_name" placeholder="Иван Иванов / ООО Ромашка"></div>
      <div class="form-group">
        <label class="form-label">Тип клиента</label>
        <select class="form-select" id="cli_type">
          <option>Физическое лицо</option><option>ИП</option><option>ООО / ЗАО</option><option>Государственная структура</option>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Телефон</label><input class="form-input" id="cli_phone" placeholder="+7 (999) 000-00-00"></div>
      <div class="form-group"><label class="form-label">Email</label><input class="form-input" id="cli_email"></div>
    </div>
    <div class="form-group"><label class="form-label">Адрес / Город</label><input class="form-input" id="cli_address"></div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">ИНН (для юр. лиц)</label><input class="form-input" id="cli_inn"></div>
      <div class="form-group"><label class="form-label">Скидка %</label><input class="form-input" type="number" id="cli_discount" placeholder="0" min="0" max="100"></div>
    </div>
    <div class="form-group"><label class="form-label">Заметки</label><textarea class="form-textarea" id="cli_notes" placeholder="Предпочтения клиента..."></textarea></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('clientModal')">Отмена</button>
      <button class="btn btn-primary"   onclick="saveClient()">💾 Сохранить</button>
    </div>
  </div>
</div>

<!-- NOTE MODAL -->
<div class="modal-overlay" id="noteModal">
  <div class="modal modal-sm">
    <div class="modal-header">
      <div class="modal-title">📝 Новая заметка</div>
      <button class="modal-close" onclick="closeModal('noteModal')">✕</button>
    </div>
    <div class="form-group"><label class="form-label">Заголовок</label><input class="form-input" id="note_title" placeholder="Краткий заголовок..."></div>
    <div class="form-group"><label class="form-label">Текст</label><textarea class="form-textarea" id="note_body" rows="5" placeholder="Важная информация для следующей смены..."></textarea></div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Приоритет</label>
        <select class="form-select" id="note_priority">
          <option value="normal">Обычная</option>
          <option value="info">Информация</option>
          <option value="important">Важная</option>
          <option value="urgent">Срочно!</option>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Смена</label><input class="form-input" id="note_shift" placeholder="Имя менеджера"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('noteModal')">Отмена</button>
      <button class="btn btn-primary"   onclick="saveNote()">💾 Сохранить</button>
    </div>
  </div>
</div>

<!-- WAREHOUSE ADD MODAL -->
<div class="modal-overlay" id="warehouseAddModal">
  <div class="modal modal-sm">
    <div class="modal-header">
      <div class="modal-title">📦 Добавить позицию</div>
      <button class="modal-close" onclick="closeModal('warehouseAddModal')">✕</button>
    </div>
    <div class="form-group"><label class="form-label">Наименование</label><input class="form-input" id="wh_name" placeholder="Бумага А4 80г/м²"></div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Категория</label>
        <select class="form-select" id="wh_cat">
          <option>Бумага</option><option>Чернила / Тонер</option><option>Баннерные материалы</option>
          <option>Плёнки и самоклейка</option><option>Переплётные материалы</option>
          <option>Химия и обслуживание</option><option>Прочее</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Единица</label>
        <select class="form-select" id="wh_unit">
          <option>шт</option><option>пачка</option><option>рулон</option>
          <option>литр</option><option>кг</option><option>м²</option><option>м</option>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Текущий остаток</label><input class="form-input" type="number" id="wh_qty" placeholder="10"></div>
      <div class="form-group"><label class="form-label">Минимальный остаток</label><input class="form-input" type="number" id="wh_minqty" placeholder="2"></div>
    </div>
    <div class="form-group"><label class="form-label">Цена за единицу ₽</label><input class="form-input" type="number" id="wh_price" placeholder="0"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('warehouseAddModal')">Отмена</button>
      <button class="btn btn-primary"   onclick="saveWarehouseItem()">💾 Добавить</button>
    </div>
  </div>
</div>

<!-- WAREHOUSE ACTION MODAL -->
<div class="modal-overlay" id="warehouseActionModal">
  <div class="modal modal-sm">
    <div class="modal-header">
      <div class="modal-title" id="whActionTitle">Операция со складом</div>
      <button class="modal-close" onclick="closeModal('warehouseActionModal')">✕</button>
    </div>
    <div style="padding:12px;background:var(--bg-dark);border-radius:10px;margin-bottom:16px;">
      <div style="font-weight:700;" id="whActionItemName">—</div>
      <div class="text-xs text-muted">Текущий остаток: <span id="whActionCurrentQty">0</span></div>
    </div>
    <div class="form-group"><label class="form-label">Количество</label><input class="form-input" type="number" id="wh_action_qty" placeholder="1" min="0.1" step="0.1"></div>
    <input type="hidden" id="wh_action_id">
    <input type="hidden" id="wh_action_type">
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('warehouseActionModal')">Отмена</button>
      <button class="btn btn-primary"   onclick="executeWarehouseAction()">✓ Выполнить</button>
    </div>
  </div>
</div>

<!-- STAFF MODAL -->
<div class="modal-overlay" id="staffAddModal">
  <div class="modal modal-sm">
    <div class="modal-header">
      <div class="modal-title">👤 Добавить сотрудника</div>
      <button class="modal-close" onclick="closeModal('staffAddModal')">✕</button>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Имя</label><input class="form-input" id="st_name" placeholder="Иван Иванов"></div>
      <div class="form-group">
        <label class="form-label">Должность</label>
        <select class="form-select" id="st_role">
          <option>Менеджер</option><option>Оператор печати</option><option>Дизайнер</option><option>Директор</option><option>Кассир</option>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Телефон</label><input class="form-input" id="st_phone"></div>
      <div class="form-group">
        <label class="form-label">PIN (4 цифры)</label>
        <input class="form-input" id="st_pin" type="password" placeholder="****" maxlength="4">
        <div class="form-hint">🔐 Для входа в систему</div>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Цвет в календаре</label>
      <input type="color" id="st_color" value="#7c3aed" style="width:60px;height:36px;border-radius:8px;border:1px solid var(--border);background:var(--bg-dark);cursor:pointer;">
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('staffAddModal')">Отмена</button>
      <button class="btn btn-primary"   onclick="saveStaff()">💾 Добавить</button>
    </div>
  </div>
</div>

<!-- CALENDAR EVENT MODAL -->
<div class="modal-overlay" id="calEventModal">
  <div class="modal modal-sm">
    <div class="modal-header">
      <div class="modal-title">📅 Новая задача</div>
      <button class="modal-close" onclick="closeModal('calEventModal')">✕</button>
    </div>
    <div class="form-group"><label class="form-label">Заголовок</label><input class="form-input" id="cal_title" placeholder="Текст задачи..."></div>
    <div class="form-row">
      <div class="form-group"><label class="form-label">Дата</label><input class="form-input" type="date" id="cal_date"></div>
      <div class="form-group"><label class="form-label">Время</label><input class="form-input" type="time" id="cal_time"></div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Тип</label>
        <select class="form-select" id="cal_type">
          <option value="task">Задача</option>
          <option value="deadline">Дедлайн</option>
          <option value="meeting">Встреча</option>
          <option value="delivery">Доставка</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Цвет</label>
        <input type="color" id="cal_color" value="#7c3aed" style="width:60px;height:36px;border-radius:8px;border:1px solid var(--border);background:var(--bg-dark);cursor:pointer;">
      </div>
    </div>
    <div class="form-group"><label class="form-label">Заметка</label><textarea class="form-textarea" id="cal_note" rows="2"></textarea></div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('calEventModal')">Отмена</button>
      <button class="btn btn-primary"   onclick="saveCalEvent()">💾 Сохранить</button>
    </div>
  </div>
</div>

<!-- ORDER DETAIL OVERLAY -->
<div class="order-detail-overlay" id="orderDetailOverlay">
  <div class="order-detail-modal" id="orderDetailModal">
    <div class="od-header">
      <div class="od-icon-wrap" id="odIconWrap" style="background:linear-gradient(135deg,rgba(124,58,237,0.3),rgba(6,182,212,0.2));">📋</div>
      <div class="od-titles">
        <div class="od-order-num" id="odOrderNum">—</div>
        <div class="od-client-name" id="odClientName">—</div>
        <div class="od-sub" id="odSub">—</div>
      </div>
      <button class="od-close" onclick="closeOrderDetail()">✕</button>
    </div>
    <div class="od-body">
      <div class="od-status-bar" id="odStatusBar"></div>
      <div class="od-grid" id="odInfoGrid"></div>
      <div id="odComment"></div>
      <div id="odParams"></div>
    </div>
    <div class="od-actions" id="odActions"></div>
  </div>
</div>

<!-- PRINT AREA -->
<div id="printArea" style="display:none;"></div>

<!-- ─── SCRIPTS ───────────────────────────────────────────── -->
<script src="js/app.js"></script>
<script src="js/modules.js"></script>

</body>
</html>