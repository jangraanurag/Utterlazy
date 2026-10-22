#!/bin/bash
set -e

echo "🔐 SSL Certificate Setup"
echo "========================="

# Check certbot
command -v certbot >/dev/null 2>&1 || {
    echo "📦 Installing certbot..."
    apt update && apt install -y certbot python3-certbot-nginx
}

DOMAINS="utterlazy.com www.utterlazy.com api.utterlazy.com catalog.utterlazy.com admin.utterlazy.com vendor.utterlazy.com ws.utterlazy.com"

echo "📋 Requesting certificates for: $DOMAINS"
echo ""
echo "Make sure your domains point to this server's IP before continuing!"
read -p "Press enter to continue..."

# Get certificates
certbot --nginx -d $DOMAINS --non-interactive --agree-tos -m admin@utterlazy.com

echo ""
echo "✅ SSL certificates obtained!"
echo "Certificates location: /etc/letsencrypt/live/utterlazy.com/"
echo ""
echo "Auto-renewal is set up via systemd timer."
echo "Test renewal: certbot renew --dry-run"
