# Hostinger VPS Deployment Guide - Ubuntu 24.04 LTS

This guide provides specific instructions for deploying the Multi-Tenant Attendance Monitoring System on a Hostinger VPS running Ubuntu 24.04 LTS.

## Table of Contents
1. [Initial VPS Setup](#initial-vps-setup)
2. [Hostinger-Specific Considerations](#hostinger-specific-considerations)
3. [Quick Deployment](#quick-deployment)
4. [Domain & DNS Configuration](#domain--dns-configuration)
5. [Performance Optimization](#performance-optimization)
6. [Troubleshooting](#troubleshooting)

---

## Initial VPS Setup

### 1. Access Your Hostinger VPS

```bash
# SSH into your VPS (use IP provided by Hostinger)
ssh root@your-vps-ip

# Or if you configured a non-root user
ssh username@your-vps-ip
```

### 2. Update System

```bash
# Update package lists
sudo apt update && sudo apt upgrade -y

# Install essential tools
sudo apt install -y software-properties-common curl wget git unzip
```

### 3. Configure Firewall

```bash
# Install and enable UFW
sudo apt install -y ufw

# Allow SSH (important - don't lock yourself out!)
sudo ufw allow OpenSSH
sudo ufw allow 22/tcp

# Allow HTTP and HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Allow MQTT (if exposing MQTT broker)
# sudo ufw allow 1883/tcp  # Only if needed externally
# sudo ufw allow 8883/tcp  # MQTT over TLS

# Enable firewall
sudo ufw enable

# Check status
sudo ufw status
```

### 4. Create Application User (Optional but Recommended)

```bash
# Create dedicated user for application
sudo adduser attendance
sudo usermod -aG sudo attendance

# Switch to new user
su - attendance
```

---

## Hostinger-Specific Considerations

### Resource Limits

Hostinger VPS plans typically offer:
- **VPS 1**: 1 vCPU, 2GB RAM, 40GB SSD
- **VPS 2**: 2 vCPU, 4GB RAM, 60GB SSD
- **VPS 3**: 4 vCPU, 8GB RAM, 80GB SSD
- **VPS 4**: 6 vCPU, 12GB RAM, 120GB SSD

**Recommended minimum**: VPS 2 (2 vCPU, 4GB RAM) for production

### Swap Configuration (for smaller VPS plans)

If using VPS 1 or VPS 2, configure swap:

```bash
# Create 2GB swap file
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile

# Make permanent
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab

# Verify
sudo swapon --show
free -h
```

### Storage Optimization

```bash
# Check current disk usage
df -h

# Clean up if needed
sudo apt autoremove -y
sudo apt clean

# Monitor disk usage
du -sh /var/www/* 2>/dev/null | sort -h
```

---

## Quick Deployment

### Step 1: Install LEMP Stack

```bash
# Install Nginx
sudo apt install -y nginx
sudo systemctl enable nginx
sudo systemctl start nginx

# Add PHP 8.3 repository
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP 8.3 and extensions
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-redis \
    php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-intl

# Install MySQL
sudo apt install -y mysql-server

# Secure MySQL
sudo mysql_secure_installation
# Answer: Yes to all prompts
# Set strong root password

# Install Redis
sudo apt install -y redis-server
sudo systemctl enable redis-server
sudo systemctl start redis-server

# Install MQTT Broker
sudo apt install -y mosquitto mosquitto-clients
sudo systemctl enable mosquitto
sudo systemctl start mosquitto
```

### Step 2: Configure MySQL

```bash
# Login to MySQL
sudo mysql

# Run these SQL commands:
```

```sql
CREATE DATABASE attendance_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'attendance'@'localhost' IDENTIFIED BY 'CHANGE_THIS_PASSWORD';
GRANT ALL PRIVILEGES ON attendance_central.* TO 'attendance'@'localhost';
GRANT ALL PRIVILEGES ON `tenant_%`.* TO 'attendance'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Step 3: Deploy Application

```bash
# Create application directory
sudo mkdir -p /var/www/attendance
sudo chown -R $USER:$USER /var/www/attendance

# Clone repository
cd /var/www/attendance
git clone <your-repository-url> .

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node.js 20.x
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Install frontend dependencies and build
npm ci
npm run build
```

### Step 4: Configure Environment

```bash
# Copy and edit environment file
cp .env.example .env
nano .env
```

Update with your Hostinger VPS details:

```bash
APP_NAME="Attendance Monitor"
APP_ENV=production
APP_KEY=base64:GENERATE_THIS_LATER
APP_DEBUG=false
APP_URL=https://attendance.yourdomain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=attendance_tenant
DB_USERNAME=attendance
DB_PASSWORD=YOUR_MYSQL_PASSWORD

CENTRAL_DB_HOST=127.0.0.1
CENTRAL_DB_PORT=3306
CENTRAL_DB_DATABASE=attendance_central
CENTRAL_DB_USERNAME=attendance
CENTRAL_DB_PASSWORD=YOUR_MYSQL_PASSWORD

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
CACHE_STORE=redis
QUEUE_CONNECTION=redis

# MQTT
MQTT_HOST=127.0.0.1
MQTT_PORT=1883
MQTT_CLIENT_ID=attendance_monitor
```

Generate application key:
```bash
php artisan key:generate
```

### Step 5: Run Migrations

```bash
# Run central database migrations
php artisan migrate --database=central --path=database/migrations/central --force
```

### Step 6: Set Permissions

```bash
sudo chown -R www-data:www-data /var/www/attendance
sudo chmod -R 755 /var/www/attendance
sudo chmod -R 775 /var/www/attendance/storage
sudo chmod -R 775 /var/www/attendance/bootstrap/cache
```

### Step 7: Configure Nginx

Create `/etc/nginx/sites-available/attendance`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name attendance.yourdomain.com *.attendance.yourdomain.com;
    root /var/www/attendance/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

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

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/attendance /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### Step 8: Install SSL Certificate

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtain certificate (requires domain to be pointed to your VPS)
sudo certbot --nginx -d attendance.yourdomain.com

# For wildcard certificate (for subdomains):
sudo certbot certonly --manual --preferred-challenges=dns \
    -d attendance.yourdomain.com -d *.attendance.yourdomain.com

# Follow prompts to add DNS TXT records
```

### Step 9: Setup Supervisor for Queue Workers

```bash
# Install Supervisor
sudo apt install -y supervisor

# Create configuration
sudo nano /etc/supervisor/conf.d/attendance-mqtt.conf
```

Add MQTT consumer configuration:
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

Create queue worker configuration:
```bash
sudo nano /etc/supervisor/conf.d/attendance-queue.conf
```

```ini
[program:attendance-queue-high]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/attendance/artisan queue:work redis --queue=attendance-high-priority --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/attendance/storage/logs/queue-high.log
stopwaitsecs=3600

[program:attendance-queue-default]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/attendance/artisan queue:work redis --queue=attendance-default --sleep=3 --tries=3 --max-time=3600
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

Start services:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
sudo supervisorctl status
```

### Step 10: Verify Deployment

```bash
# Run health check
cd /var/www/attendance
php artisan system:health

# Check services
sudo systemctl status nginx
sudo systemctl status php8.3-fpm
sudo systemctl status mysql
sudo systemctl status redis-server
sudo systemctl status mosquitto
sudo supervisorctl status
```

---

## Domain & DNS Configuration

### Configure DNS Records at Hostinger

Login to your Hostinger control panel and add DNS records:

**1. Main Domain (A Record)**
```
Type: A
Name: attendance
Value: YOUR_VPS_IP
TTL: 3600
```

**2. Wildcard Subdomain for Tenants (A Record)**
```
Type: A
Name: *
Value: YOUR_VPS_IP
TTL: 3600
```

**3. SSL Verification (CNAME) - If using external provider**
```
Type: CNAME
Name: _acme-challenge
Value: (provided by Certbot or SSL provider)
TTL: 3600
```

### Verify DNS Propagation

```bash
# Check A record
dig attendance.yourdomain.com

# Check wildcard
dig tenant1.attendance.yourdomain.com
dig tenant2.attendance.yourdomain.com

# Or use online tools
# https://dnschecker.org
```

DNS propagation can take 1-24 hours, but typically completes within 30 minutes.

---

## Performance Optimization

### 1. PHP-FPM Optimization

Edit `/etc/php/8.3/fpm/pool.d/www.conf`:

```ini
; For VPS 2 (4GB RAM)
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500

; For VPS 1 (2GB RAM) - use these instead:
; pm.max_children = 25
; pm.start_servers = 5
; pm.min_spare_servers = 3
; pm.max_spare_servers = 10
```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.3-fpm
```

### 2. Nginx Optimization

Edit `/etc/nginx/nginx.conf`:

```nginx
user www-data;
worker_processes auto;
worker_rlimit_nofile 65535;

events {
    worker_connections 4096;
    use epoll;
    multi_accept on;
}

http {
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss;

    # Cache
    open_file_cache max=2000 inactive=20s;
    open_file_cache_valid 60s;
    open_file_cache_min_uses 2;
    open_file_cache_errors on;

    # Other settings...
}
```

### 3. MySQL Optimization

Edit `/etc/mysql/mysql.conf.d/mysqld.cnf`:

```ini
[mysqld]
# For VPS 2 (4GB RAM)
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
max_connections = 200

# Query cache (for read-heavy workloads)
query_cache_type = 1
query_cache_size = 128M
query_cache_limit = 2M

# For VPS 1 (2GB RAM) - use these instead:
# innodb_buffer_pool_size = 512M
# innodb_log_file_size = 128M
# max_connections = 100
```

Restart MySQL:
```bash
sudo systemctl restart mysql
```

### 4. Redis Optimization

Edit `/etc/redis/redis.conf`:

```conf
# Memory limit (for VPS 2 with 4GB RAM)
maxmemory 512mb
maxmemory-policy allkeys-lru

# For VPS 1 (2GB RAM):
# maxmemory 256mb
```

Restart Redis:
```bash
sudo systemctl restart redis-server
```

### 5. Application Caching

```bash
cd /var/www/attendance

# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize
```

---

## Troubleshooting

### Issue: Low Memory / OOM Errors

```bash
# Check memory usage
free -h
htop  # Install: sudo apt install htop

# If using VPS 1, reduce worker processes
# Edit supervisor configs to use numprocs=1 instead of 2

# Reduce PHP-FPM processes
sudo nano /etc/php/8.3/fpm/pool.d/www.conf
# Lower pm.max_children to 15-20
```

### Issue: Slow Performance

```bash
# Check slow queries
sudo mysql -e "SHOW VARIABLES LIKE 'slow_query_log';"
sudo mysql -e "SET GLOBAL slow_query_log = 'ON';"
sudo mysql -e "SET GLOBAL long_query_time = 2;"

# Monitor slow queries
sudo tail -f /var/log/mysql/mysql-slow.log

# Check Redis latency
redis-cli --latency

# Check PHP-FPM status
sudo systemctl status php8.3-fpm

# Monitor system resources
htop
iotop  # Install: sudo apt install iotop
```

### Issue: Queue Workers Not Processing

```bash
# Check supervisor
sudo supervisorctl status

# Check queue size
redis-cli LLEN queues:attendance-high-priority

# View worker logs
tail -f /var/www/attendance/storage/logs/queue-high.log

# Restart workers
sudo supervisorctl restart attendance-queue-high:*
```

### Issue: Cannot Access Website

```bash
# Check Nginx status
sudo systemctl status nginx
sudo nginx -t

# Check firewall
sudo ufw status

# Check DNS
dig attendance.yourdomain.com

# Check logs
sudo tail -f /var/log/nginx/error.log
```

### Issue: SSL Certificate Errors

```bash
# Check certificate
sudo certbot certificates

# Renew certificate
sudo certbot renew --dry-run
sudo certbot renew

# Check Nginx SSL configuration
sudo nginx -t
```

---

## Monitoring & Maintenance

### Set Up Automated Tasks

```bash
# Edit crontab
crontab -e

# Add these lines:
```

```cron
# Laravel Scheduler (required)
* * * * * cd /var/www/attendance && php artisan schedule:run >> /dev/null 2>&1

# Database backups (2 AM daily)
0 2 * * * /usr/local/bin/backup-attendance-db.sh >> /var/log/attendance-backup.log 2>&1

# Clear old logs (weekly)
0 3 * * 0 find /var/www/attendance/storage/logs -name "*.log" -mtime +30 -delete

# Health check (every 6 hours)
0 */6 * * * cd /var/www/attendance && php artisan system:health --json > /tmp/health.json || mail -s "Health Alert" admin@yourdomain.com < /tmp/health.json
```

### Log Rotation

Create `/etc/logrotate.d/attendance`:

```
/var/www/attendance/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

---

## Hostinger Support Resources

- **VPS Management**: https://hpanel.hostinger.com/
- **Knowledge Base**: https://support.hostinger.com/
- **Live Chat**: Available 24/7 in hPanel

For application-specific issues, refer to the main deployment documentation in `docs/deployment.md`.

---

## Deployment Checklist

- [ ] VPS provisioned and accessible via SSH
- [ ] System updated and firewall configured
- [ ] LEMP stack installed (Nginx, PHP 8.3, MySQL, Redis)
- [ ] MQTT broker (Mosquitto) installed
- [ ] Application deployed and dependencies installed
- [ ] Database created and migrations run
- [ ] Environment configured (`.env`)
- [ ] File permissions set correctly
- [ ] Nginx configured and SSL certificate installed
- [ ] DNS records configured at Hostinger
- [ ] Supervisor services running
- [ ] Health check passes
- [ ] Backup cron jobs configured
- [ ] Monitoring configured
- [ ] First super admin created
- [ ] Test tenant provisioned

---

Your Multi-Tenant Attendance Monitoring System is now deployed on Hostinger VPS! 🎉

Access your application at: `https://attendance.yourdomain.com`

For ongoing support and maintenance, refer to the main documentation and Hostinger support resources.
