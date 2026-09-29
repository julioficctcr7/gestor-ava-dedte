#!/bin/bash
# =============================================================================
# Script de Aprovisionamiento Automatizado de la VM en Google Compute Engine
# Universidad Autónoma Gabriel René Moreno (U.A.G.R.M.) - DEDTE
# Región: southamerica-west1 (Santiago de Chile) para latencia mínima (<45ms)
# =============================================================================

set -e

echo "🔧 [1/5] Actualizando repositorios del sistema..."
sudo apt-get update -y
sudo apt-get install -y \
    ca-certificates \
    curl \
    gnupg \
    lsb-release \
    git \
    ufw \
    fail2ban

echo "🐳 [2/5] Instalando Docker Engine y Docker Compose V2..."
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt-get update -y
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

# Permitir al usuario actual ejecutar docker sin sudo
sudo usermod -aG docker $USER

echo "📁 [3/5] Creando estructura de directorios en /opt/gestor-ava..."
sudo mkdir -p /opt/gestor-ava/infra/nginx
sudo chown -R $USER:$USER /opt/gestor-ava

echo "🛡️ [4/5] Configurando Firewall local UFW..."
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw --force enable

echo "☁️ [5/5] Configurando Helper de Credenciales de Artifact Registry..."
# Requiere que la Service Account de la VM tenga el rol 'roles/artifactregistry.reader'
gcloud auth configure-docker southamerica-west1-docker.pkg.dev --quiet || true

echo "====================================================================="
echo "✅ VM lista para recibir despliegues continuos desde GitHub Actions."
echo "Directorio de trabajo: /opt/gestor-ava"
echo "====================================================================="
