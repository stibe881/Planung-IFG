<?php
/**
 * Konfiguration der Sync-API.
 * Diese Datei nach «config.php» kopieren und ausfüllen.
 * WICHTIG: config.php nie ins Git-Repository einchecken und nach dem
 * Einrichten das Datenbank-Passwort beim Hoster neu setzen, falls es je
 * auf unsicherem Weg (Mail, Chat) geteilt wurde.
 */
return [
    // MySQL-Zugangsdaten (vom Hoster)
    'DB_HOST' => 'm7wj.your-database.de',
    'DB_PORT' => 3306,
    'DB_NAME' => 'gespraechsplaner',
    'DB_USER' => 'HIER_BENUTZER',
    'DB_PASS' => 'HIER_PASSWORT',

    // Frei wählbarer, langer Schlüssel. Denselben Wert tragen die
    // Planer*innen im Tool unter «Sync…» ein.
    // Erzeugen z. B. mit: openssl rand -hex 24
    'SYNC_KEY' => '',

    // Von welchen Seiten die App zugreifen darf (CORS).
    'ALLOWED_ORIGINS' => [
        'https://stibe881.github.io',
    ],
];
