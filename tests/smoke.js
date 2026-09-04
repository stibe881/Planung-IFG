// Smoke-Test für den Gesprächsplaner: prüft die JS-Syntax der App und
// lässt den Planungsalgorithmus einmal über die Beispieldaten laufen.
// Aufruf: node tests/smoke.js (keine Abhängigkeiten nötig)
"use strict";
const fs = require("fs");
const path = require("path");

const html = fs.readFileSync(path.join(__dirname, "..", "index.html"), "utf8");
const m = html.match(/<script>([\s\S]*?)<\/script>/);
if (!m) { console.error("FEHLER: kein <script>-Block in index.html gefunden"); process.exit(1); }
const src = m[1];

// 1) Syntax der gesamten App prüfen (parst, ohne auszuführen)
new Function(src);
console.log("OK: JavaScript-Syntax gültig");

// 2) Planungsteil mit den Beispieldaten ausführen
const cut = src.indexOf("/* ---------- Rendering ---------- */");
if (cut < 0) { console.error("FEHLER: Rendering-Marker nicht gefunden"); process.exit(1); }
global.localStorage = { getItem: () => null, setItem: () => {} };
global.document = { querySelector: () => null };
global.window = { claude: undefined };

const test = `
solvePlan();
const A = S.plan.assignments;
const placed = S.students.filter(st => A[st.id]).length;
console.log("Geplant:", placed, "/", S.students.length, "Gespräche");
// Mit den Beispieldaten sind maximal 9 von 12 möglich (Kapazitätsgrenze)
if (placed < 9) throw new Error("Planer platziert zu wenige Gespräche: " + placed);
const busy = {};
for (const st of S.students) {
  const sl = A[st.id];
  if (!sl) continue;
  for (const mid of reqStaffIds(st.id)) {
    busy[sl] = busy[sl] || new Set();
    if (busy[sl].has(mid)) throw new Error("Doppelbuchung einer Mitarbeiter*in in einem Zeitfenster");
    busy[sl].add(mid);
  }
}
console.log("OK: keine Doppelbuchungen von Mitarbeiter*innen");
// Fixierung: ungeplante Person auf das erste Fenster setzen und neu planen
const un = S.students.find(st => !A[st.id]);
if (un) {
  S.fixed[un.id] = S.slots[0].id;
  solvePlan();
  if (S.plan.assignments[un.id] !== S.slots[0].id) throw new Error("Fixierter Termin wurde nicht übernommen");
  console.log("OK: fixierte Termine bleiben bei Neuberechnung bestehen");
  delete S.fixed[un.id];
}
// Gesprächs-Verfügbarkeit: ein Gespräch in allen Fenstern sperren -> darf nicht geplant werden
const blocked = S.students[0];
S.stuUnavail[blocked.id] = {};
for (const sl of S.slots) S.stuUnavail[blocked.id][sl.id] = 1;
solvePlan();
if (S.plan.assignments[blocked.id]) throw new Error("Gesperrtes Gespräch wurde trotzdem geplant");
console.log("OK: Gesprächs-Verfügbarkeit wird berücksichtigt");
delete S.stuUnavail[blocked.id];
// Doppel-Fenster: Gespräch über 2 aufeinanderfolgende Zeitfenster am selben Tag
const dbl = S.students.find(st => st.name === "Imhof Kim");
S.lens[dbl.id] = 2;
solvePlan();
const dblStart = S.plan.assignments[dbl.id];
if (!dblStart) throw new Error("Doppel-Fenster-Gespräch wurde nicht geplant");
const dblSeq = assignedSeq(dbl.id, dblStart);
if (!dblSeq || dblSeq.length !== 2) throw new Error("Sequenz hat nicht 2 Fenster");
const d0 = slotById(dblSeq[0]), d1 = slotById(dblSeq[1]);
if (d0.date !== d1.date) throw new Error("Doppel-Fenster liegt nicht am selben Tag");
console.log("OK: Doppel-Fenster-Gespräche werden am Stück geplant");
delete S.lens[dbl.id];
`;
eval(src.slice(0, cut) + test);
console.log("Alle Prüfungen bestanden.");
