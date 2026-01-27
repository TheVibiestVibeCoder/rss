#!/bin/bash
#
# RSS Feed Manager - Setup Script
#
# Run this script after uploading files to your server.
#

echo "=========================================="
echo "  RSS Feed Manager - Setup"
echo "=========================================="
echo ""

# Get the directory where this script is located
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$DIR"

# Create data directory
echo "Creating data directory..."
mkdir -p data

# Initialize JSON files
if [ ! -f data/feeds.json ]; then
    echo "[]" > data/feeds.json
    echo "  Created data/feeds.json"
fi

if [ ! -f data/last_check.json ]; then
    echo "{}" > data/last_check.json
    echo "  Created data/last_check.json"
fi

# Set permissions
echo ""
echo "Setting permissions..."
chmod 644 config.php
chmod 644 api.php
chmod 644 index.html
chmod 644 cron.php
chmod 644 classes/*.php
chmod 666 data/feeds.json
chmod 666 data/last_check.json
echo "  Done."

echo ""
echo "=========================================="
echo "  Setup Complete!"
echo "=========================================="
echo ""
echo "Next steps:"
echo ""
echo "1. Edit config.php and change:"
echo "   - admin_password (required!)"
echo "   - email settings"
echo "   - cron_key (if using HTTP cron)"
echo ""
echo "2. Set up a cron job (every 15 minutes):"
echo ""
echo "   Option A - PHP CLI:"
echo "   */15 * * * * /usr/bin/php ${DIR}/cron.php"
echo ""
echo "   Option B - Via HTTP (replace YOUR_KEY):"
echo "   */15 * * * * curl -s 'https://yourdomain.com/rss/cron.php?key=YOUR_KEY'"
echo ""
echo "3. Open index.html in your browser to manage feeds."
echo ""
