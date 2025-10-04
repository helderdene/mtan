# Deployment Guide - Multi-Tenant Attendance Monitoring System

This guide covers deploying the Multi-Tenant Attendance Monitoring System to a production environment.

## Table of Contents
1. [System Requirements](#system-requirements)
2. [Server Setup](#server-setup)
3. [Application Deployment](#application-deployment)
4. [Database Configuration](#database-configuration)
5. [MQTT Broker Setup](#mqtt-broker-setup)
6. [Queue Workers & Supervisor](#queue-workers--supervisor)
7. [Web Server Configuration](#web-server-configuration)
8. [SSL/TLS Configuration](#ssltls-configuration)
9. [Monitoring & Health Checks](#monitoring--health-checks)
10. [Backup & Recovery](#backup--recovery)
11. [Troubleshooting](#troubleshooting)

---

## System Requirements

### Minimum Server Specifications
- **CPU**: 4 cores (8 recommended for production)
- **RAM**: 8GB (16GB recommended)
- **Storage**: 100GB SSD (scales with tenant count)
- **OS**: Ubuntu 22.04 LTS or RHEL 8+

### Required Software
- **PHP**: 8.3 or higher
- **MySQL**: 8.0 or higher
- **Redis**: 6.0 or higher
- **Nginx**: 1.18 or higher (or Apache 2.4+)
- **Node.js**: 20.x LTS
- **NPM**: 10.x
- **Composer**: 2.x
- **Supervisor**: 4.x
- **MQTT Broker**: Mosquitto 2.x (or equivalent)

### Required PHP Extensions
```bash
php -m | grep -E "pdo|pdo_mysql|redis|mbstring|openssl|json|curl|xml|zip|gd|intl"
```

Ensure all extensions are installed:
```bash
sudo apt install -y php8.3-cli php8.3-fpm php8.3-mysql php8.3-redis \
    php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-intl
```

---

## Server Setup

### 1. Update System Packages
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git curl wget unzip
```

### 2. Install MySQL Server
```bash
# Install MySQL
sudo apt install -y mysql-server

# Secure MySQL installation
sudo mysql_secure_installation

# Create databases
sudo mysql -e "CREATE DATABASE attendance_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'attendance'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';"
sudo mysql -e "GRANT ALL PRIVILEGES ON attendance_central.* TO 'attendance'@'localhost';"
sudo mysql -e "GRANT ALL PRIVILEGES ON \`tenant_%\`.* TO 'attendance'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"
```

### 3. Install Redis
```bash
sudo apt install -y redis-server
sudo systemctl enable redis-server
sudo systemctl start redis-server

# Verify Redis is running
redis-cli ping  # Should return "PONG"
```

### 4. Install MQTT Broker (Mosquitto)
```bash
sudo apt install -y mosquitto mosquitto-clients
sudo systemctl enable mosquitto
sudo systemctl start mosquitto

# Test MQTT broker
mosquitto_sub -h localhost -t test &
mosquitto_pub -h localhost -t test -m "Hello MQTT"
```

### 5. Install PHP 8.3
```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-redis \
    php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-intl

# Verify PHP version
php -v
```

### 6. Install Composer
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### 7. Install Node.js & NPM
```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v && npm -v
```

### 8. Install Supervisor
```bash
sudo apt install -y supervisor
sudo systemctl enable supervisor
sudo systemctl start supervisor
```

---

## Application Deployment

### 1. Clone Repository
```bash
# Create application directory
sudo mkdir -p /var/www/attendance
sudo chown -R $USER:$USER /var/www/attendance

# Clone repository
cd /var/www/attendance
git clone <repository-url> .

# Or deploy via CI/CD
```

### 2. Install Dependencies
```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node dependencies
npm ci

# Build frontend assets
npm run build
```

### 3. Configure Environment
```bash
# Copy example environment file
cp .env.example .env

# Edit environment file
nano .env
```

**Critical Environment Variables:**
```bash
APP_NAME="Attendance Monitor"
APP_ENV=production
APP_KEY=base64:GENERATE_WITH_php_artisan_key:generate
APP_DEBUG=false
APP_URL=https://attendance.yourdomain.com

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=attendance_tenant
DB_USERNAME=attendance
DB_PASSWORD=YOUR_STRONG_PASSWORD

# Central Database
CENTRAL_DB_HOST=127.0.0.1
CENTRAL_DB_PORT=3306
CENTRAL_DB_DATABASE=attendance_central
CENTRAL_DB_USERNAME=attendance
CENTRAL_DB_PASSWORD=YOUR_STRONG_PASSWORD

# Redis Configuration
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

CACHE_STORE=redis
QUEUE_CONNECTION=redis

# MQTT Broker
MQTT_HOST=127.0.0.1
MQTT_PORT=1883
MQTT_CLIENT_ID=attendance_monitor
MQTT_USERNAME=
MQTT_PASSWORD=
MQTT_TLS_ENABLED=false

# Multi-Tenancy
TENANT_BASE_DOMAIN=attendance.yourdomain.com
TENANT_CACHE_TTL=5
```

### 4. Generate Application Key
```bash
php artisan key:generate
```

### 5. Run Migrations
```bash
# Run central database migrations
php artisan migrate --database=central --path=database/migrations/central --force

# Migrations for tenant databases run automatically during provisioning
```

### 6. Set Permissions
```bash
sudo chown -R www-data:www-data /var/www/attendance
sudo chmod -R 755 /var/www/attendance
sudo chmod -R 775 /var/www/attendance/storage
sudo chmod -R 775 /var/www/attendance/bootstrap/cache
```

### 7. Cache Configuration
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Database Configuration

### Multi-Tenant Database Strategy

This system uses a **database-per-tenant** approach:
- **Central Database** (`attendance_central`): Stores tenant registry, device mappings, super admins
- **Tenant Databases** (`tenant_*`): One database per tenant containing employees, attendance records, etc.

### Provisioning a New Tenant

```bash
# Method 1: Via Super Admin Interface
# Navigate to /super-admin/tenants and click "Add Tenant"

# Method 2: Via CLI
php artisan tenant:create \
    --name="Company Name" \
    --subdomain="company" \
    --plan="professional"

# Provision tenant database
php artisan tenant:provision <tenant-id>
```

### Database Backup Strategy

See [Backup & Recovery](#backup--recovery) section.

---

## MQTT Broker Setup

### Mosquitto Configuration

Edit `/etc/mosquitto/mosquitto.conf`:

```conf
# Basic Configuration
listener 1883 0.0.0.0
protocol mqtt

# Authentication
allow_anonymous false
password_file /etc/mosquitto/passwd

# Logging
log_dest file /var/log/mosquitto/mosquitto.log
log_type all
log_timestamp true

# Persistence
persistence true
persistence_location /var/lib/mosquitto/

# Connection limits
max_connections 1000
max_queued_messages 10000
```

### Create MQTT Users
```bash
# Create password file
sudo mosquitto_passwd -c /etc/mosquitto/passwd attendance_monitor

# Add device users
sudo mosquitto_passwd /etc/mosquitto/passwd device001
sudo mosquitto_passwd /etc/mosquitto/passwd device002

# Restart Mosquitto
sudo systemctl restart mosquitto
```

### TLS/SSL Configuration (Recommended for Production)

```bash
# Generate certificates (use Let's Encrypt or your CA)
sudo mkdir -p /etc/mosquitto/certs
sudo cp /path/to/ca.crt /etc/mosquitto/certs/
sudo cp /path/to/server.crt /etc/mosquitto/certs/
sudo cp /path/to/server.key /etc/mosquitto/certs/

# Update mosquitto.conf
sudo nano /etc/mosquitto/mosquitto.conf
```

Add TLS configuration:
```conf
listener 8883 0.0.0.0
protocol mqtt

cafile /etc/mosquitto/certs/ca.crt
certfile /etc/mosquitto/certs/server.crt
keyfile /etc/mosquitto/certs/server.key

require_certificate false
use_identity_as_username false
```

Update `.env`:
```bash
MQTT_PORT=8883
MQTT_TLS_ENABLED=true
MQTT_TLS_CA_FILE=/etc/mosquitto/certs/ca.crt
MQTT_TLS_VERIFY_PEER=true
```

---

## Queue Workers & Supervisor

### Supervisor Configuration

Create supervisor configuration files in `/etc/supervisor/conf.d/`:

**1. MQTT Consumer** (`/etc/supervisor/conf.d/attendance-mqtt.conf`):
```ini
[program:attendance-mqtt-consumer]
process_name=%(program_name)s
command=php /var/www/attendance/artisan mqtt:consume
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/attendance/storage/logs/mqtt-consumer.log
stopwaitsecs=3600
```

**2. High Priority Queue** (`/etc/supervisor/conf.d/attendance-queue-high.conf`):
```ini
[program:attendance-queue-high]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/attendance/artisan queue:work redis --queue=attendance-high-priority --sleep=3 --tries=3 --max-time=3600 --timeout=60
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/attendance/storage/logs/queue-high.log
stopwaitsecs=3600
```

**3. Default Queue** (`/etc/supervisor/conf.d/attendance-queue-default.conf`):
```ini
[program:attendance-queue-default]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/attendance/artisan queue:work redis --queue=attendance-default --sleep=3 --tries=3 --max-time=3600 --timeout=90
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/attendance/storage/logs/queue-default.log
stopwaitsecs=3600
```

### Start Supervisor Services

```bash
# Reload supervisor configuration
sudo supervisorctl reread
sudo supervisorctl update

# Start all services
sudo supervisorctl start attendance-mqtt-consumer:*
sudo supervisorctl start attendance-queue-high:*
sudo supervisorctl start attendance-queue-default:*

# Check status
sudo supervisorctl status
```

### Managing Queue Workers

```bash
# Restart queue workers
sudo supervisorctl restart attendance-queue-high:*
sudo supervisorctl restart attendance-queue-default:*

# View logs
tail -f /var/www/attendance/storage/logs/queue-high.log
tail -f /var/www/attendance/storage/logs/mqtt-consumer.log

# Stop all workers
sudo supervisorctl stop attendance-queue-high:*
sudo supervisorctl stop attendance-queue-default:*
```

---

## Web Server Configuration

### Nginx Configuration

Create `/etc/nginx/sites-available/attendance`:

```nginx
# Main application
server {
    listen 80;
    listen [::]:80;
    server_name attendance.yourdomain.com *.attendance.yourdomain.com;
    root /var/www/attendance/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    # Logs
    access_log /var/log/nginx/attendance-access.log;
    error_log /var/log/nginx/attendance-error.log;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/attendance /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## SSL/TLS Configuration

### Using Let's Encrypt (Certbot)

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtain certificates
sudo certbot --nginx -d attendance.yourdomain.com -d *.attendance.yourdomain.com

# Auto-renewal is configured automatically
sudo systemctl status certbot.timer
```

### Manual SSL Configuration

Update nginx configuration to include SSL:
```nginx
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name attendance.yourdomain.com *.attendance.yourdomain.com;

    ssl_certificate /etc/ssl/certs/attendance.crt;
    ssl_certificate_key /etc/ssl/private/attendance.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # ... rest of configuration
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name attendance.yourdomain.com *.attendance.yourdomain.com;
    return 301 https://$host$request_uri;
}
```

---

## Monitoring & Health Checks

### System Health Check Command

Run regular health checks:
```bash
# Basic health check
php artisan system:health

# Detailed output
php artisan system:health --detailed

# JSON output for monitoring tools
php artisan system:health --json
```

### Automated Health Monitoring

Add to crontab (`crontab -e`):
```bash
# Health check every 5 minutes
*/5 * * * * cd /var/www/attendance && php artisan system:health --json > /var/log/attendance-health.json 2>&1

# Send alerts on failure
*/5 * * * * cd /var/www/attendance && php artisan system:health || mail -s "Attendance System Health Alert" admin@yourdomain.com < /var/log/attendance-health.json
```

### Log Monitoring

```bash
# Application logs
tail -f /var/www/attendance/storage/logs/laravel.log

# MQTT logs
tail -f /var/www/attendance/storage/logs/mqtt-consumer.log

# Queue logs
tail -f /var/www/attendance/storage/logs/queue-high.log

# Nginx logs
tail -f /var/log/nginx/attendance-access.log
tail -f /var/log/nginx/attendance-error.log
```

### Performance Monitoring

Consider using:
- **New Relic** or **DataDog** for APM
- **Prometheus** + **Grafana** for metrics
- **Sentry** for error tracking

---

## Backup & Recovery

See [docs/backup.md](backup.md) for detailed backup procedures.

### Quick Backup Commands

```bash
# Backup central database
php artisan db:backup central

# Backup all tenant databases
php artisan db:backup tenants

# Backup specific tenant
php artisan db:backup tenant --tenant-id=<tenant-id>

# Full system backup
php artisan system:backup --all
```

---

## Troubleshooting

### Common Issues

**1. Queue Workers Not Processing Jobs**
```bash
# Check supervisor status
sudo supervisorctl status

# Restart queue workers
sudo supervisorctl restart attendance-queue-high:*

# Check queue size
redis-cli LLEN queues:attendance-high-priority
```

**2. MQTT Connection Failed**
```bash
# Check MQTT broker status
sudo systemctl status mosquitto

# Test MQTT connection
mosquitto_sub -h localhost -p 1883 -t "mqtt/face/+/Rec" -u attendance_monitor -P password

# Check MQTT logs
tail -f /var/log/mosquitto/mosquitto.log
```

**3. Database Connection Errors**
```bash
# Test MySQL connection
mysql -u attendance -p -e "SHOW DATABASES;"

# Check MySQL logs
sudo tail -f /var/log/mysql/error.log
```

**4. Permission Issues**
```bash
# Reset permissions
sudo chown -R www-data:www-data /var/www/attendance
sudo chmod -R 775 /var/www/attendance/storage
sudo chmod -R 775 /var/www/attendance/bootstrap/cache
```

**5. Cache Issues**
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Getting Help

- Check logs: `/var/www/attendance/storage/logs/`
- Run health check: `php artisan system:health --detailed`
- Enable debug mode temporarily: `APP_DEBUG=true` in `.env`
- Check system requirements: `php artisan about`

---

## Deployment Checklist

- [ ] Server meets minimum requirements
- [ ] All required software installed
- [ ] MySQL configured with attendance user
- [ ] Redis installed and running
- [ ] MQTT broker (Mosquitto) configured
- [ ] Application deployed and dependencies installed
- [ ] Environment configured (`.env`)
- [ ] Application key generated
- [ ] Central database migrations run
- [ ] File permissions set correctly
- [ ] Nginx/Apache configured
- [ ] SSL certificates installed
- [ ] Supervisor services configured and running
- [ ] Health check command passes
- [ ] Backup procedures configured
- [ ] Monitoring tools configured
- [ ] Firewall rules configured
- [ ] DNS records configured for wildcard subdomains
- [ ] First super admin created
- [ ] Test tenant provisioned and verified

---

## Security Considerations

1. **Change all default passwords**
2. **Use strong APP_KEY** (generated with `php artisan key:generate`)
3. **Enable SSL/TLS** for all connections (HTTPS, MQTTS)
4. **Configure firewall** (UFW or iptables)
5. **Regular security updates** (`sudo apt update && sudo apt upgrade`)
6. **Limit database access** (bind to localhost if possible)
7. **Use Redis password protection** if exposed
8. **Configure fail2ban** for SSH and web server
9. **Regular backups** (automated daily)
10. **Monitor logs** for suspicious activity

---

## Post-Deployment

After successful deployment:

1. Create super admin account
2. Login to `/super-admin` interface
3. Create first tenant
4. Provision tenant database
5. Register devices for the tenant
6. Test attendance event processing
7. Verify MQTT messages are processed correctly
8. Check health status regularly
9. Set up monitoring alerts
10. Document any custom configurations

For support, refer to the project documentation or contact the development team.
