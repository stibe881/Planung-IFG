# Gesprächsplaner

Ein Planungswerkzeug für die Schüler\*innen-Gespräche: Es koordiniert die drei Grössen
**Schüler\*innen – Mitarbeiter\*innen – Zeitfenster**, so dass am Schluss jede\*r
Schüler\*in einen Gesprächstermin hat, an dem alle benötigten Mitarbeitenden
teilnehmen können. Das Tool ersetzt die bisherige Excel-Lösung
(R-Matrix «KlientInnen und MA», Verfügbarkeits-Blatt, Konfigurations-Blatt).

## Nutzung

Die ganze Anwendung steckt in einer einzigen Datei: **`index.html`**.
Einfach im Browser öffnen (Doppelklick) – keine Installation, kein Server nötig.
Alternativ kann die Datei über GitHub Pages bereitgestellt werden
(Repository-Einstellungen → Pages → Branch auswählen), dann ist das Tool
für alle im Team per Link erreichbar.

### Arbeitsschritte

1. **Stammdaten** – Schüler\*innen, Mitarbeiter\*innen und Zeitfenster erfassen.
   Zeitfenster einzeln oder als Serie (z.&nbsp;B. 4 Fenster à 55 Min. ab 13:00).
2. **Zuordnung (R)** – wie im bisherigen Excel: pro Schüler\*in (Spalte) anklicken,
   welche Mitarbeiter\*innen (Zeilen) am Gespräch teilnehmen müssen.
3. **Verfügbarkeit (A)** – standardmässig sind alle überall verfügbar; Zellen
   anklicken, wenn jemand in einem Zeitfenster nicht kann. Die Spalte
   «Gespräche / verfügbar» warnt, wenn die Verfügbarkeit rechnerisch nicht reicht.
4. **Planung** – «Termine planen» sucht per Backtracking eine Belegung, bei der
   in keinem Zeitfenster eine Mitarbeiter\*in doppelt gebucht ist. Bereits
   vereinbarte Termine lassen sich fixieren und bleiben bei jeder Neuberechnung
   bestehen. Hinweise erklären, warum ein Gespräch ggf. nicht platzierbar ist
   (z.&nbsp;B. eine Person ist mehr Gesprächen zugeordnet, als Zeitfenster existieren).

### Daten & Austausch

- Alle Eingaben werden automatisch **im Browser gespeichert** (localStorage).
- **Daten sichern / laden**: Export und Import als JSON-Datei – so lässt sich der
  Stand teilen oder auf einen anderen Rechner mitnehmen.
- **Plan als CSV exportieren**: öffnet sich direkt in Excel (Semikolon-getrennt).
- **Drucken**: druckt die Planungsansicht als übersichtlichen Gesprächsplan.

Beim ersten Start ist das Tool mit Beispieldaten vorausgefüllt (frei erfundene
Namen; Zeitfenster und Zuordnungsstruktur wie im bisherigen Excel). Echte Namen
werden lokal im Browser erfasst und nie übertragen. Mit «Leer starten» beginnt
man mit einer leeren Planung.

## Version 2.0: Mehrere Angebote, Rollen und Microsoft-SSO

- **Mehrere Angebote/Abteilungen**: Über die Leiste unter dem Kopfbereich lassen
  sich beliebig viele getrennte Planungen führen (z. B. «IFG Sehen»,
  «Logopädie»). Bestehende Daten werden automatisch als Angebot «IFG Sehen»
  übernommen. «Daten sichern/laden» sichert immer alle Angebote zusammen.
- **Verantwortliche Planer*in**: Pro Angebot werden unter «Einstellungen» Name
  und Microsoft-E-Mail der verantwortlichen Person hinterlegt.
- **Microsoft-SSO (Entra ID)**: In `index.html` ganz oben im Skript stehen
  `MSAL_CONFIG.clientId` und `MSAL_CONFIG.tenantId`. Trägt die IT dort die
  Werte einer App-Registrierung ein (Azure-Portal → App-Registrierungen →
  Neue Registrierung → Typ «Single-Page-Anwendung (SPA)», Redirect-URI =
  Adresse der veröffentlichten Seite), erscheint «Mit Microsoft anmelden».
  Dann kann ein Angebot nur noch von der angemeldeten verantwortlichen
  Planer*in bearbeitet werden; alle anderen sehen es schreibgeschützt.
  Solange die Felder leer sind, läuft das Tool im offenen Modus.
  **Hinweis**: Die Daten liegen weiterhin lokal im Browser der jeweiligen
  Person – der Schreibschutz verhindert versehentliches Bearbeiten, ersetzt
  aber keine serverseitige Zugriffskontrolle.

## Weitere Funktionen (2.0)

- **Gesprächslängen**: Pro Schüler*in 1–3 aufeinanderfolgende Zeitfenster am
  selben Tag (Auswahl in den Stammdaten); der Planer bucht sie am Stück.
- **Status & Notizen**: Pro Gespräch «offen / Eltern informiert / bestätigt /
  durchgeführt» plus Notiz – auch in CSV-/Excel-Export enthalten.
- **Lösungsvorschläge**: Für ungeplante Gespräche rechnet das Tool konkrete
  Auswege aus (fehlende Verfügbarkeit einer einzelnen Person, zusätzliches
  Zeitfenster an einem bestimmten Tag).
- **Outlook-Export (.ics)**: Alle Gespräche oder der persönliche Kalender
  einer Mitarbeiter*in, direkt in Outlook importierbar.
- **Persönlicher Plan**: Pro Mitarbeiter*in eine eigene Ansicht mit
  Druckfunktion.
- **Excel**: Export als Arbeitsmappe (Plan, R-Matrix, Verfügbarkeiten) und
  Import einer Zuordnungsmatrix (erste Spalte Mitarbeiter*innen, erste Zeile
  Schüler*innen, «R» in den Zellen) – auch aus dem alten Excel.
- **Rückgängig**: Die letzten 30 Änderungen lassen sich zurücknehmen.
- **Barrierefreiheit**: Pfeiltasten-Navigation in den Matrizen,
  Schriftgrössen-Umschalter (A−/A+), Sprunglink, beschriftete Bedienelemente.

## Version 2.1: Zentrale Datenbank (Sync) und Team-Funktionen

### Neue Funktionen

- **Übergreifende Konfliktprüfung**: Ist dieselbe Mitarbeiter*in (Abgleich über
  den Namen) in zwei Angeboten zeitlich überschneidend verplant, erscheint in
  der Planung ein roter Hinweis.
- **Archiv & Kopie**: Angebote lassen sich archivieren (nur noch Ansicht,
  reaktivierbar) und als Kopie für ein neues Semester anlegen (Personen,
  Zuordnungen und Gesprächslängen übernommen; Termine, Status, Plan leer).
- **Fortschritt & Filter**: Balken mit der Status-Verteilung (durchgeführt/
  bestätigt/informiert/offen) und Tabellen-Filter «nur ohne Termin» /
  «nur Organisation offen».
- **Sperrzeiten**: Abwesenheiten über einen Datumsbereich in einem Schritt
  erfassen (für Mitarbeiter*innen oder Gespräche), statt Zellen einzeln zu
  klicken.
- **Änderungsprotokoll**: Pro Angebot die letzten 200 Änderungen mit
  Zeitstempel und (bei aktivem SSO) Namen – Knopf «Protokoll».

### Zentrale Datenbank einrichten (MySQL + PHP-API)

Damit alle Planer*innen denselben Stand sehen, synchronisiert das Tool über
eine kleine PHP-API auf eurem Webspace (der Browser kann nicht direkt mit
MySQL sprechen, und Zugangsdaten dürfen nie in die App):

1. Ordner `server/` auf den Webspace hochladen (z. B. nach
   `/gespraechsplaner/`).
2. Dort `config.example.php` nach `config.php` kopieren und ausfüllen:
   DB-Zugangsdaten, ein selbst gewählter langer `SYNC_KEY`
   (z. B. `openssl rand -hex 24`) und unter `ALLOWED_ORIGINS` die Adresse,
   unter der die App läuft.
3. Die Tabelle legt die API beim ersten Aufruf automatisch an.
4. Im Tool auf **«Sync…»** klicken, API-Adresse
   (`https://…/gespraechsplaner/api.php`) und den `SYNC_KEY` eintragen.

Danach synchronisiert das Tool automatisch (ca. 3 Sekunden nach jeder
Änderung sowie beim Öffnen). Konflikte werden erkannt (Versionszähler pro
Angebot) und zur Entscheidung vorgelegt. Ohne Sync-Konfiguration arbeitet
das Tool wie bisher rein lokal.

**Sicherheit**: `config.php` niemals ins Repository einchecken (steht in
`.gitignore`). Datenbank-Passwörter, die je per Mail/Chat geteilt wurden,
beim Hoster neu setzen. Der `SYNC_KEY` schützt die API vor Fremdzugriff;
alle Personen mit Schlüssel können lesen und schreiben.

## Version 2.4: Login-Pflicht und Azure-Gruppen

Bei aktivem SSO zeigt die App vor der Microsoft-Anmeldung nichts mehr an
(Anmelde-Bildschirm); auch die Daten-Synchronisation startet erst nach dem
Login. Zusätzlich steuern Entra-ID-Gruppen den Zugriff pro Angebot:

- **Einrichtung durch die IT (einmalig)**: In der App-Registrierung unter
  **Token configuration → «Add groups claim» → Security groups** aktivieren.
  Dadurch stehen die Gruppenzugehörigkeiten im Anmelde-Token.
- **Pro Angebot**: Unter «Einstellungen» die **Objekt-ID** der zuständigen
  Entra-ID-Gruppe hinterlegen (zu finden im Entra-Portal unter Gruppen →
  Übersicht). Dann gilt: Nur Gruppenmitglieder sehen und bearbeiten das
  Angebot; die hinterlegte verantwortliche Planer*in hat immer Zugriff.
  Ohne Gruppen-ID ist das Angebot für alle Angemeldeten sichtbar.
- Wer angemeldet ist, aber keinem sichtbaren Angebot angehört, sieht einen
  Hinweis-Bildschirm («keinem Angebot zugeteilt»).

Hinweis: Bei sehr vielen Gruppenmitgliedschaften (>200) liefert Microsoft
die Gruppen nicht mehr im Token («groups overage») – dann für die App-Rolle
eine dedizierte kleine Gruppe verwenden.

## CI/CD

Bei jedem Push läuft die GitHub-Actions-Pipeline (`.github/workflows/ci-cd.yml`):

1. **Testen** – `node tests/smoke.js` prüft die JavaScript-Syntax der App und
   lässt den Planungsalgorithmus über die Beispieldaten laufen (keine
   Doppelbuchungen, fixierte Termine bleiben bestehen).
2. **Veröffentlichen** – bei erfolgreichem Test wird `index.html` automatisch
   auf **GitHub Pages** deployt. Das Tool ist danach unter
   `https://stibe881.github.io/Planung-IFG/` erreichbar – dieser Link kann
   direkt ans Team weitergegeben werden.

Voraussetzung (einmalig): Falls das erste Deployment fehlschlägt, in den
Repository-Einstellungen unter **Settings → Pages** als Source
«GitHub Actions» wählen. Pull Requests durchlaufen nur den Test-Schritt.
