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

// Authentifizierung über den gemeinsamen Schlüssel
$key = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (!hash_equals((string)($config['SYNC_KEY'] ?? ''), $key) || $config['SYNC_KEY'] === '') {
    http_response_code(401);
    echo json_encode(['error' => 'Ungültiger oder fehlender Sync-Schlüssel.']);
    exit;
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

$action = $_GET['action'] ?? '';

// App-Konfiguration (z. B. Microsoft-SSO) zentral aus der config.php ausliefern,
// damit Updates der index.html keine Einstellungen überschreiben.
if ($action === 'config') {
    echo json_encode(['msal' => [
        'clientId' => (string)($config['MSAL_CLIENT_ID'] ?? ''),
        'tenantId' => (string)($config['MSAL_TENANT_ID'] ?? ''),
    ]]);
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
echo json_encode(['error' => 'Unbekannte Aktion. Erlaubt: pull, push, delete.']);
