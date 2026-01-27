# RSS to Email System 📰

Ein komplettes System zum Verwalten und Überwachen von RSS Feeds mit automatischen Email-Benachrichtigungen.

## Features

✨ **Schöne HTML-Emails** - Professionell formatierte Benachrichtigungen
📋 **Web-Interface** - Einfache Verwaltung deiner Feeds
🔐 **Passwort-Schutz** - Sicherer Admin-Bereich
📊 **JSON Storage** - Alle Daten in einfachen JSON-Files
⚡ **Test-Funktion** - Sofort prüfen ob alles funktioniert
🎯 **Multi-Feed Support** - Beliebig viele Feeds gleichzeitig überwachen

## Installation

### 1. Files hochladen

Lade alle Dateien in ein Verzeichnis auf deinem Server hoch, z.B.:
```
/home/USERNAME/public_html/rss/
```

### 2. Permissions setzen

```bash
chmod 644 config.php
chmod 644 parser.php
chmod 644 admin.php
chmod 666 feeds.json
chmod 666 last_check.json
```

Falls die JSON-Files noch nicht existieren, werden sie automatisch erstellt.

### 3. Passwort ändern

Öffne `config.php` und ändere diese Zeile:

```php
define('ADMIN_PASSWORD', 'dein_sicheres_passwort_hier');
```

**WICHTIG:** Wähle ein sicheres Passwort!

### 4. Email-Adresse konfigurieren

In `config.php` findest du auch:

```php
define('EMAIL_TO', 'markus@disinfoconsulting.eu');
define('EMAIL_FROM', 'alerts@rss.markusschwinghammer.com');
```

Passe diese an deine Bedürfnisse an.

### 5. Cronjob einrichten

**Via cPanel:**
1. Gehe zu "Cron Jobs"
2. Füge einen neuen Cronjob hinzu:

**Alle 15 Minuten:**
```
*/15 * * * * /usr/bin/php /home/USERNAME/public_html/rss/parser.php
```

**Jede Stunde:**
```
0 * * * * /usr/bin/php /home/USERNAME/public_html/rss/parser.php
```

**Oder mit curl:**
```
*/15 * * * * curl -s https://rss.markusschwinghammer.com/parser.php
```

## Verwendung

### Admin-Interface

1. Öffne im Browser: `https://rss.markusschwinghammer.com/admin.php`
2. Login mit deinem Passwort
3. Füge RSS Feeds hinzu mit Name und URL

### Feed URLs finden

**Google Alerts:**
1. Gehe zu https://www.google.com/alerts
2. Erstelle einen Alert
3. Klicke auf das Zahnrad-Symbol → "RSS Feed"
4. Kopiere die URL (Format: `https://www.google.com/alerts/feeds/...`)

**Andere RSS Feeds:**
- Meistens am Ende der URL: `/feed` oder `/rss`
- Oder im HTML-Code: `<link rel="alternate" type="application/rss+xml"`
- Tools wie https://rss.app/ können Feeds von Websites extrahieren

### Feeds verwalten

- **Pausieren:** Feed bleibt gespeichert, wird aber nicht gecheckt
- **Aktivieren:** Feed wird wieder überwacht
- **Löschen:** Feed wird komplett entfernt
- **Test Run:** Sofort alle Feeds checken (ohne auf Cronjob zu warten)

## Email-Format

Deine Emails enthalten:

- 📰 **Header** mit Zeitstempel
- 📋 **Feed-Sections** gruppiert nach Feed-Name
- 📄 **Artikel** mit:
  - Titel (klickbar)
  - Veröffentlichungsdatum
  - Content/Beschreibung
- ✉️ **Text-Version** als Fallback

## Troubleshooting

### Keine Emails erhalten?

1. **Test-Run im Admin-Interface** - Siehst du neue Artikel?
2. **Spam-Ordner** checken
3. **PHP mail() Funktion** testen:
   ```php
   mail('deine@email.com', 'Test', 'Test-Mail');
   ```
4. **Cronjob** läuft? In cPanel unter "Cron Jobs" → "Current Cron Jobs"

### Feeds werden nicht erkannt?

- Prüfe ob die URL wirklich ein RSS/Atom Feed ist (im Browser öffnen)
- Manche Feeds brauchen User-Agent Header
- Check die Error-Logs in cPanel

### Permission Errors?

```bash
chmod 777 feeds.json
chmod 777 last_check.json
```

## Datei-Struktur

```
rss/
├── config.php          # Konfiguration & FeedManager Class
├── parser.php          # RSS Parser & Email Sender
├── admin.php           # Web-Interface
├── feeds.json          # Deine Feed-Liste (auto-generiert)
├── last_check.json     # Timestamps (auto-generiert)
└── README.md           # Diese Datei
```

## Advanced: Mehrere Email-Adressen

In `parser.php` kannst du die `sendEmail()` Funktion anpassen:

```php
$recipients = [
    'markus@disinfoconsulting.eu',
    'team@disinfoconsulting.eu'
];

foreach ($recipients as $recipient) {
    mail($recipient, $subject, $body, $headers);
}
```

## Advanced: Feed-spezifische Einstellungen

Du kannst in `feeds.json` zusätzliche Parameter hinzufügen:

```json
{
  "abc123": {
    "name": "Important Feed",
    "url": "https://...",
    "priority": "high",
    "notify_immediately": true
  }
}
```

Dann in `parser.php` entsprechend anpassen.

## Support

Bei Fragen oder Problemen:
- Check die Error-Logs: cPanel → "Errors"
- Test die einzelnen Komponenten separat
- Prüfe ob PHP mail() am Server funktioniert

## Nächste Schritte

Mögliche Erweiterungen:
- [ ] Slack/Discord Notifications
- [ ] Email-Digest (nur 1x täglich alle Artikel)
- [ ] Filter/Keywords pro Feed
- [ ] Webhook Support
- [ ] Database statt JSON (bei vielen Feeds)

---

**Viel Erfolg mit deinem RSS Monitoring System! 🚀**