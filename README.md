# Gesprächsplaner IFG

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
