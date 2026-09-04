#!/bin/sh
# Aktualisiert den Gesprächsplaner auf diesem Webspace auf die neueste Version.
# Aufruf im App-Ordner:  ./update.sh
# Die config.php wird dabei nie angetastet.
set -e
cd "$(dirname "$0")"
BRANCH="claude/schueler-gespraeche-planung-8s76z9"

curl -fsSL -o index.html "https://stibe881.github.io/Planung-IFG/index.html"
curl -fsSL -o api.php "https://raw.githubusercontent.com/stibe881/Planung-IFG/$BRANCH/server/api.php"
curl -fsSL -o update.sh.neu "https://raw.githubusercontent.com/stibe881/Planung-IFG/$BRANCH/server/update.sh" \
  && mv update.sh.neu update.sh && chmod +x update.sh

echo "Fertig. Installierter Stand:"
grep -o "Version [0-9.]* vom [0-9.]*" index.html | head -1
