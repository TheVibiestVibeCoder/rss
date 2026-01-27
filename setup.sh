#!/bin/bash

# Quick Setup Script für RSS to Email System
# Führe dieses Script im Upload-Verzeichnis aus

echo "🚀 RSS to Email System - Quick Setup"
echo "======================================"
echo ""

# Erstelle leere JSON Files falls nicht vorhanden
if [ ! -f feeds.json ]; then
    echo "[]" > feeds.json
    echo "✓ feeds.json erstellt"
fi

if [ ! -f last_check.json ]; then
    echo "{}" > last_check.json
    echo "✓ last_check.json erstellt"
fi

# Setze Permissions
chmod 644 config.php
chmod 644 parser.php
chmod 644 admin.php
chmod 644 README.md
chmod 666 feeds.json
chmod 666 last_check.json

echo "✓ Permissions gesetzt"
echo ""
echo "📝 Nächste Schritte:"
echo "===================="
echo ""
echo "1. Öffne config.php und ändere:"
echo "   - ADMIN_PASSWORD"
echo "   - EMAIL_TO (falls nötig)"
echo "   - EMAIL_FROM (falls nötig)"
echo ""
echo "2. Richte einen Cronjob ein:"
echo "   */15 * * * * /usr/bin/php $(pwd)/parser.php"
echo ""
echo "3. Öffne admin.php im Browser und füge Feeds hinzu"
echo ""
echo "✅ Setup abgeschlossen!"