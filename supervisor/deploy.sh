#!/bin/bash

# Supervisor Deployment Script for Queue Workers
# This script deploys Supervisor configuration files for Laravel queue workers

set -e

echo "Deploying Supervisor configuration for queue workers..."

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run as root or with sudo"
    exit 1
fi

# Configuration
SUPERVISOR_CONF_DIR="/etc/supervisor/conf.d"
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SUPERVISOR_DIR="${PROJECT_DIR}/supervisor"

# Copy configuration files
echo "Copying configuration files to ${SUPERVISOR_CONF_DIR}..."
cp "${SUPERVISOR_DIR}/attendance-high-priority-worker.conf" "${SUPERVISOR_CONF_DIR}/"
cp "${SUPERVISOR_DIR}/attendance-default-worker.conf" "${SUPERVISOR_CONF_DIR}/"
cp "${SUPERVISOR_DIR}/notification-worker.conf" "${SUPERVISOR_CONF_DIR}/"
cp "${SUPERVISOR_DIR}/reporting-worker.conf" "${SUPERVISOR_CONF_DIR}/"

# Update project path in configuration files
echo "Updating project paths in configuration files..."
sed -i "s|/var/www/html|${PROJECT_DIR}|g" "${SUPERVISOR_CONF_DIR}/attendance-high-priority-worker.conf"
sed -i "s|/var/www/html|${PROJECT_DIR}|g" "${SUPERVISOR_CONF_DIR}/attendance-default-worker.conf"
sed -i "s|/var/www/html|${PROJECT_DIR}|g" "${SUPERVISOR_CONF_DIR}/notification-worker.conf"
sed -i "s|/var/www/html|${PROJECT_DIR}|g" "${SUPERVISOR_CONF_DIR}/reporting-worker.conf"

# Reload Supervisor configuration
echo "Reloading Supervisor configuration..."
supervisorctl reread
supervisorctl update

# Start queue workers
echo "Starting queue workers..."
supervisorctl start attendance-high-priority-worker:*
supervisorctl start attendance-default-worker:*
supervisorctl start notification-worker:*
supervisorctl start reporting-worker:*

# Check status
echo ""
echo "Queue worker status:"
supervisorctl status | grep -E "(attendance-high-priority|attendance-default|notification|reporting)"

echo ""
echo "Deployment complete!"
echo ""
echo "Useful commands:"
echo "  supervisorctl status                    # View all worker status"
echo "  supervisorctl stop attendance-*         # Stop all attendance workers"
echo "  supervisorctl restart notification-*    # Restart notification workers"
echo "  supervisorctl tail -f attendance-high-priority-worker:attendance-high-priority-worker_00  # View logs"
