#!/bin/bash
set -e

echo "🚀 UtterLazy Production Deploy Script"
echo "===================================="

# ─── 1. Check requirements ───
command -v docker >/dev/null 2>&1 || { echo "❌ Docker required. Install: curl -fsSL https://get.docker.com | sh"; exit 1; }
command -v docker compose >/dev/null 2>&1 || { echo "❌ Docker Compose required."; exit 1; }

# ─── 2. Load env ───
if [ ! -f .env ]; then
    echo "📝 Creating .env from .env.example..."
    cp api-gateway/.env.example api-gateway/.env
    cp catalog-service/.env.example catalog-service/.env
    echo "⚠️  Edit .env files with your production values!"
fi

# ─── 3. Build web apps ───
echo "📦 Building web apps..."
cd web/admin-web && npm install && npm run build && cd ../..
cd web/customer-web && npm install && npm run build && cd ../..
cd web/vendor-web && npm install && npm run build && cd ../..
echo "✅ Web apps built!"

# ─── 4. Build Docker images ───
echo "🐳 Building Docker images..."
docker compose -f docker-compose.prod.yml build
echo "✅ Docker images built!"

# ─── 5. Start services ───
echo "🚀 Starting services..."
docker compose -f docker-compose.prod.yml up -d
echo "✅ Services started!"

# ─── 6. Run migrations & seeders ───
echo "🗄️  Running migrations..."
sleep 10
docker compose -f docker-compose.prod.yml exec catalog-service php artisan migrate --force --seed
docker compose -f docker-compose.prod.yml exec api-gateway php artisan migrate --force --seed
echo "✅ Migrations done!"

# ─── 7. Cache config ───
docker compose -f docker-compose.prod.yml exec catalog-service php artisan config:cache
docker compose -f docker-compose.prod.yml exec catalog-service php artisan route:cache
docker compose -f docker-compose.prod.yml exec api-gateway php artisan config:cache
echo "✅ Config cached!"

# ─── 8. Health check ───
echo "🏥 Running health checks..."
sleep 5
curl -f http://localhost:8001/api/health && echo " ✅ Catalog healthy" || echo " ⚠️ Catalog health check failed"
curl -f http://localhost:8000/api/health && echo " ✅ Gateway healthy" || echo " ⚠️ Gateway health check failed"

echo ""
echo "🎉 Deploy complete!"
echo ""
echo "📋 Next steps:"
echo "   1. Point your domains to this server's IP"
echo "   2. Run SSL certbot (see setup-ssl.sh)"
echo "   3. Update .env files with production API keys"
echo ""
echo "Domains:"
echo "  utterlazy.com         → Customer Web"
echo "  admin.utterlazy.com   → Admin Panel"
echo "  vendor.utterlazy.com  → Vendor Panel"
echo "  api.utterlazy.com     → API Gateway"
echo "  catalog.utterlazy.com → Catalog API"
echo "  ws.utterlazy.com      → WebSocket"
