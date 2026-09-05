<?php
/**
 * Sync-API des Gesprächsplaners.
 *
 * Installation:
 *  1. Ordner «server» auf den Webspace hochladen (z. B. /gespraechsplaner/).
 *  2. config.example.php nach config.php kopieren und ausfüllen
 *     (DB-Zugangsdaten, SYNC_KEY, ALLOWED_ORIGIN).
 *  3. Im Tool unter «Sync…» die Adresse dieser Datei und den SYNC_KEY eintragen.
 *
 * Die Tabelle wird beim ersten Aufruf automatisch angelegt.
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'config.php fehlt – bitte config.example.php kopieren und ausfüllen.']);
    exit;
}
$config = require $configFile;

// CORS: Aufrufe von der veröffentlichten App-Adresse erlauben
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = $config['ALLOWED_ORIGINS'] ?? [];
if ($origin !== '' && (in_array($origin, $allowed, true) || in_array('*', $allowed, true))) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit; // Preflight
}

// App-Konfiguration (Microsoft-SSO) ist ohne Schlüssel abrufbar: Client-ID
// und Tenant-ID sind bei Single-Page-Apps designbedingt öffentliche Werte.
// So sehen auch frische Browser (ohne Sync-Einrichtung) den Login-Button.
if (($_GET['action'] ?? '') === 'config') {
    echo json_encode(['msal' => [
        'clientId' => (string)($config['MSAL_CLIENT_ID'] ?? ''),
        'tenantId' => (string)($config['MSAL_TENANT_ID'] ?? ''),
    ]]);
    exit;
}

$action = $_GET['action'] ?? '';

// Die Eltern-Terminwahl ist öffentlich erreichbar; sie authentifiziert sich
// über das zufällige Link-Token statt über den Sync-Schlüssel.
$publicActions = ['booking_get', 'booking_choose'];
if (!in_array($action, $publicActions, true)) {
    // Authentifizierung über den gemeinsamen Schlüssel
    $key = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!hash_equals((string)($config['SYNC_KEY'] ?? ''), $key) || $config['SYNC_KEY'] === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Ungültiger oder fehlender Sync-Schlüssel.']);
        exit;
    }
}

mysqli_report(MYSQLI_REPORT_OFF); // Fehler über connect_errno statt Exceptions behandeln
$db = @new mysqli(
    $config['DB_HOST'], $config['DB_USER'], $config['DB_PASS'], $config['DB_NAME'],
    (int)($config['DB_PORT'] ?? 3306)
);
if ($db->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'Datenbankverbindung fehlgeschlagen.']);
    exit;
}
$db->set_charset('utf8mb4');

$db->query(
    'CREATE TABLE IF NOT EXISTS projekte (
        id         VARCHAR(64)  NOT NULL PRIMARY KEY,
        name       VARCHAR(255) NOT NULL DEFAULT "",
        version    INT UNSIGNED NOT NULL DEFAULT 1,
        json       MEDIUMTEXT   NOT NULL,
        updated_by VARCHAR(255) NOT NULL DEFAULT "",
        updated_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

/* ---------- Eltern-Terminwahl ---------- */

// Ein Gespräch belegt 1–3 aufeinanderfolgende Zeitfenster am selben Tag
function seqForPhp(array $data, string $sid, string $startId): ?array {
    $slots = $data['slots'] ?? [];
    $idx = -1;
    foreach ($slots as $i => $sl) {
        if (($sl['id'] ?? '') === $startId) { $idx = $i; break; }
    }
    if ($idx < 0) return null;
    $len = max(1, min(3, (int)($data['lens'][$sid] ?? 1)));
    $seq = [$slots[$idx]['id']];
    for ($k = 1; $k < $len; $k++) {
        if (!isset($slots[$idx + $k]) || ($slots[$idx + $k]['date'] ?? '') !== ($slots[$idx]['date'] ?? null)) return null;
        $seq[] = $slots[$idx + $k]['id'];
    }
    return $seq;
}

// Kollidiert der gewünschte Termin mit einem anderen (fixierten/geplanten)
// Gespräch, das dieselben Mitarbeiter*innen braucht?
function bookingConflict(array $data, string $sid, string $startId): ?string {
    $seq = seqForPhp($data, $sid, $startId);
    if ($seq === null) return 'Dieses Zeitfenster existiert nicht mehr.';
    $need = array_keys($data['req'][$sid] ?? []);
    foreach (($data['students'] ?? []) as $st) {
        $oid = $st['id'] ?? '';
        if ($oid === '' || $oid === $sid) continue;
        $oStart = $data['fixed'][$oid] ?? ($data['plan']['assignments'][$oid] ?? null);
        if (!$oStart) continue;
        $oSeq = seqForPhp($data, $oid, (string)$oStart) ?? [(string)$oStart];
        if (!array_intersect($seq, $oSeq)) continue;
        if (array_intersect($need, array_keys($data['req'][$oid] ?? []))) return 'belegt';
    }
    return null;
}

// Projekt-Zeile suchen, deren bookings-Ablage das Token enthält
function bookingFind(mysqli $db, string $token): ?array {
    if (!preg_match('/^[0-9a-f]{16,64}$/', $token)) return null;
    $res = $db->query('SELECT id, name, version, json FROM projekte');
    while ($r = $res->fetch_assoc()) {
        $obj = json_decode($r['json'], true);
        if (!is_array($obj)) continue;
        foreach (($obj['data']['bookings'] ?? []) as $sid => $b) {
            if (is_array($b) && hash_equals((string)($b['token'] ?? ''), $token)) {
                return ['row' => $r, 'obj' => $obj, 'sid' => (string)$sid, 'booking' => $b];
            }
        }
    }
    return null;
}

function bookingFreeOffers(array $data, string $sid, array $booking, string $skipStartId = ''): array {
    $free = [];
    foreach (($booking['offers'] ?? []) as $o) {
        $id = (string)($o['startId'] ?? '');
        if ($id === '' || $id === $skipStartId) continue;
        if (bookingConflict($data, $sid, $id) === null) $free[] = ['startId' => $id, 'label' => (string)($o['label'] ?? '')];
    }
    return $free;
}

if ($action === 'booking_get') {
    $hit = bookingFind($db, (string)($_GET['token'] ?? ''));
    if ($hit === null) {
        echo json_encode(['error' => 'Dieser Link ist nicht oder nicht mehr gültig. Bitte wenden Sie sich an die Schule.']);
        exit;
    }
    $b = $hit['booking'];
    $chosenLabel = null;
    if (!empty($b['chosen'])) {
        foreach (($b['offers'] ?? []) as $o) {
            if (($o['startId'] ?? '') === $b['chosen']) { $chosenLabel = (string)($o['label'] ?? ''); break; }
        }
        $chosenLabel = $chosenLabel ?: 'vereinbart – Details kennt die Schule';
    }
    echo json_encode([
        'student' => (string)($b['student'] ?? ''),
        'project' => (string)($hit['obj']['name'] ?? $hit['row']['name']),
        'offers'  => $chosenLabel === null ? bookingFreeOffers($hit['obj']['data'] ?? [], $hit['sid'], $b) : [],
        'chosen'  => $chosenLabel,
    ]);
    exit;
}

if ($action === 'booking_choose') {
    $body = json_decode(file_get_contents('php://input'), true);
    $token = is_array($body) ? (string)($body['token'] ?? '') : '';
    $startId = is_array($body) ? (string)($body['startId'] ?? '') : '';
    $hit = bookingFind($db, $token);
    if ($hit === null || $startId === '') {
        echo json_encode(['error' => 'Dieser Link ist nicht oder nicht mehr gültig. Bitte wenden Sie sich an die Schule.']);
        exit;
    }
    $obj = $hit['obj'];
    $sid = $hit['sid'];
    $b = $hit['booking'];
    $data = $obj['data'] ?? [];
    if (!empty($b['chosen'])) {
        $label = 'vereinbart';
        foreach (($b['offers'] ?? []) as $o) if (($o['startId'] ?? '') === $b['chosen']) $label = (string)($o['label'] ?? $label);
        echo json_encode(['ok' => true, 'label' => $label]);
        exit;
    }
    $offer = null;
    foreach (($b['offers'] ?? []) as $o) if (($o['startId'] ?? '') === $startId) { $offer = $o; break; }
    if ($offer === null) {
        echo json_encode(['error' => 'Dieser Termin steht nicht zur Auswahl.', 'offers' => bookingFreeOffers($data, $sid, $b)]);
        exit;
    }
    if (bookingConflict($data, $sid, $startId) !== null) {
        echo json_encode([
            'error'  => 'Dieser Termin wurde inzwischen anderweitig vergeben – bitte wählen Sie einen anderen.',
            'offers' => bookingFreeOffers($data, $sid, $b, $startId),
        ]);
        exit;
    }
    $obj['data']['fixed'][$sid] = $startId;
    if (isset($obj['data']['plan']['assignments'])) $obj['data']['plan']['assignments'][$sid] = $startId;
    $obj['data']['status'][$sid] = 'bestaetigt';
    $obj['data']['bookings'][$sid]['chosen'] = $startId;
    if (!isset($obj['data']['log']) || !is_array($obj['data']['log'])) $obj['data']['log'] = [];
    array_unshift($obj['data']['log'], [
        'ts'   => (int)round(microtime(true) * 1000),
        'user' => 'Eltern (Terminwahl-Link)',
        'txt'  => 'Termin für «' . ($b['student'] ?? '?') . '» reserviert: ' . ($offer['label'] ?? ''),
    ]);
    $newJson = json_encode($obj, JSON_UNESCAPED_UNICODE);
    $oldV = (int)$hit['row']['version'];
    $newV = $oldV + 1;
    $stmt = $db->prepare('UPDATE projekte SET version = ?, json = ?, updated_by = ? WHERE id = ? AND version = ?');
    $by = 'Eltern (Terminwahl)';
    $stmt->bind_param('isssi', $newV, $newJson, $by, $hit['row']['id'], $oldV);
    $stmt->execute();
    if ($stmt->affected_rows < 1) {
        // Paralleler Schreibzugriff – die Eltern versuchen es einfach erneut
        echo json_encode(['error' => 'Bitte versuchen Sie es gleich noch einmal.', 'offers' => bookingFreeOffers($data, $sid, $b)]);
        exit;
    }
    echo json_encode(['ok' => true, 'label' => (string)($offer['label'] ?? '')]);
    exit;
}

/* ---------- Präsenz: Wer arbeitet gerade an welchem Angebot? ---------- */
if ($action === 'presence') {
    $db->query(
        'CREATE TABLE IF NOT EXISTS praesenz (
            project_id VARCHAR(64)  NOT NULL,
            user       VARCHAR(191) NOT NULL,
            seen_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (project_id, user)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $body = json_decode(file_get_contents('php://input'), true);
    $pid  = is_array($body) ? substr((string)($body['project'] ?? ''), 0, 64) : '';
    $user = is_array($body) ? substr((string)($body['user'] ?? ''), 0, 191) : '';
    if ($pid !== '' && $user !== '') {
        $stmt = $db->prepare('REPLACE INTO praesenz (project_id, user) VALUES (?, ?)');
        $stmt->bind_param('ss', $pid, $user);
        $stmt->execute();
    }
    $db->query('DELETE FROM praesenz WHERE seen_at < NOW() - INTERVAL 90 SECOND');
    $others = [];
    $stmt = $db->prepare('SELECT user FROM praesenz WHERE project_id = ? AND user <> ?');
    $stmt->bind_param('ss', $pid, $user);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $others[] = $r['user'];
    echo json_encode(['others' => $others]);
    exit;
}

if ($action === 'pull') {
    $rows = [];
    $res = $db->query('SELECT id, name, version, json, updated_by, updated_at FROM projekte');
    while ($r = $res->fetch_assoc()) {
        $r['version'] = (int)$r['version'];
        $rows[] = $r;
    }
    echo json_encode(['projects' => $rows]);
    exit;
}

if ($action === 'push') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body) || !isset($body['id'], $body['json'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Anfrage.']);
        exit;
    }
    $id   = substr((string)$body['id'], 0, 64);
    $name = substr((string)($body['name'] ?? ''), 0, 255);
    $base = (int)($body['baseVersion'] ?? 0);
    $by   = substr((string)($body['updated_by'] ?? ''), 0, 255);
    $json = (string)$body['json'];
    if (strlen($json) > 8 * 1024 * 1024) {
        http_response_code(413);
        echo json_encode(['error' => 'Datensatz zu gross.']);
        exit;
    }

    $stmt = $db->prepare('SELECT version FROM projekte WHERE id = ? FOR UPDATE');
    $db->begin_transaction();
    $stmt->bind_param('s', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row === null) {
        $v = $base > 0 ? $base + 1 : 1;
        $ins = $db->prepare('INSERT INTO projekte (id, name, version, json, updated_by) VALUES (?, ?, ?, ?, ?)');
        $ins->bind_param('ssiss', $id, $name, $v, $json, $by);
        $ins->execute();
        $db->commit();
        echo json_encode(['version' => $v]);
        exit;
    }

    $serverVersion = (int)$row['version'];
    if ($serverVersion > $base) {
        $db->rollback();
        echo json_encode(['conflict' => true, 'version' => $serverVersion]);
        exit;
    }
    $v = $serverVersion + 1;
    $upd = $db->prepare('UPDATE projekte SET name = ?, version = ?, json = ?, updated_by = ? WHERE id = ?');
    $upd->bind_param('sisss', $name, $v, $json, $by, $id);
    $upd->execute();
    $db->commit();
    echo json_encode(['version' => $v]);
    exit;
}

if ($action === 'delete') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body) || !isset($body['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Anfrage.']);
        exit;
    }
    $id = substr((string)$body['id'], 0, 64);
    $stmt = $db->prepare('DELETE FROM projekte WHERE id = ?');
    $stmt->bind_param('s', $id);
    $stmt->execute();
    echo json_encode(['deleted' => $stmt->affected_rows > 0]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unbekannte Aktion. Erlaubt: pull, push, delete, presence, booking_get, booking_choose, config.']);
