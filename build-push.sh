#!/bin/bash
###############################################################################
# Mikhmonv2 Docker Build & Push Script
# Usage: ./build-push.sh [tag]
# Default tag: latest
###############################################################################

set -e

DOCKER_USER="iamlatif"
IMAGE_NAME="mikhmonv2"
TAG="${1:-latest}"
FULL_TAG="${DOCKER_USER}/${IMAGE_NAME}:${TAG}"

echo "=================================================="
echo "  Mikhmonv2 Docker Builder"
echo "  Target: ${FULL_TAG}"
echo "=================================================="

# Check if user is logged in to Docker Hub
echo ""
echo "[1/5] Checking Docker Hub login..."
if ! docker info 2>/dev/null | grep -q "Username"; then
    echo "WARNING: You don't appear to be logged in to Docker Hub."
    echo "Run: docker login -u ${DOCKER_USER}"
    echo ""
    read -p "Do you want to login now? (y/n): " choice
    if [[ "$choice" == "y" || "$choice" == "Y" ]]; then
        docker login -u ${DOCKER_USER}
    else
        echo "Aborting. Please login first with: docker login -u ${DOCKER_USER}"
        exit 1
    fi
fi

# Show current Git commit
echo ""
echo "[2/5] Git status:"
git log -1 --oneline 2>/dev/null || echo "  (not a git repo or no commits)"

# Build image
echo ""
echo "[3/5] Building Docker image..."
docker build -t ${FULL_TAG} -t ${DOCKER_USER}/${IMAGE_NAME}:latest .

# Verify image built
echo ""
echo "[4/5] Verifying image..."
docker images ${DOCKER_USER}/${IMAGE_NAME} --format "table {{.Repository}}:{{.Tag}}\t{{.Size}}\t{{.CreatedAt}}"

# Push to Docker Hub
echo ""
echo "[5/5] Pushing to Docker Hub..."
docker push ${FULL_TAG}
if [ "$TAG" != "latest" ]; then
    docker push ${DOCKER_USER}/${IMAGE_NAME}:latest
fi

echo ""
echo "=================================================="
echo "  SUCCESS! Image pushed to:"
echo "  ${FULL_TAG}"
echo "=================================================="
echo ""
echo "To use this image in docker-compose.yml, update:"
echo "  php_7_4:"
echo "    image: ${FULL_TAG}"
