<?php
require_once 'config.php';
checkLogin();
requirePermission('activities', 'view');

$activityDataDir = defined('ACTIVITY_DATA_PATH')
    ? ACTIVITY_DATA_PATH
    : dirname(dirname(dirname(DB_PATH))) . DIRECTORY_SEPARATOR . 'fanlong_activity' . DIRECTORY_SEPARATOR . 'data';
define('ACTIVITY_DATA_DIR', realpath($activityDataDir) ?: $activityDataDir);
define('ACTIVITIES_JSON', ACTIVITY_DATA_DIR . '/activities.json');
define('LEGACY_ACTIVITY_JSON', ACTIVITY_DATA_DIR . '/activity_config.json');
define('LEGACY_STATE_JSON', ACTIVITY_DATA_DIR . '/activity_state.json');
define('ACTIVITY_STATES_DIR', ACTIVITY_DATA_DIR . '/activity_states');

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function readJsonFile($path, $default = []) {
    if (!file_exists($path)) return $default;
    $raw = file_get_contents($path);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

function writeJsonFile($path, $data) {
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
    file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function normalizeActivitiesData() {
    $data = readJsonFile(ACTIVITIES_JSON, null);
    if (!$data) {
        $legacy = readJsonFile(LEGACY_ACTIVITY_JSON, []);
        $aid = $legacy['activity_id'] ?? 'activity_' . date('YmdHis');
        $data = [
            'current_activity_id' => $aid,
            'activities' => $legacy ? [$aid => $legacy] : []
        ];
        writeJsonFile(ACTIVITIES_JSON, $data);
    }
    if (!isset($data['activities']) || !is_array($data['activities'])) $data['activities'] = [];
    if (empty($data['current_activity_id']) && !empty($data['activities'])) {
        foreach ($data['activities'] as $firstKey => $_) {
            $data['current_activity_id'] = $firstKey;
            break;
        }
        writeJsonFile(ACTIVITIES_JSON, $data);
    }
    return $data;
}

function defaultActivity($id = '') {
    $id = $id ?: 'activity_' . date('YmdHis');
    return [
        'activity_id' => $id,
        'name' => '未命名活动',
        'enabled' => false,
        'phase' => 'preheat',
        'summary' => '',
        'announcement' => [],
        'focus' => [],
        'flow' => ['preheat' => [], 'formal' => []],
        'preheat_days' => 4,
        'stat_keys' => ['stat_face','stat_charm','stat_intel','stat_biz','stat_talk','stat_body','stat_art','stat_obed'],
        'lantern_riddle' => [
            'enabled' => false,
            'max_draws_per_user' => 5,
            'reward' => ['type' => 'stat', 'stat_key' => 'random', 'amount_mode' => 'fixed', 'amount' => 1, 'min' => 1, 'max' => 1],
            'riddles' => []
        ],
        'field_prompt' => ['max_draws_per_user' => 2],
        'servant_assignment' => ['enabled' => false],
        'fields' => []
    ];
}

function defaultState($activity_id) {
    return [
        'activity_id' => $activity_id,
        'riddle_users' => [],
        'riddle_draw_counts' => [],
        'prompt_users' => [],
        'servant_pool' => [],
        'servant_assignments' => [],
        'applications' => [],
        'records' => [],
        'next_application_id' => 1,
        'next_record_id' => 1,
    ];
}

function decodeLines($text) {
    $lines = preg_split('/\R/u', trim((string)$text));
    $out = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') $out[] = $line;
    }
    return $out;
}

function activityStatePath($activity_id) {
    return ACTIVITY_STATES_DIR . '/' . $activity_id . '.json';
}

function readActivityState($activity_id) {
    $path = activityStatePath($activity_id);
    $state = readJsonFile($path, null);
    if (!$state) {
        $legacy = readJsonFile(LEGACY_STATE_JSON, null);
        if ($legacy && (($legacy['activity_id'] ?? '') === $activity_id)) {
            $state = $legacy;
        }
    }
    if (!$state) $state = defaultState($activity_id);
    foreach (defaultState($activity_id) as $key => $value) {
        if (!isset($state[$key])) $state[$key] = $value;
    }
    $state['activity_id'] = $activity_id;
    return $state;
}

function saveActivityState($activity_id, $state) {
    $state['activity_id'] = $activity_id;
    writeJsonFile(activityStatePath($activity_id), $state);
}

function saveActivitiesData($data) {
    writeJsonFile(ACTIVITIES_JSON, $data);
}

function redirectTo($url) {
    header('Location: ' . $url);
    exit();
}

function editUrl($activity_id, $anchor = '') {
    $url = 'activities.php?action=edit&id=' . urlencode($activity_id);
    return $anchor ? $url . '#' . $anchor : $url;
}

function countStateRows($activity_id) {
    $state = readActivityState($activity_id);
    $records = 0;
    foreach (($state['records'] ?? []) as $r) $records += count($r['entries'] ?? []);
    return [
        'riddles' => count($state['riddle_users'] ?? []),
        'prompts' => count($state['prompt_users'] ?? []),
        'servants' => count($state['servant_pool'] ?? []),
        'assignments' => count($state['servant_assignments'] ?? []),
        'records' => $records,
    ];
}

function nextRiddleId($riddles) {
    $max = 0;
    foreach ($riddles as $riddle) {
        if (preg_match('/^r(\d+)$/', $riddle['id'] ?? '', $m)) {
            $max = max($max, intval($m[1]));
        }
    }
    return 'r' . str_pad((string)($max + 1), 3, '0', STR_PAD_LEFT);
}

function normalizeRiddlesFromPost($ids, $prompts, $answers, $deletes) {
    $riddles = [];
    $used = [];
    $rows = max(count($ids), count($prompts), count($answers));
    for ($i = 0; $i < $rows; $i++) {
        if (isset($deletes[$i])) continue;
        $prompt = trim($prompts[$i] ?? '');
        $answer = trim($answers[$i] ?? '');
        $id = trim($ids[$i] ?? '');
        if ($prompt === '' && $answer === '') continue;
        if ($id === '') $id = nextRiddleId($riddles);
        $base = preg_replace('/[^A-Za-z0-9_\-]/', '', $id) ?: nextRiddleId($riddles);
        $id = $base;
        $n = 2;
        while (isset($used[$id])) {
            $id = $base . '_' . $n;
            $n++;
        }
        $used[$id] = true;
        $riddles[] = ['id' => $id, 'prompt' => $prompt, 'answer' => $answer];
    }
    return $riddles;
}

function getUserNameSafe($user_id) {
    try {
        $stmt = getDB()->prepare("SELECT name FROM users WHERE id = ? OR uid = ? LIMIT 1");
        $stmt->execute([(string)$user_id, (string)$user_id]);
        $name = $stmt->fetchColumn();
        return $name ?: (string)$user_id;
    } catch (Exception $e) {
        return (string)$user_id;
    }
}

function resolveUserIdSafe($user_id) {
    try {
        $stmt = getDB()->prepare("SELECT id FROM users WHERE id = ? OR uid = ? LIMIT 1");
        $stmt->execute([(string)$user_id, (string)$user_id]);
        $id = $stmt->fetchColumn();
        return $id ? (string)$id : (string)$user_id;
    } catch (Exception $e) {
        return (string)$user_id;
    }
}

function settlementRewardMap() {
    return [
        '虞元' => ['type' => 'currency', 'key' => 'yuCoin'],
        'yuCoin' => ['type' => 'currency', 'key' => 'yuCoin'],
        '名誉' => ['type' => 'currency', 'key' => 'reputation'],
        'reputation' => ['type' => 'currency', 'key' => 'reputation'],
        '颜值' => ['type' => 'stat', 'key' => 'stat_face'],
        'stat_face' => ['type' => 'stat', 'key' => 'stat_face'],
        '魅力' => ['type' => 'stat', 'key' => 'stat_charm'],
        'stat_charm' => ['type' => 'stat', 'key' => 'stat_charm'],
        '智力' => ['type' => 'stat', 'key' => 'stat_intel'],
        'stat_intel' => ['type' => 'stat', 'key' => 'stat_intel'],
        '商业' => ['type' => 'stat', 'key' => 'stat_biz'],
        'stat_biz' => ['type' => 'stat', 'key' => 'stat_biz'],
        '口才' => ['type' => 'stat', 'key' => 'stat_talk'],
        'stat_talk' => ['type' => 'stat', 'key' => 'stat_talk'],
        '体能' => ['type' => 'stat', 'key' => 'stat_body'],
        'stat_body' => ['type' => 'stat', 'key' => 'stat_body'],
        '才艺' => ['type' => 'stat', 'key' => 'stat_art'],
        'stat_art' => ['type' => 'stat', 'key' => 'stat_art'],
        '服从' => ['type' => 'stat', 'key' => 'stat_obed'],
        '威慑' => ['type' => 'stat', 'key' => 'stat_obed'],
        '服从_威慑' => ['type' => 'stat', 'key' => 'stat_obed'],
        '服从威慑' => ['type' => 'stat', 'key' => 'stat_obed'],
        'stat_obed' => ['type' => 'stat', 'key' => 'stat_obed'],
    ];
}

function parseSettlementRewards($text) {
    $map = settlementRewardMap();
    $items = [];
    if (preg_match_all('/([\p{Han}A-Za-z_]+)\s*([+\-＋－])\s*(\d+)/u', (string)$text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $label = $m[1];
            if (!isset($map[$label])) continue;
            $delta = intval($m[3]);
            if ($m[2] === '-' || $m[2] === '－') $delta = -$delta;
            $items[] = [
                'label' => $label,
                'type' => $map[$label]['type'],
                'key' => $map[$label]['key'],
                'delta' => $delta,
            ];
        }
    }
    return $items;
}

function normalizeGuessRewardFromPost($prefix = 'reward') {
    $type = $_POST[$prefix . '_type'] ?? 'stat';
    if (!in_array($type, ['stat', 'currency', 'item'], true)) $type = 'stat';
    $mode = $_POST[$prefix . '_amount_mode'] ?? 'fixed';
    if (!in_array($mode, ['fixed', 'random'], true)) $mode = 'fixed';
    $reward = [
        'type' => $type,
        'amount_mode' => $mode,
        'amount' => max(0, intval($_POST[$prefix . '_amount'] ?? 1)),
        'min' => max(0, intval($_POST[$prefix . '_min'] ?? 1)),
        'max' => max(0, intval($_POST[$prefix . '_max'] ?? 1)),
    ];
    if ($reward['max'] < $reward['min']) {
        $tmp = $reward['min'];
        $reward['min'] = $reward['max'];
        $reward['max'] = $tmp;
    }
    if ($type === 'stat') {
        $statKey = $_POST[$prefix . '_stat_key'] ?? 'random';
        $reward['stat_key'] = in_array($statKey, array_merge(['random'], ALL_STAT_FIELDS), true) ? $statKey : 'random';
    } elseif ($type === 'currency') {
        $currencyKey = $_POST[$prefix . '_currency_key'] ?? 'yuCoin';
        $reward['currency_key'] = in_array($currencyKey, ['yuCoin', 'reputation'], true) ? $currencyKey : 'yuCoin';
    } elseif ($type === 'item') {
        $reward['item_name'] = trim($_POST[$prefix . '_item_name'] ?? '');
    }
    return $reward;
}

function normalizeGuessReward($reward) {
    if (!is_array($reward)) $reward = [];
    if (($reward['type'] ?? '') === 'random_stat') {
        return [
            'type' => 'stat',
            'stat_key' => 'random',
            'amount_mode' => 'fixed',
            'amount' => intval($reward['amount'] ?? 1),
            'min' => intval($reward['amount'] ?? 1),
            'max' => intval($reward['amount'] ?? 1),
        ];
    }
    $type = $reward['type'] ?? 'stat';
    if (!in_array($type, ['stat', 'currency', 'item'], true)) $type = 'stat';
    $mode = $reward['amount_mode'] ?? 'fixed';
    if (!in_array($mode, ['fixed', 'random'], true)) $mode = 'fixed';
    return array_merge([
        'type' => $type,
        'stat_key' => 'random',
        'currency_key' => 'yuCoin',
        'item_name' => '',
        'amount_mode' => $mode,
        'amount' => intval($reward['amount'] ?? 1),
        'min' => intval($reward['min'] ?? ($reward['amount'] ?? 1)),
        'max' => intval($reward['max'] ?? ($reward['amount'] ?? 1)),
    ], $reward);
}

function getShopItemsForReward() {
    try {
        return getDB()->query("SELECT name, type, is_selling FROM items ORDER BY name")->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function applySettlementRewards($user_id, $text) {
    $items = parseSettlementRewards($text);
    if (empty($items)) return [];

    $db = getDB();
    $uid = resolveUserIdSafe($user_id);
    $applied = [];
    $statFields = ALL_STAT_FIELDS;

    foreach ($items as $item) {
        if ($item['type'] === 'currency') {
            $stmt = $db->prepare("SELECT currency FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$uid]);
            $row = $stmt->fetch();
            if (!$row) continue;
            $currency = safeJsonDecode($row['currency'] ?? '{}');
            $key = $item['key'];
            $currency[$key] = max(0, intval($currency[$key] ?? 0) + intval($item['delta']));
            $db->prepare("UPDATE users SET currency = ? WHERE id = ?")
               ->execute([json_encode($currency, JSON_UNESCAPED_UNICODE), $uid]);
            $applied[] = $item;
        } elseif ($item['type'] === 'stat' && in_array($item['key'], $statFields, true)) {
            $key = $item['key'];
            $db->prepare("INSERT OR IGNORE INTO user_stats (user_id) VALUES (?)")->execute([$uid]);
            $db->prepare("UPDATE user_stats SET {$key} = MAX(0, COALESCE({$key}, 0) + ?) WHERE user_id = ?")
               ->execute([intval($item['delta']), $uid]);
            $applied[] = $item;
        }
    }
    return $applied;
}

function searchUsersSafe($q) {
    $q = trim((string)$q);
    if ($q === '') return [];
    try {
        $stmt = getDB()->prepare("SELECT id, uid, name FROM users WHERE id LIKE ? OR uid LIKE ? OR name LIKE ? ORDER BY name LIMIT 20");
        $like = '%' . $q . '%';
        $stmt->execute([$like, $like, $like]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function flattenRecords($state, $q = '') {
    $rows = [];
    $q = trim((string)$q);
    foreach (($state['records'] ?? []) as $uid => $archive) {
        $name = getUserNameSafe($uid);
        if ($q !== '' && stripos($uid, $q) === false && stripos($name, $q) === false) continue;
        foreach (($archive['entries'] ?? []) as $entry) {
            $entry['user_id'] = $uid;
            $entry['user_name'] = $name;
            $entry['markers_text'] = implode('；', $archive['markers'] ?? []);
            $entry['followups_text'] = implode('；', $archive['followups'] ?? []);
            $rows[] = $entry;
        }
    }
    usort($rows, function($a, $b) {
        return intval($b['created_at'] ?? 0) <=> intval($a['created_at'] ?? 0);
    });
    return $rows;
}

function exportActivityCsv($activity, $state, $type) {
    $filename = ($activity['activity_id'] ?? 'activity') . '_' . $type . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    if ($type === 'markers') {
        fputcsv($out, ['活动ID', '活动名称', '玩家ID', '玩家名', '标记', '后续']);
        foreach (($state['records'] ?? []) as $uid => $archive) {
            fputcsv($out, [
                $activity['activity_id'] ?? '',
                $activity['name'] ?? '',
                $uid,
                getUserNameSafe($uid),
                implode('；', $archive['markers'] ?? []),
                implode('；', $archive['followups'] ?? []),
            ]);
        }
    } else {
        fputcsv($out, ['活动ID', '活动名称', '玩家ID', '玩家名', '记录编号', '结算内容', '标记', '后续', '操作人', '时间']);
        foreach (flattenRecords($state) as $row) {
            fputcsv($out, [
                $activity['activity_id'] ?? '',
                $activity['name'] ?? '',
                $row['user_id'] ?? '',
                $row['user_name'] ?? '',
                $row['record_id'] ?? '',
                $row['result'] ?? '',
                $row['markers_text'] ?? '',
                $row['followups_text'] ?? '',
                $row['settled_by'] ?? '',
                !empty($row['created_at']) ? date('Y-m-d H:i:s', intval($row['created_at'])) : '',
            ]);
        }
    }
    fclose($out);
    exit();
}

$data = normalizeActivitiesData();
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? '';
$activities = $data['activities'];
$current_id = $data['current_activity_id'] ?? '';

if (isset($_GET['export'])) {
    $exportId = $_GET['id'] ?? $current_id;
    if (!isset($activities[$exportId])) {
        http_response_code(404);
        exit('Activity not found');
    }
    exportActivityCsv($activities[$exportId], readActivityState($exportId), $_GET['export']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('activities', 'edit');
    $pa = $_POST['action'] ?? '';
    $data = normalizeActivitiesData();
    $activities = $data['activities'];

    if ($pa === 'save_basic') {
        $old_id = trim($_POST['old_id'] ?? '');
        $aid = trim($_POST['activity_id'] ?? '');
        if ($aid === '') $aid = 'activity_' . date('YmdHis');
        $activity = $old_id && isset($data['activities'][$old_id]) ? $data['activities'][$old_id] : defaultActivity($aid);
        $activity['activity_id'] = $aid;
        $activity['name'] = trim($_POST['name'] ?? '未命名活动');
        $activity['enabled'] = isset($_POST['enabled']);
        $activity['phase'] = $_POST['phase'] ?? 'preheat';
        $activity['summary'] = trim($_POST['summary'] ?? '');
        $activity['announcement'] = decodeLines($_POST['announcement'] ?? '');
        $activity['focus'] = decodeLines($_POST['focus'] ?? '');
        $activity['preheat_days'] = intval($_POST['preheat_days'] ?? 4);
        $activity['lantern_riddle']['enabled'] = isset($_POST['riddle_enabled']);
        $activity['lantern_riddle']['max_draws_per_user'] = max(0, intval($_POST['riddle_max'] ?? 5));
        $activity['servant_assignment']['enabled'] = isset($_POST['servant_enabled']);
        $activity['field_prompt']['max_draws_per_user'] = max(0, intval($_POST['prompt_max'] ?? 2));
        $activity['lantern_riddle']['reward'] = normalizeGuessRewardFromPost();

        if ($old_id && $old_id !== $aid) {
            unset($data['activities'][$old_id]);
            $oldStatePath = activityStatePath($old_id);
            $newStatePath = activityStatePath($aid);
            if (file_exists($oldStatePath) && !file_exists($newStatePath)) rename($oldStatePath, $newStatePath);
        }
        $data['activities'][$aid] = $activity;
        if (isset($_POST['set_current']) || empty($data['current_activity_id'])) $data['current_activity_id'] = $aid;
        saveActivitiesData($data);
        logAction('activities', 'update', $aid, null, ['name' => $activity['name']]);
        setFlash('success', '活动总控已保存');
        redirectTo(editUrl($aid, 'control'));
    }

    if ($pa === 'save_json') {
        $aid = trim($_POST['activity_id'] ?? '');
        $json = $_POST['activity_json'] ?? '';
        $activity = json_decode($json, true);
        if (!$aid || !is_array($activity)) {
            setFlash('danger', 'JSON 格式错误，未保存');
            redirectTo(editUrl($aid));
        }
        $activity['activity_id'] = $aid;
        $data['activities'][$aid] = $activity;
        saveActivitiesData($data);
        logAction('activities', 'update_json', $aid);
        setFlash('success', '活动 JSON 已保存');
        redirectTo(editUrl($aid, 'json'));
    }

    if ($pa === 'save_riddles') {
        $aid = trim($_POST['activity_id'] ?? '');
        if (isset($data['activities'][$aid])) {
            $activity = $data['activities'][$aid];
            $activity['lantern_riddle']['enabled'] = isset($_POST['riddle_enabled']);
            $activity['lantern_riddle']['max_draws_per_user'] = max(0, intval($_POST['riddle_max'] ?? 5));
            $activity['lantern_riddle']['reward'] = normalizeGuessRewardFromPost();
            $activity['lantern_riddle']['riddles'] = normalizeRiddlesFromPost(
                $_POST['riddle_id'] ?? [],
                $_POST['riddle_prompt'] ?? [],
                $_POST['riddle_answer'] ?? [],
                $_POST['riddle_delete'] ?? []
            );
            $data['activities'][$aid] = $activity;
            saveActivitiesData($data);
            logAction('activities', 'save_riddles', $aid, null, ['count' => count($activity['lantern_riddle']['riddles'])]);
            setFlash('success', '灯谜配置已保存');
        }
        redirectTo(editUrl($aid, 'riddles'));
    }

    if ($pa === 'save_fields') {
        $aid = trim($_POST['activity_id'] ?? '');
        if (isset($data['activities'][$aid])) {
            $activity = $data['activities'][$aid];
            $activity['field_prompt']['max_draws_per_user'] = max(0, intval($_POST['prompt_max'] ?? 2));
            $fields = [];
            foreach (($_POST['fields'] ?? []) as $oldName => $fieldPost) {
                $name = trim($fieldPost['name'] ?? $oldName);
                if ($name === '') continue;
                $existingField = $activity['fields'][$oldName] ?? [];
                $fields[$name] = array_merge($existingField, [
                    'summary' => trim($fieldPost['summary'] ?? ''),
                    'position' => decodeLines($fieldPost['position'] ?? ''),
                    'participants' => decodeLines($fieldPost['participants'] ?? ''),
                    'plots' => decodeLines($fieldPost['plots'] ?? ''),
                    'stats' => decodeLines($fieldPost['stats'] ?? ''),
                    'rewards' => decodeLines($fieldPost['rewards'] ?? ''),
                    'consequences' => decodeLines($fieldPost['consequences'] ?? ''),
                    'host_tip' => trim($fieldPost['host_tip'] ?? ''),
                    'prompts' => decodeLines($fieldPost['prompts'] ?? ''),
                ]);
            }
            $activity['fields'] = $fields;
            $data['activities'][$aid] = $activity;
            saveActivitiesData($data);
            logAction('activities', 'save_fields', $aid, null, ['count' => count($fields)]);
            setFlash('success', '场域与题面已保存');
        }
        redirectTo(editUrl($aid, 'fields'));
    }

    if ($pa === 'reset_state') {
        $aid = trim($_POST['activity_id'] ?? '');
        saveActivityState($aid, defaultState($aid));
        logAction('activities', 'reset_state', $aid);
        setFlash('success', '本期状态已重置');
        redirectTo(editUrl($aid, 'control'));
    }

    if ($pa === 'clear_riddle_records') {
        $aid = trim($_POST['activity_id'] ?? '');
        $state = readActivityState($aid);
        $state['riddle_users'] = [];
        $state['riddle_draw_counts'] = [];
        saveActivityState($aid, $state);
        logAction('activities', 'clear_riddles', $aid);
        setFlash('success', '本期灯谜记录已清空');
        redirectTo(editUrl($aid, 'riddle-records'));
    }

    if ($pa === 'clear_prompt_records') {
        $aid = trim($_POST['activity_id'] ?? '');
        $state = readActivityState($aid);
        $state['prompt_users'] = [];
        saveActivityState($aid, $state);
        logAction('activities', 'clear_prompts', $aid);
        setFlash('success', '本期题面抽取记录已清空');
        redirectTo(editUrl($aid, 'prompt-records'));
    }

    if ($pa === 'remove_servant') {
        $aid = trim($_POST['activity_id'] ?? '');
        $uid = trim($_POST['user_id'] ?? '');
        $state = readActivityState($aid);
        unset($state['servant_pool'][$uid]);
        foreach (($state['servant_assignments'] ?? []) as $masterId => $assignment) {
            if (($assignment['servant_user_id'] ?? '') === $uid) unset($state['servant_assignments'][$masterId]);
        }
        saveActivityState($aid, $state);
        logAction('activities', 'remove_servant', $aid, null, ['user_id' => $uid]);
        setFlash('success', '已移除公奴报名');
        redirectTo(editUrl($aid, 'servants'));
    }

    if ($pa === 'clear_assignments') {
        $aid = trim($_POST['activity_id'] ?? '');
        $state = readActivityState($aid);
        $state['servant_assignments'] = [];
        foreach (($state['servant_pool'] ?? []) as $uid => $item) {
            $state['servant_pool'][$uid]['available'] = true;
            $state['servant_pool'][$uid]['assigned_to'] = '';
        }
        saveActivityState($aid, $state);
        logAction('activities', 'clear_assignments', $aid);
        setFlash('success', '本期分配关系已清空');
        redirectTo(editUrl($aid, 'servants'));
    }

    if ($pa === 'save_record') {
        $aid = trim($_POST['activity_id'] ?? '');
        $uid = trim($_POST['target_id'] ?? '');
        $result = trim($_POST['result'] ?? '');
        $marker = trim($_POST['marker'] ?? '');
        $followup = trim($_POST['followup'] ?? '');
        if ($aid && $uid && ($result !== '' || $marker !== '' || $followup !== '')) {
            $state = readActivityState($aid);
            $recordId = trim($_POST['record_id'] ?? '');
            $records = $state['records'] ?? [];
            $archive = $records[$uid] ?? ['entries' => [], 'markers' => [], 'followups' => []];
            if ($recordId !== '') {
                foreach ($archive['entries'] as $idx => $entry) {
                    if ((string)($entry['record_id'] ?? '') === $recordId) {
                        $archive['entries'][$idx]['result'] = $result;
                        $archive['entries'][$idx]['updated_at'] = time();
                        $archive['entries'][$idx]['updated_by'] = $_SESSION['admin_id'] ?? 'admin';
                    }
                }
            } elseif ($result !== '') {
                $next = intval($state['next_record_id'] ?? 1);
                $state['next_record_id'] = $next + 1;
                $appliedRewards = applySettlementRewards($uid, $result);
                $archive['entries'][] = [
                    'record_id' => $next,
                    'activity_name' => $data['activities'][$aid]['name'] ?? '',
                    'source' => 'admin_settlement',
                    'result' => $result,
                    'applied_rewards' => $appliedRewards,
                    'settled_by' => $_SESSION['admin_id'] ?? 'admin',
                    'created_at' => time(),
                ];
            }
            if ($marker !== '' && !in_array($marker, $archive['markers'] ?? [])) $archive['markers'][] = $marker;
            if ($followup !== '' && !in_array($followup, $archive['followups'] ?? [])) $archive['followups'][] = $followup;
            $records[$uid] = $archive;
            $state['records'] = $records;
            saveActivityState($aid, $state);
            logAction('activities', 'save_record', $aid, null, ['user_id' => $uid, 'record_id' => $recordId]);
            setFlash('success', $recordId !== '' ? '结算记录已修正' : '结算记录已新增');
        } else {
            setFlash('warning', '请填写玩家ID以及结算/标记/后续内容');
        }
        redirectTo(editUrl($aid, 'records'));
    }

    if ($pa === 'delete_record') {
        $aid = trim($_POST['activity_id'] ?? '');
        $uid = trim($_POST['user_id'] ?? '');
        $recordId = trim($_POST['record_id'] ?? '');
        $state = readActivityState($aid);
        if (isset($state['records'][$uid]['entries'])) {
            $state['records'][$uid]['entries'] = array_values(array_filter(
                $state['records'][$uid]['entries'],
                function($entry) use ($recordId) {
                    return (string)($entry['record_id'] ?? '') !== $recordId;
                }
            ));
            saveActivityState($aid, $state);
            logAction('activities', 'delete_record', $aid, null, ['user_id' => $uid, 'record_id' => $recordId]);
            setFlash('success', '误结算记录已删除');
        }
        redirectTo(editUrl($aid, 'records'));
    }

    if ($pa === 'set_current') {
        $aid = trim($_POST['activity_id'] ?? '');
        if (isset($data['activities'][$aid])) {
            $data['current_activity_id'] = $aid;
            saveActivitiesData($data);
            logAction('activities', 'set_current', $aid);
            setFlash('success', '当前活动已切换');
        }
        redirectTo('activities.php');
    }

    if ($pa === 'toggle') {
        $aid = trim($_POST['activity_id'] ?? '');
        if (isset($data['activities'][$aid])) {
            $data['activities'][$aid]['enabled'] = empty($data['activities'][$aid]['enabled']);
            saveActivitiesData($data);
            logAction('activities', 'toggle', $aid);
            setFlash('success', '活动开关已更新');
        }
        redirectTo('activities.php');
    }

    if ($pa === 'copy') {
        $aid = trim($_POST['activity_id'] ?? '');
        if (isset($data['activities'][$aid])) {
            $new = $data['activities'][$aid];
            $new_id = $aid . '_copy_' . date('His');
            $new['activity_id'] = $new_id;
            $new['name'] = ($new['name'] ?? $aid) . ' 副本';
            $new['enabled'] = false;
            $data['activities'][$new_id] = $new;
            saveActivitiesData($data);
            logAction('activities', 'copy', $aid, null, ['new_id' => $new_id]);
            setFlash('success', '活动已复制');
        }
        redirectTo('activities.php');
    }

    if ($pa === 'delete') {
        requirePermission('activities', 'delete');
        $aid = trim($_POST['activity_id'] ?? '');
        if (isset($data['activities'][$aid])) {
            unset($data['activities'][$aid]);
            if (($data['current_activity_id'] ?? '') === $aid) {
                $data['current_activity_id'] = '';
                foreach ($data['activities'] as $firstKey => $_) {
                    $data['current_activity_id'] = $firstKey;
                    break;
                }
            }
            saveActivitiesData($data);
            logAction('activities', 'delete', $aid);
            setFlash('success', '活动已删除');
        }
        redirectTo('activities.php');
    }
}

$data = normalizeActivitiesData();
$activities = $data['activities'];
$current_id = $data['current_activity_id'] ?? '';
$edit = null;
if ($action === 'add') $edit = defaultActivity();
if ($action === 'edit' && $id && isset($activities[$id])) $edit = $activities[$id];
$editState = $edit ? readActivityState($edit['activity_id'] ?? '') : null;
$guessReward = $edit ? normalizeGuessReward($edit['lantern_riddle']['reward'] ?? []) : normalizeGuessReward([]);
$shopItems = getShopItemsForReward();
$recordQuery = trim($_GET['q'] ?? '');
$userMatches = $recordQuery !== '' ? searchUsersSafe($recordQuery) : [];
$recordRows = $editState ? flattenRecords($editState, $recordQuery) : [];

$page_title = '活动管理';
$page_icon = 'fas fa-calendar-days';
$page_subtitle = '月度活动运营配置';
require_once 'header.php';
?>

<style>
.activity-nav { position: sticky; top: 0; z-index: 5; background: var(--bs-body-bg); padding: .5rem 0; }
.activity-nav .btn { border-radius: 999px; }
.ops-card .card-header { font-weight: 700; }
.mini-table td, .mini-table th { font-size: 12px; vertical-align: middle; }
.textarea-sm { min-height: 88px; }
.field-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; }
</style>

<?php if(!is_dir(ACTIVITY_DATA_DIR)): ?>
<div class="alert alert-danger border-0 rounded-3">活动配置目录不存在：<code><?php echo h(ACTIVITY_DATA_DIR); ?></code></div>
<?php endif; ?>

<?php if($edit): ?>
<?php
  $aid = $edit['activity_id'] ?? '';
  $riddles = $edit['lantern_riddle']['riddles'] ?? [];
  $fields = $edit['fields'] ?? [];
  $state = $editState ?: defaultState($aid);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <div class="small text-muted">编辑活动</div>
    <h5 class="mb-0"><?php echo h($edit['name'] ?? '未命名活动'); ?> <?php if($aid===$current_id): ?><span class="badge bg-primary ms-1">当前</span><?php endif; ?></h5>
  </div>
  <a href="activities.php" class="btn btn-sm btn-outline-secondary">返回列表</a>
</div>

<div class="activity-nav mb-3">
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-sm btn-outline-primary" href="#control">活动总控</a>
    <a class="btn btn-sm btn-outline-primary" href="#riddles">抽题/灯谜</a>
    <a class="btn btn-sm btn-outline-primary" href="#servants">公奴分配</a>
    <a class="btn btn-sm btn-outline-primary" href="#fields">场域与题面</a>
    <a class="btn btn-sm btn-outline-primary" href="#records">活动结算</a>
    <a class="btn btn-sm btn-outline-primary" href="#exports">导出与余波</a>
    <a class="btn btn-sm btn-outline-secondary" href="#json">高级 JSON</a>
  </div>
</div>

<form method="POST" class="card ops-card mb-4" id="control">
  <input type="hidden" name="action" value="save_basic">
  <input type="hidden" name="old_id" value="<?php echo h($aid); ?>">
  <div class="card-header"><i class="fas fa-sliders me-2"></i>活动总控</div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-3">
        <label class="form-label small fw-semibold">活动ID</label>
        <input class="form-control font-monospace" name="activity_id" value="<?php echo h($aid); ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label small fw-semibold">当前活动名称</label>
        <input class="form-control" name="name" value="<?php echo h($edit['name'] ?? ''); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">当前阶段</label>
        <select class="form-select" name="phase">
          <?php foreach(['preheat'=>'预热期','formal'=>'正式期','aftermath'=>'余波期','closed'=>'关闭'] as $k=>$v): ?>
          <option value="<?php echo h($k); ?>" <?php echo (($edit['phase'] ?? '')===$k?'selected':''); ?>><?php echo h($v); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="enabled" id="enabledSwitch" <?php echo !empty($edit['enabled'])?'checked':''; ?>>
          <label class="form-check-label" for="enabledSwitch">是否开启活动</label>
        </div>
      </div>
      <div class="col-md-12">
        <label class="form-label small fw-semibold">活动简介</label>
        <textarea class="form-control" name="summary" rows="2"><?php echo h($edit['summary'] ?? ''); ?></textarea>
      </div>
      <div class="col-md-6">
        <label class="form-label small fw-semibold">公告文案 <span class="text-muted">一行一条</span></label>
        <textarea class="form-control" name="announcement" rows="6"><?php echo h(implode("\n", $edit['announcement'] ?? [])); ?></textarea>
      </div>
      <div class="col-md-6">
        <label class="form-label small fw-semibold">活动重点 <span class="text-muted">一行一条</span></label>
        <textarea class="form-control" name="focus" rows="6"><?php echo h(implode("\n", $edit['focus'] ?? [])); ?></textarea>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-semibold">预热天数</label>
        <input type="number" class="form-control" name="preheat_days" value="<?php echo intval($edit['preheat_days'] ?? 4); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-semibold">灯谜次数/人</label>
        <input type="number" class="form-control" name="riddle_max" value="<?php echo intval($edit['lantern_riddle']['max_draws_per_user'] ?? 5); ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-semibold">题面次数/人</label>
        <input type="number" class="form-control" name="prompt_max" value="<?php echo intval($edit['field_prompt']['max_draws_per_user'] ?? 2); ?>">
      </div>
      <div class="col-md-4 d-flex align-items-end gap-3 flex-wrap">
        <label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="set_current" <?php echo $aid===$current_id?'checked':''; ?>> 设为当前活动</label>
        <label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="riddle_enabled" <?php echo !empty($edit['lantern_riddle']['enabled'])?'checked':''; ?>> 开启灯谜</label>
        <label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="servant_enabled" <?php echo !empty($edit['servant_assignment']['enabled'])?'checked':''; ?>> 开启分配</label>
      </div>
      <div class="col-12">
        <div class="border rounded-3 p-3 bg-light">
          <div class="fw-semibold mb-2">猜题奖励</div>
          <div class="row g-2">
            <div class="col-md-2">
              <label class="form-label small fw-semibold">奖励类型</label>
              <select class="form-select" name="reward_type">
                <?php foreach(['stat'=>'属性','currency'=>'货币','item'=>'物品'] as $k=>$v): ?>
                <option value="<?php echo h($k); ?>" <?php echo ($guessReward['type'] ?? '')===$k?'selected':''; ?>><?php echo h($v); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label small fw-semibold">属性目标</label>
              <select class="form-select" name="reward_stat_key">
                <option value="random" <?php echo ($guessReward['stat_key'] ?? '')==='random'?'selected':''; ?>>随机属性</option>
                <?php foreach(ALL_STAT_FIELDS as $sf): ?>
                <option value="<?php echo h($sf); ?>" <?php echo ($guessReward['stat_key'] ?? '')===$sf?'selected':''; ?>><?php echo h(t($sf, $sf)); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label small fw-semibold">货币目标</label>
              <select class="form-select" name="reward_currency_key">
                <option value="yuCoin" <?php echo ($guessReward['currency_key'] ?? '')==='yuCoin'?'selected':''; ?>><?php echo h(t('term_yuCoin', '虞元')); ?></option>
                <option value="reputation" <?php echo ($guessReward['currency_key'] ?? '')==='reputation'?'selected':''; ?>><?php echo h(t('term_reputation', '名誉')); ?></option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">物品目标</label>
              <select class="form-select" name="reward_item_name">
                <option value="">选择已创建物品</option>
                <?php foreach($shopItems as $item): ?>
                <option value="<?php echo h($item['name']); ?>" <?php echo ($guessReward['item_name'] ?? '')===$item['name']?'selected':''; ?>><?php echo h($item['name']); ?><?php echo empty($item['is_selling']) ? '（下架）' : ''; ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">数值模式</label>
              <select class="form-select" name="reward_amount_mode">
                <option value="fixed" <?php echo ($guessReward['amount_mode'] ?? '')==='fixed'?'selected':''; ?>>固定数值</option>
                <option value="random" <?php echo ($guessReward['amount_mode'] ?? '')==='random'?'selected':''; ?>>随机区间</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label small fw-semibold">固定数值</label>
              <input type="number" class="form-control" name="reward_amount" value="<?php echo intval($guessReward['amount'] ?? 1); ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label small fw-semibold">随机最小</label>
              <input type="number" class="form-control" name="reward_min" value="<?php echo intval($guessReward['min'] ?? 1); ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label small fw-semibold">随机最大</label>
              <input type="number" class="form-control" name="reward_max" value="<?php echo intval($guessReward['max'] ?? 1); ?>">
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <div class="small text-muted">选择物品奖励时，会从商店现有物品发放到玩家背包；可选择已下架但仍存在的物品。</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <span class="small text-muted">状态文件：<code><?php echo h(activityStatePath($aid)); ?></code></span>
    <button class="btn btn-primary"><i class="fas fa-save me-1"></i>一键保存配置</button>
  </div>
</form>

<form method="POST" class="mb-4" onsubmit="return confirm('确认重置本期状态？灯谜、题面、公奴分配、结算记录都会清空。')">
  <input type="hidden" name="action" value="reset_state">
  <input type="hidden" name="activity_id" value="<?php echo h($aid); ?>">
  <button class="btn btn-outline-danger"><i class="fas fa-rotate-left me-1"></i>一键重置本期状态</button>
</form>

<form method="POST" class="card ops-card mb-4" id="riddles">
  <input type="hidden" name="action" value="save_riddles">
  <input type="hidden" name="activity_id" value="<?php echo h($aid); ?>">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-puzzle-piece me-2"></i>抽题/灯谜管理</span>
    <span class="badge bg-secondary"><?php echo count($riddles); ?> 题</span>
  </div>
  <div class="card-body">
    <div class="row g-3 mb-3">
      <div class="col-md-3"><label class="form-label small fw-semibold">每人可抽次数</label><input type="number" class="form-control" name="riddle_max" value="<?php echo intval($edit['lantern_riddle']['max_draws_per_user'] ?? 5); ?>"></div>
      <div class="col-md-3 d-flex align-items-end"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="riddle_enabled" <?php echo !empty($edit['lantern_riddle']['enabled'])?'checked':''; ?>> 开启抽题</label></div>
    </div>
    <div class="border rounded-3 p-3 bg-light mb-3">
      <div class="fw-semibold mb-2">猜题奖励</div>
      <div class="row g-2">
        <div class="col-md-2">
          <label class="form-label small fw-semibold">奖励类型</label>
          <select class="form-select" name="reward_type">
            <?php foreach(['stat'=>'属性','currency'=>'货币','item'=>'物品'] as $k=>$v): ?>
            <option value="<?php echo h($k); ?>" <?php echo ($guessReward['type'] ?? '')===$k?'selected':''; ?>><?php echo h($v); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-semibold">属性目标</label>
          <select class="form-select" name="reward_stat_key">
            <option value="random" <?php echo ($guessReward['stat_key'] ?? '')==='random'?'selected':''; ?>>随机属性</option>
            <?php foreach(ALL_STAT_FIELDS as $sf): ?>
            <option value="<?php echo h($sf); ?>" <?php echo ($guessReward['stat_key'] ?? '')===$sf?'selected':''; ?>><?php echo h(t($sf, $sf)); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-semibold">货币目标</label>
          <select class="form-select" name="reward_currency_key">
            <option value="yuCoin" <?php echo ($guessReward['currency_key'] ?? '')==='yuCoin'?'selected':''; ?>><?php echo h(t('term_yuCoin', '虞元')); ?></option>
            <option value="reputation" <?php echo ($guessReward['currency_key'] ?? '')==='reputation'?'selected':''; ?>><?php echo h(t('term_reputation', '名誉')); ?></option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">物品目标</label>
          <select class="form-select" name="reward_item_name">
            <option value="">选择已创建物品</option>
            <?php foreach($shopItems as $item): ?>
            <option value="<?php echo h($item['name']); ?>" <?php echo ($guessReward['item_name'] ?? '')===$item['name']?'selected':''; ?>><?php echo h($item['name']); ?><?php echo empty($item['is_selling']) ? '（下架）' : ''; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-semibold">数值模式</label>
          <select class="form-select" name="reward_amount_mode">
            <option value="fixed" <?php echo ($guessReward['amount_mode'] ?? '')==='fixed'?'selected':''; ?>>固定数值</option>
            <option value="random" <?php echo ($guessReward['amount_mode'] ?? '')==='random'?'selected':''; ?>>随机区间</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-semibold">固定数值</label>
          <input type="number" class="form-control" name="reward_amount" value="<?php echo intval($guessReward['amount'] ?? 1); ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-semibold">随机最小</label>
          <input type="number" class="form-control" name="reward_min" value="<?php echo intval($guessReward['min'] ?? 1); ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-semibold">随机最大</label>
          <input type="number" class="form-control" name="reward_max" value="<?php echo intval($guessReward['max'] ?? 1); ?>">
        </div>
      </div>
    </div>
    <div class="table-responsive" style="max-height:520px">
      <table class="table table-sm table-bordered mini-table align-middle">
        <thead class="table-active sticky-top"><tr><th style="width:90px">ID</th><th>Emoji 题面</th><th>答案</th><th style="width:70px">删除</th></tr></thead>
        <tbody>
        <?php foreach(array_values($riddles) as $i=>$r): ?>
          <tr>
            <td><input class="form-control form-control-sm font-monospace" name="riddle_id[<?php echo $i; ?>]" value="<?php echo h($r['id'] ?? ''); ?>"></td>
            <td><input class="form-control form-control-sm" name="riddle_prompt[<?php echo $i; ?>]" value="<?php echo h($r['prompt'] ?? ''); ?>"></td>
            <td><input class="form-control form-control-sm" name="riddle_answer[<?php echo $i; ?>]" value="<?php echo h($r['answer'] ?? ''); ?>"></td>
            <td class="text-center"><input class="form-check-input" type="checkbox" name="riddle_delete[<?php echo $i; ?>]"></td>
          </tr>
        <?php endforeach; ?>
        <?php for($j=0;$j<5;$j++): $i=count($riddles)+$j; ?>
          <tr class="table-light">
            <td><input class="form-control form-control-sm font-monospace" name="riddle_id[<?php echo $i; ?>]" placeholder="自动"></td>
            <td><input class="form-control form-control-sm" name="riddle_prompt[<?php echo $i; ?>]" placeholder="新增题面"></td>
            <td><input class="form-control form-control-sm" name="riddle_answer[<?php echo $i; ?>]" placeholder="答案"></td>
            <td></td>
          </tr>
        <?php endfor; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer text-end"><button class="btn btn-primary"><i class="fas fa-save me-1"></i>保存灯谜</button></div>
</form>

<div class="card ops-card mb-4" id="riddle-records">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-list-check me-2"></i>玩家抽题记录</span>
    <form method="POST" onsubmit="return confirm('确认清空本期灯谜记录？')">
      <input type="hidden" name="action" value="clear_riddle_records">
      <input type="hidden" name="activity_id" value="<?php echo h($aid); ?>">
      <button class="btn btn-sm btn-outline-danger">清空本期灯谜记录</button>
    </form>
  </div>
  <div class="card-body p-0">
    <table class="table table-sm mini-table mb-0">
      <thead class="table-active"><tr><th class="ps-3">玩家</th><th>抽题次数</th><th>已答对</th><th>最近题目</th></tr></thead>
      <tbody>
      <?php foreach(($state['riddle_users'] ?? []) as $uid=>$ru): $draws=$ru['draws'] ?? []; $last=end($draws); ?>
        <tr><td class="ps-3"><?php echo h(getUserNameSafe($uid)); ?><br><code><?php echo h($uid); ?></code></td><td><?php echo count($draws); ?></td><td><?php echo count(array_filter($draws, function($d){ return !empty($d['correct']); })); ?></td><td><?php echo h($last['riddle_id'] ?? '-'); ?></td></tr>
      <?php endforeach; ?>
      <?php if(empty($state['riddle_users'])): ?><tr><td colspan="4" class="text-center text-muted py-4">暂无灯谜抽题记录</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card ops-card mb-4" id="servants">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-people-arrows me-2"></i>公奴分配</span>
    <form method="POST" onsubmit="return confirm('确认清空本期分配关系？报名池会保留并恢复可分配。')">
      <input type="hidden" name="action" value="clear_assignments">
      <input type="hidden" name="activity_id" value="<?php echo h($aid); ?>">
      <button class="btn btn-sm btn-outline-danger">清空本期分配</button>
    </form>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-lg-6">
        <h6 class="fw-bold">公奴报名池</h6>
        <div class="table-responsive">
          <table class="table table-sm mini-table align-middle">
            <thead class="table-active"><tr><th>公奴</th><th>状态</th><th>备注</th><th style="width:80px">操作</th></tr></thead>
            <tbody>
            <?php foreach(($state['servant_pool'] ?? []) as $uid=>$item): ?>
              <tr>
                <td><?php echo h($item['name'] ?? getUserNameSafe($uid)); ?><br><code><?php echo h($uid); ?></code></td>
                <td><?php echo !empty($item['available']) ? '<span class="badge bg-success">可分配</span>' : '<span class="badge bg-secondary">已分配</span>'; ?></td>
                <td><?php echo h($item['note'] ?? ''); ?></td>
                <td>
                  <form method="POST" onsubmit="return confirm('确认移除该报名？')">
                    <input type="hidden" name="action" value="remove_servant">
                    <input type="hidden" name="activity_id" value="<?php echo h($aid); ?>">
                    <input type="hidden" name="user_id" value="<?php echo h($uid); ?>">
                    <button class="btn btn-sm btn-outline-danger py-0">移除</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if(empty($state['servant_pool'])): ?><tr><td colspan="4" class="text-center text-muted py-4">暂无公奴报名</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="col-lg-6">
        <h6 class="fw-bold">已分配关系</h6>
        <div class="table-responsive">
          <table class="table table-sm mini-table">
            <thead class="table-active"><tr><th>参会者</th><th>随行公奴</th><th>时间</th></tr></thead>
            <tbody>
            <?php foreach(($state['servant_assignments'] ?? []) as $mid=>$as): ?>
              <tr><td><?php echo h($as['master_name'] ?? getUserNameSafe($mid)); ?><br><code><?php echo h($mid); ?></code></td><td><?php echo h($as['servant_name'] ?? ''); ?><br><code><?php echo h($as['servant_user_id'] ?? ''); ?></code></td><td><?php echo !empty($as['created_at']) ? date('Y-m-d H:i', intval($as['created_at'])) : '-'; ?></td></tr>
            <?php endforeach; ?>
            <?php if(empty($state['servant_assignments'])): ?><tr><td colspan="3" class="text-center text-muted py-4">暂无分配关系</td></tr><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<form method="POST" class="card ops-card mb-4" id="fields">
  <input type="hidden" name="action" value="save_fields">
  <input type="hidden" name="activity_id" value="<?php echo h($aid); ?>">
  <div class="card-header"><i class="fas fa-map-location-dot me-2"></i>场域与题面</div>
  <div class="card-body">
    <div class="row g-3 mb-3">
      <div class="col-md-3"><label class="form-label small fw-semibold">每人题面抽取次数</label><input type="number" class="form-control" name="prompt_max" value="<?php echo intval($edit['field_prompt']['max_draws_per_user'] ?? 2); ?>"></div>
    </div>
    <div class="field-grid">
    <?php foreach($fields as $fname=>$field): ?>
      <div class="border rounded-3 p-3">
        <label class="form-label small fw-semibold">场域名称</label>
        <input class="form-control mb-2" name="fields[<?php echo h($fname); ?>][name]" value="<?php echo h($fname); ?>">
        <label class="form-label small fw-semibold">说明</label>
        <textarea class="form-control textarea-sm mb-2" name="fields[<?php echo h($fname); ?>][summary]"><?php echo h($field['summary'] ?? ''); ?></textarea>
        <label class="form-label small fw-semibold">定位 <span class="text-muted">一行一条</span></label>
        <textarea class="form-control textarea-sm mb-2" name="fields[<?php echo h($fname); ?>][position]"><?php echo h(implode("\n", $field['position'] ?? [])); ?></textarea>
        <label class="form-label small fw-semibold">参与人群</label>
        <textarea class="form-control textarea-sm mb-2" name="fields[<?php echo h($fname); ?>][participants]"><?php echo h(implode("\n", $field['participants'] ?? [])); ?></textarea>
        <label class="form-label small fw-semibold">可写剧情</label>
        <textarea class="form-control textarea-sm mb-2" name="fields[<?php echo h($fname); ?>][plots]"><?php echo h(implode("\n", $field['plots'] ?? [])); ?></textarea>
        <label class="form-label small fw-semibold">判定属性</label>
        <textarea class="form-control textarea-sm mb-2" name="fields[<?php echo h($fname); ?>][stats]"><?php echo h(implode("\n", $field['stats'] ?? [])); ?></textarea>
        <label class="form-label small fw-semibold">奖励</label>
        <textarea class="form-control textarea-sm mb-2" name="fields[<?php echo h($fname); ?>][rewards]"><?php echo h(implode("\n", $field['rewards'] ?? [])); ?></textarea>
        <label class="form-label small fw-semibold">失败后果</label>
        <textarea class="form-control textarea-sm mb-2" name="fields[<?php echo h($fname); ?>][consequences]"><?php echo h(implode("\n", $field['consequences'] ?? [])); ?></textarea>
        <label class="form-label small fw-semibold">主持人提示</label>
        <textarea class="form-control textarea-sm mb-2" name="fields[<?php echo h($fname); ?>][host_tip]"><?php echo h($field['host_tip'] ?? ''); ?></textarea>
        <label class="form-label small fw-semibold">开戏题面库 <span class="text-muted">一行一条</span></label>
        <textarea class="form-control" rows="10" name="fields[<?php echo h($fname); ?>][prompts]"><?php echo h(implode("\n", $field['prompts'] ?? [])); ?></textarea>
      </div>
    <?php endforeach; ?>
    <?php if(empty($fields)): ?><div class="text-muted">暂无场域，可在高级 JSON 中初始化。</div><?php endif; ?>
    </div>
  </div>
  <div class="card-footer text-end"><button class="btn btn-primary"><i class="fas fa-save me-1"></i>保存场域与题面</button></div>
</form>

<div class="card ops-card mb-4" id="prompt-records">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="fas fa-ticket me-2"></i>玩家已抽题面次数</span>
    <form method="POST" onsubmit="return confirm('确认清空本期题面抽取记录？')">
      <input type="hidden" name="action" value="clear_prompt_records">
      <input type="hidden" name="activity_id" value="<?php echo h($aid); ?>">
      <button class="btn btn-sm btn-outline-danger">清空题面记录</button>
    </form>
  </div>
  <div class="card-body p-0">
    <table class="table table-sm mini-table mb-0">
      <thead class="table-active"><tr><th class="ps-3">玩家</th><th>次数</th><th>最近场域</th><th>最近题面</th></tr></thead>
      <tbody>
      <?php foreach(($state['prompt_users'] ?? []) as $uid=>$pu): $draws=$pu['draws'] ?? []; $last=end($draws); ?>
        <tr><td class="ps-3"><?php echo h(getUserNameSafe($uid)); ?><br><code><?php echo h($uid); ?></code></td><td><?php echo count($draws); ?></td><td><?php echo h($last['field'] ?? '-'); ?></td><td><?php echo h($last['prompt'] ?? '-'); ?></td></tr>
      <?php endforeach; ?>
      <?php if(empty($state['prompt_users'])): ?><tr><td colspan="4" class="text-center text-muted py-4">暂无题面抽取记录</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card ops-card mb-4" id="records">
  <div class="card-header"><i class="fas fa-file-signature me-2"></i>活动结算</div>
  <div class="card-body">
    <form class="row g-2 mb-3" method="GET">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" value="<?php echo h($aid); ?>">
      <div class="col-md-8"><input class="form-control" name="q" value="<?php echo h($recordQuery); ?>" placeholder="搜索玩家ID / QQ / 名字"></div>
      <div class="col-md-4"><button class="btn btn-outline-primary w-100">搜索玩家</button></div>
    </form>
    <?php if($recordQuery !== '' && !empty($userMatches)): ?>
    <div class="mb-3 small">
      <span class="text-muted">匹配玩家：</span>
      <?php foreach($userMatches as $u): ?><span class="badge bg-light text-dark me-1"><?php echo h($u['name'] ?? ''); ?> / <?php echo h($u['id'] ?? ''); ?></span><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <form method="POST" class="border rounded-3 p-3 mb-3">
      <input type="hidden" name="action" value="save_record">
      <input type="hidden" name="activity_id" value="<?php echo h($aid); ?>">
      <div class="alert alert-light border small mb-3">
        标准格式：<code>虞元+20 名誉+5 颜值+1 魅力+1 智力+1 商业+1 口才+1 体能+1 才艺+1 服从_威慑+1 标记:席间得名 后续:席后私谈</code>。
        可用 <code>+</code> 或 <code>-</code>；虞元/名誉会写入用户货币，八项属性会写入用户属性，标记/后续只进入活动档案。
      </div>
      <div class="row g-2">
        <div class="col-md-3"><label class="form-label small fw-semibold">玩家ID/QQ</label><input class="form-control" name="target_id" value="<?php echo h($recordQuery); ?>"></div>
        <div class="col-md-3"><label class="form-label small fw-semibold">奖励 / 结算内容</label><input class="form-control" name="result" placeholder="虞元+20 名誉+5 口才+1"></div>
        <div class="col-md-3"><label class="form-label small fw-semibold">标记</label><input class="form-control" name="marker" placeholder="席间得名"></div>
        <div class="col-md-3"><label class="form-label small fw-semibold">后续</label><input class="form-control" name="followup" placeholder="席后私谈"></div>
        <div class="col-12 text-end"><button class="btn btn-primary"><i class="fas fa-plus me-1"></i>手动录入奖励 / 标记 / 后续</button></div>
      </div>
    </form>
    <div class="table-responsive">
      <table class="table table-sm mini-table align-middle">
        <thead class="table-active"><tr><th>玩家</th><th>编号</th><th>结算内容</th><th>标记/后续</th><th>时间</th><th style="width:170px">修正 / 删除</th></tr></thead>
        <tbody>
        <?php foreach($recordRows as $row): ?>
          <tr>
            <td><?php echo h($row['user_name'] ?? ''); ?><br><code><?php echo h($row['user_id'] ?? ''); ?></code></td>
            <td><?php echo h($row['record_id'] ?? ''); ?></td>
            <td><?php echo h($row['result'] ?? ''); ?></td>
            <td><span class="text-muted">标记：</span><?php echo h($row['markers_text'] ?? ''); ?><br><span class="text-muted">后续：</span><?php echo h($row['followups_text'] ?? ''); ?></td>
            <td><?php echo !empty($row['created_at']) ? date('Y-m-d H:i', intval($row['created_at'])) : '-'; ?></td>
            <td>
              <form method="POST" class="d-flex gap-1 mb-1">
                <input type="hidden" name="action" value="save_record">
                <input type="hidden" name="activity_id" value="<?php echo h($aid); ?>">
                <input type="hidden" name="target_id" value="<?php echo h($row['user_id'] ?? ''); ?>">
                <input type="hidden" name="record_id" value="<?php echo h($row['record_id'] ?? ''); ?>">
                <input class="form-control form-control-sm" name="result" value="<?php echo h($row['result'] ?? ''); ?>">
                <button class="btn btn-sm btn-outline-primary">修正</button>
              </form>
              <form method="POST" onsubmit="return confirm('确认删除这条误结算？')">
                <input type="hidden" name="action" value="delete_record">
                <input type="hidden" name="activity_id" value="<?php echo h($aid); ?>">
                <input type="hidden" name="user_id" value="<?php echo h($row['user_id'] ?? ''); ?>">
                <input type="hidden" name="record_id" value="<?php echo h($row['record_id'] ?? ''); ?>">
                <button class="btn btn-sm btn-outline-danger w-100">删除误结算</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if(empty($recordRows)): ?><tr><td colspan="6" class="text-center text-muted py-4">暂无活动档案记录</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card ops-card mb-4" id="exports">
  <div class="card-header"><i class="fas fa-download me-2"></i>导出与余波</div>
  <div class="card-body d-flex flex-wrap gap-2">
    <a class="btn btn-outline-success" href="activities.php?action=edit&id=<?php echo urlencode($aid); ?>&export=settlements">导出本期结算记录</a>
    <a class="btn btn-outline-success" href="activities.php?action=edit&id=<?php echo urlencode($aid); ?>&export=markers">导出标记/后续列表</a>
    <span class="text-muted small align-self-center">CSV 可直接给管理写余波总结。</span>
  </div>
</div>

<form method="POST" class="card ops-card" id="json">
  <input type="hidden" name="action" value="save_json">
  <input type="hidden" name="activity_id" value="<?php echo h($aid); ?>">
  <div class="card-header"><i class="fas fa-code me-2"></i>高级 JSON 配置</div>
  <div class="card-body">
    <textarea class="form-control font-monospace" name="activity_json" rows="26"><?php echo h(json_encode($edit, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></textarea>
  </div>
  <div class="card-footer text-end">
    <button class="btn btn-outline-warning"><i class="fas fa-code me-1"></i>保存 JSON</button>
  </div>
</form>

<?php else: ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <p class="text-muted small mb-0">配置文件：<code><?php echo h(ACTIVITIES_JSON); ?></code></p>
  <?php if(can('activities','edit')): ?>
  <a class="btn btn-sm btn-primary" href="activities.php?action=add"><i class="fas fa-plus me-1"></i>新增活动</a>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header"><i class="fas fa-list me-2"></i>活动列表（<?php echo count($activities); ?>）</div>
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead><tr class="table-active">
        <th class="ps-4">活动</th>
        <th>状态</th>
        <th>阶段</th>
        <th>配置量</th>
        <th>记录</th>
        <th class="pe-4" style="width:300px">操作</th>
      </tr></thead>
      <tbody>
      <?php foreach($activities as $aid=>$a): $stats=countStateRows($aid); ?>
      <tr>
        <td class="ps-4">
          <div class="fw-semibold"><?php echo h($a['name'] ?? $aid); ?> <?php if($aid===$current_id): ?><span class="badge bg-primary ms-1">当前</span><?php endif; ?></div>
          <code class="small"><?php echo h($aid); ?></code>
        </td>
        <td><?php echo !empty($a['enabled']) ? '<span class="badge bg-success">开启</span>' : '<span class="badge bg-secondary">关闭</span>'; ?></td>
        <td><span class="badge bg-info"><?php echo h($a['phase'] ?? ''); ?></span></td>
        <td class="small text-muted">
          场域 <?php echo count($a['fields'] ?? []); ?> /
          灯谜 <?php echo count($a['lantern_riddle']['riddles'] ?? []); ?>
        </td>
        <td class="small text-muted">
          灯谜 <?php echo $stats['riddles']; ?> /
          题面 <?php echo $stats['prompts']; ?> /
          报名 <?php echo $stats['servants']; ?> /
          分配 <?php echo $stats['assignments']; ?> /
          结算 <?php echo $stats['records']; ?>
        </td>
        <td class="pe-4">
          <div class="d-flex gap-1 flex-wrap">
            <a class="btn btn-sm btn-outline-primary py-0 px-2" href="activities.php?action=edit&id=<?php echo urlencode($aid); ?>">运营</a>
            <?php if(can('activities','edit')): ?>
            <form method="POST" class="d-inline"><input type="hidden" name="action" value="set_current"><input type="hidden" name="activity_id" value="<?php echo h($aid); ?>"><button class="btn btn-sm btn-outline-info py-0 px-2">设当前</button></form>
            <form method="POST" class="d-inline"><input type="hidden" name="action" value="toggle"><input type="hidden" name="activity_id" value="<?php echo h($aid); ?>"><button class="btn btn-sm btn-outline-warning py-0 px-2"><?php echo !empty($a['enabled'])?'关闭':'开启'; ?></button></form>
            <form method="POST" class="d-inline"><input type="hidden" name="action" value="copy"><input type="hidden" name="activity_id" value="<?php echo h($aid); ?>"><button class="btn btn-sm btn-outline-secondary py-0 px-2">复制</button></form>
            <?php endif; ?>
            <?php if(can('activities','delete')): ?>
            <form method="POST" class="d-inline" onsubmit="return confirm('确认删除该活动？')"><input type="hidden" name="action" value="delete"><input type="hidden" name="activity_id" value="<?php echo h($aid); ?>"><button class="btn btn-sm btn-outline-danger py-0 px-2">删除</button></form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($activities)): ?><tr><td colspan="6" class="text-center py-5 text-muted">暂无活动</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once 'footer.php'; ?>
