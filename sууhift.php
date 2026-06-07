<?php
/**
 * @name        Касса смены
 * @icon        💳
 * @description Касса, операции, QR-оплата, отчёты, ЗП
 * @version     5.1
 * @sidebar     true
 * @color       #f59e0b
 */

// ============ ИНИЦИАЛИЗАЦИЯ ============
if (!isset($moduleDB['shifts']))              $moduleDB['shifts']              = ['current'=>null,'history'=>[]];
if (!isset($moduleDB['finance']))             $moduleDB['finance']             = [];
if (!isset($moduleDB['salary']))              $moduleDB['salary']              = [];
if (!isset($moduleDB['salary']['records']))   $moduleDB['salary']['records']   = [];
if (!isset($moduleDB['salary']['employees'])) $moduleDB['salary']['employees'] = [];
if (!isset($moduleDB['reports']))             $moduleDB['reports']             = [];
if (!isset($moduleDB['shift_buttons']))       $moduleDB['shift_buttons']       = [
    ['id'=>'btn_1','label'=>'Фото 10×15', 'amount'=>15, 'type'=>'income','icon'=>'📸','color'=>'#f59e0b'],
    ['id'=>'btn_2','label'=>'Копия А4',   'amount'=>10, 'type'=>'income','icon'=>'📄','color'=>'#10b981'],
    ['id'=>'btn_3','label'=>'Печать А4',  'amount'=>20, 'type'=>'income','icon'=>'🖨️','color'=>'#3b82f6'],
    ['id'=>'btn_4','label'=>'Ламинация',  'amount'=>50, 'type'=>'income','icon'=>'✨','color'=>'#8b5cf6'],
];

// ============ HELPERS ============
function shiftFinanceExists($finance, $uniqKey) {
    foreach ($finance as $f) {
        if (isset($f['_uniqKey']) && $f['_uniqKey'] === $uniqKey) return true;
    }
    return false;
}

function shiftDetectCategory($desc, $type) {
    $d = mb_strtolower(trim($desc), 'UTF-8');
    if ($type === 'expense') {
        if (mb_strpos($d,'аренд',0,'UTF-8')      !==false) return 'Аренда помещения';
        if (mb_strpos($d,'зарплат',0,'UTF-8')    !==false) return 'Зарплата сотрудникам';
        if (mb_strpos($d,'бумаг',0,'UTF-8')      !==false||
            mb_strpos($d,'чернил',0,'UTF-8')     !==false||
            mb_strpos($d,'расходник',0,'UTF-8')  !==false||
            mb_strpos($d,'картридж',0,'UTF-8')   !==false) return 'Расходные материалы';
        if (mb_strpos($d,'инкассац',0,'UTF-8')   !==false) return 'Инкассация';
        if (mb_strpos($d,'налог',0,'UTF-8')      !==false||
            mb_strpos($d,'взнос',0,'UTF-8')      !==false) return 'Налоги / Взносы';
        if (mb_strpos($d,'реклам',0,'UTF-8')     !==false) return 'Реклама / Маркетинг';
        if (mb_strpos($d,'ремонт',0,'UTF-8')     !==false||
            mb_strpos($d,'оборудован',0,'UTF-8') !==false) return 'Ремонт оборудования';
        if (mb_strpos($d,'коммунал',0,'UTF-8')   !==false||
            mb_strpos($d,'электр',0,'UTF-8')     !==false||
            mb_strpos($d,'интернет',0,'UTF-8')   !==false) return 'Коммунальные услуги';
        return 'Прочие расходы';
    }
    if (mb_strpos($d,'фото',0,'UTF-8')       !==false||
        mb_strpos($d,'снимок',0,'UTF-8')     !==false) return 'Фотопечать';
    if (mb_strpos($d,'баннер',0,'UTF-8')     !==false||
        mb_strpos($d,'широкоформ',0,'UTF-8') !==false) return 'Баннерная печать';
    if (mb_strpos($d,'копи',0,'UTF-8')       !==false||
        mb_strpos($d,'распечат',0,'UTF-8')   !==false||
        mb_strpos($d,'печат',0,'UTF-8')      !==false) return 'Копирование / Распечатка';
    if (mb_strpos($d,'визит',0,'UTF-8')      !==false||
        mb_strpos($d,'листовк',0,'UTF-8')    !==false||
        mb_strpos($d,'буклет',0,'UTF-8')     !==false||
        mb_strpos($d,'флаер',0,'UTF-8')      !==false) return 'Бизнес-полиграфия';
    if (mb_strpos($d,'дизайн',0,'UTF-8')     !==false||
        mb_strpos($d,'макет',0,'UTF-8')      !==false) return 'Дизайн';
    if (mb_strpos($d,'ламин',0,'UTF-8')      !==false) return 'Ламинация';
    if (mb_strpos($d,'перепл',0,'UTF-8')     !==false) return 'Переплёт';
    if (mb_strpos($d,'сувен',0,'UTF-8')      !==false||
        mb_strpos($d,'кружк',0,'UTF-8')      !==false||
        mb_strpos($d,'магнит',0,'UTF-8')     !==false) return 'Сувенирная продукция';
    if (mb_strpos($d,'доставк',0,'UTF-8')    !==false) return 'Доставка';
    if (mb_strpos($d,'аванс',0,'UTF-8')      !==false||
        mb_strpos($d,'предоплат',0,'UTF-8')  !==false) return 'Авансовый платёж';
    return 'Выручка кассы';
}

function shiftBuildFinRecord($op, $shift) {
    $manager   = $shift['manager']  ?? 'Менеджер';
    $empId     = $shift['empId']    ?? '';
    $shiftDate = date('d.m.Y', strtotime($shift['openTime'] ?? 'now'));
    $desc      = trim($op['desc']   ?? '');
    $category  = shiftDetectCategory($desc, $op['type']);
    $finDesc   = $desc
        ? '💳 Смена '.$shiftDate.' ['.$manager.'] — '.$desc
        : '💳 Смена '.$shiftDate.' ['.$manager.'] — '.
          ($op['type']==='income' ? 'Поступление' : 'Изъятие');
    return [
        'id'        => 'shift_op_'.$op['id'],
        '_uniqKey'  => 'shift_op_'.$op['id'],
        'type'      => $op['type'],
        'date'      => $op['time'],
        'amount'    => floatval($op['amount']),
        'category'  => $category,
        'desc'      => $finDesc,
        'method'    => $op['method'] ?? 'Наличные',
        'client'    => $manager,
        'empId'     => $empId,
        'fromShift' => true,
        'shiftId'   => $shift['id'] ?? null,
        'shiftDate' => $shiftDate,
        'manager'   => $manager,
        'createdAt' => date('Y-m-d H:i:s'),
    ];
}

// ============ ROUTER ============
switch ($moduleAction) {

    case 'list':
    case 'current':
        echo json_encode(['ok'=>true,'data'=>$moduleDB['shifts']['current']]);
        break;

    case 'managers':
        $emps   = $moduleDB['salary']['employees'] ?? [];
        $active = array_values(array_filter($emps, fn($e)=>($e['status']??'active')==='active'));
        echo json_encode([
            'ok'   => true,
            'data' => array_map(fn($e)=>[
                'id'       => $e['id'],
                'name'     => $e['name'],
                'position' => $e['position'] ?? '',
                'color'    => $e['color']    ?? '#f59e0b',
                'bonusPct' => floatval($e['bonusPct'] ?? 0.1),
            ], $active)
        ]);
        break;

    case 'getButtons':
        echo json_encode(['ok'=>true,'data'=>$moduleDB['shift_buttons']]);
        break;

    case 'addButton':
        $b = [
            'id'     => 'btn_'.uniqid().'_'.rand(100,999),
            'label'  => trim($moduleBody['label']  ?? ''),
            'amount' => floatval($moduleBody['amount'] ?? 0),
            'type'   => in_array($moduleBody['type']??'income',['income','expense'])
                        ? $moduleBody['type'] : 'income',
            'icon'   => trim($moduleBody['icon']  ?? '💳'),
            'color'  => trim($moduleBody['color'] ?? '#f59e0b'),
        ];
        if (!$b['label']) { echo json_encode(['ok'=>false,'error'=>'Нет названия']); break; }
        $moduleDB['shift_buttons'][] = $b;
        writeDB($moduleDB);
        echo json_encode(['ok'=>true,'data'=>$b]);
        break;

    case 'deleteButton':
        $bid = $moduleBody['id'] ?? $_GET['id'] ?? null;
        if (!$bid) { echo json_encode(['ok'=>false,'error'=>'Нет ID']); break; }
        $moduleDB['shift_buttons'] = array_values(array_filter(
            $moduleDB['shift_buttons'], fn($b)=>(string)$b['id']!==(string)$bid
        ));
        writeDB($moduleDB);
        echo json_encode(['ok'=>true]);
        break;

    case 'open':
        if ($moduleDB['shifts']['current']) {
            echo json_encode(['ok'=>false,'error'=>'Смена уже открыта']);
            break;
        }
        $iKey = trim($moduleBody['iKey'] ?? '');
        if ($iKey) {
            foreach ($moduleDB['shifts']['history'] as $hs) {
                if (($hs['iKey'] ?? '') === $iKey) {
                    echo json_encode(['ok'=>false,'error'=>'Дубль: смена уже открыта с этим ключом']);
                    break 2;
                }
            }
        }
        $empId    = trim($moduleBody['empId']   ?? '');
        $manager  = trim($moduleBody['manager'] ?? 'Менеджер');
        $bonusPct = 0.1;
        foreach ($moduleDB['salary']['employees'] as $emp) {
            if ((string)$emp['id']===(string)$empId) {
                $bonusPct = floatval($emp['bonusPct'] ?? 0.1);
                $manager  = $emp['name'];
                break;
            }
        }
        $shift = [
            'id'           => uniqid('shift_', true),
            'iKey'         => $iKey,
            'empId'        => $empId,
            'manager'      => $manager,
            'startCash'    => floatval($moduleBody['startCash'] ?? 0),
            'cash'         => floatval($moduleBody['startCash'] ?? 0),
            'bonusPct'     => $bonusPct,
            'totalIncome'  => 0,
            'totalExpense' => 0,
            'accruedBonus' => 0,
            'operations'   => [],
            'openTime'     => date('Y-m-d H:i:s'),
            'closeTime'    => null,
            'completed'    => false,
        ];
        $moduleDB['shifts']['current'] = $shift;
        writeDB($moduleDB);
        writeLog('SHIFT_OPEN', $shift['manager'].' | startCash:'.$shift['startCash']);
        echo json_encode(['ok'=>true,'data'=>$shift]);
        break;

    case 'operation':
        if (!$moduleDB['shifts']['current']) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'Смена не открыта']);
            break;
        }
        $iKey = trim($moduleBody['iKey'] ?? '');
        if ($iKey) {
            foreach ($moduleDB['shifts']['current']['operations'] as $existOp) {
                if (($existOp['iKey'] ?? '') === $iKey) {
                    echo json_encode(['ok'=>true,'data'=>$moduleDB['shifts']['current'],'_dup'=>true]);
                    break 2;
                }
            }
        }
        $type   = $moduleBody['type']   ?? 'income';
        $amount = floatval($moduleBody['amount'] ?? 0);
        $desc   = trim($moduleBody['desc']   ?? '');
        $method = trim($moduleBody['method'] ?? 'Наличные');
        $qty    = max(1, intval($moduleBody['qty'] ?? 1));

        if ($amount <= 0) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'Сумма должна быть больше нуля']);
            break;
        }
        if (!in_array($type, ['income','expense'])) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'Неверный тип']);
            break;
        }

        $finalAmount = round($amount * $qty, 2);
        $finalDesc   = $qty > 1 ? $desc.' × '.$qty : $desc;

        $op = [
            'id'     => uniqid('op_', true),
            'iKey'   => $iKey,
            'type'   => $type,
            'amount' => $finalAmount,
            'desc'   => $finalDesc,
            'method' => $method,
            'qty'    => $qty,
            'price'  => $amount,
            'time'   => date('Y-m-d H:i:s'),
        ];

        $moduleDB['shifts']['current']['operations'][] = $op;

        if ($type === 'income') {
            $moduleDB['shifts']['current']['cash']         += $finalAmount;
            $moduleDB['shifts']['current']['totalIncome']  += $finalAmount;
            $bonusPct = floatval($moduleDB['shifts']['current']['bonusPct'] ?? 0.1);
            $moduleDB['shifts']['current']['accruedBonus'] +=
                round($finalAmount * $bonusPct / 100, 2);
        } else {
            $moduleDB['shifts']['current']['cash']         -= $finalAmount;
            $moduleDB['shifts']['current']['totalExpense'] += $finalAmount;
        }

        $finRecord = shiftBuildFinRecord($op, $moduleDB['shifts']['current']);
        if (!shiftFinanceExists($moduleDB['finance'], $finRecord['_uniqKey'])) {
            array_unshift($moduleDB['finance'], $finRecord);
        }

        writeDB($moduleDB);
        echo json_encode([
            'ok'           => true,
            'data'         => $moduleDB['shifts']['current'],
            'finance'      => $finRecord,
            'accruedBonus' => $moduleDB['shifts']['current']['accruedBonus'],
        ]);
        break;

    case 'deleteOperation':
        if (!$moduleDB['shifts']['current']) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'Смена не открыта']);
            break;
        }
        $opId  = $moduleBody['opId'] ?? null;
        $found = null;
        foreach ($moduleDB['shifts']['current']['operations'] as $i => $op) {
            if ((string)$op['id'] === (string)$opId) { $found = $op; $foundIdx = $i; break; }
        }
        if (!$found) {
            http_response_code(404);
            echo json_encode(['ok'=>false,'error'=>'Операция не найдена']);
            break;
        }
        if ($found['type'] === 'income') {
            $moduleDB['shifts']['current']['cash']         -= $found['amount'];
            $moduleDB['shifts']['current']['totalIncome']  -= $found['amount'];
            $bonusPct = floatval($moduleDB['shifts']['current']['bonusPct'] ?? 0.1);
            $moduleDB['shifts']['current']['accruedBonus'] -=
                round($found['amount'] * $bonusPct / 100, 2);
            $moduleDB['shifts']['current']['accruedBonus'] =
                max(0, $moduleDB['shifts']['current']['accruedBonus']);
        } else {
            $moduleDB['shifts']['current']['cash']         += $found['amount'];
            $moduleDB['shifts']['current']['totalExpense'] -= $found['amount'];
        }
        array_splice($moduleDB['shifts']['current']['operations'], $foundIdx, 1);
        $moduleDB['finance'] = array_values(array_filter(
            $moduleDB['finance'],
            fn($f) => ($f['_uniqKey'] ?? '') !== 'shift_op_'.$opId
        ));
        writeDB($moduleDB);
        writeLog('SHIFT_OP_DELETE', 'opId='.$opId);
        echo json_encode(['ok'=>true,'data'=>$moduleDB['shifts']['current']]);
        break;

    case 'editOperation':
        if (!$moduleDB['shifts']['current']) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'Смена не открыта']);
            break;
        }
        $opId      = $moduleBody['opId']   ?? null;
        $newAmt    = floatval($moduleBody['amount'] ?? 0);
        $newDesc   = trim($moduleBody['desc']   ?? '');
        $newMethod = trim($moduleBody['method'] ?? 'Наличные');
        $newQty    = max(1, intval($moduleBody['qty']   ?? 1));
        $newPrice  = floatval($moduleBody['price']  ?? $newAmt);

        if ($newAmt <= 0) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'Сумма должна быть больше нуля']);
            break;
        }

        $found = false;
        foreach ($moduleDB['shifts']['current']['operations'] as &$op) {
            if ((string)$op['id'] !== (string)$opId) continue;
            $oldAmt  = $op['amount'];
            $oldType = $op['type'];
            $diff    = $newAmt - $oldAmt;
            if ($oldType === 'income') {
                $moduleDB['shifts']['current']['cash']        += $diff;
                $moduleDB['shifts']['current']['totalIncome'] += $diff;
                $bonusPct = floatval($moduleDB['shifts']['current']['bonusPct'] ?? 0.1);
                $oldBonus = round($oldAmt * $bonusPct / 100, 2);
                $newBonus = round($newAmt * $bonusPct / 100, 2);
                $moduleDB['shifts']['current']['accruedBonus'] += ($newBonus - $oldBonus);
                $moduleDB['shifts']['current']['accruedBonus'] =
                    max(0, $moduleDB['shifts']['current']['accruedBonus']);
            } else {
                $moduleDB['shifts']['current']['cash']         -= $diff;
                $moduleDB['shifts']['current']['totalExpense'] += $diff;
            }
            $op['amount'] = $newAmt;
            $op['desc']   = $newDesc;
            $op['method'] = $newMethod;
            $op['price']  = $newPrice;
            $op['qty']    = $newQty;
            $found        = true;
            foreach ($moduleDB['finance'] as &$f) {
                if (($f['_uniqKey'] ?? '') === 'shift_op_'.$opId) {
                    $f['amount'] = $newAmt;
                    $f['desc']   = '💳 [Исправлено] '.$newDesc;
                    $f['method'] = $newMethod;
                    break;
                }
            }
            unset($f);
            break;
        }
        unset($op);

        if (!$found) {
            http_response_code(404);
            echo json_encode(['ok'=>false,'error'=>'Операция не найдена']);
            break;
        }
        writeDB($moduleDB);
        echo json_encode(['ok'=>true,'data'=>$moduleDB['shifts']['current']]);
        break;

    case 'close':
        if (!$moduleDB['shifts']['current']) {
            http_response_code(400);
            echo json_encode(['ok'=>false,'error'=>'Смена не открыта']);
            break;
        }
        $shift              = $moduleDB['shifts']['current'];
        $shift['closeTime'] = date('Y-m-d H:i:s');
        $shift['endCash']   = floatval($moduleBody['endCash']    ?? $shift['cash']);
        $shift['note']      = trim($moduleBody['note']           ?? '');
        $shift['completed'] = true;
        $baseSalary         = floatval($moduleBody['baseSalary'] ?? 0);
        $accruedBonus       = floatval($shift['accruedBonus']    ?? 0);
        $totalSalary        = round($baseSalary + $accruedBonus, 2);
        $shift['baseSalary']   = $baseSalary;
        $shift['accruedBonus'] = $accruedBonus;
        $shift['totalSalary']  = $totalSalary;
        $manager   = $shift['manager'];
        $empId     = $shift['empId'] ?? '';
        $shiftDate = date('d.m.Y', strtotime($shift['openTime']));

        foreach ($shift['operations'] as $op) {
            $fr = shiftBuildFinRecord($op, $shift);
            if (!shiftFinanceExists($moduleDB['finance'], $fr['_uniqKey'])) {
                array_unshift($moduleDB['finance'], $fr);
            }
        }

        $diff = round($shift['endCash'] - $shift['cash'], 2);
        $shift['cashDiff'] = $diff;
        if (abs($diff) >= 0.01) {
            $diffKey  = 'shift_diff_'.$shift['id'];
            $diffType = $diff < 0 ? 'expense' : 'income';
            if (!shiftFinanceExists($moduleDB['finance'], $diffKey)) {
                array_unshift($moduleDB['finance'], [
                    'id'        => $diffKey,
                    '_uniqKey'  => $diffKey,
                    'type'      => $diffType,
                    'date'      => $shift['closeTime'],
                    'amount'    => abs($diff),
                    'category'  => $diff < 0 ? 'Недостача кассы' : 'Излишек кассы',
                    'desc'      => ($diff<0?'⚠️ Недостача':'✅ Излишек').
                                   ' | Смена '.$shiftDate.' ['.$manager.']'.
                                   ' | расчёт: '.$shift['cash'].'₽'.
                                   ' | факт: '.$shift['endCash'].'₽',
                    'method'    => 'Наличные',
                    'client'    => $manager,
                    'fromShift' => true,
                    'shiftId'   => $shift['id'],
                    'manager'   => $manager,
                    'isDiff'    => true,
                    'createdAt' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        if ($totalSalary > 0) {
            $salKey = 'shift_salary_'.$shift['id'];
            if (!shiftFinanceExists($moduleDB['finance'], $salKey)) {
                array_unshift($moduleDB['finance'], [
                    'id'        => $salKey,
                    '_uniqKey'  => $salKey,
                    'type'      => 'expense',
                    'date'      => $shift['closeTime'],
                    'amount'    => $totalSalary,
                    'category'  => 'Зарплата и выплаты',
                    'desc'      => '👔 ЗП смены '.$shiftDate.' ['.$manager.']'.
                                   ' | оклад: '.$baseSalary.'₽'.
                                   ' | бонус '.$shift['bonusPct'].'%: '.$accruedBonus.'₽',
                    'method'    => 'Наличные',
                    'client'    => $manager,
                    'fromShift' => true,
                    'shiftId'   => $shift['id'],
                    'manager'   => $manager,
                    'createdAt' => date('Y-m-d H:i:s'),
                ]);
            }
            if ($empId) {
                $salRec = [
                    'id'        => 'sal_shift_'.$shift['id'],
                    'staffName' => $manager,
                    'staffId'   => $empId,
                    'type'      => 'salary',
                    'amount'    => $totalSalary,
                    'period'    => date('Y-m', strtotime($shift['openTime'])),
                    'note'      => 'Смена '.$shiftDate.' | оклад: '.$baseSalary.'₽ | бонус: '.$accruedBonus.'₽',
                    'date'      => $shift['closeTime'],
                    'revenue'   => $shift['totalIncome'] ?? 0,
                ];
                $exists = false;
                foreach ($moduleDB['salary']['records'] as $sr) {
                    if ($sr['id'] === $salRec['id']) { $exists = true; break; }
                }
                if (!$exists) array_unshift($moduleDB['salary']['records'], $salRec);
            }
        }

        // Разбивка по методам оплаты для Z-отчёта
        $methodTotals = [];
        foreach ($shift['operations'] as $op) {
            $m = $op['method'] ?? 'Наличные';
            if (!isset($methodTotals[$m])) $methodTotals[$m] = ['income'=>0,'expense'=>0];
            $methodTotals[$m][$op['type']] += $op['amount'];
        }

        $report = [
            'id'             => 'report_'.$shift['id'],
            'type'           => 'shift_z',
            'shiftId'        => $shift['id'],
            'date'           => $shift['closeTime'],
            'shiftDate'      => $shiftDate,
            'manager'        => $manager,
            'empId'          => $empId,
            'openTime'       => $shift['openTime'],
            'closeTime'      => $shift['closeTime'],
            'startCash'      => $shift['startCash'],
            'endCash'        => $shift['endCash'],
            'calcCash'       => $shift['cash'],
            'cashDiff'       => $diff,
            'totalIncome'    => $shift['totalIncome']  ?? 0,
            'totalExpense'   => $shift['totalExpense'] ?? 0,
            'profit'         => ($shift['totalIncome']??0) - ($shift['totalExpense']??0),
            'baseSalary'     => $baseSalary,
            'bonusPct'       => $shift['bonusPct'] ?? 0.1,
            'accruedBonus'   => $accruedBonus,
            'totalSalary'    => $totalSalary,
            'operationsCount'=> count($shift['operations']),
            'operations'     => $shift['operations'],
            'methodTotals'   => $methodTotals,
            'note'           => $shift['note'],
            'createdAt'      => date('Y-m-d H:i:s'),
        ];
        array_unshift($moduleDB['reports'], $report);
        if (count($moduleDB['reports']) > 200) {
            $moduleDB['reports'] = array_slice($moduleDB['reports'], 0, 200);
        }

        $moduleDB['shifts']['history'][] = $shift;
        $moduleDB['shifts']['current']   = null;
        writeDB($moduleDB);
        writeLog('SHIFT_CLOSE',
            $manager.
            ' | доход:'.$shift['totalIncome'].
            ' | расход:'.$shift['totalExpense'].
            ' | зп:'.$totalSalary.
            ' | расхождение:'.$diff
        );
        echo json_encode(['ok'=>true,'data'=>$shift,'report'=>$report]);
        break;

    case 'history':
        $hist = array_reverse($moduleDB['shifts']['history'] ?? []);
        echo json_encode(['ok'=>true,'data'=>array_slice($hist,0,50)]);
        break;

    case 'reports':
        echo json_encode(['ok'=>true,'data'=>array_slice($moduleDB['reports']??[],0,50)]);
        break;

    case 'lastEndCash':
        $empId = trim($moduleBody['empId'] ?? $_GET['empId'] ?? '');
        $hist  = array_reverse($moduleDB['shifts']['history'] ?? []);
        $last  = null;
        foreach ($hist as $hs) {
            if (!$empId || (string)($hs['empId']??'') === (string)$empId) {
                $last = $hs;
                break;
            }
        }
        echo json_encode([
            'ok'      => true,
            'endCash' => $last ? ($last['endCash'] ?? $last['cash'] ?? 0) : 0,
            'date'    => $last ? ($last['closeTime'] ?? null) : null,
        ]);
        break;

    default:
        echo json_encode(['ok'=>true,'data'=>null]);
}
?>
<!--MODULE_JS_START-->
<script>
/* ================================================================
   КАССА СМЕНЫ v5.1
================================================================ */
CRM.registerModule({
  id:    'shift',
  name:  'Касса смены',
  icon:  '💳',
  color: '#f59e0b',

  _current:          null,
  _managers:         [],
  _buttons:          [],
  _opType:           'income',
  _activeTab:        'cashier',
  _durationInterval: null,
  _editingOpId:      null,
  _submitLock:       false,
  _openLock:         false,
  _closeLock:        false,
  _longShiftWarned:  false,
  _opsView:          'chrono',
  _opsFilter:        '',
  _btnUsage:         {},

  // ================================================================
  // PAGE HTML
  // ================================================================
  page: `
    <div class="page-header">
      <div>
        <div class="page-title">💳 Касса смены</div>
        <div class="page-subtitle">Синхронизация с финансами и зарплатой в реальном времени</div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <button class="btn btn-sm" id="sh_tab_btn_cashier"
          onclick="CRM.modules.shift._tab('cashier')"
          style="background:rgba(245,158,11,0.15);border:1px solid rgba(245,158,11,0.4);
                 color:#f59e0b;font-weight:700;">
          💳 Касса
        </button>
        <button class="btn btn-secondary btn-sm" id="sh_tab_btn_reports"
          onclick="CRM.modules.shift._tab('reports')">
          📋 Отчёты
        </button>
        <button class="btn btn-secondary btn-sm" id="sh_tab_btn_settings"
          onclick="CRM.modules.shift._tab('settings')">
          ⚙️ Кнопки
        </button>
        <button class="btn btn-secondary btn-sm"
          onclick="CRM.modules.shift._openHotkeysModal()"
          title="Горячие клавиши">
          ⌨️
        </button>
        <button class="btn btn-secondary btn-sm"
          onclick="CRM.modules.shift.render()">🔄</button>
      </div>
    </div>

    <!-- ═══ ТАБ: КАССА ═══ -->
    <div id="sh_tab_cashier">

      <!-- БЛОК ОТКРЫТИЯ СМЕНЫ -->
      <div id="sh_open_block" style="display:none;">
        <div style="max-width:500px;margin:0 auto;">
          <div class="card" style="text-align:center;padding:36px 32px;">
            <div style="font-size:4.5rem;margin-bottom:18px;
                        filter:drop-shadow(0 0 24px rgba(245,158,11,0.4));">🔓</div>
            <div style="font-size:1.5rem;font-weight:900;margin-bottom:6px;">Открыть смену</div>
            <div style="font-size:0.82rem;color:var(--text-muted);margin-bottom:28px;">
              Операции автоматически попадают в финансовый журнал
            </div>

            <div class="form-group" style="text-align:left;">
              <label class="form-label">👤 Менеджер смены</label>
              <select class="form-select" id="sh_manager_sel">
                <option value="">⏳ Загрузка сотрудников...</option>
              </select>
              <div id="sh_no_emp" style="display:none;margin-top:8px;">
                <input class="form-input" id="sh_manager_manual" placeholder="Введите имя вручную">
              </div>
            </div>

            <div class="form-group" style="text-align:left;">
              <label class="form-label">💰 Начальная сумма в кассе ₽</label>
              <input class="form-input" type="number" id="sh_start_cash" placeholder="0" value="0">
              <div class="form-hint" id="sh_start_cash_hint" style="color:var(--accent3);"></div>
            </div>

            <div id="sh_bonus_hint" style="display:none;
                  background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);
                  border-radius:10px;padding:10px 14px;margin-bottom:16px;text-align:left;">
              <div style="font-size:0.75rem;font-weight:700;color:var(--accent3);">🎁 Бонус с продаж</div>
              <div id="sh_bonus_hint_text" style="font-size:0.78rem;color:var(--text-muted);margin-top:3px;"></div>
            </div>

            <button class="btn btn-success" id="sh_open_btn"
                    style="width:100%;padding:14px;font-size:1rem;font-weight:800;"
                    onclick="CRM.modules.shift.openShift()">
              ▶ Открыть смену
            </button>
          </div>
        </div>
      </div>

      <!-- АКТИВНАЯ СМЕНА -->
      <div id="sh_active_block" style="display:none;">

        <!-- Шапка-статистика -->
        <div id="sh_stat_card" style="
              background:linear-gradient(135deg,rgba(245,158,11,0.08) 0%,rgba(16,185,129,0.05) 100%);
              border:1px solid rgba(245,158,11,0.2);border-radius:18px;
              padding:20px 24px;margin-bottom:20px;">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;">
            <div style="min-width:220px;">
              <div style="font-size:0.65rem;text-transform:uppercase;letter-spacing:1.5px;
                          color:var(--text-muted);font-weight:700;margin-bottom:4px;">Активная смена</div>
              <div style="font-size:1.25rem;font-weight:900;" id="sh_hdr_manager">—</div>
              <div style="font-size:0.73rem;color:var(--text-muted);margin-top:2px;" id="sh_hdr_time">—</div>

              <!-- Инфоблок заработка -->
              <div style="margin-top:12px;background:rgba(139,92,246,0.1);
                          border:1px solid rgba(139,92,246,0.25);border-radius:12px;padding:12px 14px;">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:8px;">
                  <input type="checkbox" id="sh_show_earned_cb"
                    onchange="CRM.modules.shift._toggleEarnedBlock(this.checked)"
                    style="width:16px;height:16px;cursor:pointer;">
                  <span style="font-size:0.75rem;font-weight:700;color:#a78bfa;">💜 Показать мой заработок</span>
                </label>
                <div id="sh_earned_block" style="display:none;">
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                    <span style="font-size:0.72rem;color:var(--text-muted);">
                      Бонус <span id="sh_earn_pct">0.1</span>% с дохода <span id="sh_earn_income">0 ₽</span>
                    </span>
                    <span style="font-weight:800;color:#a78bfa;font-size:1rem;" id="sh_earn_bonus">0 ₽</span>
                  </div>
                  <div style="height:4px;background:rgba(139,92,246,0.15);border-radius:2px;overflow:hidden;">
                    <div id="sh_earn_bar"
                         style="height:100%;background:linear-gradient(90deg,#8b5cf6,#a78bfa);
                                border-radius:2px;width:0%;transition:width 0.4s;"></div>
                  </div>
                  <div style="font-size:0.7rem;color:var(--text-muted);margin-top:4px;">
                    + оклад за день: <b id="sh_earn_base_preview">—</b>
                  </div>
                </div>
              </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:20px;text-align:center;">
              <div>
                <div style="font-size:1.4rem;font-weight:900;color:var(--accent3);" id="sh_stat_cash">0 ₽</div>
                <div style="font-size:0.62rem;color:var(--text-muted);margin-top:2px;">В кассе</div>
              </div>
              <div>
                <div style="font-size:1.4rem;font-weight:900;color:var(--accent2);" id="sh_stat_income">0 ₽</div>
                <div style="font-size:0.62rem;color:var(--text-muted);margin-top:2px;">Доход</div>
              </div>
              <div>
                <div style="font-size:1.4rem;font-weight:900;color:var(--danger);" id="sh_stat_expense">0 ₽</div>
                <div style="font-size:0.62rem;color:var(--text-muted);margin-top:2px;">Расход</div>
              </div>
              <div>
                <div style="font-size:1.4rem;font-weight:900;color:#a78bfa;" id="sh_stat_dur">0ч 00м</div>
                <div style="font-size:0.62rem;color:var(--text-muted);margin-top:2px;">Время</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Основная рабочая область -->
        <div style="display:grid;grid-template-columns:1fr 380px;gap:18px;align-items:start;">

          <!-- ЛЕВАЯ КОЛОНКА -->
          <div>
            <!-- Быстрые кнопки -->
            <div style="margin-bottom:16px;">
              <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:1px;
                          font-weight:700;color:var(--text-muted);margin-bottom:10px;">
                ⚡ Быстрые операции
                <span style="font-weight:400;font-size:0.68rem;margin-left:6px;">
                  (нажмите — заполнит форму, задайте количество и проведите)
                </span>
              </div>
              <div id="sh_quick_btns" style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;"></div>
            </div>

            <!-- Форма операции -->
            <div class="card">
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                <div class="card-title" style="margin:0;" id="sh_form_title">✍️ Операция</div>
                <div style="display:flex;gap:6px;">
                  <button id="sh_btn_income" class="btn btn-success btn-sm"
                    onclick="CRM.modules.shift._setType('income')">▲ Приход</button>
                  <button id="sh_btn_expense" class="btn btn-secondary btn-sm"
                    onclick="CRM.modules.shift._setType('expense')">▼ Расход</button>
                </div>
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                  <label class="form-label">Цена за 1 шт. ₽</label>
                  <input class="form-input" type="number" id="sh_op_amount" placeholder="0"
                         style="font-size:1.3rem;font-weight:700;text-align:center;"
                         oninput="CRM.modules.shift._calcTotal()">
                  <div style="display:flex;gap:4px;margin-top:6px;flex-wrap:wrap;">
                    ${[10,20,50,100,200,500,1000].map(v=>`
                      <button class="btn btn-secondary btn-xs"
                        onclick="document.getElementById('sh_op_amount').value=${v};
                                 CRM.modules.shift._calcTotal()">${v}</button>
                    `).join('')}
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">Количество</label>
                  <div style="display:flex;align-items:center;gap:8px;">
                    <button class="btn btn-secondary btn-sm"
                      style="padding:8px 14px;font-size:1.1rem;"
                      onclick="CRM.modules.shift._changeQty(-1)">−</button>
                    <input class="form-input" type="number" id="sh_op_qty" value="1" min="1"
                           style="text-align:center;font-size:1.2rem;font-weight:700;width:70px;"
                           oninput="CRM.modules.shift._calcTotal()">
                    <button class="btn btn-secondary btn-sm"
                      style="padding:8px 14px;font-size:1.1rem;"
                      onclick="CRM.modules.shift._changeQty(1)">+</button>
                  </div>
                  <div id="sh_op_total_preview"
                       style="margin-top:6px;font-size:0.82rem;color:var(--accent3);
                              font-weight:700;text-align:center;"></div>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">Описание / За что</label>
                <input class="form-input" id="sh_op_desc"
                       placeholder="Фотопечать 10×15, копирование А4..."
                       list="sh_desc_hints"
                       onkeydown="if(event.key==='Enter')CRM.modules.shift.submitOp()">
                <datalist id="sh_desc_hints">
                  <option value="Фотопечать 10×15">
                  <option value="Фотопечать А4">
                  <option value="Баннерная печать">
                  <option value="Копирование А4">
                  <option value="Ламинация А4">
                  <option value="Переплёт документов">
                  <option value="Визитки">
                  <option value="Дизайн макета">
                  <option value="Бумага А4">
                  <option value="Чернила для принтера">
                  <option value="Аренда помещения">
                </datalist>
              </div>

              <div class="form-group">
                <label class="form-label">Метод оплаты</label>
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;">
                  ${[['Наличные','💵'],['Карта','💳'],['QR / СБП','📱'],['Перевод','🏦']].map(([m,ico])=>`
                    <button id="sh_method_${m.replace(/[^а-яёa-z0-9]/gi,'_')}"
                      class="btn btn-secondary btn-sm sh-method-btn"
                      style="font-size:0.72rem;padding:7px 4px;"
                      onclick="CRM.modules.shift._setMethod('${m}')">
                      ${ico} ${m}
                    </button>
                  `).join('')}
                </div>
                <input type="hidden" id="sh_op_method" value="Наличные">
              </div>

              <!-- Блок расчёта сдачи -->
              <div id="sh_change_block"
                   style="background:rgba(245,158,11,0.07);border:1px solid rgba(245,158,11,0.2);
                          border-radius:10px;padding:12px;margin-bottom:12px;display:none;">
                <div style="font-size:0.75rem;font-weight:700;color:#f59e0b;margin-bottom:8px;">
                  💵 Расчёт сдачи
                </div>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div style="flex:1;">
                    <label class="form-label" style="font-size:0.7rem;">Клиент дал ₽</label>
                    <input class="form-input" type="number" id="sh_given_cash" placeholder="0"
                           oninput="CRM.modules.shift._calcChange()"
                           style="text-align:center;font-weight:700;">
                  </div>
                  <div style="font-size:1.5rem;color:var(--text-muted);padding-top:16px;">→</div>
                  <div style="flex:1;text-align:center;">
                    <div style="font-size:0.7rem;color:var(--text-muted);margin-bottom:4px;">Сдача</div>
                    <div id="sh_change_result" style="font-size:1.5rem;font-weight:900;color:var(--accent3);">— ₽</div>
                  </div>
                </div>
              </div>

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:4px;">
                <button class="btn btn-success" id="sh_submit_btn"
                        style="padding:13px;font-size:0.95rem;font-weight:800;"
                        onclick="CRM.modules.shift.submitOp()">
                  ▲ Провести приход
                </button>
                <button class="btn btn-secondary"
                        style="padding:13px;font-size:0.82rem;"
                        onclick="CRM.modules.shift._clearForm()">
                  🗑 Очистить
                </button>
              </div>

              <div id="sh_edit_cancel_row" style="display:none;margin-top:8px;">
                <button class="btn btn-secondary btn-sm" style="width:100%;"
                        onclick="CRM.modules.shift._cancelEdit()">
                  ✕ Отменить редактирование
                </button>
              </div>
            </div>

            <!-- Закрытие смены -->
            <div class="card mt-16" style="border:1px solid rgba(239,68,68,0.2);">
              <div class="card-title" style="color:var(--danger);">🔒 Закрыть смену</div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                  <label class="form-label">Фактическая сумма в кассе ₽</label>
                  <input class="form-input" type="number" id="sh_end_cash" placeholder="0"
                         oninput="CRM.modules.shift._onEndCashInput(this.value)">
                  <div class="form-hint" id="sh_expected_hint" style="font-weight:700;">Ожидается: 0 ₽</div>
                  <div id="sh_diff_indicator"
                       style="margin-top:6px;padding:6px 10px;border-radius:8px;
                              font-size:0.78rem;font-weight:700;display:none;"></div>
                </div>
                <div class="form-group">
                  <label class="form-label">
                    Оклад за день ₽
                    <span id="sh_close_bonus_lbl" style="color:#a78bfa;font-size:0.78rem;"></span>
                  </label>
                  <input class="form-input" type="number" id="sh_base_salary" placeholder="0" value="0"
                         oninput="CRM.modules.shift._updateSalaryPreview()">
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                  <label class="form-label">Заметки</label>
                  <input class="form-input" id="sh_close_note" placeholder="Передать заказы, материалы...">
                </div>
              </div>

              <div id="sh_salary_summary"
                   style="background:rgba(16,185,129,0.07);border:1px solid rgba(16,185,129,0.2);
                          border-radius:10px;padding:12px;margin-bottom:14px;">
                <div id="sh_salary_rows" style="font-size:0.82rem;"></div>
              </div>

              <button class="btn btn-danger" id="sh_close_btn"
                      style="padding:13px 28px;font-weight:800;font-size:0.95rem;"
                      onclick="CRM.modules.shift.closeShift()">
                🔒 Закрыть смену и сформировать Z-отчёт
              </button>
            </div>
          </div>

          <!-- ПРАВАЯ КОЛОНКА: лог операций -->
          <div>
            <div class="card" style="position:sticky;top:80px;">
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <div style="font-weight:700;font-size:0.88rem;">📋 Операции смены</div>
                <div id="sh_ops_summary" style="font-size:0.75rem;color:var(--text-muted);"></div>
              </div>

              <div style="display:flex;gap:6px;margin-bottom:10px;">
                <input class="form-input" id="sh_ops_search"
                       placeholder="🔍 Поиск операций..."
                       style="font-size:0.78rem;padding:6px 10px;flex:1;"
                       oninput="CRM.modules.shift._opsFilter=this.value;CRM.modules.shift._renderOps()">
                <button class="btn btn-secondary btn-xs" id="sh_ops_view_btn"
                        onclick="CRM.modules.shift._toggleOpsView()"
                        title="Переключить: хронология / по категориям">≡</button>
              </div>

              <div id="sh_ops_stats"
                   style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;margin-bottom:10px;"></div>

              <div id="sh_ops_list" style="max-height:calc(100vh - 360px);overflow-y:auto;"></div>
            </div>
          </div>
        </div>

      </div><!-- /sh_active_block -->

      <!-- История смен -->
      <div class="section-label" style="margin-top:28px;margin-bottom:12px;">📅 История смен</div>
      <div class="card">
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Дата</th><th>Менеджер</th><th>Длит.</th>
                <th>Доход</th><th>Расход</th><th>Прибыль</th>
                <th>Касса факт</th><th>Расхожд.</th><th>ЗП</th><th></th>
              </tr>
            </thead>
            <tbody id="sh_history_body"></tbody>
          </table>
        </div>
      </div>
    </div><!-- /sh_tab_cashier -->

    <!-- ═══ ТАБ: ОТЧЁТЫ ═══ -->
    <div id="sh_tab_reports" style="display:none;">
      <div id="sh_reports_list"></div>
    </div>

    <!-- ═══ ТАБ: КНОПКИ ═══ -->
    <div id="sh_tab_settings" style="display:none;">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

        <div class="card">
          <div class="card-title">⚡ Управление быстрыми кнопками</div>
          <div id="sh_btns_list" style="margin-bottom:16px;"></div>

          <div style="background:var(--bg-card2);border-radius:12px;padding:16px;">
            <div style="font-weight:700;font-size:0.85rem;margin-bottom:12px;">➕ Добавить кнопку</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
              <div class="form-group">
                <label class="form-label">Название</label>
                <input class="form-input" id="sb_label" placeholder="Печать А4"
                       onkeydown="if(event.key==='Enter')CRM.modules.shift._addButton()">
              </div>
              <div class="form-group">
                <label class="form-label">Цена ₽</label>
                <input class="form-input" type="number" id="sb_amount" placeholder="0"
                       onkeydown="if(event.key==='Enter')CRM.modules.shift._addButton()">
              </div>
              <div class="form-group">
                <label class="form-label">Тип</label>
                <select class="form-select" id="sb_type">
                  <option value="income">▲ Приход</option>
                  <option value="expense">▼ Расход</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Иконка</label>
                <input class="form-input" id="sb_icon" value="🖨️"
                       style="font-size:1.2rem;text-align:center;">
              </div>
              <div class="form-group" style="grid-column:1/-1;">
                <label class="form-label">Цвет</label>
                <div style="display:flex;gap:8px;align-items:center;">
                  <input type="color" id="sb_color" value="#f59e0b"
                    style="width:48px;height:38px;border-radius:8px;
                           border:1px solid var(--border);cursor:pointer;padding:2px;">
                  <div style="display:flex;gap:5px;flex-wrap:wrap;">
                    ${['#f59e0b','#10b981','#3b82f6','#8b5cf6','#ef4444','#ec4899','#06b6d4','#84cc16'].map(c=>`
                      <div onclick="document.getElementById('sb_color').value='${c}'"
                           style="width:24px;height:24px;border-radius:6px;background:${c};
                                  cursor:pointer;border:2px solid transparent;transition:border 0.15s;"
                           onmouseover="this.style.borderColor='white'"
                           onmouseout="this.style.borderColor='transparent'"></div>
                    `).join('')}
                  </div>
                </div>
              </div>
            </div>
            <button class="btn btn-success btn-sm" style="width:100%;margin-top:8px;"
                    onclick="CRM.modules.shift._addButton()">
              ➕ Добавить
            </button>
          </div>
        </div>

        <div class="card">
          <div class="card-title">👁 Превью кнопок</div>
          <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:12px;">
            Нажатие на кнопку заполняет форму — менеджер сам вводит количество перед проведением
          </div>
          <div id="sh_btns_preview" style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;"></div>
          <div id="sh_btns_preview_empty" style="display:none;text-align:center;padding:20px;
               color:var(--text-muted);font-size:0.8rem;">
            <div style="font-size:2rem;opacity:0.3;margin-bottom:6px;">👁</div>
            Добавьте кнопки слева
          </div>
        </div>

      </div>
    </div><!-- /sh_tab_settings -->

    <!-- ═══ QR-МОДАЛКА ═══ -->
    <div id="sh_qr_overlay" style="
      display:none;position:fixed;inset:0;background:rgba(0,0,0,0.78);
      z-index:99999;align-items:center;justify-content:center;backdrop-filter:blur(6px);">
      <div style="background:var(--bg-card);border-radius:22px;padding:32px 28px;
                  width:360px;max-width:95vw;box-shadow:0 28px 80px rgba(0,0,0,0.55);
                  border:1px solid rgba(255,255,255,0.07);position:relative;text-align:center;">
        <button onclick="CRM.modules.shift._closeQR()"
          style="position:absolute;top:14px;right:16px;background:none;border:none;
                 cursor:pointer;font-size:1.5rem;color:var(--text-muted);line-height:1;padding:4px;">✕</button>
        <div style="font-size:0.68rem;text-transform:uppercase;letter-spacing:1.5px;
                    color:var(--text-muted);font-weight:700;margin-bottom:10px;">
          📱 Оплата через Озон Pay · СБП
        </div>
        <div style="font-size:2rem;font-weight:900;color:var(--accent3);margin-bottom:4px;"
             id="sh_qr_amount_lbl">—</div>
        <div style="font-size:0.82rem;color:var(--text-muted);margin-bottom:20px;"
             id="sh_qr_desc_lbl">—</div>
        <div id="sh_qr_box" style="width:220px;height:220px;margin:0 auto 20px;background:white;
              border-radius:16px;padding:10px;display:flex;align-items:center;
              justify-content:center;box-shadow:0 6px 30px rgba(0,0,0,0.25);
              position:relative;overflow:hidden;">
          <div id="sh_qr_loader" style="text-align:center;">
            <div style="width:36px;height:36px;border:3px solid rgba(59,130,246,0.25);
                        border-top-color:var(--accent);border-radius:50%;margin:0 auto 8px;
                        animation:sh_spin 0.8s linear infinite;"></div>
            <div style="font-size:0.72rem;color:#999;">Создаём платёж...</div>
          </div>
          <img id="sh_qr_img" src="" alt="QR"
               style="display:none;width:200px;height:200px;border-radius:6px;">
          <div id="sh_qr_paid_overlay" style="display:none;position:absolute;inset:0;
               background:rgba(16,185,129,0.93);border-radius:14px;flex-direction:column;
               align-items:center;justify-content:center;">
            <div style="font-size:3.5rem;">✅</div>
            <div style="font-size:1rem;font-weight:900;color:#fff;margin-top:8px;">Оплачено!</div>
          </div>
        </div>
        <style>
          @keyframes sh_spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }
        </style>
        <div id="sh_qr_status"
             style="display:inline-block;padding:7px 18px;border-radius:20px;
                    background:rgba(245,158,11,0.12);color:#f59e0b;
                    font-weight:700;font-size:0.85rem;margin-bottom:16px;">⏳ Создаём...</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px;">
          <button class="btn btn-success btn-sm" id="sh_qr_check_btn"
                  onclick="CRM.modules.shift._qrCheckOnce()" disabled>🔄 Проверить оплату</button>
          <button class="btn btn-secondary btn-sm" id="sh_qr_print_btn"
                  onclick="CRM.modules.shift._qrPrint()" disabled>🖨️ Распечатать QR</button>
        </div>
        <label style="cursor:pointer;display:flex;align-items:center;justify-content:center;
                       gap:6px;font-size:0.74rem;color:var(--text-muted);">
          <input type="checkbox" id="sh_qr_autopoll_cb"
                 onchange="CRM.modules.shift._qrTogglePoll(this.checked)">
          Автопроверка каждые 10 сек.
        </label>
        <div id="sh_qr_poll_status"
             style="font-size:0.7rem;color:var(--text-muted);margin-top:4px;min-height:14px;"></div>
      </div>
    </div>

    <!-- ═══ МОДАЛКА ГОРЯЧИХ КЛАВИШ ═══ -->
    <div id="sh_hotkeys_overlay" style="
      display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);
      z-index:99998;align-items:center;justify-content:center;backdrop-filter:blur(4px);">
      <div style="background:var(--bg-card);border-radius:20px;padding:28px;
                  width:420px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,0.5);
                  border:1px solid rgba(255,255,255,0.07);position:relative;">
        <button onclick="CRM.modules.shift._closeHotkeysModal()"
          style="position:absolute;top:14px;right:16px;background:none;border:none;
                 cursor:pointer;font-size:1.4rem;color:var(--text-muted);padding:4px;">✕</button>
        <div style="font-size:1.1rem;font-weight:900;margin-bottom:4px;">⌨️ Горячие клавиши</div>
        <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:20px;">
          Работают когда фокус не в поле ввода
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;">
          ${[
            ['Ctrl + Enter', 'Провести операцию'],
            ['Esc',          'Очистить форму'],
            ['Ctrl + G',     'Фокус на поле суммы'],
            ['+  /  =',      'Переключить на Приход'],
            ['−',            'Переключить на Расход'],
            ['1 — 9',        'Быстрые кнопки по номеру'],
          ].map(([key, desc]) => `
            <div style="display:flex;align-items:center;justify-content:space-between;
                        padding:8px 12px;background:var(--bg-dark);border-radius:8px;">
              <span style="font-size:0.8rem;color:var(--text-muted);">${desc}</span>
              <kbd style="background:rgba(59,130,246,0.15);border:1px solid rgba(59,130,246,0.3);
                          border-radius:6px;padding:3px 10px;font-size:0.78rem;
                          font-weight:700;color:var(--accent);font-family:monospace;">
                ${key}
              </kbd>
            </div>
          `).join('')}
        </div>
        <div style="margin-top:16px;padding:12px;background:rgba(245,158,11,0.07);
                    border-radius:10px;border:1px solid rgba(245,158,11,0.15);">
          <div style="font-size:0.72rem;font-weight:700;color:#f59e0b;margin-bottom:6px;">
            ⚡ Нумерация быстрых кнопок
          </div>
          <div style="font-size:0.72rem;color:var(--text-muted);line-height:1.7;">
            Первые 9 кнопок в разделе <b>⚙️ Кнопки</b> доступны по цифрам 1–9.<br>
            Порядок = порядок в списке. Номер отображается на самой кнопке.
          </div>
        </div>
      </div>
    </div>
  `,

  // ================================================================
  // INIT / RENDER
  // ================================================================
  async render() {
    const [mRes, bRes, curRes] = await Promise.all([
      CRM.api('shift','managers'),
      CRM.api('shift','getButtons'),
      CRM.api('shift','current'),
    ]);

    this._managers = mRes?.data  || [];
    this._buttons  = bRes?.data  || [];
    this._current  = curRes?.data || null;

    this._btnUsage = JSON.parse(localStorage.getItem('shift_btn_usage') || '{}');

    this._fillManagerSelect();
    this._tab(this._activeTab, true);

    if (this._current) {
      this._showActive();
    } else {
      this._showOpen();
    }

    this._renderQuickBtns();
    await this._loadHistory();
    await this._loadReports();
    this._renderBtnsSettings();

    if (this._durationInterval) clearInterval(this._durationInterval);
    if (this._current) {
      this._durationInterval = setInterval(() => this._tick(), 1000);
    }

    this._setMethod('Наличные');
    this._setupHotkeys();
    if (this._current) this._restoreDraft();
  },

  // ================================================================
  // ГОРЯЧИЕ КЛАВИШИ
  // ================================================================
  _hotkeyHandler: null,
  _setupHotkeys() {
    if (this._hotkeyHandler) document.removeEventListener('keydown', this._hotkeyHandler);
    this._hotkeyHandler = (e) => {
      if (!this._current) return;
      const tag     = document.activeElement?.tagName?.toLowerCase();
      const isInput = tag === 'input' || tag === 'textarea' || tag === 'select';
      if (e.ctrlKey && e.key === 'Enter') { e.preventDefault(); this.submitOp(); return; }
      if (e.key === 'Escape' && !isInput) { this._clearForm(); return; }
      if (e.ctrlKey && e.key === 'g')     { e.preventDefault(); document.getElementById('sh_op_amount')?.focus(); return; }
      if (!isInput) {
        if (e.key === '+' || e.key === '=') { this._setType('income');  return; }
        if (e.key === '-')                  { this._setType('expense'); return; }
        const n = parseInt(e.key);
        if (n >= 1 && n <= 9 && this._buttons[n-1]) { this._fillFromBtn(this._buttons[n-1]); return; }
      }
    };
    document.addEventListener('keydown', this._hotkeyHandler);
  },

  _openHotkeysModal() {
    const el = document.getElementById('sh_hotkeys_overlay');
    if (el) el.style.display = 'flex';
  },
  _closeHotkeysModal() {
    const el = document.getElementById('sh_hotkeys_overlay');
    if (el) el.style.display = 'none';
  },

  // ================================================================
  // ЧЕРНОВИК
  // ================================================================
  _saveDraft() {
    if (!this._current) return;
    try {
      localStorage.setItem('shift_draft_' + this._current.id, JSON.stringify({
        amount: document.getElementById('sh_op_amount')?.value || '',
        qty:    document.getElementById('sh_op_qty')?.value    || '1',
        desc:   document.getElementById('sh_op_desc')?.value   || '',
        method: document.getElementById('sh_op_method')?.value || 'Наличные',
        type:   this._opType,
      }));
    } catch(e) {}
  },
  _restoreDraft() {
    if (!this._current) return;
    try {
      const raw = localStorage.getItem('shift_draft_' + this._current.id);
      if (!raw) return;
      const d = JSON.parse(raw);
      if (!d.amount) return;
      const aEl = document.getElementById('sh_op_amount');
      const qEl = document.getElementById('sh_op_qty');
      const dEl = document.getElementById('sh_op_desc');
      if (aEl) aEl.value = d.amount;
      if (qEl) qEl.value = d.qty;
      if (dEl) dEl.value = d.desc;
      this._setType(d.type || 'income');
      this._setMethod(d.method || 'Наличные');
      this._calcTotal();
      notify('📝 Черновик формы восстановлен', 'info');
    } catch(e) {}
  },
  _clearDraft() {
    if (!this._current) return;
    try { localStorage.removeItem('shift_draft_' + this._current.id); } catch(e) {}
  },

  // ================================================================
  // ТАБЫ
  // ================================================================
  _tab(name, silent) {
    this._activeTab = name;
    ['cashier','reports','settings'].forEach(t => {
      const el  = document.getElementById('sh_tab_' + t);
      const btn = document.getElementById('sh_tab_btn_' + t);
      if (el) el.style.display = (t === name) ? 'block' : 'none';
      if (btn) {
        if (t === name) {
          btn.style.background = 'rgba(245,158,11,0.15)';
          btn.style.border     = '1px solid rgba(245,158,11,0.4)';
          btn.style.color      = '#f59e0b';
          btn.style.fontWeight = '700';
        } else {
          btn.style.background = '';
          btn.style.border     = '';
          btn.style.color      = '';
          btn.style.fontWeight = '';
        }
      }
    });
    if (!silent) {
      if (name === 'settings') this._renderBtnsSettings();
      if (name === 'reports')  this._loadReports();
      if (name === 'cashier')  this._loadHistory();
    }
  },

  // ================================================================
  // МЕНЕДЖЕР
  // ================================================================
  _fillManagerSelect() {
    const sel = document.getElementById('sh_manager_sel');
    if (!sel) return;
    if (!this._managers.length) {
      sel.style.display = 'none';
      const no = document.getElementById('sh_no_emp');
      if (no) no.style.display = 'block';
      return;
    }
    sel.innerHTML =
      '<option value="">— Выберите менеджера —</option>' +
      this._managers.map(m =>
        `<option value="${m.id}" data-name="${m.name}" data-bonus="${m.bonusPct}" data-color="${m.color}">
           ${m.name}${m.position ? ' ('+m.position+')' : ''}
         </option>`
      ).join('');
    sel.onchange = () => this._onManagerChange();
  },

  _onManagerChange() {
    const sel = document.getElementById('sh_manager_sel');
    if (!sel?.value) return;
    const opt      = sel.options[sel.selectedIndex];
    const bonusPct = parseFloat(opt.getAttribute('data-bonus') || '0.1');
    const hint     = document.getElementById('sh_bonus_hint');
    const txt      = document.getElementById('sh_bonus_hint_text');
    if (hint) hint.style.display = 'block';
    if (txt)  txt.textContent    = `${opt.getAttribute('data-name')} — ${bonusPct}% бонуса с каждого прихода`;

    CRM.api('shift','lastEndCash',{ empId: sel.value }).then(res => {
      if (res?.ok && res.endCash > 0) {
        const sc = document.getElementById('sh_start_cash');
        const h  = document.getElementById('sh_start_cash_hint');
        if (sc && !sc.value) sc.value = res.endCash;
        if (h) {
          const d = res.date ? new Date(res.date).toLocaleDateString('ru') : '';
          h.textContent = `↑ Остаток прошлой смены ${d}`;
        }
      }
    });
  },

  // ================================================================
  // ПОКАЗАТЬ БЛОКИ
  // ================================================================
  _showOpen() {
    const ob = document.getElementById('sh_open_block');
    const ab = document.getElementById('sh_active_block');
    if (ob) ob.style.display = 'block';
    if (ab) ab.style.display = 'none';
    if (this._durationInterval) { clearInterval(this._durationInterval); this._durationInterval = null; }
  },

  _showActive() {
    const ob = document.getElementById('sh_open_block');
    const ab = document.getElementById('sh_active_block');
    if (ob) ob.style.display = 'none';
    if (ab) ab.style.display = 'block';
    this._updateStats();
    this._renderOps();
    this._updateSalaryPreview();
    this._setMethod('Наличные');
    this._longShiftWarned = false;
  },

  // ================================================================
  // БЫСТРЫЕ КНОПКИ
  // ================================================================
  _renderQuickBtns() {
    const el = document.getElementById('sh_quick_btns');
    if (!el) return;

    if (!this._buttons.length) {
      el.innerHTML = `
        <div style="grid-column:1/-1;text-align:center;padding:20px;
                    color:var(--text-muted);font-size:0.8rem;">
          <div style="font-size:2rem;opacity:0.3;margin-bottom:6px;">⚡</div>
          Нет кнопок. Добавьте в ⚙️ Кнопки
        </div>`;
      return;
    }

    el.innerHTML = this._buttons.map((btn, idx) => {
      const usage = this._btnUsage[btn.id] || 0;
      return `
        <button
          title="${btn.label} — ${btn.type==='income'?'Приход':'Расход'} ${formatMoney(btn.amount)}${idx<9?' (клавиша '+(idx+1)+')':''}"
          onclick="CRM.modules.shift._fillFromBtn(${JSON.stringify(btn).replace(/"/g,'&quot;')})"
          style="display:flex;flex-direction:column;align-items:center;justify-content:center;
                 gap:4px;padding:10px 6px;border-radius:14px;
                 border:1.5px solid ${btn.color}44;background:${btn.color}12;
                 cursor:pointer;transition:all 0.15s;min-height:84px;
                 color:var(--text);font-family:inherit;position:relative;"
          onmouseover="this.style.background='${btn.color}25';this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 16px ${btn.color}30'"
          onmouseout="this.style.background='${btn.color}12';this.style.transform='translateY(0)';this.style.boxShadow='none'">
          <span style="position:absolute;top:4px;left:6px;font-size:0.55rem;
                       color:${btn.color};opacity:0.7;font-weight:900;">
            ${idx < 9 ? idx+1 : ''}
          </span>
          <span style="font-size:1.4rem;">${btn.icon}</span>
          <span style="font-size:0.68rem;font-weight:700;text-align:center;line-height:1.2;
                       max-width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            ${btn.label}
          </span>
          <span style="font-size:0.76rem;font-weight:900;
                       color:${btn.type==='income'?'var(--accent3)':'var(--danger)'};">
            ${btn.type==='income'?'+':'−'}${formatMoney(btn.amount)}
          </span>
          ${usage > 0 ? `<span style="font-size:0.58rem;color:${btn.color};opacity:0.8;">×${usage} сег.</span>` : ''}
        </button>`;
    }).join('');
  },

  _fillFromBtn(btn) {
    this._setType(btn.type);
    const amtEl  = document.getElementById('sh_op_amount');
    const descEl = document.getElementById('sh_op_desc');
    const qtyEl  = document.getElementById('sh_op_qty');
    if (amtEl)  amtEl.value  = btn.amount;
    if (descEl) descEl.value = btn.label;
    if (qtyEl)  { qtyEl.value = 1; }
    this._calcTotal();
    if (qtyEl) { qtyEl.select(); qtyEl.focus(); }

    const today = new Date().toDateString();
    if (localStorage.getItem('shift_btn_usage_date') !== today) {
      this._btnUsage = {};
      localStorage.setItem('shift_btn_usage_date', today);
    }
    this._btnUsage[btn.id] = (this._btnUsage[btn.id] || 0) + 1;
    localStorage.setItem('shift_btn_usage', JSON.stringify(this._btnUsage));
    this._renderQuickBtns();
  },

  // ================================================================
  // ФОРМА
  // ================================================================
  _setType(type) {
    this._opType = type;
    const ib = document.getElementById('sh_btn_income');
    const eb = document.getElementById('sh_btn_expense');
    const sb = document.getElementById('sh_submit_btn');
    const ft = document.getElementById('sh_form_title');
    const cb = document.getElementById('sh_change_block');

    if (type === 'income') {
      if (ib) ib.className = 'btn btn-success btn-sm';
      if (eb) eb.className = 'btn btn-secondary btn-sm';
      if (sb) { sb.textContent = '▲ Провести приход'; sb.className = 'btn btn-success'; sb.style.cssText = 'padding:13px;font-size:0.95rem;font-weight:800;'; }
      if (ft) ft.textContent = '✍️ Приход';
      if (cb) cb.style.display = (document.getElementById('sh_op_method')?.value === 'Наличные') ? 'block' : 'none';
    } else {
      if (eb) eb.className = 'btn btn-danger btn-sm';
      if (ib) ib.className = 'btn btn-secondary btn-sm';
      if (sb) { sb.textContent = '▼ Провести расход'; sb.className = 'btn btn-danger'; sb.style.cssText = 'padding:13px;font-size:0.95rem;font-weight:800;'; }
      if (ft) ft.textContent = '✍️ Расход';
      if (cb) cb.style.display = 'none';
    }
  },

  _setMethod(method) {
    document.getElementById('sh_op_method').value = method;
    document.querySelectorAll('.sh-method-btn').forEach(b => {
      const active = b.textContent.trim().includes(method);
      b.style.background  = active ? 'rgba(59,130,246,0.15)' : '';
      b.style.borderColor = active ? 'var(--accent)' : '';
      b.style.color       = active ? 'var(--accent)' : '';
      b.style.fontWeight  = active ? '700' : '';
    });

    const cb = document.getElementById('sh_change_block');
    if (cb) cb.style.display = (method === 'Наличные' && this._opType === 'income') ? 'block' : 'none';

    const qrHint = document.getElementById('sh_qr_method_hint');
    if (method === 'QR / СБП') {
      if (!qrHint) {
        const submitBtn = document.getElementById('sh_submit_btn');
        if (submitBtn) {
          const hint = document.createElement('div');
          hint.id = 'sh_qr_method_hint';
          hint.style.cssText = 'margin-bottom:8px;padding:7px 12px;background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.25);border-radius:8px;font-size:0.75rem;color:var(--accent);text-align:center;';
          hint.textContent = '📱 После нажатия откроется QR-код для оплаты';
          submitBtn.parentNode.insertBefore(hint, submitBtn);
        }
      }
    } else {
      if (qrHint) qrHint.remove();
    }
  },

  _changeQty(delta) {
    const el = document.getElementById('sh_op_qty');
    if (!el) return;
    el.value = Math.max(1, (parseInt(el.value) || 1) + delta);
    this._calcTotal();
  },

  _calcTotal() {
    const amt = parseFloat(document.getElementById('sh_op_amount')?.value) || 0;
    const qty = Math.max(1, parseInt(document.getElementById('sh_op_qty')?.value) || 1);
    const el  = document.getElementById('sh_op_total_preview');
    if (!el) return;
    if (qty > 1 && amt > 0) {
      el.textContent = `${qty} × ${formatMoney(amt)} = ${formatMoney(amt * qty)}`;
      el.style.color = this._opType === 'income' ? 'var(--accent3)' : 'var(--danger)';
    } else if (amt > 0) {
      el.textContent = `Итого: ${formatMoney(amt)}`;
      el.style.color = this._opType === 'income' ? 'var(--accent3)' : 'var(--danger)';
    } else {
      el.textContent = '';
    }
    this._calcChange();
    this._saveDraft();
  },

  _calcChange() {
    const amt   = parseFloat(document.getElementById('sh_op_amount')?.value) || 0;
    const qty   = Math.max(1, parseInt(document.getElementById('sh_op_qty')?.value) || 1);
    const total = amt * qty;
    const given = parseFloat(document.getElementById('sh_given_cash')?.value) || 0;
    const resEl = document.getElementById('sh_change_result');
    if (!resEl) return;
    if (given > 0 && total > 0) {
      const change = given - total;
      resEl.textContent = (change >= 0 ? '' : '−') + formatMoney(Math.abs(change));
      resEl.style.color = change >= 0 ? 'var(--accent3)' : 'var(--danger)';
    } else {
      resEl.textContent = '— ₽';
      resEl.style.color = 'var(--accent3)';
    }
  },

  _clearForm() {
    ['sh_op_amount','sh_op_desc'].forEach(id => { const e = document.getElementById(id); if(e) e.value = ''; });
    const q = document.getElementById('sh_op_qty');   if (q) q.value = 1;
    const p = document.getElementById('sh_op_total_preview'); if (p) p.textContent = '';
    const g = document.getElementById('sh_given_cash');       if (g) g.value = '';
    const r = document.getElementById('sh_change_result');    if (r) { r.textContent = '— ₽'; r.style.color = 'var(--accent3)'; }
    this._cancelEdit();
    this._clearDraft();
  },

  // ================================================================
  // ИНДИКАТОР РАСХОЖДЕНИЯ
  // ================================================================
  _onEndCashInput(val) {
    const s   = this._current;
    if (!s) return;
    const fact = parseFloat(val) || 0;
    const diff = fact - (s.cash || 0);
    const ind  = document.getElementById('sh_diff_indicator');
    if (!ind) return;
    if (val === '' || val === null) { ind.style.display = 'none'; return; }
    ind.style.display = 'block';
    if (Math.abs(diff) < 0.01) {
      ind.style.background = 'rgba(16,185,129,0.1)';
      ind.style.color      = 'var(--accent3)';
      ind.textContent      = '✅ Совпадает — расхождений нет';
    } else if (diff < 0) {
      ind.style.background = 'rgba(239,68,68,0.1)';
      ind.style.color      = 'var(--danger)';
      ind.textContent      = `⚠️ Недостача: ${Math.abs(diff).toFixed(2)} ₽`;
    } else {
      ind.style.background = 'rgba(245,158,11,0.1)';
      ind.style.color      = '#f59e0b';
      ind.textContent      = `✅ Излишек: ${diff.toFixed(2)} ₽`;
    }
  },

  // ================================================================
  // ОТКРЫТЬ СМЕНУ
  // ================================================================
  async openShift() {
    if (this._openLock) return;
    const sel    = document.getElementById('sh_manager_sel');
    const manual = document.getElementById('sh_manager_manual');
    let empId = '', manager = '';

    if (sel?.value) {
      const opt = sel.options[sel.selectedIndex];
      empId   = sel.value;
      manager = opt.getAttribute('data-name') || opt.text;
    } else if (manual?.value?.trim()) {
      manager = manual.value.trim();
    }

    if (!manager) { notify('Выберите или введите менеджера', 'error'); return; }

    const startCash = parseFloat(document.getElementById('sh_start_cash')?.value) || 0;
    const iKey      = 'open_' + empId + '_' + new Date().toDateString();

    this._openLock = true;
    const btn = document.getElementById('sh_open_btn');
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Открываем...'; }

    try {
      const res = await CRM.api('shift','open',{ empId, manager, startCash, iKey });
      if (res?.ok) {
        this._current = res.data;
        notify('✅ Смена открыта! Удачной работы, ' + manager, 'success');
        this._showActive();
        if (this._durationInterval) clearInterval(this._durationInterval);
        this._durationInterval = setInterval(() => this._tick(), 1000);
        this._setupHotkeys();
      } else {
        notify(res?.error || 'Ошибка открытия смены', 'error');
      }
    } finally {
      this._openLock = false;
      if (btn) { btn.disabled = false; btn.textContent = '▶ Открыть смену'; }
    }
  },

  // ================================================================
  // ЗАКРЫТЬ СМЕНУ
  // ================================================================
  async closeShift() {
    if (!this._current || this._closeLock) return;
    const endCash    = parseFloat(document.getElementById('sh_end_cash')?.value);
    const baseSalary = parseFloat(document.getElementById('sh_base_salary')?.value) || 0;
    const note       = document.getElementById('sh_close_note')?.value?.trim() || '';

    if (isNaN(endCash)) { notify('Введите фактическую сумму в кассе', 'error'); return; }

    const s     = this._current;
    const bonus = s.accruedBonus || 0;
    const total = baseSalary + bonus;
    const diff  = endCash - s.cash;

    const msg =
      `Закрыть смену?\n\n` +
      `👤 ${s.manager}\n` +
      `📋 Операций: ${s.operations.length}\n` +
      `💰 Доход: ${formatMoney(s.totalIncome||0)}\n` +
      `📤 Расход: ${formatMoney(s.totalExpense||0)}\n` +
      `💵 Касса расчёт: ${formatMoney(s.cash)}\n` +
      `💵 Касса факт: ${formatMoney(endCash)}\n` +
      (Math.abs(diff)>=0.01 ? `\n${diff<0?'⚠️ Недостача':'✅ Излишек'}: ${Math.abs(diff).toFixed(2)} ₽\n` : '\n✅ Расхождений нет\n') +
      `\n👔 ЗП: оклад ${formatMoney(baseSalary)} + бонус ${formatMoney(bonus)} = ${formatMoney(total)}`;

    if (!confirm(msg)) return;

    this._closeLock = true;
    const btn = document.getElementById('sh_close_btn');
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Закрываем...'; }

    try {
      const res = await CRM.api('shift','close',{ endCash, baseSalary, note });
      if (res?.ok) {
        if (this._durationInterval) { clearInterval(this._durationInterval); this._durationInterval = null; }
        this._clearDraft();
        this._btnUsage = {};
        localStorage.removeItem('shift_btn_usage');
        this._current         = null;
        this._longShiftWarned = false;
        if (typeof forceRefresh === 'function') await forceRefresh();
        if (res.report) this._printZ(res.report);
        notify('🔒 Смена закрыта. Z-отчёт сформирован!', 'success');
        this._showOpen();
        this._fillManagerSelect();
        await this._loadHistory();
        await this._loadReports();
      } else {
        notify(res?.error || 'Ошибка закрытия', 'error');
      }
    } finally {
      this._closeLock = false;
      if (btn) { btn.disabled = false; btn.textContent = '🔒 Закрыть смену и сформировать Z-отчёт'; }
    }
  },

  // ================================================================
  // ПРОВЕСТИ ОПЕРАЦИЮ
  // ================================================================
  async submitOp() {
    if (this._submitLock) return;
    const amount = parseFloat(document.getElementById('sh_op_amount')?.value);
    const qty    = Math.max(1, parseInt(document.getElementById('sh_op_qty')?.value) || 1);
    const desc   = document.getElementById('sh_op_desc')?.value?.trim() || '';
    const method = document.getElementById('sh_op_method')?.value || 'Наличные';

    if (!amount || amount <= 0) { notify('Введите сумму больше нуля', 'error'); return; }

    if (method === 'QR / СБП' && this._opType === 'income') {
      await this._openQRModal(amount * qty, desc, amount, qty);
      return;
    }

    if (this._editingOpId) {
      await this._saveEdit(amount * qty, desc, method, qty, amount);
      return;
    }

    this._submitLock = true;
    const btn = document.getElementById('sh_submit_btn');
    const origText = btn?.textContent;
    if (btn) { btn.disabled = true; btn.textContent = '⏳'; }

    const iKey = `op_${Date.now()}_${Math.random().toString(36).slice(2,8)}`;

    try {
      const res = await CRM.api('shift','operation',{
        type: this._opType, amount, qty, desc, method, iKey,
      });

      if (res?._dup) { notify('⚠️ Операция уже была проведена (дубль)', 'warning'); return; }

      if (res?.ok) {
        this._current = res.data;
        this._clearForm();
        this._updateStats();
        this._renderOps();
        this._updateSalaryPreview();
        const cat = this._cat(desc, this._opType);
        notify(
          `${this._opType==='income'?'▲':'▼'} ${formatMoney(amount*qty)} → ${cat} ✓`,
          this._opType === 'income' ? 'success' : 'info'
        );
      } else {
        notify(res?.error || 'Ошибка', 'error');
      }
    } finally {
      this._submitLock = false;
      if (btn) { btn.disabled = false; btn.textContent = origText; }
    }
  },

  // ================================================================
  // РЕДАКТИРОВАНИЕ / УДАЛЕНИЕ
  // ================================================================
  _startEdit(op) {
    this._editingOpId = op.id;
    const amtEl  = document.getElementById('sh_op_amount');
    const qtyEl  = document.getElementById('sh_op_qty');
    const descEl = document.getElementById('sh_op_desc');
    if (amtEl)  amtEl.value  = op.price  || op.amount;
    if (qtyEl)  qtyEl.value  = op.qty    || 1;
    if (descEl) descEl.value = op.desc   || '';
    this._setType(op.type);
    this._setMethod(op.method || 'Наличные');
    this._calcTotal();
    const cancelRow = document.getElementById('sh_edit_cancel_row');
    if (cancelRow) cancelRow.style.display = 'block';
    const ft = document.getElementById('sh_form_title');
    if (ft) ft.innerHTML = '✏️ Редактирование <span style="font-size:0.72rem;color:var(--warning);">• изменение</span>';
    document.getElementById('sh_op_amount')?.scrollIntoView({ behavior:'smooth', block:'center' });
    document.getElementById('sh_op_amount')?.focus();
  },

  _cancelEdit() {
    this._editingOpId = null;
    const cancelRow   = document.getElementById('sh_edit_cancel_row');
    if (cancelRow) cancelRow.style.display = 'none';
    const ft = document.getElementById('sh_form_title');
    if (ft) ft.textContent = '✍️ Операция';
    this._setType('income');
  },

  async _saveEdit(newAmount, newDesc, newMethod, newQty, newPrice) {
    if (this._submitLock) return;
    this._submitLock = true;
    const btn = document.getElementById('sh_submit_btn');
    const origText = btn?.textContent;
    if (btn) { btn.disabled = true; btn.textContent = '⏳'; }
    try {
      const res = await CRM.api('shift','editOperation',{
        opId:   this._editingOpId,
        amount: newAmount,
        desc:   newDesc,
        method: newMethod,
        qty:    newQty   || 1,
        price:  newPrice || newAmount,
      });
      if (res?.ok) {
        this._current = res.data;
        this._cancelEdit();
        this._clearForm();
        this._updateStats();
        this._renderOps();
        this._updateSalaryPreview();
        notify('✅ Операция обновлена', 'success');
      } else {
        notify(res?.error || 'Ошибка', 'error');
      }
    } finally {
      this._submitLock = false;
      if (btn) { btn.disabled = false; btn.textContent = origText; }
    }
  },

  async _deleteOp(opId) {
    if (!confirm('Удалить операцию?\nБаланс будет скорректирован.')) return;
    const res = await CRM.api('shift','deleteOperation',{ opId });
    if (res?.ok) {
      this._current = res.data;
      this._updateStats();
      this._renderOps();
      this._updateSalaryPreview();
      notify('Операция удалена', 'info');
    } else {
      notify(res?.error || 'Ошибка', 'error');
    }
  },

  // ================================================================
  // ОБНОВЛЕНИЕ UI
  // ================================================================
  _updateStats() {
    const s = this._current;
    if (!s) return;
    const set = (id, v) => { const e = document.getElementById(id); if(e) e.textContent = v; };
    set('sh_hdr_manager',  '👤 ' + s.manager);
    set('sh_hdr_time',     'Открыта: ' + new Date(s.openTime).toLocaleString('ru'));
    set('sh_stat_cash',    formatMoney(s.cash));
    set('sh_stat_income',  formatMoney(s.totalIncome  || 0));
    set('sh_stat_expense', formatMoney(s.totalExpense || 0));

    const hint = document.getElementById('sh_expected_hint');
    if (hint) hint.textContent = 'Ожидается: ' + formatMoney(s.cash);

    const endEl = document.getElementById('sh_end_cash');
    if (endEl && !endEl.value) endEl.value = Math.round(s.cash);

    this._updateEarnedBlock();
    this._updateSalaryPreview();

    try {
      localStorage.setItem('shift_backup_' + s.id, JSON.stringify({...s, _savedAt: new Date().toISOString()}));
    } catch(e) {}
  },

  _tick() {
    if (!this._current) return;
    const el = document.getElementById('sh_stat_dur');
    if (!el) return;
    const ms = Date.now() - new Date(this._current.openTime);
    const h  = Math.floor(ms / 3600000);
    const m  = Math.floor((ms % 3600000) / 60000);
    el.textContent = h + 'ч ' + String(m).padStart(2,'0') + 'м';

    if (ms > 9 * 3600000 && !this._longShiftWarned) {
      this._longShiftWarned = true;
      notify('⚠️ Смена длится уже более 9 часов! Не забудьте закрыть смену.', 'warning');
    }
  },

  _toggleEarnedBlock(show) {
    const block = document.getElementById('sh_earned_block');
    if (block) block.style.display = show ? 'block' : 'none';
    if (show)  this._updateEarnedBlock();
  },

  _updateEarnedBlock() {
    const s = this._current;
    if (!s) return;
    const pct    = s.bonusPct     || 0.1;
    const income = s.totalIncome  || 0;
    const bonus  = s.accruedBonus || 0;
    const set = (id, v) => { const e = document.getElementById(id); if(e) e.textContent = v; };
    set('sh_earn_pct',    pct);
    set('sh_earn_income', formatMoney(income));
    set('sh_earn_bonus',  formatMoney(bonus));
    const barEl = document.getElementById('sh_earn_bar');
    if (barEl) barEl.style.width = Math.min(100, Math.round(bonus / 1000 * 100)) + '%';
    const base   = parseFloat(document.getElementById('sh_base_salary')?.value) || 0;
    const baseEl = document.getElementById('sh_earn_base_preview');
    if (baseEl) baseEl.textContent = base > 0 ? formatMoney(base) : 'не указан';
  },

  _updateSalaryPreview() {
    const s = this._current;
    if (!s) return;
    const base  = parseFloat(document.getElementById('sh_base_salary')?.value) || 0;
    const bonus = s.accruedBonus || 0;
    const total = base + bonus;
    const pct   = s.bonusPct || 0.1;
    const lbl   = document.getElementById('sh_close_bonus_lbl');
    if (lbl) lbl.textContent = ` + бонус ${pct}% = +${formatMoney(bonus)}`;
    const rows  = document.getElementById('sh_salary_rows');
    if (rows) rows.innerHTML = `
      <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid rgba(255,255,255,0.06);">
        <span style="color:var(--text-muted);">Оклад за день</span>
        <span style="font-weight:700;">${formatMoney(base)}</span>
      </div>
      <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid rgba(255,255,255,0.06);">
        <span style="color:var(--text-muted);">Бонус ${pct}% с дохода ${formatMoney(s.totalIncome||0)}</span>
        <span style="font-weight:700;color:#a78bfa;">+${formatMoney(bonus)}</span>
      </div>
      <div style="display:flex;justify-content:space-between;padding:7px 0;">
        <span style="font-weight:800;">ИТОГО к выплате</span>
        <span style="font-weight:900;color:var(--accent3);font-size:1.05rem;">${formatMoney(total)}</span>
      </div>`;
    this._updateEarnedBlock();
  },

  // ================================================================
  // РЕНДЕР ОПЕРАЦИЙ
  // ================================================================
  _toggleOpsView() {
    this._opsView = this._opsView === 'chrono' ? 'category' : 'chrono';
    const btn = document.getElementById('sh_ops_view_btn');
    if (btn) btn.title = this._opsView === 'chrono'
      ? 'Сейчас: хронология — нажмите для группировки'
      : 'Сейчас: по категориям — нажмите для хронологии';
    this._renderOps();
  },

  _renderOps() {
    const allOps = [...(this._current?.operations || [])].reverse();
    const query  = (this._opsFilter || '').toLowerCase().trim();
    const ops    = query
      ? allOps.filter(op =>
          (op.desc||'').toLowerCase().includes(query) ||
          (op.method||'').toLowerCase().includes(query) ||
          String(op.amount).includes(query))
      : allOps;

    const el = document.getElementById('sh_ops_list');
    if (!el) return;

    // Мини-статистика
    const statsEl = document.getElementById('sh_ops_stats');
    if (statsEl && allOps.length) {
      const totalIn  = allOps.filter(o=>o.type==='income').reduce((s,o)=>s+o.amount,0);
      const totalOut = allOps.filter(o=>o.type==='expense').reduce((s,o)=>s+o.amount,0);
      const ms       = this._current ? Date.now() - new Date(this._current.openTime) : 0;
      const perHour  = (ms > 360000 && allOps.length) ? (allOps.length / (ms/3600000)).toFixed(1) : allOps.length;
      statsEl.innerHTML = `
        <div style="text-align:center;padding:6px;background:rgba(16,185,129,0.07);border-radius:8px;">
          <div style="font-weight:800;color:var(--accent3);font-size:0.85rem;">+${formatMoney(totalIn)}</div>
          <div style="font-size:0.6rem;color:var(--text-muted);">Приход</div>
        </div>
        <div style="text-align:center;padding:6px;background:rgba(239,68,68,0.07);border-radius:8px;">
          <div style="font-weight:800;color:var(--danger);font-size:0.85rem;">−${formatMoney(totalOut)}</div>
          <div style="font-size:0.6rem;color:var(--text-muted);">Расход</div>
        </div>
        <div style="text-align:center;padding:6px;background:rgba(139,92,246,0.07);border-radius:8px;">
          <div style="font-weight:800;color:#a78bfa;font-size:0.85rem;">${perHour}/ч</div>
          <div style="font-size:0.6rem;color:var(--text-muted);">Скорость</div>
        </div>`;
    } else if (statsEl) {
      statsEl.innerHTML = '';
    }

    const sumEl = document.getElementById('sh_ops_summary');
    if (sumEl) sumEl.textContent = allOps.length
      ? `${allOps.length} оп.${query ? ' / найдено: '+ops.length : ''}`
      : '';

    if (!ops.length) {
      el.innerHTML = `
        <div style="text-align:center;padding:28px;color:var(--text-muted);">
          <div style="font-size:2.5rem;opacity:0.3;margin-bottom:8px;">📋</div>
          <div style="font-size:0.82rem;">${query ? 'Ничего не найдено' : 'Операций пока нет'}</div>
        </div>`;
      return;
    }

    if (this._opsView === 'category') {
      const groups = {};
      ops.forEach(op => {
        const cat = this._cat(op.desc, op.type);
        if (!groups[cat]) groups[cat] = { ops:[], total:0, type:op.type };
        groups[cat].ops.push(op);
        groups[cat].total += op.amount;
      });
      el.innerHTML = Object.entries(groups).map(([cat, g]) => `
        <div style="margin-bottom:8px;">
          <div style="font-size:0.7rem;font-weight:700;color:var(--text-muted);
                      padding:4px 4px 6px;border-bottom:1px solid rgba(45,53,86,0.5);
                      display:flex;justify-content:space-between;">
            <span>${cat}</span>
            <span style="color:${g.type==='income'?'var(--accent3)':'var(--danger)'};">${formatMoney(g.total)}</span>
          </div>
          ${g.ops.map(op => this._renderOpRow(op)).join('')}
        </div>`).join('');
    } else {
      el.innerHTML = ops.map(op => this._renderOpRow(op)).join('');
    }
  },

  _renderOpRow(op) {
    return `
      <div style="display:flex;align-items:center;gap:8px;padding:9px 4px;
                  border-bottom:1px solid rgba(45,53,86,0.5);
                  ${this._editingOpId===op.id?'background:rgba(245,158,11,0.06);border-radius:8px;':''}">
        <div style="width:28px;height:28px;border-radius:7px;flex-shrink:0;
                    display:flex;align-items:center;justify-content:center;font-size:0.85rem;
                    background:${op.type==='income'?'rgba(16,185,129,0.15)':'rgba(239,68,68,0.15)'};">
          ${op.type==='income'?'▲':'▼'}
        </div>
        <div style="flex:1;min-width:0;">
          <div style="font-weight:600;font-size:0.8rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            ${op.desc || (op.type==='income'?'Поступление':'Изъятие')}
            ${(op.qty||1)>1?`<span style="color:var(--text-muted);font-size:0.7rem;">×${op.qty}</span>`:''}
          </div>
          <div style="font-size:0.67rem;color:var(--text-muted);margin-top:1px;">
            ${new Date(op.time).toLocaleTimeString('ru',{hour:'2-digit',minute:'2-digit'})}
            · ${op.method||'Наличные'}
          </div>
        </div>
        <span style="font-weight:800;font-size:0.88rem;flex-shrink:0;
                     color:${op.type==='income'?'var(--accent3)':'var(--danger)'};">
          ${op.type==='income'?'+':'−'}${formatMoney(op.amount)}
        </span>
        <div style="display:flex;gap:3px;flex-shrink:0;">
          <button class="btn btn-secondary btn-xs" title="Редактировать"
            onclick="CRM.modules.shift._startEdit(${JSON.stringify(op).replace(/"/g,'&quot;')})">✏️</button>
          <button class="btn btn-danger btn-xs" title="Удалить"
            onclick="CRM.modules.shift._deleteOp('${op.id}')">🗑</button>
        </div>
      </div>`;
  },

  // ================================================================
  // QR (без изменений)
  // ================================================================
  _qrPayment:   null,
  _qrPollTimer: null,
  _qrTickTimer: null,
  _qrAmount:    0,
  _qrDesc:      '',
  _qrQty:       1,
  _qrUnitPrice: 0,

  async _openQRModal(totalAmount, desc, unitPrice, qty) {
    this._qrAmount    = totalAmount;
    this._qrDesc      = desc;
    this._qrQty       = qty;
    this._qrUnitPrice = unitPrice;
    this._qrPayment   = null;
    this._qrStopPoll();

    const sd = (id, v) => { const e=document.getElementById(id); if(e) e.style.display=v; };
    sd('sh_qr_loader','block'); sd('sh_qr_img','none'); sd('sh_qr_paid_overlay','none');

    document.getElementById('sh_qr_amount_lbl').textContent = formatMoney(totalAmount);
    document.getElementById('sh_qr_desc_lbl').textContent   = desc || 'Оплата услуг';
    this._qrSetStatus('creating','⏳ Создаём платёж...');

    ['sh_qr_check_btn','sh_qr_print_btn'].forEach(id => {
      const b=document.getElementById(id); if(b) b.disabled=true;
    });
    const cb=document.getElementById('sh_qr_autopoll_cb'); if(cb) cb.checked=false;
    const overlay=document.getElementById('sh_qr_overlay'); if(overlay) overlay.style.display='flex';

    const tid = setTimeout(() => {
      const loader=document.getElementById('sh_qr_loader');
      if(loader && loader.style.display!=='none') {
        loader.innerHTML='<div style="color:var(--danger);font-size:0.8rem;padding:10px;">⏱ Превышено время ожидания<br><small style="color:#999;">Проверьте настройки эквайринга</small></div>';
        this._qrSetStatus('error','❌ Таймаут');
      }
    }, 30000);

    await this._qrCreate(totalAmount, desc);
    clearTimeout(tid);
  },

  async _qrCreate(amount, desc) {
    try {
      const res = await CRM.api('bank_account','create_payment',{
        amount,
        description: desc || 'Оплата услуг типографии',
        clientName:'', orderId:'', payMode:'sbp',
      });

      if (!res?.ok) {
        this._qrSetStatus('error','❌ '+(res?.error||'Ошибка API'));
        document.getElementById('sh_qr_loader').innerHTML =
          `<div style="color:var(--danger);font-size:0.8rem;padding:10px;">❌ ${res?.error||'Ошибка создания платежа'}<br><small style="color:#999;">Проверьте настройки эквайринга</small></div>`;
        return;
      }

      this._qrPayment = res.payment;
      const url = res.payment?.paymentUrl || '';
      document.getElementById('sh_qr_loader').style.display = 'none';

      if (url) {
        const qrImg = document.getElementById('sh_qr_img');
        if (qrImg) {
          qrImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=8&data='+encodeURIComponent(url);
          qrImg.style.display = 'block';
        }
      } else {
        document.getElementById('sh_qr_loader').innerHTML = '<div style="font-size:0.8rem;color:#999;">URL не получен от API</div>';
        document.getElementById('sh_qr_loader').style.display = 'block';
      }

      ['sh_qr_check_btn','sh_qr_print_btn'].forEach(id => {
        const b=document.getElementById(id); if(b) b.disabled=false;
      });
      this._qrSetStatus('pending','⏳ Ожидает оплаты');
      const cb=document.getElementById('sh_qr_autopoll_cb');
      if(cb) { cb.checked=true; this._qrTogglePoll(true); }

    } catch(e) {
      this._qrSetStatus('error','❌ '+e.message);
    }
  },

  async _qrCheckOnce() {
    if (!this._qrPayment) return;
    const btn=document.getElementById('sh_qr_check_btn');
    if(btn) { btn.disabled=true; btn.textContent='⏳'; }
    try {
      const res = await CRM.api('bank_account','payment_status',{
        paymentId: this._qrPayment.paymentId,
        extId:     this._qrPayment.extId,
        payMode:   'sbp',
      });
      if (res?.ok && res.isPaid) {
        await this._qrOnPaid();
      } else {
        this._qrSetStatus('pending','⏳ '+(res?.status||'Ожидает'));
        notify('Ещё не оплачено','info');
      }
    } finally {
      if(btn) { btn.disabled=false; btn.textContent='🔄 Проверить оплату'; }
    }
  },

  _qrTogglePoll(enabled) {
    this._qrStopPoll();
    const statusEl=document.getElementById('sh_qr_poll_status');
    if (!enabled||!this._qrPayment) { if(statusEl) statusEl.textContent=''; return; }

    let countdown=10;
    const upd=()=>{ if(statusEl) statusEl.textContent=`Проверка через ${countdown} сек.`; countdown--; if(countdown<0) countdown=9; };
    upd();
    this._qrTickTimer=setInterval(upd,1000);
    this._qrPollTimer=setInterval(async()=>{
      countdown=10;
      if(!this._qrPayment) { this._qrStopPoll(); return; }
      const res=await CRM.api('bank_account','payment_status',{
        paymentId:this._qrPayment.paymentId, extId:this._qrPayment.extId, payMode:'sbp',
      });
      if(res?.ok&&res.isPaid) await this._qrOnPaid();
    },10000);
  },

  _qrStopPoll() {
    if(this._qrPollTimer) { clearInterval(this._qrPollTimer); this._qrPollTimer=null; }
    if(this._qrTickTimer) { clearInterval(this._qrTickTimer); this._qrTickTimer=null; }
    const s=document.getElementById('sh_qr_poll_status'); if(s) s.textContent='';
  },

  async _qrOnPaid() {
    this._qrStopPoll();
    this._qrSetStatus('paid','✅ Оплачено!');
    const ov=document.getElementById('sh_qr_paid_overlay'); if(ov) ov.style.display='flex';
    notify('✅ Оплата получена через QR / СБП!','success');

    const iKey=`qr_${this._qrPayment?.paymentId||Date.now()}`;
    const res=await CRM.api('shift','operation',{
      type:'income', amount:this._qrUnitPrice, qty:this._qrQty,
      desc:this._qrDesc+' [QR / СБП]', method:'QR / СБП', iKey,
    });
    if(res?.ok&&!res?._dup) {
      this._current=res.data;
      this._clearForm();
      this._updateStats();
      this._renderOps();
      this._updateSalaryPreview();
    }
    setTimeout(()=>this._closeQR(),2500);
  },

  _closeQR() {
    this._qrStopPoll();
    const overlay=document.getElementById('sh_qr_overlay'); if(overlay) overlay.style.display='none';
    this._qrPayment=null;
  },

  _qrSetStatus(state,text) {
    const badge=document.getElementById('sh_qr_status'); if(!badge) return;
    const styles={
      creating:['rgba(59,130,246,0.1)','var(--accent)'],
      pending: ['rgba(245,158,11,0.12)','#f59e0b'],
      paid:    ['rgba(16,185,129,0.15)','var(--accent3)'],
      error:   ['rgba(239,68,68,0.1)','var(--danger)'],
    };
    const [bg,color]=styles[state]||styles.pending;
    badge.style.background=bg; badge.style.color=color; badge.textContent=text;
  },

  _qrPrint() {
    const img    = document.getElementById('sh_qr_img')?.src || '';
    const amount = formatMoney(this._qrAmount);
    const desc   = this._qrDesc || 'Оплата услуг';
    const html = `<!DOCTYPE html><html><head><meta charset="utf-8">
<title>QR-оплата ${amount}</title>
<style>body{font-family:Arial,sans-serif;text-align:center;padding:40px;max-width:400px;margin:0 auto;}
.amount{font-size:2.8rem;font-weight:900;color:#10b981;margin:12px 0 6px;}
.desc{font-size:0.9rem;color:#666;margin-bottom:24px;}
img{width:240px;height:240px;border:3px solid #3b82f6;border-radius:12px;padding:8px;}
.hint{font-size:0.8rem;color:#999;margin-top:16px;line-height:1.6;}
.sbp{font-size:0.75rem;color:#3b82f6;font-weight:700;margin-top:8px;}</style>
</head><body>
<div style="font-size:1rem;font-weight:700;margin-bottom:4px;">💳 Оплата через Озон Pay</div>
<div class="amount">${amount}</div>
<div class="desc">${desc}</div>
${img?`<img src="${img}" alt="QR код для оплаты">`:'<div style="color:#dc2626;">QR не загружен</div>'}
<div class="hint">Отсканируйте QR-код камерой телефона<br>или приложением любого банка</div>
<div class="sbp">📱 СБП — быстрые платежи</div>
<script>window.onload=()=>window.print();<\/script></body></html>`;
    const w=window.open('','_blank'); if(w){w.document.write(html);w.document.close();}
  },

  // ================================================================
  // ИСТОРИЯ СМЕН
  // ================================================================
  async _loadHistory() {
    const res   = await CRM.api('shift','history');
    const hist  = res?.data || [];
    const tbody = document.getElementById('sh_history_body');
    if (!tbody) return;

    tbody.innerHTML = hist.length
      ? hist.map(s => {
          const ms   = s.closeTime ? new Date(s.closeTime)-new Date(s.openTime) : 0;
          const dur  = ms ? Math.floor(ms/3600000)+'ч '+String(Math.floor((ms%3600000)/60000)).padStart(2,'0')+'м' : '—';
          const diff = ((s.endCash||0)-(s.cash||0)).toFixed(2);
          const prof = (s.totalIncome||0)-(s.totalExpense||0);
          return `<tr>
            <td class="text-xs">${new Date(s.openTime).toLocaleDateString('ru')}</td>
            <td style="font-weight:600;">${s.manager}</td>
            <td>${dur}</td>
            <td style="color:var(--accent3);font-weight:700;">${formatMoney(s.totalIncome||0)}</td>
            <td style="color:var(--danger);font-weight:700;">${formatMoney(s.totalExpense||0)}</td>
            <td style="font-weight:800;color:${prof>=0?'var(--accent3)':'var(--danger)'};">${formatMoney(prof)}</td>
            <td>${formatMoney(s.endCash||s.cash||0)}</td>
            <td style="color:${Math.abs(diff)<0.01?'var(--accent3)':diff<0?'var(--danger)':'var(--accent4)'};font-weight:700;">
              ${Math.abs(diff)<0.01?'✓ 0':(diff>0?'+':'')+diff} ₽
            </td>
            <td style="color:#a78bfa;font-weight:700;">${formatMoney(s.totalSalary||0)}</td>
            <td>
              <button class="btn btn-secondary btn-xs"
                onclick="CRM.modules.shift._printZ(${JSON.stringify(s).replace(/"/g,'&quot;')})">🖨️</button>
            </td>
          </tr>`;
        }).join('')
      : `<tr><td colspan="10"><div class="empty-state"><div class="icon">📅</div><div class="title">Смен пока не было</div></div></td></tr>`;
  },

  // ================================================================
  // ОТЧЁТЫ
  // ================================================================
  async _loadReports() {
    const res     = await CRM.api('shift','reports');
    const reports = res?.data || [];
    const el      = document.getElementById('sh_reports_list');
    if (!el) return;

    if (!reports.length) {
      el.innerHTML = `<div class="empty-state"><div class="icon">📋</div>
        <div class="title">Z-Отчётов пока нет</div>
        <div class="subtitle">Появятся после закрытия первой смены</div></div>`;
      return;
    }

    el.innerHTML = reports.map(r => {
      const ms  = r.closeTime&&r.openTime ? new Date(r.closeTime)-new Date(r.openTime) : 0;
      const dur = ms ? Math.floor(ms/3600000)+'ч '+String(Math.floor((ms%3600000)/60000)).padStart(2,'0')+'м' : '—';
      const mt  = r.methodTotals || {};
      const methodRows = Object.entries(mt).map(([m,v])=>`
        <div style="display:flex;justify-content:space-between;font-size:0.75rem;padding:2px 0;">
          <span style="color:var(--text-muted);">${m}</span>
          <span style="font-weight:700;color:var(--accent3);">${formatMoney(v.income||0)}</span>
        </div>`).join('');

      return `
        <div class="card" style="margin-bottom:12px;border:1px solid rgba(245,158,11,0.12);">
          <div style="display:flex;justify-content:space-between;align-items:center;
                      margin-bottom:12px;flex-wrap:wrap;gap:10px;">
            <div>
              <div style="font-weight:900;font-size:0.95rem;">📋 Z-Отчёт · ${r.shiftDate}</div>
              <div style="font-size:0.78rem;color:var(--text-muted);margin-top:2px;">
                👤 ${r.manager} · ⏱ ${dur} · ${r.operationsCount} операций
              </div>
            </div>
            <button class="btn btn-secondary btn-sm"
              onclick="CRM.modules.shift._printZ(${JSON.stringify(r).replace(/"/g,'&quot;')})">
              🖨️ Печать
            </button>
          </div>

          <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;">
            <div style="text-align:center;padding:10px;background:rgba(16,185,129,0.07);border-radius:10px;">
              <div style="font-size:1rem;font-weight:900;color:var(--accent3);">${formatMoney(r.totalIncome||0)}</div>
              <div style="font-size:0.65rem;color:var(--text-muted);margin-top:2px;">Доход</div>
            </div>
            <div style="text-align:center;padding:10px;background:rgba(239,68,68,0.07);border-radius:10px;">
              <div style="font-size:1rem;font-weight:900;color:var(--danger);">${formatMoney(r.totalExpense||0)}</div>
              <div style="font-size:0.65rem;color:var(--text-muted);margin-top:2px;">Расход</div>
            </div>
            <div style="text-align:center;padding:10px;background:rgba(59,130,246,0.07);border-radius:10px;">
              <div style="font-size:1rem;font-weight:900;color:${(r.profit||0)>=0?'var(--accent3)':'var(--danger)'};">${formatMoney(r.profit||0)}</div>
              <div style="font-size:0.65rem;color:var(--text-muted);margin-top:2px;">Прибыль</div>
            </div>
            <div style="text-align:center;padding:10px;background:rgba(139,92,246,0.07);border-radius:10px;">
              <div style="font-size:1rem;font-weight:900;color:#a78bfa;">${formatMoney(r.totalSalary||0)}</div>
              <div style="font-size:0.65rem;color:var(--text-muted);margin-top:2px;">ЗП</div>
            </div>
          </div>

          ${Object.keys(mt).length ? `
            <div style="margin-top:10px;padding:10px;background:rgba(59,130,246,0.05);border-radius:8px;">
              <div style="font-size:0.68rem;font-weight:700;color:var(--text-muted);
                          margin-bottom:6px;text-transform:uppercase;letter-spacing:1px;">
                💳 По методам оплаты
              </div>
              ${methodRows}
            </div>` : ''}

          ${(r.cashDiff && Math.abs(r.cashDiff)>=0.01) ? `
            <div style="margin-top:10px;padding:7px 12px;border-radius:8px;font-size:0.78rem;font-weight:700;
                        background:${r.cashDiff<0?'rgba(239,68,68,0.1)':'rgba(16,185,129,0.1)'};
                        color:${r.cashDiff<0?'var(--danger)':'var(--accent3)'};">
              ${r.cashDiff<0?'⚠️ Недостача':'✅ Излишек'}: ${Math.abs(r.cashDiff).toFixed(2)} ₽
            </div>` : ''}
        </div>`;
    }).join('');
  },

  // ================================================================
  // НАСТРОЙКИ КНОПОК
  // ================================================================
  _renderBtnsSettings() {
    const listEl    = document.getElementById('sh_btns_list');
    const previewEl = document.getElementById('sh_btns_preview');
    const emptyPrev = document.getElementById('sh_btns_preview_empty');
    if (!listEl) return;

    if (!this._buttons.length) {
      listEl.innerHTML = `
        <div style="text-align:center;padding:24px;color:var(--text-muted);
                    border:2px dashed rgba(245,158,11,0.2);border-radius:12px;">
          <div style="font-size:2rem;opacity:0.4;margin-bottom:8px;">⚡</div>
          <div style="font-size:0.82rem;">Кнопок пока нет</div>
          <div style="font-size:0.72rem;margin-top:4px;">Заполните форму ниже</div>
        </div>`;
    } else {
      listEl.innerHTML = this._buttons.map((btn, idx) => `
        <div style="display:flex;align-items:center;gap:10px;background:var(--bg-dark);
                    border-radius:10px;padding:10px 12px;margin-bottom:7px;
                    border:1px solid ${btn.color}22;">
          <div style="width:22px;height:22px;border-radius:5px;flex-shrink:0;
                      background:${btn.color}22;border:1px solid ${btn.color}44;
                      display:flex;align-items:center;justify-content:center;
                      font-size:0.65rem;font-weight:900;color:${btn.color};">
            ${idx < 9 ? idx+1 : '—'}
          </div>
          <span style="font-size:1.3rem;">${btn.icon}</span>
          <div style="flex:1;">
            <div style="font-weight:700;font-size:0.83rem;">${btn.label}</div>
            <div style="font-size:0.7rem;color:var(--text-muted);">
              ${btn.type==='income'?'▲ Приход':'▼ Расход'}
              · <b style="color:${btn.type==='income'?'var(--accent3)':'var(--danger)'};">
                ${btn.type==='income'?'+':'−'}${formatMoney(btn.amount)}
              </b>
            </div>
          </div>
          <div style="width:16px;height:16px;border-radius:4px;background:${btn.color};
                      flex-shrink:0;box-shadow:0 0 6px ${btn.color}66;"></div>
          <button class="btn btn-danger btn-xs" title="Удалить кнопку"
            onclick="CRM.modules.shift._deleteBtn('${btn.id}')">🗑</button>
        </div>`).join('');
    }

    if (previewEl) {
      if (!this._buttons.length) {
        previewEl.style.display = 'none';
        if (emptyPrev) emptyPrev.style.display = 'block';
      } else {
        previewEl.style.display = 'grid';
        if (emptyPrev) emptyPrev.style.display = 'none';
        previewEl.innerHTML = this._buttons.map((btn, idx) => `
          <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;
                      gap:4px;padding:10px 4px;border-radius:12px;background:${btn.color}12;
                      border:1.5px solid ${btn.color}44;min-height:80px;position:relative;">
            <span style="position:absolute;top:4px;left:6px;font-size:0.55rem;
                         color:${btn.color};opacity:0.8;font-weight:900;">${idx<9?idx+1:''}</span>
            <span style="font-size:1.3rem;">${btn.icon}</span>
            <span style="font-size:0.65rem;font-weight:700;text-align:center;
                         max-width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${btn.label}</span>
            <span style="font-size:0.72rem;font-weight:900;
                         color:${btn.type==='income'?'var(--accent3)':'var(--danger)'};">
              ${btn.type==='income'?'+':'−'}${formatMoney(btn.amount)}
            </span>
          </div>`).join('');
      }
    }
  },

  async _addButton() {
    const label  = document.getElementById('sb_label')?.value?.trim();
    const amount = parseFloat(document.getElementById('sb_amount')?.value) || 0;
    const type   = document.getElementById('sb_type')?.value   || 'income';
    const icon   = document.getElementById('sb_icon')?.value?.trim() || '💳';
    const color  = document.getElementById('sb_color')?.value  || '#f59e0b';

    if (!label)  { notify('Введите название', 'error'); return; }
    if (!amount) { notify('Введите сумму',    'error'); return; }

    const res = await CRM.api('shift','addButton',{ label, amount, type, icon, color });
    if (res?.ok) {
      this._buttons.push(res.data);
      this._renderBtnsSettings();
      this._renderQuickBtns();
      document.getElementById('sb_label').value  = '';
      document.getElementById('sb_amount').value = '';
      notify('✅ Кнопка добавлена: ' + label, 'success');
    } else {
      notify(res?.error || 'Ошибка', 'error');
    }
  },

  async _deleteBtn(id) {
    if (!confirm('Удалить кнопку?')) return;
    const res = await CRM.api('shift','deleteButton',{ id });
    if (res?.ok) {
      this._buttons = this._buttons.filter(b => b.id !== id);
      this._renderBtnsSettings();
      this._renderQuickBtns();
      notify('Кнопка удалена', 'info');
    }
  },

  // ================================================================
  // Z-ОТЧЁТ ПЕЧАТЬ
  // ================================================================
  _printZ(r) {
    const ops     = r.operations || [];
    const totalIn = ops.filter(o=>o.type==='income').reduce((s,o)=>s+o.amount,0);
    const totalOut= ops.filter(o=>o.type==='expense').reduce((s,o)=>s+o.amount,0);
    const ms      = r.closeTime&&r.openTime ? new Date(r.closeTime)-new Date(r.openTime) : 0;
    const dur     = ms ? Math.floor(ms/3600000)+'ч '+String(Math.floor((ms%3600000)/60000)).padStart(2,'0')+'м' : '—';
    const mt      = r.methodTotals || {};
    const methodSection = Object.keys(mt).length
      ? `<h3>💳 ПО МЕТОДАМ ОПЛАТЫ</h3>
         ${Object.entries(mt).map(([m,v])=>`
           <div class="row">
             <span>${m}</span>
             <span class="inc"><b>+${(v.income||0).toFixed(2)} ₽</b>
             ${v.expense?`<span class="exp"> / −${(v.expense||0).toFixed(2)} ₽</span>`:''}</span>
           </div>`).join('')}`
      : '';

    const html = `<!DOCTYPE html><html><head><meta charset="utf-8">
<title>Z-Отчёт ${r.shiftDate}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Courier New',monospace;font-size:12px;padding:20px;max-width:620px;margin:0 auto;}
.hdr{text-align:center;border-bottom:2px solid #000;padding-bottom:12px;margin-bottom:14px;}
.hdr h1{font-size:18px;font-weight:900;margin-bottom:4px;}
h3{font-size:12px;font-weight:900;margin:16px 0 8px;border-bottom:1px dashed #000;padding-bottom:4px;}
.row{display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px dotted #ccc;}
.row.total{font-weight:900;font-size:13px;border-top:2px solid #000;border-bottom:2px solid #000;padding:7px 0;margin-top:4px;}
.inc{color:#16a34a;} .exp{color:#dc2626;}
table{width:100%;border-collapse:collapse;margin-top:8px;font-size:11px;}
td,th{border:1px solid #ddd;padding:5px 7px;}
th{background:#f5f5f5;font-weight:900;}
.sign{display:flex;justify-content:space-between;margin-top:40px;}
</style></head><body>
<div class="hdr"><h1>Z - О Т Ч Ё Т</h1><div>Кассовая смена · ${r.shiftDate}</div></div>

<h3>📋 СМЕНА</h3>
<div class="row"><span>Менеджер</span><span><b>${r.manager}</b></span></div>
<div class="row"><span>Открыта</span><span>${new Date(r.openTime).toLocaleString('ru')}</span></div>
<div class="row"><span>Закрыта</span><span>${new Date(r.closeTime).toLocaleString('ru')}</span></div>
<div class="row"><span>Длительность</span><span>${dur}</span></div>
<div class="row"><span>Операций</span><span>${r.operationsCount}</span></div>

<h3>💰 КАССА</h3>
<div class="row"><span>Начальный остаток</span><span>${(r.startCash||0).toFixed(2)} ₽</span></div>
<div class="row"><span>Приход за смену</span><span class="inc"><b>+${(r.totalIncome||0).toFixed(2)} ₽</b></span></div>
<div class="row"><span>Расход за смену</span><span class="exp"><b>−${(r.totalExpense||0).toFixed(2)} ₽</b></span></div>
<div class="row"><span>Итог расчётный</span><span><b>${(r.calcCash||0).toFixed(2)} ₽</b></span></div>
<div class="row"><span>Фактически в кассе</span><span><b>${(r.endCash||0).toFixed(2)} ₽</b></span></div>
<div class="row total"><span>РАСХОЖДЕНИЕ</span>
  <span class="${Math.abs(r.cashDiff||0)<0.01?'inc':'exp'}">
    <b>${(r.cashDiff||0)>=0?'+':''}${(r.cashDiff||0).toFixed(2)} ₽
    ${Math.abs(r.cashDiff||0)<0.01?'✓ Норма':(r.cashDiff||0)<0?'⚠ Недостача':'✓ Излишек'}</b>
  </span>
</div>

${methodSection}

<h3>📊 ИТОГИ</h3>
<div class="row"><span>Доход</span><span class="inc"><b>${(r.totalIncome||0).toFixed(2)} ₽</b></span></div>
<div class="row"><span>Расход</span><span class="exp"><b>${(r.totalExpense||0).toFixed(2)} ₽</b></span></div>
<div class="row total"><span>ПРИБЫЛЬ СМЕНЫ</span>
  <span class="${(r.profit||0)>=0?'inc':'exp'}"><b>${(r.profit||0).toFixed(2)} ₽</b></span>
</div>

<h3>👔 ЗАРПЛАТА</h3>
<div class="row"><span>Оклад за день</span><span>${(r.baseSalary||0).toFixed(2)} ₽</span></div>
<div class="row">
  <span>Бонус ${r.bonusPct||0.1}% с дохода ${(r.totalIncome||0).toFixed(2)} ₽</span>
  <span class="inc">+${(r.accruedBonus||0).toFixed(2)} ₽</span>
</div>
<div class="row total"><span>ИТОГО К ВЫПЛАТЕ</span><span><b>${(r.totalSalary||0).toFixed(2)} ₽</b></span></div>

<h3>📋 ОПЕРАЦИИ</h3>
<table>
  <thead><tr><th>Время</th><th>Тип</th><th>Описание</th><th>Кол.</th><th>Метод</th><th>Сумма</th></tr></thead>
  <tbody>
    ${ops.map(op=>`
      <tr>
        <td>${new Date(op.time).toLocaleTimeString('ru',{hour:'2-digit',minute:'2-digit'})}</td>
        <td class="${op.type==='income'?'inc':'exp'}">${op.type==='income'?'▲':'▼'}</td>
        <td>${op.desc||'—'}</td>
        <td style="text-align:center;">${op.qty||1}</td>
        <td>${op.method||'Нал.'}</td>
        <td class="${op.type==='income'?'inc':'exp'}"><b>${op.type==='income'?'+':'−'}${op.amount.toFixed(2)} ₽</b></td>
      </tr>`).join('')}
    <tr><td colspan="5"><b>ИТОГО</b></td>
      <td><span class="inc">+${totalIn.toFixed(2)}</span> / <span class="exp">−${totalOut.toFixed(2)}</span> ₽</td>
    </tr>
  </tbody>
</table>

${r.note?`<p style="margin-top:12px;"><b>📝 Заметки:</b> ${r.note}</p>`:''}
<div class="sign">
  <div>Менеджер: _____________________</div>
  <div>Принял: _____________________</div>
</div>
<script>window.onload=()=>window.print();<\/script>
</body></html>`;
    const w=window.open('','_blank'); if(w){w.document.write(html);w.document.close();}
  },

  // ================================================================
  // КАТЕГОРИЯ
  // ================================================================
  _cat(desc, type) {
    const d = (desc||'').toLowerCase();
    if (type==='expense') {
      if (d.includes('аренд'))                          return 'Аренда';
      if (d.includes('зарплат'))                        return 'Зарплата';
      if (d.includes('бумаг')||d.includes('чернил'))   return 'Расходники';
      if (d.includes('налог'))                          return 'Налоги';
      if (d.includes('реклам'))                         return 'Реклама';
      return 'Прочий расход';
    }
    if (d.includes('фото'))                             return 'Фотопечать';
    if (d.includes('баннер'))                           return 'Баннер';
    if (d.includes('копи')||d.includes('печат'))        return 'Печать';
    if (d.includes('визит')||d.includes('листовк'))     return 'Полиграфия';
    if (d.includes('ламин'))                            return 'Ламинация';
    if (d.includes('аванс'))                            return 'Авансовый платёж';
    return 'Выручка кассы';
  },
});
</script>