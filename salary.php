<?php
/**
 * @name        Сотрудники и Зарплата
 * @icon        👔
 * @description База сотрудников, смены, зарплата, бонусы
 * @version     5.0
 * @sidebar     true
 * @color       #10b981
 */

if (!isset($moduleDB['salary']))              $moduleDB['salary']              = [];
if (!isset($moduleDB['salary']['records']))   $moduleDB['salary']['records']   = [];
if (!isset($moduleDB['salary']['employees'])) $moduleDB['salary']['employees'] = [];
if (!isset($moduleDB['salary']['shifts']))    $moduleDB['salary']['shifts']    = [];
if (!isset($moduleDB['finance']))             $moduleDB['finance']             = [];

switch ($moduleAction) {

    case 'list':
        echo json_encode(['ok'=>true,'data'=>$moduleDB['salary']['records']]);
        break;

    case 'employees':
        echo json_encode(['ok'=>true,'data'=>$moduleDB['salary']['employees']]);
        break;

    case 'addEmployee': {
        $emp = [
            'id'          => 'emp_'.time().rand(100,999),
            'name'        => $moduleBody['name']         ?? '',
            'position'    => $moduleBody['position']     ?? '',
            'phone'       => $moduleBody['phone']        ?? '',
            'email'       => $moduleBody['email']        ?? '',
            'salary'      => floatval($moduleBody['salary']   ?? 0),
            'bonusPct'    => floatval($moduleBody['bonusPct'] ?? 0),
            'schedule'    => $moduleBody['schedule']     ?? '5/2',
            'startDate'   => $moduleBody['startDate']    ?? date('Y-m-d'),
            'birthDate'   => $moduleBody['birthDate']    ?? '',
            'status'      => $moduleBody['status']       ?? 'active',
            'color'       => $moduleBody['color']        ?? '#7c3aed',
            'notes'       => $moduleBody['notes']        ?? '',
            'photo'       => $moduleBody['photo']        ?? '',
            'address'     => $moduleBody['address']      ?? '',
            'passportNote'=> $moduleBody['passportNote'] ?? '',
            'pin'         => $moduleBody['pin']          ?? '',
        ];
        $moduleDB['salary']['employees'][] = $emp;
        writeDB($moduleDB);
        writeLog('EMP_ADD', $emp['name']);
        echo json_encode(['ok'=>true,'data'=>$emp]);
        break;
    }

    case 'updateEmployee': {
        $id    = $moduleBody['id'] ?? null;
        $found = false;
        foreach ($moduleDB['salary']['employees'] as &$e) {
            if ((string)$e['id'] === (string)$id) {
                foreach (['name','position','phone','email','salary','bonusPct',
                          'schedule','status','color','notes','photo',
                          'address','birthDate','passportNote','startDate','pin'] as $f) {
                    if (isset($moduleBody[$f])) $e[$f] = $moduleBody[$f];
                }
                $found = true;
                break;
            }
        }
        unset($e);
        if (!$found) { echo json_encode(['ok'=>false,'error'=>'Сотрудник не найден']); break; }
        writeDB($moduleDB);
        echo json_encode(['ok'=>true]);
        break;
    }

    case 'deleteEmployee': {
        $id = $_GET['id'] ?? $moduleBody['id'] ?? null;
        if (!$id) { echo json_encode(['ok'=>false,'error'=>'Нет ID']); break; }
        $moduleDB['salary']['employees'] = array_values(array_filter(
            $moduleDB['salary']['employees'],
            fn($e) => (string)$e['id'] !== (string)$id
        ));
        writeDB($moduleDB);
        writeLog('EMP_DELETE','ID: '.$id);
        echo json_encode(['ok'=>true]);
        break;
    }

    case 'add': {
        $record = [
            'id'        => 'sal_'.time().rand(100,999),
            'staffName' => $moduleBody['staffName'] ?? '',
            'staffId'   => $moduleBody['staffId']   ?? '',
            'type'      => $moduleBody['type']       ?? 'salary',
            'amount'    => floatval($moduleBody['amount'] ?? 0),
            'period'    => $moduleBody['period']     ?? date('Y-m'),
            'note'      => $moduleBody['note']       ?? '',
            'date'      => date('Y-m-d H:i:s'),
            'revenue'   => floatval($moduleBody['revenue'] ?? 0),
        ];
        array_unshift($moduleDB['salary']['records'], $record);

        if ($record['type'] !== 'fine' && $record['amount'] > 0) {
            $lbls = [
                'salary'        => 'Зарплата',
                'advance'       => 'Аванс',
                'bonus'         => 'Премия',
                'revenue_bonus' => 'Бонус с выручки',
            ];
            $lbl = $lbls[$record['type']] ?? 'Выплата';
            $moduleDB['finance'][] = [
                'id'         => 'fin_sal_'.time().rand(1000,9999),
                'type'       => 'expense',
                'date'       => $record['date'],
                'category'   => 'Зарплата и выплаты',
                'desc'       => $lbl.': '.$record['staffName'].
                                ($record['note'] ? ' — '.$record['note'] : ''),
                'amount'     => $record['amount'],
                'method'     => 'Безнал',
                'client'     => $record['staffName'],
                '_salaryId'  => $record['id'],
                'salaryType' => $record['type'],
            ];
        }
        writeDB($moduleDB);
        writeLog('SAL_ADD',$record['staffName'].' '.$record['amount'].'₽');
        echo json_encode(['ok'=>true,'data'=>$record]);
        break;
    }

    case 'delete': {
        $id = $_GET['id'] ?? $moduleBody['id'] ?? null;
        if (!$id) { echo json_encode(['ok'=>false,'error'=>'Нет ID']); break; }
        $found = null;
        foreach ($moduleDB['salary']['records'] as $r) {
            if ((string)$r['id'] === (string)$id) { $found = $r; break; }
        }
        $moduleDB['salary']['records'] = array_values(array_filter(
            $moduleDB['salary']['records'],
            fn($r) => (string)$r['id'] !== (string)$id
        ));
        if ($found && isset($moduleDB['finance'])) {
            $moduleDB['finance'] = array_values(array_filter(
                $moduleDB['finance'],
                fn($f) => ($f['_salaryId'] ?? '') !== (string)$id
            ));
        }
        writeDB($moduleDB);
        writeLog('SAL_DELETE','ID: '.$id);
        echo json_encode(['ok'=>true]);
        break;
    }

    case 'summary': {
        $period  = $_GET['period'] ?? date('Y-m');
        $records = array_filter(
            $moduleDB['salary']['records'],
            fn($r) => $r['period'] === $period
        );
        $byStaff = [];
        foreach ($records as $r) {
            $n = $r['staffName'];
            if (!isset($byStaff[$n]))
                $byStaff[$n] = ['salary'=>0,'advance'=>0,'bonus'=>0,'fine'=>0,'revenue_bonus'=>0];
            $byStaff[$n][$r['type']] = ($byStaff[$n][$r['type']] ?? 0) + $r['amount'];
        }
        echo json_encode(['ok'=>true,'period'=>$period,'byStaff'=>$byStaff]);
        break;
    }

    case 'listShifts': {
        $from   = $_GET['from'] ?? date('Y-m-01');
        $to     = $_GET['to']   ?? date('Y-m-t');
        $shifts = array_filter(
            $moduleDB['salary']['shifts'] ?? [],
            fn($s) => $s['date'] >= $from && $s['date'] <= $to
        );
        echo json_encode(['ok'=>true,'data'=>array_values($shifts)]);
        break;
    }

    case 'addShift': {
        $shift = [
            'id'      => 'sh_'.time().rand(100,999),
            'empId'   => $moduleBody['empId']   ?? '',
            'empName' => $moduleBody['empName']  ?? '',
            'date'    => $moduleBody['date']     ?? date('Y-m-d'),
            'start'   => $moduleBody['start']    ?? '09:00',
            'end'     => $moduleBody['end']      ?? '18:00',
            'revenue' => floatval($moduleBody['revenue'] ?? 0),
            'note'    => $moduleBody['note']     ?? '',
            'color'   => $moduleBody['color']    ?? '#7c3aed',
            'branch'  => $moduleBody['branch']   ?? '',
        ];
        $moduleDB['salary']['shifts'][] = $shift;
        writeDB($moduleDB);
        writeLog('SHIFT_ADD',$shift['empName'].' '.$shift['date']);
        echo json_encode(['ok'=>true,'data'=>$shift]);
        break;
    }

    case 'deleteShift': {
        $id = $_GET['id'] ?? $moduleBody['id'] ?? null;
        if (!$id) { echo json_encode(['ok'=>false,'error'=>'Нет ID']); break; }
        $moduleDB['salary']['shifts'] = array_values(array_filter(
            $moduleDB['salary']['shifts'] ?? [],
            fn($s) => (string)$s['id'] !== (string)$id
        ));
        writeDB($moduleDB);
        writeLog('SHIFT_DELETE','ID: '.$id);
        echo json_encode(['ok'=>true]);
        break;
    }

    default:
        echo json_encode(['error'=>'Неизвестное действие: '.$moduleAction]);
        break;
}
?>
<!--MODULE_JS_START-->
<script>
(function () {
'use strict';

/* ─── Защита от двойной регистрации / слетания ─── */
if (window.__SAL_REGISTERED) {
    /* Модуль уже есть — просто перерендерим */
    if (window.__SAL && typeof window.__SAL.render === 'function') {
        window.__SAL.render();
    }
    return;
}
window.__SAL_REGISTERED = true;

/* ════════════════════════════════════════════
   УТИЛИТЫ
════════════════════════════════════════════ */
function fmoney(v) {
    return typeof formatMoney === 'function'
        ? formatMoney(v)
        : (parseFloat(v)||0).toLocaleString('ru-RU') + ' ₽';
}
function fdate(s) {
    return typeof formatDate === 'function' ? formatDate(s) : (s || '—');
}
function el(id)       { return document.getElementById(id); }
function val(id, def) { var e = el(id); return e ? e.value : (def !== undefined ? def : ''); }
function setVal(id,v) { var e = el(id); if (e) e.value = (v == null ? '' : v); }
function pad2(n)      { return String(n).padStart(2,'0'); }
function esc(s)       { return String(s||'').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function buildPeriodOptions() {
    var html = '';
    for (var i = 0; i < 6; i++) {
        var d = new Date();
        d.setMonth(d.getMonth() - i);
        var v = d.getFullYear() + '-' + pad2(d.getMonth()+1);
        var l = d.toLocaleDateString('ru', {month:'long', year:'numeric'});
        html += '<option value="'+v+'">'+l+'</option>';
    }
    return html;
}
function curPeriod() {
    return val('sal_period_filter') || new Date().toISOString().slice(0,7);
}

/* ════════════════════════════════════════════
   КОНСТАНТЫ
════════════════════════════════════════════ */
var STATUS_LABEL = { active:'Работает', vacation:'Отпуск', sick:'Больничный', fired:'Уволен' };
var STATUS_ICON  = { active:'✅', vacation:'🏖', sick:'🤒', fired:'❌' };
var STATUS_COLOR = { active:'#10b981', vacation:'#f59e0b', sick:'#3b82f6', fired:'#ef4444' };
var STATUS_BG    = { active:'rgba(16,185,129,.15)', vacation:'rgba(245,158,11,.15)',
                     sick:'rgba(59,130,246,.15)',   fired:'rgba(239,68,68,.15)' };
var PAY_LABEL    = { salary:'Зарплата', advance:'Аванс', bonus:'Премия',
                     revenue_bonus:'Бонус', fine:'Штраф' };
var PAY_ICON     = { salary:'💰', advance:'💳', bonus:'🎁', revenue_bonus:'📈', fine:'⚠️' };
var PAY_COLOR    = { salary:'var(--accent3)', advance:'var(--accent2)', bonus:'var(--accent)',
                     revenue_bonus:'#f59e0b', fine:'var(--danger)' };

/* ════════════════════════════════════════════
   CSS — ВСТАВЛЯЕМ ОДИН РАЗ, ЧИСТО
════════════════════════════════════════════ */
var STYLE_ID = 'sal-v5-styles';
function injectStyles() {
    if (el(STYLE_ID)) el(STYLE_ID).remove();   /* всегда пересоздаём чтобы не было протухших */
    var s = document.createElement('style');
    s.id  = STYLE_ID;
    s.textContent = `

/* ══ GRID КАРТОЧЕК ══ */
.sal-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 18px;
}

/* ══ КАРТОЧКА ══ */
.sal-card {
    position: relative;
    border-radius: 20px;
    overflow: hidden;
    background: rgba(255,255,255,0.04);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    border: 1px solid rgba(255,255,255,0.09);
    box-shadow: 0 8px 32px rgba(0,0,0,.32), inset 0 1px 0 rgba(255,255,255,.07);
    transition: transform .22s cubic-bezier(.34,1.56,.64,1), box-shadow .22s;
    cursor: pointer;
}
.sal-card:hover {
    transform: translateY(-5px) scale(1.01);
    box-shadow: 0 20px 54px rgba(0,0,0,.42), inset 0 1px 0 rgba(255,255,255,.10);
}
.sal-card.fired { opacity: .5; filter: grayscale(.5); }

/* ══ ФОТО-БАННЕР ══ */
.sal-card-photo {
    width: 100%;
    aspect-ratio: 4/3;
    object-fit: cover;
    object-position: center top;
    display: block;
    background: var(--bg-dark);
}
.sal-card-photo-placeholder {
    width: 100%;
    aspect-ratio: 4/3;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
    font-weight: 900;
    color: rgba(255,255,255,.7);
    letter-spacing: -2px;
    user-select: none;
}

/* Стеклянный оверлей поверх фото */
.sal-card-photo-overlay {
    position: absolute;
    top: 0; left: 0; right: 0;
    aspect-ratio: 4/3;
    background: linear-gradient(to bottom,
        rgba(0,0,0,0) 40%,
        rgba(0,0,0,.72) 100%);
    pointer-events: none;
}

/* Бейдж статуса — поверх фото */
.sal-card-status-badge {
    position: absolute;
    top: 10px; right: 10px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: .65rem;
    font-weight: 700;
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,.14);
    letter-spacing: .3px;
}

/* Имя поверх низа фото */
.sal-card-name-overlay {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    /* высота = aspect-ratio 4/3 от контейнера */
    padding: 0 14px 10px;
}
.sal-card-name-overlay .sal-cn-name {
    font-size: 1rem;
    font-weight: 800;
    color: #fff;
    text-shadow: 0 1px 6px rgba(0,0,0,.7);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    line-height: 1.2;
}
.sal-card-name-overlay .sal-cn-pos {
    font-size: .7rem;
    color: rgba(255,255,255,.75);
    text-shadow: 0 1px 4px rgba(0,0,0,.6);
    margin-top: 1px;
}

/* ══ НИЖНЯЯ ЧАСТЬ КАРТОЧКИ ══ */
.sal-card-body {
    padding: 12px 14px 14px;
}

/* Три мини-стата */
.sal-card-stats {
    display: grid;
    grid-template-columns: repeat(3,1fr);
    gap: 6px;
    margin-bottom: 10px;
}
.sal-stat {
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 10px;
    padding: 7px 4px;
    text-align: center;
}
.sal-stat-val {
    font-size: .88rem;
    font-weight: 800;
    line-height: 1;
}
.sal-stat-lbl {
    font-size: .56rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .4px;
    margin-top: 3px;
}

/* Нижняя строка: оклад + кнопка */
.sal-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 10px;
    border-top: 1px solid rgba(255,255,255,.06);
}
.sal-card-salary {
    font-size: .85rem;
    font-weight: 800;
    color: #10b981;
}
.sal-card-salary-lbl {
    font-size: .6rem;
    color: var(--text-muted);
    font-weight: 600;
    text-transform: uppercase;
}
.sal-card-open-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    font-size: .72rem;
    font-weight: 700;
    background: rgba(124,58,237,.2);
    color: var(--accent);
    border: 1px solid rgba(124,58,237,.3);
    transition: background .15s, transform .1s;
    letter-spacing: .2px;
}
.sal-card-open-btn:hover {
    background: rgba(124,58,237,.35);
    transform: scale(1.04);
}

/* ══ МОДАЛКА-ПРОФИЛЬ ══ */
.sal-profile-header {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 18px;
}
.sal-profile-photo {
    width: 100%;
    height: 200px;
    object-fit: cover;
    object-position: center top;
    display: block;
}
.sal-profile-photo-placeholder {
    width: 100%;
    height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 6rem;
    font-weight: 900;
    color: rgba(255,255,255,.8);
}
.sal-profile-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to bottom, rgba(0,0,0,0) 35%, rgba(0,0,0,.85) 100%);
    pointer-events: none;
}
.sal-profile-info {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    padding: 16px;
}
.sal-profile-name {
    font-size: 1.3rem;
    font-weight: 800;
    color: #fff;
    text-shadow: 0 1px 8px rgba(0,0,0,.7);
    margin-bottom: 2px;
}
.sal-profile-pos {
    font-size: .82rem;
    color: rgba(255,255,255,.75);
    text-shadow: 0 1px 4px rgba(0,0,0,.6);
}
.sal-profile-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: .68rem;
    font-weight: 700;
    margin-top: 6px;
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,.15);
}

/* Кнопки действий в профиле */
.sal-profile-actions {
    display: grid;
    grid-template-columns: repeat(4,1fr);
    gap: 8px;
    margin-bottom: 18px;
}
.sal-act-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    padding: 10px 6px;
    border-radius: 14px;
    border: 1px solid rgba(255,255,255,.09);
    background: rgba(255,255,255,.05);
    backdrop-filter: blur(10px);
    cursor: pointer;
    transition: background .15s, transform .12s;
    text-align: center;
}
.sal-act-btn:hover { background: rgba(255,255,255,.1); transform: translateY(-2px); }
.sal-act-btn .icon { font-size: 1.3rem; line-height: 1; }
.sal-act-btn .lbl  { font-size: .62rem; font-weight: 700; color: var(--text-muted);
                      text-transform: uppercase; letter-spacing: .3px; }
.sal-act-btn.danger { border-color: rgba(239,68,68,.25); background: rgba(239,68,68,.08); }
.sal-act-btn.danger:hover { background: rgba(239,68,68,.18); }
.sal-act-btn.primary { border-color: rgba(124,58,237,.3); background: rgba(124,58,237,.12); }
.sal-act-btn.primary:hover { background: rgba(124,58,237,.25); }
.sal-act-btn.success { border-color: rgba(16,185,129,.3); background: rgba(16,185,129,.1); }
.sal-act-btn.success:hover { background: rgba(16,185,129,.22); }

/* Инфо-плитки */
.sal-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 14px;
}
.sal-info-tile {
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 12px;
    padding: 10px 12px;
}
.sal-info-tile-lbl {
    font-size: .6rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .4px;
    margin-bottom: 3px;
}
.sal-info-tile-val {
    font-size: .82rem;
    font-weight: 600;
}

/* PIN плитка */
.sal-pin-tile {
    background: rgba(124,58,237,.08);
    border: 1px solid rgba(124,58,237,.2);
    border-radius: 12px;
    padding: 10px 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
}
.sal-pin-val {
    font-family: monospace;
    font-size: 1.1rem;
    font-weight: 800;
    letter-spacing: 4px;
    flex: 1;
}
.sal-pin-eye {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text-muted);
    font-size: 1rem;
    padding: 2px;
    transition: color .15s;
}
.sal-pin-eye:hover { color: var(--text); }

/* ══ НЕДЕЛЬНЫЙ КАЛЕНДАРЬ ══ */
.sal-week-wrap {
    background: rgba(255,255,255,.03);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 18px;
    overflow: hidden;
}
.sal-week-head {
    display: grid;
    grid-template-columns: 52px repeat(7,1fr);
    border-bottom: 1px solid rgba(255,255,255,.08);
}
.sal-week-head-time {
    padding: 10px;
    background: rgba(255,255,255,.03);
    border-right: 1px solid rgba(255,255,255,.07);
}
.sal-week-head-day {
    padding: 10px 6px;
    text-align: center;
    border-right: 1px solid rgba(255,255,255,.06);
    cursor: default;
}
.sal-week-head-day.today {
    background: rgba(124,58,237,.12);
}
.sal-week-head-day .wday {
    font-size: .65rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: .5px;
}
.sal-week-head-day .wdate {
    font-size: 1.1rem;
    font-weight: 800;
    margin-top: 2px;
}
.sal-week-head-day.today .wdate {
    color: var(--accent);
}
.sal-week-body {
    display: grid;
    grid-template-columns: 52px repeat(7,1fr);
    max-height: 540px;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,.1) transparent;
}
.sal-week-body::-webkit-scrollbar { width: 4px; }
.sal-week-body::-webkit-scrollbar-thumb { background: rgba(255,255,255,.12); border-radius: 4px; }

.sal-time-col {
    display: flex;
    flex-direction: column;
    border-right: 1px solid rgba(255,255,255,.07);
}
.sal-time-cell {
    height: 52px;
    display: flex;
    align-items: flex-start;
    justify-content: flex-end;
    padding: 3px 6px 0 0;
    font-size: .58rem;
    font-weight: 600;
    color: var(--text-muted);
    border-bottom: 1px solid rgba(255,255,255,.04);
    box-sizing: border-box;
    flex-shrink: 0;
}
.sal-day-col {
    display: flex;
    flex-direction: column;
    border-right: 1px solid rgba(255,255,255,.06);
    position: relative;
}
.sal-day-col.today { background: rgba(124,58,237,.04); }
.sal-hour-row {
    height: 52px;
    border-bottom: 1px solid rgba(255,255,255,.04);
    box-sizing: border-box;
    flex-shrink: 0;
    position: relative;
    cursor: pointer;
    transition: background .1s;
}
.sal-hour-row:hover { background: rgba(255,255,255,.03); }

/* Блок смены внутри часового ряда */
.sal-shift-block {
    position: absolute;
    left: 2px; right: 2px;
    border-radius: 8px;
    padding: 3px 6px;
    font-size: .62rem;
    font-weight: 700;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    cursor: pointer;
    z-index: 2;
    box-shadow: 0 2px 8px rgba(0,0,0,.25);
    transition: filter .15s, transform .1s;
    line-height: 1.3;
}
.sal-shift-block:hover {
    filter: brightness(1.2);
    transform: scaleX(1.02);
}

/* Текущее время */
.sal-now-line {
    position: absolute;
    left: 0; right: 0;
    height: 2px;
    background: #ef4444;
    z-index: 5;
    border-radius: 2px;
}
.sal-now-line::before {
    content: '';
    position: absolute;
    left: -1px; top: -4px;
    width: 10px; height: 10px;
    border-radius: 50%;
    background: #ef4444;
}

/* ══ ФОТО ЗАГРУЗКА ══ */
.sal-photo-upload {
    display: flex;
    gap: 14px;
    align-items: flex-start;
    margin-bottom: 16px;
}
.sal-photo-box {
    width: 88px;
    height: 88px;
    border-radius: 16px;
    background: var(--bg-dark);
    border: 2px dashed rgba(255,255,255,.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.2rem;
    flex-shrink: 0;
    overflow: hidden;
    cursor: pointer;
    transition: border-color .2s;
}
.sal-photo-box:hover { border-color: var(--accent); }
.sal-photo-box img { width:100%; height:100%; object-fit:cover; }

/* ══ PIN INPUT ══ */
.sal-pin-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.sal-pin-wrap input {
    flex: 1;
    letter-spacing: 4px;
    font-weight: 700;
    font-family: monospace;
    font-size: 1rem;
    padding-right: 40px !important;
}
.sal-pin-eye-btn {
    position: absolute;
    right: 10px;
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text-muted);
    font-size: 1rem;
    padding: 0;
    transition: color .15s;
    line-height: 1;
}
.sal-pin-eye-btn:hover { color: var(--text); }

/* ══ КПИ КАРТОЧКИ ══ */
.sal-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4,1fr);
    gap: 14px;
    margin-bottom: 22px;
}
@media(max-width:640px) {
    .sal-kpi-grid { grid-template-columns: repeat(2,1fr); }
    .sal-grid     { grid-template-columns: repeat(auto-fill, minmax(200px,1fr)); }
    .sal-profile-actions { grid-template-columns: repeat(2,1fr); }
    .sal-week-head, .sal-week-body { grid-template-columns: 40px repeat(7,1fr); }
}

/* Кнопка недели */
.sal-week-nav {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
    flex-wrap: wrap;
}
.sal-week-nav-label {
    font-weight: 700;
    font-size: .95rem;
    min-width: 220px;
    text-align: center;
}
.sal-week-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 12px;
}
.sal-legend-item {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: .68rem;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 20px;
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.09);
}
.sal-legend-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
}

/* Таб */
.sal-tab-bar {
    display: flex;
    gap: 4px;
    border-bottom: 1px solid rgba(255,255,255,.08);
    margin-bottom: 0;
}
.sal-tab-bar .tab {
    padding: 10px 18px;
    border: none;
    background: none;
    color: var(--text-muted);
    font-size: .82rem;
    font-weight: 700;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: color .15s, border-color .15s;
    border-radius: 0;
    letter-spacing: .2px;
}
.sal-tab-bar .tab:hover { color: var(--text); }
.sal-tab-bar .tab.active {
    color: var(--accent);
    border-bottom-color: var(--accent);
}

`;
    document.head.appendChild(s);
}
injectStyles();

/* ════════════════════════════════════════════
   HTML СТРАНИЦЫ
════════════════════════════════════════════ */
var PAGE_HTML = (function buildPage() {

    /* Шапка */
    var h = '';
    h += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px;">';
    h += '  <div>';
    h += '    <div style="font-size:1.35rem;font-weight:800;letter-spacing:-.3px;">👔 Сотрудники и Зарплата</div>';
    h += '    <div style="font-size:.78rem;color:var(--text-muted);margin-top:2px;">База, смены, выплаты, бонусы</div>';
    h += '  </div>';
    h += '  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
    h += '    <select class="form-select" style="width:160px;" id="sal_period_filter">' + buildPeriodOptions() + '</select>';
    h += '    <button class="btn btn-primary btn-sm" id="sal_btn_add_emp">+ Сотрудник</button>';
    h += '  </div>';
    h += '</div>';

    /* KPI */
    h += '<div class="sal-kpi-grid" id="sal_kpi"></div>';

    /* Табы */
    h += '<div class="sal-tab-bar" id="sal_tabs_bar">';
    h += '  <button class="tab active" id="sal_tab_btn_employees" onclick="window.__SAL.showTab(\'employees\')">👥 Сотрудники</button>';
    h += '  <button class="tab"        id="sal_tab_btn_shifts"    onclick="window.__SAL.showTab(\'shifts\')">📅 График смен</button>';
    h += '  <button class="tab"        id="sal_tab_btn_payroll"   onclick="window.__SAL.showTab(\'payroll\')">💵 Выплаты</button>';
    h += '</div>';

    /* Блок сотрудники */
    h += '<div id="sal_block_employees" style="display:block;padding-top:20px;">';
    h += '  <div class="sal-grid" id="sal_emp_grid"></div>';
    h += '</div>';

    /* Блок смены */
    h += '<div id="sal_block_shifts" style="display:none;padding-top:16px;">';
    h += '  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">';
    h += '    <div class="sal-week-nav">';
    h += '      <button class="btn btn-secondary btn-sm" id="sal_week_prev">← Назад</button>';
    h += '      <span id="sal_week_label" class="sal-week-nav-label"></span>';
    h += '      <button class="btn btn-secondary btn-sm" id="sal_week_next">Вперёд →</button>';
    h += '      <button class="btn btn-secondary btn-sm" id="sal_week_today">Сегодня</button>';
    h += '    </div>';
    h += '    <button class="btn btn-success btn-sm" id="sal_btn_add_shift">+ Добавить смену</button>';
    h += '  </div>';
    h += '  <div id="sal_week_legend" class="sal-week-legend"></div>';
    h += '  <div id="sal_week_grid"></div>';
    h += '</div>';

    /* Блок выплаты */
    h += '<div id="sal_block_payroll" style="display:none;padding-top:16px;">';
    h += '  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">';
    /* Сводка */
    h += '    <div class="card">';
    h += '      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">';
    h += '        <div class="card-title" style="margin-bottom:0;">📊 Сводка за период</div>';
    h += '      </div>';
    h += '      <div id="sal_summary"></div>';
    h += '    </div>';
    /* Таблица */
    h += '    <div class="card">';
    h += '      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">';
    h += '        <div class="card-title" style="margin-bottom:0;">📋 Записи выплат</div>';
    h += '        <button class="btn btn-primary btn-sm" id="sal_btn_add_pay">+ Выплата</button>';
    h += '      </div>';
    h += '      <div style="overflow-x:auto;">';
    h += '        <table style="width:100%;border-collapse:collapse;font-size:.83rem;">';
    h += '          <thead><tr>';
    ['Дата','Сотрудник','Тип','Сумма','Заметка',''].forEach(function(hd){
        h += '<th style="background:var(--bg-card2);padding:8px 10px;text-align:left;font-size:.68rem;font-weight:700;text-transform:uppercase;color:var(--text-muted);border-bottom:1px solid var(--border);">' + hd + '</th>';
    });
    h += '          </tr></thead>';
    h += '          <tbody id="sal_table"></tbody>';
    h += '        </table>';
    h += '      </div>';
    h += '    </div>';
    h += '  </div>';
    h += '</div>';

    /* ══ МОДАЛКА: КАРТОЧКА СОТРУДНИКА (полная) ══ */
    h += '<div class="modal-overlay" id="salProfileModal">';
    h += '  <div class="modal" style="max-width:520px;max-height:90vh;overflow-y:auto;">';
    h += '    <div class="modal-header">';
    h += '      <div class="modal-title">👔 Карточка сотрудника</div>';
    h += '      <button class="modal-close" onclick="closeModal(\'salProfileModal\')">✕</button>';
    h += '    </div>';
    h += '    <div id="sal_profile_body"></div>';
    h += '  </div>';
    h += '</div>';

    /* ══ МОДАЛКА: ФОРМА СОТРУДНИКА ══ */
    h += '<div class="modal-overlay" id="salEmpModal">';
    h += '  <div class="modal" style="max-width:700px;max-height:90vh;overflow-y:auto;">';
    h += '    <div class="modal-header">';
    h += '      <div class="modal-title" id="salEmpModalTitle">👔 Новый сотрудник</div>';
    h += '      <button class="modal-close" onclick="closeModal(\'salEmpModal\')">✕</button>';
    h += '    </div>';
    /* Фото загрузка */
    h += '    <div class="sal-photo-upload">';
    h += '      <div class="sal-photo-box" id="sal_photo_prev" onclick="el(\'sal_emp_photo\').focus()">👤</div>';
    h += '      <div style="flex:1;">';
    h += '        <label class="form-label">Фото (URL)</label>';
    h += '        <input class="form-input" id="sal_emp_photo" placeholder="https://...jpg">';
    h += '        <div style="font-size:.68rem;color:var(--text-muted);margin-top:4px;">Загрузите фото на imgbb.com или postimages.org и вставьте прямую ссылку</div>';
    h += '      </div>';
    h += '    </div>';
    /* Поля */
    h += '    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">';
    h += '      <div class="form-group"><label class="form-label">ФИО *</label><input class="form-input" id="sal_emp_name" placeholder="Иванов Иван Иванович"></div>';
    h += '      <div class="form-group"><label class="form-label">Должность *</label><input class="form-input" id="sal_emp_position" placeholder="Менеджер / Оператор"></div>';
    h += '      <div class="form-group"><label class="form-label">Телефон</label><input class="form-input" id="sal_emp_phone" placeholder="+7 (999) 000-00-00"></div>';
    h += '      <div class="form-group"><label class="form-label">Email</label><input class="form-input" id="sal_emp_email" placeholder="ivan@mail.ru"></div>';
    h += '      <div class="form-group"><label class="form-label">Оклад ₽/мес</label><input class="form-input" type="number" id="sal_emp_salary" placeholder="40000"></div>';
    h += '      <div class="form-group"><label class="form-label">Бонус с выручки %</label><input class="form-input" type="number" id="sal_emp_bonus_pct" placeholder="10"></div>';
    h += '      <div class="form-group"><label class="form-label">График</label><select class="form-select" id="sal_emp_schedule"><option value="5/2">5/2</option><option value="2/2">2/2</option><option value="3/3">3/3</option><option value="6/1">6/1</option><option value="custom">Индивидуально</option></select></div>';
    h += '      <div class="form-group"><label class="form-label">Дата выхода</label><input class="form-input" type="date" id="sal_emp_start"></div>';
    h += '      <div class="form-group"><label class="form-label">Дата рождения</label><input class="form-input" type="date" id="sal_emp_birth"></div>';
    h += '      <div class="form-group"><label class="form-label">Адрес</label><input class="form-input" id="sal_emp_address" placeholder="г. Москва..."></div>';
    h += '      <div class="form-group"><label class="form-label">Статус</label><select class="form-select" id="sal_emp_status"><option value="active">✅ Работает</option><option value="vacation">🏖 В отпуске</option><option value="sick">🤒 Больничный</option><option value="fired">❌ Уволен</option></select></div>';
    h += '      <div class="form-group"><label class="form-label">Цвет в календаре</label><input type="color" id="sal_emp_color" value="#7c3aed" style="width:56px;height:36px;border-radius:8px;border:1px solid var(--border);background:var(--bg-dark);cursor:pointer;"></div>';
    /* PIN */
    h += '      <div class="form-group"><label class="form-label">🔐 PIN-код</label>';
    h += '        <div class="sal-pin-wrap"><input class="form-input" id="sal_emp_pin" placeholder="1234" maxlength="6" type="password" autocomplete="new-password">';
    h += '          <button class="sal-pin-eye-btn" type="button" onclick="window.__SAL.togglePinForm()">👁</button>';
    h += '        </div></div>';
    /* Паспорт */
    h += '      <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Паспортные данные</label><textarea class="form-textarea" id="sal_emp_passport" rows="2" placeholder="Серия, номер..."></textarea></div>';
    h += '      <div class="form-group" style="grid-column:1/-1;"><label class="form-label">Характеристика / Заметки</label><textarea class="form-textarea" id="sal_emp_notes" rows="2" placeholder="Навыки, особенности..."></textarea></div>';
    h += '    </div>';
    h += '    <input type="hidden" id="sal_emp_edit_id">';
    h += '    <div class="modal-footer">';
    h += '      <button class="btn btn-secondary" onclick="closeModal(\'salEmpModal\')">Отмена</button>';
    h += '      <button class="btn btn-primary" id="sal_emp_save_btn">💾 Сохранить</button>';
    h += '    </div>';
    h += '  </div>';
    h += '</div>';

    /* ══ МОДАЛКА: СМЕНА ══ */
    h += '<div class="modal-overlay" id="salShiftModal">';
    h += '  <div class="modal modal-sm">';
    h += '    <div class="modal-header"><div class="modal-title">📅 Добавить смену</div><button class="modal-close" onclick="closeModal(\'salShiftModal\')">✕</button></div>';
    h += '    <div class="form-group"><label class="form-label">Сотрудник</label><select class="form-select" id="sal_shift_emp" onchange="window.__SAL.calcShiftBonus()"></select></div>';
    h += '    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">';
    h += '      <div class="form-group"><label class="form-label">Дата</label><input class="form-input" type="date" id="sal_shift_date"></div>';
    h += '      <div class="form-group"><label class="form-label">Начало</label><input class="form-input" type="time" id="sal_shift_start" value="09:00"></div>';
    h += '      <div class="form-group"><label class="form-label">Конец</label><input class="form-input" type="time" id="sal_shift_end" value="18:00"></div>';
    h += '      <div class="form-group"><label class="form-label">Выручка ₽</label><input class="form-input" type="number" id="sal_shift_rev" placeholder="0" oninput="window.__SAL.calcShiftBonus()"></div>';
    h += '    </div>';
    h += '    <div class="form-group"><label class="form-label">📍 Филиал / Точка</label><input class="form-input" id="sal_shift_branch" placeholder="Центральная, ТЦ Мега..."></div>';
    h += '    <div class="form-group"><label class="form-label">Заметка</label><input class="form-input" id="sal_shift_note" placeholder="Особенности..."></div>';
    h += '    <div id="sal_shift_bonus_block" style="display:none;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.25);border-radius:10px;padding:12px;margin-bottom:12px;">';
    h += '      <div style="font-size:.82rem;color:var(--accent3);font-weight:700;">💡 Авто-бонус с выручки</div>';
    h += '      <div id="sal_shift_bonus_text" style="font-size:.78rem;margin-top:4px;color:var(--text-muted);"></div>';
    h += '      <label style="display:flex;align-items:center;gap:8px;margin-top:8px;cursor:pointer;font-size:.82rem;"><input type="checkbox" id="sal_shift_add_bonus" checked>Записать бонус в выплаты</label>';
    h += '    </div>';
    h += '    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal(\'salShiftModal\')">Отмена</button><button class="btn btn-success" id="sal_shift_save_btn">💾 Сохранить</button></div>';
    h += '  </div>';
    h += '</div>';

    /* ══ МОДАЛКА: ВЫПЛАТА ══ */
    h += '<div class="modal-overlay" id="salPayModal">';
    h += '  <div class="modal modal-sm">';
    h += '    <div class="modal-header"><div class="modal-title">💵 Добавить выплату</div><button class="modal-close" onclick="closeModal(\'salPayModal\')">✕</button></div>';
    h += '    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">';
    h += '      <div class="form-group"><label class="form-label">Сотрудник</label><input class="form-input" id="sal_pay_name" placeholder="Имя" list="salPayDL"><datalist id="salPayDL"></datalist></div>';
    h += '      <div class="form-group"><label class="form-label">Тип выплаты</label><select class="form-select" id="sal_pay_type"><option value="salary">💰 Зарплата</option><option value="advance">💳 Аванс</option><option value="bonus">🎁 Премия</option><option value="revenue_bonus">📈 Бонус</option><option value="fine">⚠️ Штраф</option></select></div>';
    h += '      <div class="form-group"><label class="form-label">Сумма ₽</label><input class="form-input" type="number" id="sal_pay_amount" placeholder="0"></div>';
    h += '      <div class="form-group"><label class="form-label">Период</label><input class="form-input" type="month" id="sal_pay_period"></div>';
    h += '    </div>';
    h += '    <div class="form-group"><label class="form-label">Примечание</label><input class="form-input" id="sal_pay_note" placeholder="Комментарий..."></div>';
    h += '    <div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal(\'salPayModal\')">Отмена</button><button class="btn btn-success" id="sal_pay_save_btn">💾 Сохранить</button></div>';
    h += '  </div>';
    h += '</div>';

    return h;
}());

/* ════════════════════════════════════════════
   ОБЪЕКТ МОДУЛЯ
════════════════════════════════════════════ */
var SAL = {
    employees:   [],
    weekStart:   null,   /* Monday of current week */
    curTab:      'employees',
    _nowTimer:   null,

    /* ──────────────────────
       ИНИЦИАЛИЗАЦИЯ
    ────────────────────── */
    render: function () {
        var self = this;

        /* Устанавливаем начало недели на текущий понедельник */
        if (!self.weekStart) self.weekStart = self._getMondayOf(new Date());

        /* Биндим кнопки ОДИН РАЗ */
        self._once('sal_period_filter', 'change', function () {
            self.renderKPI();
            self.renderPayroll();
            self.renderEmployees();
        });
        self._once('sal_btn_add_emp',    'click', function () { self.openEmpForm(); });
        self._once('sal_btn_add_shift',  'click', function () { self.openShiftForm(); });
        self._once('sal_btn_add_pay',    'click', function () { self.openPayForm(); });
        self._once('sal_week_prev',      'click', function () {
            self.weekStart = new Date(self.weekStart.getTime() - 7*86400000);
            self.renderWeek();
        });
        self._once('sal_week_next',      'click', function () {
            self.weekStart = new Date(self.weekStart.getTime() + 7*86400000);
            self.renderWeek();
        });
        self._once('sal_week_today',     'click', function () {
            self.weekStart = self._getMondayOf(new Date());
            self.renderWeek();
        });
        self._once('sal_emp_save_btn',   'click', function () { self.saveEmployee(); });
        self._once('sal_shift_save_btn', 'click', function () { self.saveShift(); });
        self._once('sal_pay_save_btn',   'click', function () { self.savePayroll(); });

        self._once('sal_emp_photo', 'input', function () {
            self.previewPhoto(this.value);
        });

        self.showTab(self.curTab, true);

        CRM.api('salary','employees').then(function (r) {
            self.employees = r.data || [];
            self.renderEmployees();
            self.renderKPI();
        });
        self.renderPayroll();
        self.renderWeek();
    },

    /* Биндинг без дублирования */
    _once: function (id, ev, fn) {
        var e = el(id);
        if (!e || e['_sal_' + ev]) return;
        e['_sal_' + ev] = true;
        e.addEventListener(ev, fn);
    },

    _getMondayOf: function (d) {
        var day  = d.getDay();
        var diff = (day === 0 ? -6 : 1 - day);
        var mon  = new Date(d);
        mon.setHours(0,0,0,0);
        mon.setDate(mon.getDate() + diff);
        return mon;
    },

    /* ──────────────────────
       ТАБЫ
    ────────────────────── */
    showTab: function (tab, silent) {
        this.curTab = tab;
        ['employees','shifts','payroll'].forEach(function (t) {
            var btn = el('sal_tab_btn_' + t);
            var blk = el('sal_block_' + t);
            var on  = (t === tab);
            if (btn) btn.className = 'tab' + (on ? ' active' : '');
            if (blk) blk.style.display = on ? 'block' : 'none';
        });
        if (!silent && tab === 'shifts') this.renderWeek();
    },

    /* ──────────────────────
       KPI
    ────────────────────── */
    renderKPI: function () {
        var self   = this;
        var period = curPeriod();
        CRM.api('salary','list').then(function (r) {
            var recs  = (r.data||[]).filter(function(x){ return x.period === period; });
            var totS  = recs.filter(function(x){ return x.type==='salary'; }).reduce(function(a,b){return a+b.amount;},0);
            var totA  = recs.filter(function(x){ return x.type==='advance'; }).reduce(function(a,b){return a+b.amount;},0);
            var totB  = recs.filter(function(x){ return x.type==='bonus'||x.type==='revenue_bonus'; }).reduce(function(a,b){return a+b.amount;},0);
            var totF  = recs.filter(function(x){ return x.type==='fine'; }).reduce(function(a,b){return a+b.amount;},0);
            var totO  = totS + totA + totB - totF;
            var act   = self.employees.filter(function(e){ return e.status==='active'; }).length;
            var kEl   = el('sal_kpi');
            if (!kEl) return;
            kEl.innerHTML =
                _kpi('💰','Зарплаты',fmoney(totS),period,'green') +
                _kpi('🎁','Авансы + Бонусы',fmoney(totA+totB),'за период','cyan') +
                _kpi('👥','Активных',act,'сотрудников','purple') +
                _kpi('💸','Итого выплачено',fmoney(totO),'за период','red');
        });
        function _kpi(icon,lbl,val,sub,cls) {
            return '<div class="stat-card '+cls+'">'+
                '<div class="stat-icon">'+icon+'</div>'+
                '<div class="stat-label">'+lbl+'</div>'+
                '<div class="stat-value '+cls+'">'+val+'</div>'+
                '<div class="stat-sub">'+sub+'</div>'+
                '</div>';
        }
    },

    /* ──────────────────────
       КАРТОЧКИ СОТРУДНИКОВ
    ────────────────────── */
    renderEmployees: function () {
        var self   = this;
        var period = curPeriod();
        var y      = parseInt(period.split('-')[0]);
        var m      = parseInt(period.split('-')[1]);
        var from   = period + '-01';
        var to     = period + '-' + pad2(new Date(y,m,0).getDate());
        var gridEl = el('sal_emp_grid');
        if (!gridEl) return;

        Promise.all([
            CRM.api('salary','list'),
            CRM.api('salary','listShifts',null,{from:from,to:to})
        ]).then(function (res) {
            var recs   = res[0].data || [];
            var shifts = res[1].data || [];
            var active = self.employees.filter(function(e){ return e.status==='active'; });
            var others = self.employees.filter(function(e){ return e.status!=='active'; });
            var sorted = active.concat(others);

            if (!sorted.length) {
                gridEl.innerHTML =
                    '<div class="card" style="grid-column:1/-1;text-align:center;padding:48px;">'+
                    '<div style="font-size:3.5rem;opacity:.25;">👥</div>'+
                    '<div style="font-size:1rem;font-weight:700;margin-top:14px;">Сотрудников пока нет</div>'+
                    '<div style="font-size:.82rem;color:var(--text-muted);margin-top:6px;">Нажмите «+ Сотрудник»</div></div>';
                return;
            }

            gridEl.innerHTML = sorted.map(function (emp) {
                var empRecs   = recs.filter(function(x){ return x.staffId===emp.id && x.period===period; });
                var paid      = empRecs.filter(function(x){ return x.type!=='fine'; }).reduce(function(a,b){return a+b.amount;},0);
                var fines     = empRecs.filter(function(x){ return x.type==='fine'; }).reduce(function(a,b){return a+b.amount;},0);
                var empShifts = shifts.filter(function(s){ return s.empId===emp.id; });
                var shiftCnt  = empShifts.length;
                var totalHrs  = empShifts.reduce(function(acc,s){
                    if (!s.start||!s.end) return acc;
                    var sp=s.start.split(':'), ep=s.end.split(':');
                    return acc + Math.max(0,(parseInt(ep[0])*60+parseInt(ep[1]))-(parseInt(sp[0])*60+parseInt(sp[1])))/60;
                },0);
                var color     = emp.color || '#7c3aed';
                var sc        = emp.status || 'active';
                var sColor    = STATUS_COLOR[sc] || '#94a3b8';
                var sBg       = STATUS_BG[sc]    || 'rgba(148,163,184,.15)';
                var sIcon     = STATUS_ICON[sc]  || '';
                var sLabel    = STATUS_LABEL[sc] || sc;

                /* Фото / плейсхолдер */
                var photoHtml = emp.photo
                    ? '<img class="sal-card-photo" src="'+esc(emp.photo)+'" alt="" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'">' +
                      '<div class="sal-card-photo-placeholder" style="display:none;background:linear-gradient(135deg,'+color+','+color+'88);">'+esc(emp.name.charAt(0))+'</div>'
                    : '<div class="sal-card-photo-placeholder" style="background:linear-gradient(135deg,'+color+','+color+'88);">'+esc(emp.name.charAt(0))+'</div>';

                return '<div class="sal-card'+(sc==='fired'?' fired':'')+'" onclick="window.__SAL.openProfile(\''+emp.id+'\')" style="cursor:pointer;">'+

                    /* Фото зона */
                    '<div style="position:relative;">'+
                        photoHtml +
                        '<div class="sal-card-photo-overlay"></div>'+
                        /* Статус-бейдж поверх фото */
                        '<div class="sal-card-status-badge" style="background:'+sBg+';color:'+sColor+';">'+
                            sIcon+' '+sLabel+
                        '</div>'+
                        /* Имя и должность поверх фото */
                        '<div class="sal-card-name-overlay">'+
                            '<div class="sal-cn-name">'+esc(emp.name)+'</div>'+
                            '<div class="sal-cn-pos">'+esc(emp.position)+'</div>'+
                        '</div>'+
                    '</div>'+

                    /* Тело карточки */
                    '<div class="sal-card-body">'+
                        /* 3 стата */
                        '<div class="sal-card-stats">'+
                            '<div class="sal-stat">'+
                                '<div class="sal-stat-val" style="color:#f59e0b;">'+shiftCnt+'</div>'+
                                '<div class="sal-stat-lbl">Смен</div>'+
                            '</div>'+
                            '<div class="sal-stat">'+
                                '<div class="sal-stat-val" style="color:#3b82f6;">'+totalHrs.toFixed(1)+'</div>'+
                                '<div class="sal-stat-lbl">Часов</div>'+
                            '</div>'+
                            '<div class="sal-stat">'+
                                '<div class="sal-stat-val" style="color:#ef4444;">'+(fines>0?('−'+fmoney(fines)):'—')+'</div>'+
                                '<div class="sal-stat-lbl">Штрафы</div>'+
                            '</div>'+
                        '</div>'+
                        /* Нижняя строка: оклад + кнопка открыть */
                        '<div class="sal-card-footer">'+
                            '<div>'+
                                '<div class="sal-card-salary">'+fmoney(emp.salary)+'</div>'+
                                '<div class="sal-card-salary-lbl">оклад / мес</div>'+
                            '</div>'+
                            '<button class="sal-card-open-btn" onclick="event.stopPropagation();window.__SAL.openProfile(\''+emp.id+'\')">'+
                                '📂 Открыть'+
                            '</button>'+
                        '</div>'+
                    '</div>'+

                '</div>'; /* /sal-card */
            }).join('');
        });
    },

    /* ──────────────────────
       ПРОФИЛЬ СОТРУДНИКА (МОДАЛКА)
    ────────────────────── */
    openProfile: function (empId) {
        var self = this;
        var emp  = self.employees.find(function(e){ return e.id===empId; });
        if (!emp) return;

        var period = curPeriod();
        var y = parseInt(period.split('-')[0]);
        var m = parseInt(period.split('-')[1]);
        var from = period+'-01';
        var to   = period+'-'+pad2(new Date(y,m,0).getDate());

        Promise.all([
            CRM.api('salary','list'),
            CRM.api('salary','listShifts',null,{from:from,to:to})
        ]).then(function (res) {
            var recs   = (res[0].data||[]).filter(function(x){ return x.staffId===emp.id && x.period===period; });
            var shifts = (res[1].data||[]).filter(function(s){ return s.empId===emp.id; });
            var paid   = recs.filter(function(x){return x.type!=='fine';}).reduce(function(a,b){return a+b.amount;},0);
            var fines  = recs.filter(function(x){return x.type==='fine';}).reduce(function(a,b){return a+b.amount;},0);
            var bonuses= recs.filter(function(x){return x.type==='bonus'||x.type==='revenue_bonus';}).reduce(function(a,b){return a+b.amount;},0);
            var shiftCnt = shifts.length;
            var totalHrs = shifts.reduce(function(acc,s){
                if(!s.start||!s.end) return acc;
                var sp=s.start.split(':'),ep=s.end.split(':');
                return acc+Math.max(0,(parseInt(ep[0])*60+parseInt(ep[1]))-(parseInt(sp[0])*60+parseInt(sp[1])))/60;
            },0);
            var totalRev = shifts.reduce(function(a,s){return a+(s.revenue||0);},0);
            var calcBonus= (totalRev>0&&emp.bonusPct>0) ? (totalRev*emp.bonusPct/100) : 0;
            var color    = emp.color||'#7c3aed';
            var sc       = emp.status||'active';
            var sColor   = STATUS_COLOR[sc]||'#94a3b8';
            var sBg      = STATUS_BG[sc]||'rgba(148,163,184,.15)';
            var sIcon    = STATUS_ICON[sc]||'';
            var sLabel   = STATUS_LABEL[sc]||sc;

            /* Фото-шапка профиля */
            var photoBlock = emp.photo
                ? '<img class="sal-profile-photo" src="'+esc(emp.photo)+'" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'">'+
                  '<div class="sal-profile-photo-placeholder" style="display:none;background:linear-gradient(135deg,'+color+','+color+'88);">'+esc(emp.name.charAt(0))+'</div>'
                : '<div class="sal-profile-photo-placeholder" style="background:linear-gradient(135deg,'+color+','+color+'88);">'+esc(emp.name.charAt(0))+'</div>';

            var html = '';

            /* Шапка с фото */
            html += '<div class="sal-profile-header" style="background:'+color+'22;">';
            html += photoBlock;
            html += '<div class="sal-profile-overlay"></div>';
            html += '<div class="sal-profile-info">';
            html += '  <div class="sal-profile-name">'+esc(emp.name)+'</div>';
            html += '  <div class="sal-profile-pos">'+esc(emp.position)+'</div>';
            html += '  <div class="sal-profile-status" style="background:'+sBg+';color:'+sColor+';">'+sIcon+' '+sLabel+'</div>';
            html += '</div></div>';

            /* Кнопки действий */
            html += '<div class="sal-profile-actions">';
            html += '  <button class="sal-act-btn primary" onclick="closeModal(\'salProfileModal\');window.__SAL.openEmpForm(\''+emp.id+'\')"><span class="icon">✏️</span><span class="lbl">Изменить</span></button>';
            html += '  <button class="sal-act-btn success" onclick="closeModal(\'salProfileModal\');window.__SAL.openPayForm(\''+esc(emp.name)+'\')"><span class="icon">💵</span><span class="lbl">Выплата</span></button>';
            html += '  <button class="sal-act-btn success" onclick="closeModal(\'salProfileModal\');window.__SAL.showTab(\'shifts\');window.__SAL.openShiftForm()"><span class="icon">📅</span><span class="lbl">Смена</span></button>';
            html += '  <button class="sal-act-btn danger"  onclick="closeModal(\'salProfileModal\');window.__SAL.deleteEmp(\''+emp.id+'\')"><span class="icon">🗑️</span><span class="lbl">Удалить</span></button>';
            html += '</div>';

            /* Финансовый дашборд */
            html += '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:16px;">';
            html += _tile('💰 Выплачено',   fmoney(paid),    '#10b981');
            html += _tile('⚠️ Штрафы',      fmoney(fines),   '#ef4444');
            html += _tile('🎁 Бонусы',      fmoney(bonuses), '#f59e0b');
            html += _tile('📅 Смен',        shiftCnt,        '#3b82f6');
            html += _tile('⏱ Часов',        totalHrs.toFixed(1), '#8b5cf6');
            html += _tile('📈 Выручка',     fmoney(totalRev),'#06b6d4');
            html += '</div>';

            /* Расчётный бонус */
            if (calcBonus > 0) {
                html += '<div style="background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2);border-radius:12px;padding:10px 14px;margin-bottom:14px;font-size:.82rem;">';
                html += '📊 Расчётный бонус <b>'+emp.bonusPct+'%</b> с выручки '+fmoney(totalRev)+' = <b style="color:#10b981;">'+fmoney(calcBonus)+'</b>';
                html += '</div>';
            }

            /* Инфо-плитки */
            html += '<div class="sal-info-grid">';
            if (emp.phone)     html += _infoTile('📞 Телефон',     '<a href="tel:'+esc(emp.phone)+'" style="color:var(--accent2);">'+esc(emp.phone)+'</a>');
            if (emp.email)     html += _infoTile('✉️ Email',       esc(emp.email));
            if (emp.schedule)  html += _infoTile('📅 График',      esc(emp.schedule));
            if (emp.startDate) html += _infoTile('🗓 Дата выхода', esc(emp.startDate));
            if (emp.birthDate) html += _infoTile('🎂 Дата рожд.',  esc(emp.birthDate));
            if (emp.address)   html += _infoTile('🏠 Адрес',       esc(emp.address));
            if (emp.bonusPct)  html += _infoTile('🎁 Бонус %',     emp.bonusPct+'% с выручки');
            html += _infoTile('💰 Оклад', fmoney(emp.salary));
            html += '</div>';

            /* PIN */
            if (emp.pin) {
                html += '<div class="sal-pin-tile">';
                html += '  <span style="font-size:.72rem;color:var(--text-muted);font-weight:700;">🔐 PIN-КОД</span>';
                html += '  <span class="sal-pin-val" id="profile_pin_val">••••••</span>';
                html += '  <button class="sal-pin-eye" onclick="var v=el(\'profile_pin_val\');v.textContent=v.textContent.includes(\'•\')?\''+esc(emp.pin)+'\':\' ••••••\'">👁</button>';
                html += '</div>';
            } else {
                html += '<div style="font-size:.75rem;color:var(--danger);margin-bottom:12px;padding:8px 12px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:10px;">⚠️ PIN-код не установлен — сотрудник не сможет войти в личный кабинет</div>';
            }

            /* Заметки */
            if (emp.notes) {
                html += '<div style="padding:12px;background:var(--bg-dark);border-radius:12px;margin-bottom:10px;">';
                html += '<div style="font-size:.65rem;color:var(--text-muted);text-transform:uppercase;font-weight:700;letter-spacing:.4px;margin-bottom:6px;">ХАРАКТЕРИСТИКА</div>';
                html += '<div style="font-size:.83rem;line-height:1.5;">'+esc(emp.notes)+'</div></div>';
            }
            if (emp.passportNote) {
                html += '<div style="padding:12px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);border-radius:12px;">';
                html += '<div style="font-size:.65rem;color:var(--danger);text-transform:uppercase;font-weight:700;letter-spacing:.4px;margin-bottom:6px;">🔐 ПАСПОРТНЫЕ ДАННЫЕ</div>';
                html += '<div style="font-size:.83rem;line-height:1.5;">'+esc(emp.passportNote)+'</div></div>';
            }

            var bEl = el('sal_profile_body');
            if (bEl) bEl.innerHTML = html;
            openModal('salProfileModal');
        });

        function _tile(lbl,v,clr) {
            return '<div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:10px 8px;text-align:center;">'+
                '<div style="font-size:.88rem;font-weight:800;color:'+clr+';">'+v+'</div>'+
                '<div style="font-size:.6rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;margin-top:3px;">'+lbl+'</div></div>';
        }
        function _infoTile(lbl,v) {
            return '<div class="sal-info-tile"><div class="sal-info-tile-lbl">'+lbl+'</div><div class="sal-info-tile-val">'+v+'</div></div>';
        }
    },

    /* ──────────────────────
       ФОРМА СОТРУДНИКА
    ────────────────────── */
    openEmpForm: function (empId) {
        var emp = empId ? this.employees.find(function(e){ return e.id===empId; }) : null;
        var t   = el('salEmpModalTitle');
        if (t) t.textContent = emp ? '✏️ Редактировать' : '👔 Новый сотрудник';
        setVal('sal_emp_edit_id',   emp ? emp.id           : '');
        setVal('sal_emp_name',      emp ? emp.name         : '');
        setVal('sal_emp_position',  emp ? emp.position     : '');
        setVal('sal_emp_phone',     emp ? emp.phone        : '');
        setVal('sal_emp_email',     emp ? emp.email        : '');
        setVal('sal_emp_salary',    emp ? emp.salary       : '');
        setVal('sal_emp_bonus_pct', emp ? emp.bonusPct     : '');
        setVal('sal_emp_schedule',  emp ? emp.schedule     : '5/2');
        setVal('sal_emp_start',     emp ? emp.startDate    : new Date().toISOString().slice(0,10));
        setVal('sal_emp_birth',     emp ? emp.birthDate    : '');
        setVal('sal_emp_address',   emp ? emp.address      : '');
        setVal('sal_emp_passport',  emp ? emp.passportNote : '');
        setVal('sal_emp_notes',     emp ? emp.notes        : '');
        setVal('sal_emp_photo',     emp ? emp.photo        : '');
        setVal('sal_emp_status',    emp ? emp.status       : 'active');
        setVal('sal_emp_color',     emp ? (emp.color||'#7c3aed') : '#7c3aed');
        setVal('sal_emp_pin',       emp ? (emp.pin||'')    : '');
        var pi = el('sal_emp_pin'); if (pi) pi.type = 'password';
        this.previewPhoto(emp ? emp.photo : '');
        openModal('salEmpModal');
    },

    previewPhoto: function (url) {
        var box = el('sal_photo_prev');
        if (!box) return;
        box.innerHTML = (url && url.trim())
            ? '<img src="'+url+'" style="width:100%;height:100%;object-fit:cover;border-radius:14px;" onerror="this.parentNode.innerHTML=\'👤\'">'
            : '👤';
    },

    togglePinForm: function () {
        var i = el('sal_emp_pin'); if (i) i.type = i.type==='password'?'text':'password';
    },

    saveEmployee: function () {
        var self = this;
        var name = val('sal_emp_name').trim();
        var pos  = val('sal_emp_position').trim();
        if (!name) { notify('Введите ФИО','error'); return; }
        if (!pos)  { notify('Введите должность','error'); return; }
        var editId = val('sal_emp_edit_id');
        var data   = {
            name: name, position: pos,
            phone: val('sal_emp_phone'), email: val('sal_emp_email'),
            salary: parseFloat(val('sal_emp_salary'))||0,
            bonusPct: parseFloat(val('sal_emp_bonus_pct'))||0,
            schedule: val('sal_emp_schedule'),
            startDate: val('sal_emp_start'), birthDate: val('sal_emp_birth'),
            address: val('sal_emp_address'), passportNote: val('sal_emp_passport'),
            notes: val('sal_emp_notes'), photo: val('sal_emp_photo').trim(),
            status: val('sal_emp_status'), color: val('sal_emp_color'),
            pin: val('sal_emp_pin').trim()
        };
        if (editId) data.id = editId;
        CRM.api('salary', editId ? 'updateEmployee' : 'addEmployee', data)
            .then(function (res) {
                if (!res||!res.ok) { notify('Ошибка сохранения','error'); return null; }
                notify(editId ? 'Данные обновлены' : 'Сотрудник добавлен: '+name, 'success');
                closeModal('salEmpModal');
                return CRM.api('salary','employees');
            })
            .then(function (r) {
                if (!r) return;
                self.employees = r.data || [];
                self.renderEmployees();
                self.renderKPI();
            });
    },

    deleteEmp: function (empId) {
        var self = this;
        if (!confirm('Удалить сотрудника? Это действие нельзя отменить.')) return;
        CRM.api('salary','deleteEmployee',{id:empId})
            .then(function () {
                notify('Сотрудник удалён','error');
                return CRM.api('salary','employees');
            })
            .then(function (r) {
                self.employees = r.data||[];
                self.renderEmployees();
                self.renderKPI();
            });
    },

    /* ──────────────────────
       НЕДЕЛЬНЫЙ КАЛЕНДАРЬ
    ────────────────────── */
    renderWeek: function () {
        var self     = this;
        var monday   = self.weekStart;
        var weekDays = [];
        for (var i = 0; i < 7; i++) {
            var d = new Date(monday.getTime() + i*86400000);
            weekDays.push(d);
        }

        /* Лейбл */
        var lbl = el('sal_week_label');
        if (lbl) {
            var first = weekDays[0], last = weekDays[6];
            lbl.textContent =
                first.toLocaleDateString('ru',{day:'numeric',month:'short'}) + ' — ' +
                last.toLocaleDateString('ru',{day:'numeric',month:'short',year:'numeric'});
        }

        var from = weekDays[0].getFullYear()+'-'+pad2(weekDays[0].getMonth()+1)+'-'+pad2(weekDays[0].getDate());
        var to   = weekDays[6].getFullYear()+'-'+pad2(weekDays[6].getMonth()+1)+'-'+pad2(weekDays[6].getDate());

        CRM.api('salary','listShifts',null,{from:from,to:to}).then(function (res) {
            var shifts  = res.data || [];
            var gridEl  = el('sal_week_grid');
            var legEl   = el('sal_week_legend');
            if (!gridEl) return;

            /* Легенда по сотрудникам */
            var empInWeek = {};
            shifts.forEach(function(s){
                if (!empInWeek[s.empId]) empInWeek[s.empId] = {name:s.empName, color:s.color||'#7c3aed'};
            });
            if (legEl) {
                var lkeys = Object.keys(empInWeek);
                legEl.innerHTML = lkeys.length
                    ? lkeys.map(function(k){
                        return '<div class="sal-legend-item">'+
                            '<div class="sal-legend-dot" style="background:'+empInWeek[k].color+';"></div>'+
                            empInWeek[k].name+
                        '</div>';
                    }).join('')
                    : '';
            }

            var HOUR_START = 7;   /* начало шкалы: 07:00 */
            var HOUR_END   = 23;  /* конец:        23:00 */
            var HOURS      = HOUR_END - HOUR_START;
            var CELL_H     = 52;  /* px на час     */
            var today      = new Date().toDateString();
            var dayNames   = ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];

            /* Шапка */
            var headHtml = '<div class="sal-week-head-time"></div>';
            weekDays.forEach(function(d,i){
                var isToday = d.toDateString()===today;
                headHtml +=
                    '<div class="sal-week-head-day'+(isToday?' today':'')+'">'+
                    '<div class="wday">'+dayNames[i]+'</div>'+
                    '<div class="wdate">'+d.getDate()+'</div>'+
                    '</div>';
            });

            /* Время + колонки */
            var timeHtml = '<div class="sal-time-col">';
            for (var h2 = HOUR_START; h2 < HOUR_END; h2++) {
                timeHtml += '<div class="sal-time-cell">'+pad2(h2)+':00</div>';
            }
            timeHtml += '</div>';

            /* Колонка каждого дня */
            var colsHtml = '';
            weekDays.forEach(function(d) {
                var ds      = d.getFullYear()+'-'+pad2(d.getMonth()+1)+'-'+pad2(d.getDate());
                var isToday = d.toDateString()===today;
                var dayShifts = shifts.filter(function(s){ return s.date===ds; });

                /* Строим часовые ряды */
                var hoursHtml = '';
                for (var h3 = HOUR_START; h3 < HOUR_END; h3++) {
                    /* Клик по пустому часу → открыть форму смены с датой */
                    hoursHtml += '<div class="sal-hour-row" onclick="window.__SAL.openShiftForm(\''+ds+'\')" title="Добавить смену '+pad2(h3)+':00"></div>';
                }

                /* Блоки смен — позиционируем абсолютно */
                var blocksHtml = dayShifts.map(function(s) {
                    if (!s.start||!s.end) return '';
                    var sp  = s.start.split(':'), ep = s.end.split(':');
                    var sM  = parseInt(sp[0])*60+parseInt(sp[1]);
                    var eM  = parseInt(ep[0])*60+parseInt(ep[1]);
                    var top = (sM/60 - HOUR_START) * CELL_H;
                    var ht  = Math.max(22, (eM-sM)/60 * CELL_H - 3);
                    var clr = s.color || '#7c3aed';
                    var branch = s.branch ? ' · '+s.branch : '';
                    return '<div class="sal-shift-block" '+
                        'style="top:'+top+'px;height:'+ht+'px;'+
                        'background:'+clr+'28;'+
                        'color:'+clr+';'+
                        'border-left:3px solid '+clr+';"'+
                        ' onclick="event.stopPropagation();window.__SAL.askDeleteShift(\''+s.id+'\')"'+
                        ' title="'+esc(s.empName)+' '+s.start+'–'+s.end+(s.branch?' | '+s.branch:'')+(s.revenue>0?' | ₽'+s.revenue:'')+ ' (клик = удалить)">' +
                        esc(s.empName.split(' ')[0])+' '+s.start+'–'+s.end + branch +
                        (s.revenue>0?' 💰':'') +
                    '</div>';
                }).join('');

                colsHtml +=
                    '<div class="sal-day-col'+(isToday?' today':'')+'" style="position:relative;">'+
                    hoursHtml +
                    /* Блоки смен поверх часовых рядов, внутри position:relative */
                    '<div style="position:absolute;top:0;left:0;right:0;pointer-events:none;z-index:3;">'+
                        /* Обертка с pointer-events auto чтобы клики работали */
                        '<div style="pointer-events:auto;">'+blocksHtml+'</div>'+
                    '</div>'+
                    /* Линия текущего времени */
                    (isToday ? self._nowLine(HOUR_START, CELL_H) : '') +
                    '</div>';
            });

            gridEl.innerHTML =
                '<div class="sal-week-wrap">'+
                '<div class="sal-week-head">'+headHtml+'</div>'+
                '<div class="sal-week-body">'+timeHtml+colsHtml+'</div>'+
                '</div>';

            /* Скролл к рабочим часам */
            var wBody = gridEl.querySelector('.sal-week-body');
            if (wBody) wBody.scrollTop = (8 - HOUR_START) * CELL_H;

            /* Таймер обновления линии "сейчас" */
            if (self._nowTimer) clearInterval(self._nowTimer);
            self._nowTimer = setInterval(function () {
                document.querySelectorAll('.sal-now-line').forEach(function(ln) {
                    var now   = new Date();
                    var mins  = now.getHours()*60+now.getMinutes();
                    ln.style.top = ((mins/60 - HOUR_START)*CELL_H) + 'px';
                });
            }, 30000);
        });
    },

    _nowLine: function (hourStart, cellH) {
        var now  = new Date();
        var mins = now.getHours()*60 + now.getMinutes();
        var top  = (mins/60 - hourStart) * cellH;
        if (top < 0) return '';
        return '<div class="sal-now-line" style="top:'+top+'px;"></div>';
    },

    /* ──────────────────────
       ФОРМА СМЕНЫ
    ────────────────────── */
    openShiftForm: function (dateStr) {
        var self   = this;
        var empSel = el('sal_shift_emp');
        if (empSel) {
            var active = self.employees.filter(function(e){ return e.status==='active'; });
            if (!active.length) { notify('Сначала добавьте сотрудника','error'); return; }
            empSel.innerHTML = active.map(function(e){
                return '<option value="'+e.id+'" data-name="'+esc(e.name)+'" data-bonus="'+e.bonusPct+'">'+esc(e.name)+' ('+esc(e.position)+')</option>';
            }).join('');
        }
        setVal('sal_shift_date',   dateStr || new Date().toISOString().slice(0,10));
        setVal('sal_shift_start',  '09:00');
        setVal('sal_shift_end',    '18:00');
        setVal('sal_shift_rev',    '');
        setVal('sal_shift_note',   '');
        setVal('sal_shift_branch', '');
        var bb = el('sal_shift_bonus_block');
        if (bb) bb.style.display = 'none';
        openModal('salShiftModal');
    },

    calcShiftBonus: function () {
        var empSel  = el('sal_shift_emp');
        var revEl   = el('sal_shift_rev');
        var block   = el('sal_shift_bonus_block');
        var txtEl   = el('sal_shift_bonus_text');
        if (!empSel||!revEl||!block) return;
        var revenue  = parseFloat(revEl.value)||0;
        var opt      = empSel.options[empSel.selectedIndex];
        var bonusPct = opt ? parseFloat(opt.getAttribute('data-bonus')||'0') : 0;
        if (revenue>0&&bonusPct>0) {
            var bonus = (revenue*bonusPct/100).toFixed(0);
            block.style.display = '';
            if (txtEl) txtEl.textContent = (opt?opt.text:'')+' → выручка '+fmoney(revenue)+' × '+bonusPct+'% = бонус '+fmoney(parseFloat(bonus));
        } else {
            block.style.display = 'none';
        }
    },

    saveShift: function () {
        var self     = this;
        var empSel   = el('sal_shift_emp');
        var empId    = empSel ? empSel.value : '';
        var opt      = empSel ? empSel.options[empSel.selectedIndex] : null;
        var empName  = opt ? opt.getAttribute('data-name') : '';
        var bonusPct = opt ? parseFloat(opt.getAttribute('data-bonus')||'0') : 0;
        var date     = val('sal_shift_date');
        var revenue  = parseFloat(val('sal_shift_rev'))||0;
        var branch   = val('sal_shift_branch').trim();
        if (!empId) { notify('Выберите сотрудника','error'); return; }
        if (!date)  { notify('Укажите дату','error'); return; }
        var emp   = self.employees.find(function(e){ return e.id===empId; });
        var bonus = (revenue>0&&bonusPct>0) ? parseFloat((revenue*bonusPct/100).toFixed(0)) : 0;
        CRM.api('salary','addShift',{
            empId:empId, empName:empName, date:date,
            start:val('sal_shift_start'), end:val('sal_shift_end'),
            revenue:revenue, note:val('sal_shift_note'),
            branch:branch, color:emp?(emp.color||'#7c3aed'):'#7c3aed'
        }).then(function () {
            var addBonEl = el('sal_shift_add_bonus');
            if (bonus>0&&addBonEl&&addBonEl.checked) {
                return CRM.api('salary','add',{
                    staffName:empName, staffId:empId, type:'revenue_bonus',
                    amount:bonus, period:date.slice(0,7),
                    note:'Бонус '+bonusPct+'% с выручки '+fmoney(revenue)+' за '+date+(branch?' ('+branch+')':''),
                    revenue:revenue
                });
            }
            return null;
        }).then(function (res) {
            if (res&&res.ok) {
                notify('✅ Смена + бонус '+fmoney(bonus)+' записан','success');
                if (typeof refreshDashboard==='function') refreshDashboard();
            } else {
                notify('✅ Смена сохранена','success');
            }
            closeModal('salShiftModal');
            self.renderWeek();
            self.renderKPI();
            self.renderEmployees();
        });
    },

    askDeleteShift: function (id) {
        var self = this;
        if (!confirm('Удалить эту смену?')) return;
        CRM.api('salary','deleteShift',{id:id}).then(function () {
            notify('Смена удалена','error');
            self.renderWeek();
            self.renderEmployees();
        });
    },

    /* ──────────────────────
       ВЫПЛАТЫ
    ────────────────────── */
    renderPayroll: function () {
        var self   = this;
        var period = curPeriod();
        Promise.all([
            CRM.api('salary','list'),
            CRM.api('salary','summary',null,{period:period})
        ]).then(function (res) {
            var recs    = (res[0].data||[]).filter(function(x){ return x.period===period; });
            var summary = res[1].byStaff||{};

            /* Сводка */
            var sumEl = el('sal_summary');
            if (sumEl) {
                var keys = Object.keys(summary);
                sumEl.innerHTML = !keys.length
                    ? '<div style="text-align:center;padding:28px;color:var(--text-muted);"><div style="font-size:2rem;opacity:.25;">💵</div><div style="margin-top:8px;">Выплат за период нет</div></div>'
                    : keys.map(function(name){
                        var d     = summary[name];
                        var total = (d.salary||0)+(d.advance||0)+(d.bonus||0)+(d.revenue_bonus||0)-(d.fine||0);
                        return '<div style="display:flex;align-items:center;gap:10px;padding:10px;background:var(--bg-dark);border-radius:10px;margin-bottom:6px;">'+
                            '<div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;flex-shrink:0;">'+name.charAt(0)+'</div>'+
                            '<div style="flex:1;min-width:0;">'+
                                '<div style="font-weight:700;font-size:.85rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'+esc(name)+'</div>'+
                                '<div style="font-size:.7rem;color:var(--text-muted);">'+
                                    (d.salary?'Зп '+fmoney(d.salary)+' ':'')+
                                    (d.advance?'Аванс '+fmoney(d.advance)+' ':'')+
                                    (d.bonus?'Премия '+fmoney(d.bonus)+' ':'')+
                                    (d.revenue_bonus?'Бонус '+fmoney(d.revenue_bonus)+' ':'')+
                                    (d.fine?'⚠️−'+fmoney(d.fine):'')+
                                '</div>'+
                            '</div>'+
                            '<div style="font-weight:800;color:var(--accent3);font-size:.95rem;">'+fmoney(total)+'</div>'+
                        '</div>';
                    }).join('');
            }

            /* Таблица */
            var tbody = el('sal_table');
            if (!tbody) return;
            tbody.innerHTML = !recs.length
                ? '<tr><td colspan="6" style="text-align:center;padding:28px;color:var(--text-muted);"><div style="font-size:2rem;opacity:.25;">💵</div><div style="margin-top:8px;">Выплат за выбранный период нет</div></td></tr>'
                : recs.map(function(r){
                    return '<tr style="border-bottom:1px solid rgba(45,53,86,.5);">'+
                        '<td style="padding:9px 10px;font-size:.78rem;white-space:nowrap;">'+fdate(r.date)+'</td>'+
                        '<td style="padding:9px 10px;font-weight:600;font-size:.83rem;">'+esc(r.staffName)+'</td>'+
                        '<td style="padding:9px 10px;"><span style="color:'+(PAY_COLOR[r.type]||'var(--text)')+';font-weight:700;font-size:.78rem;">'+(PAY_ICON[r.type]||'')+' '+(PAY_LABEL[r.type]||r.type)+'</span></td>'+
                        '<td style="padding:9px 10px;font-weight:700;font-size:.88rem;color:'+(r.type==='fine'?'var(--danger)':'var(--accent3)')+';'+'">'+
                            (r.type==='fine'?'−':'+')+fmoney(r.amount)+'</td>'+
                        '<td style="padding:9px 10px;font-size:.76rem;color:var(--text-muted);">'+(r.note||'—')+'</td>'+
                        '<td style="padding:9px 10px;"><button class="btn btn-danger btn-xs" onclick="window.__SAL.deletePayroll(\''+r.id+'\')">🗑️</button></td>'+
                    '</tr>';
                }).join('');
        });
    },

    openPayForm: function (staffName) {
        var dlEl = el('salPayDL');
        if (dlEl) {
            dlEl.innerHTML = this.employees.map(function(e){ return '<option value="'+esc(e.name)+'">'; }).join('');
        }
        setVal('sal_pay_name',   typeof staffName==='string' ? staffName : '');
        setVal('sal_pay_period', new Date().toISOString().slice(0,7));
        setVal('sal_pay_amount', '');
        setVal('sal_pay_note',   '');
        openModal('salPayModal');
    },

    savePayroll: function () {
        var self   = this;
        var name   = val('sal_pay_name').trim();
        var amount = parseFloat(val('sal_pay_amount'))||0;
        if (!name)   { notify('Введите имя сотрудника','error'); return; }
        if (!amount) { notify('Введите сумму','error'); return; }
        var emp = self.employees.find(function(e){ return e.name===name; });
        CRM.api('salary','add',{
            staffName:name, staffId:emp?emp.id:'',
            type:val('sal_pay_type'), amount:amount,
            period:val('sal_pay_period'), note:val('sal_pay_note')
        }).then(function (res) {
            if (!res||!res.ok) { notify('Ошибка сохранения','error'); return; }
            notify('✅ Выплата '+fmoney(amount)+' записана','success');
            closeModal('salPayModal');
            self.renderPayroll();
            self.renderKPI();
            self.renderEmployees();
            if (typeof refreshDashboard==='function') refreshDashboard();
            var fp = document.getElementById('page-finance');
            if (fp&&fp.classList.contains('active')&&typeof renderFinanceTable==='function')
                renderFinanceTable();
        });
    },

    deletePayroll: function (id) {
        var self = this;
        if (!confirm('Удалить запись? Связанный расход тоже удалится.')) return;
        CRM.api('salary','delete',null,{id:id}).then(function () {
            notify('Запись удалена','error');
            self.renderPayroll();
            self.renderKPI();
            self.renderEmployees();
            if (typeof refreshDashboard==='function') refreshDashboard();
            var fp = document.getElementById('page-finance');
            if (fp&&fp.classList.contains('active')&&typeof renderFinanceTable==='function')
                renderFinanceTable();
        });
    }
};

/* ══ ГЛОБАЛЬНЫЙ ДОСТУП ══ */
window.__SAL = SAL;
window.__SAL_el = el;  /* хелпер для inline onclick */

/* ══ РЕГИСТРАЦИЯ В CRM ══ */
CRM.registerModule({
    id:     'salary',
    name:   'Сотрудники',
    icon:   '👔',
    color:  '#10b981',
    page:   PAGE_HTML,
    render: function () {
        /* Пересоздаём стили при каждом рендере — защита от "протухания" */
        injectStyles();
        window.__SAL.render();
    }
});

})();
</script>