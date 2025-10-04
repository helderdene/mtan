# Database Backup & Recovery Procedures

This document outlines backup strategies and recovery procedures for the Multi-Tenant Attendance Monitoring System.

## Table of Contents
1. [Backup Strategy](#backup-strategy)
2. [Automated Backup Scripts](#automated-backup-scripts)
3. [Manual Backup Procedures](#manual-backup-procedures)
4. [Recovery Procedures](#recovery-procedures)
5. [Backup Storage & Retention](#backup-storage--retention)
6. [Testing Backups](#testing-backups)

---

## Backup Strategy

### What to Backup
1. **Central Database** (`attendance_central`): Tenant registry, device mappings, super admins
2. **Tenant Databases** (`tenant_*`): All tenant-specific data
3. **Application Files**: Code, configuration, uploads
4. **Redis Data**: Queue data, cache (optional, can be regenerated)
5. **MQTT Configuration**: Broker config, certificates

### Backup Frequency
- **Database Backups**: Daily at 2:00 AM
- **Application Files**: Weekly or after each deployment
- **Configuration Files**: After any changes
- **Redis**: Not backed up (ephemeral data)

### Backup Retention
- **Daily Backups**: Keep for 7 days
- **Weekly Backups**: Keep for 4 weeks
- **Monthly Backups**: Keep for 12 months
- **Critical Backups**: Keep indefinitely (before major upgrades)

---

## Automated Backup Scripts

### Setup Backup Directory Structure

```bash
# Create backup directories
sudo mkdir -p /var/backups/attendance/{daily,weekly,monthly,databases,files}
sudo chown -R www-data:www-data /var/backups/attendance
sudo chmod -R 750 /var/backups/attendance
```

### Database Backup Script

Create `/usr/local/bin/backup-attendance-db.sh`:

```bash
#!/bin/bash

# Configuration
BACKUP_DIR="/var/backups/attendance/databases"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=7
DB_USER="attendance"
DB_PASS="YOUR_PASSWORD"

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Backup central database
echo "Backing up central database..."
mysqldump -u "$DB_USER" -p"$DB_PASS" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    attendance_central > "$BACKUP_DIR/central_$DATE.sql"

# Compress central backup
gzip "$BACKUP_DIR/central_$DATE.sql"

# Get list of all tenant databases
TENANT_DBS=$(mysql -u "$DB_USER" -p"$DB_PASS" -N -e "SHOW DATABASES LIKE 'tenant_%';")

# Backup each tenant database
for DB in $TENANT_DBS; do
    echo "Backing up tenant database: $DB"
    mysqldump -u "$DB_USER" -p"$DB_PASS" \
        --single-transaction \
        --routines \
        --triggers \
        "$DB" > "$BACKUP_DIR/${DB}_$DATE.sql"

    # Compress tenant backup
    gzip "$BACKUP_DIR/${DB}_$DATE.sql"
done

# Remove old backups
find "$BACKUP_DIR" -name "*.sql.gz" -type f -mtime +$RETENTION_DAYS -delete

echo "Database backup completed: $DATE"
echo "Backups stored in: $BACKUP_DIR"

# Send notification (optional)
# echo "Database backup completed" | mail -s "Attendance Backup Status" admin@yourdomain.com
```

Make script executable:
```bash
sudo chmod +x /usr/local/bin/backup-attendance-db.sh
```

### Application Files Backup Script

Create `/usr/local/bin/backup-attendance-files.sh`:

```bash
#!/bin/bash

# Configuration
BACKUP_DIR="/var/backups/attendance/files"
APP_DIR="/var/www/attendance"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Backup application files
echo "Backing up application files..."
tar -czf "$BACKUP_DIR/app_$DATE.tar.gz" \
    --exclude="$APP_DIR/storage/logs/*" \
    --exclude="$APP_DIR/storage/framework/cache/*" \
    --exclude="$APP_DIR/storage/framework/sessions/*" \
    --exclude="$APP_DIR/node_modules" \
    --exclude="$APP_DIR/vendor" \
    -C "$(dirname $APP_DIR)" "$(basename $APP_DIR)"

# Backup .env file separately (encrypted)
echo "Backing up .env file..."
cp "$APP_DIR/.env" "$BACKUP_DIR/.env_$DATE"
gpg --symmetric --cipher-algo AES256 "$BACKUP_DIR/.env_$DATE"
rm "$BACKUP_DIR/.env_$DATE"

# Remove old backups
find "$BACKUP_DIR" -name "app_*.tar.gz" -type f -mtime +$RETENTION_DAYS -delete
find "$BACKUP_DIR" -name ".env_*.gpg" -type f -mtime +$RETENTION_DAYS -delete

echo "Files backup completed: $DATE"
echo "Backups stored in: $BACKUP_DIR"
```

Make script executable:
```bash
sudo chmod +x /usr/local/bin/backup-attendance-files.sh
```

### Automated Backup Scheduling

Add to crontab (`sudo crontab -e`):

```bash
# Database backups - Daily at 2:00 AM
0 2 * * * /usr/local/bin/backup-attendance-db.sh >> /var/log/attendance-backup.log 2>&1

# Application files backup - Weekly on Sunday at 3:00 AM
0 3 * * 0 /usr/local/bin/backup-attendance-files.sh >> /var/log/attendance-backup.log 2>&1

# Monthly backup - First day of month at 4:00 AM
0 4 1 * * /usr/local/bin/backup-attendance-db.sh && cp /var/backups/attendance/databases/central_*.sql.gz /var/backups/attendance/monthly/
```

---

## Manual Backup Procedures

### Backup Central Database

```bash
# Standard backup
mysqldump -u attendance -p attendance_central > central_backup.sql

# Compressed backup
mysqldump -u attendance -p attendance_central | gzip > central_backup.sql.gz

# Backup with structure only (no data)
mysqldump -u attendance -p --no-data attendance_central > central_structure.sql
```

### Backup Single Tenant Database

```bash
# Find tenant database name
mysql -u attendance -p -e "SELECT id, database_name FROM attendance_central.tenants WHERE company_name='Company Name';"

# Backup specific tenant
mysqldump -u attendance -p tenant_abc123_def456 > tenant_backup.sql

# Compressed backup
mysqldump -u attendance -p tenant_abc123_def456 | gzip > tenant_backup.sql.gz
```

### Backup All Tenant Databases

```bash
# List all tenant databases
TENANT_DBS=$(mysql -u attendance -p -N -e "SHOW DATABASES LIKE 'tenant_%';")

# Backup each database
for DB in $TENANT_DBS; do
    echo "Backing up $DB..."
    mysqldump -u attendance -p $DB | gzip > "${DB}_backup.sql.gz"
done
```

### Backup Application Files

```bash
# Backup entire application (excluding vendor and node_modules)
tar -czf attendance_app_backup.tar.gz \
    --exclude="node_modules" \
    --exclude="vendor" \
    --exclude="storage/logs/*" \
    -C /var/www attendance

# Backup only storage directory (uploads, generated files)
tar -czf attendance_storage_backup.tar.gz \
    -C /var/www/attendance storage

# Backup configuration files
tar -czf attendance_config_backup.tar.gz \
    -C /var/www/attendance .env config
```

---

## Recovery Procedures

### Restore Central Database

```bash
# Extract compressed backup
gunzip central_backup.sql.gz

# Drop existing database (BE CAREFUL!)
mysql -u attendance -p -e "DROP DATABASE IF EXISTS attendance_central;"
mysql -u attendance -p -e "CREATE DATABASE attendance_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Restore from backup
mysql -u attendance -p attendance_central < central_backup.sql

# Verify restoration
mysql -u attendance -p attendance_central -e "SELECT COUNT(*) FROM tenants;"
```

### Restore Single Tenant Database

```bash
# Extract compressed backup
gunzip tenant_abc123_def456_backup.sql.gz

# Drop existing database
mysql -u attendance -p -e "DROP DATABASE IF EXISTS tenant_abc123_def456;"
mysql -u attendance -p -e "CREATE DATABASE tenant_abc123_def456 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Restore from backup
mysql -u attendance -p tenant_abc123_def456 < tenant_abc123_def456_backup.sql

# Verify restoration
mysql -u attendance -p tenant_abc123_def456 -e "SHOW TABLES;"
```

### Restore All Tenant Databases

```bash
# For each backup file
for BACKUP in tenant_*_backup.sql.gz; do
    # Extract database name
    DB_NAME=$(echo $BACKUP | sed 's/_backup.sql.gz//')

    echo "Restoring $DB_NAME..."

    # Extract backup
    gunzip $BACKUP

    # Create database
    mysql -u attendance -p -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

    # Restore
    mysql -u attendance -p $DB_NAME < ${BACKUP%.gz}

    # Re-compress
    gzip ${BACKUP%.gz}
done
```

### Restore Application Files

```bash
# Extract application backup
tar -xzf attendance_app_backup.tar.gz -C /var/www/

# Restore permissions
sudo chown -R www-data:www-data /var/www/attendance
sudo chmod -R 755 /var/www/attendance
sudo chmod -R 775 /var/www/attendance/storage
sudo chmod -R 775 /var/www/attendance/bootstrap/cache

# Restore .env file (decrypt if encrypted)
gpg --decrypt .env_20241002_020000.gpg > /var/www/attendance/.env

# Reinstall dependencies
cd /var/www/attendance
composer install --no-dev --optimize-autoloader
npm ci
npm run build

# Clear and rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart services
sudo supervisorctl restart all
sudo systemctl reload php8.3-fpm
sudo systemctl reload nginx
```

---

## Backup Storage & Retention

### Local Storage

Backups are stored in `/var/backups/attendance/`:
```
/var/backups/attendance/
├── daily/          # Daily backups (7 days retention)
├── weekly/         # Weekly backups (4 weeks retention)
├── monthly/        # Monthly backups (12 months retention)
├── databases/      # All database backups
└── files/          # Application file backups
```

### Remote Storage (Recommended)

#### Option 1: AWS S3

Install AWS CLI:
```bash
sudo apt install -y awscli
aws configure
```

Sync backups to S3:
```bash
# Sync database backups
aws s3 sync /var/backups/attendance/databases/ s3://your-bucket/attendance/databases/ --storage-class STANDARD_IA

# Sync file backups
aws s3 sync /var/backups/attendance/files/ s3://your-bucket/attendance/files/ --storage-class STANDARD_IA
```

Add to crontab:
```bash
# Sync to S3 daily at 5:00 AM
0 5 * * * aws s3 sync /var/backups/attendance/ s3://your-bucket/attendance/ --storage-class STANDARD_IA
```

#### Option 2: rsync to Remote Server

```bash
# Setup SSH key authentication first
ssh-keygen -t rsa -b 4096
ssh-copy-id backup@backup-server.com

# Rsync backups
rsync -avz --delete \
    /var/backups/attendance/ \
    backup@backup-server.com:/backups/attendance/
```

Add to crontab:
```bash
# Rsync to remote server daily at 5:00 AM
0 5 * * * rsync -avz --delete /var/backups/attendance/ backup@backup-server.com:/backups/attendance/
```

---

## Testing Backups

### Backup Verification Script

Create `/usr/local/bin/test-attendance-backups.sh`:

```bash
#!/bin/bash

echo "Testing Attendance System Backups..."
echo "===================================="

# Test central database backup
LATEST_CENTRAL=$(ls -t /var/backups/attendance/databases/central_*.sql.gz | head -1)
if [ -f "$LATEST_CENTRAL" ]; then
    echo "✓ Central database backup found: $LATEST_CENTRAL"

    # Test integrity
    gunzip -t "$LATEST_CENTRAL" 2>/dev/null
    if [ $? -eq 0 ]; then
        echo "  ✓ Backup integrity verified"
    else
        echo "  ✗ Backup integrity check FAILED"
    fi
else
    echo "✗ No central database backup found"
fi

# Test tenant database backups
TENANT_COUNT=$(ls -1 /var/backups/attendance/databases/tenant_*.sql.gz 2>/dev/null | wc -l)
echo "✓ Found $TENANT_COUNT tenant database backups"

# Test application files backup
LATEST_APP=$(ls -t /var/backups/attendance/files/app_*.tar.gz | head -1)
if [ -f "$LATEST_APP" ]; then
    echo "✓ Application files backup found: $LATEST_APP"

    # Test integrity
    tar -tzf "$LATEST_APP" > /dev/null 2>&1
    if [ $? -eq 0 ]; then
        echo "  ✓ Backup integrity verified"
    else
        echo "  ✗ Backup integrity check FAILED"
    fi
else
    echo "✗ No application files backup found"
fi

echo "===================================="
echo "Backup test completed"
```

Run monthly:
```bash
sudo chmod +x /usr/local/bin/test-attendance-backups.sh

# Add to crontab - Run on 1st of each month at 6:00 AM
0 6 1 * * /usr/local/bin/test-attendance-backups.sh | mail -s "Attendance Backup Test Results" admin@yourdomain.com
```

### Full Restoration Test

Perform a full restoration test quarterly:

1. **Setup Test Environment**: Use separate server or VM
2. **Restore Databases**: Follow restoration procedures
3. **Restore Application**: Deploy application files
4. **Verify Functionality**:
   - Login to super admin panel
   - Check tenant list
   - View attendance records
   - Test MQTT connection
   - Verify queue workers

Document test results and fix any issues found.

---

## Disaster Recovery Plan

### Recovery Time Objectives (RTO)
- **Critical**: 4 hours (central database, application)
- **High**: 8 hours (all tenant databases)
- **Normal**: 24 hours (full system restoration)

### Recovery Point Objectives (RPO)
- **Maximum data loss**: 24 hours (daily backups)
- **Recommended**: 1 hour (incremental backups)

### Emergency Contacts
- **Database Administrator**: [Contact Info]
- **System Administrator**: [Contact Info]
- **Application Developer**: [Contact Info]
- **Backup Service Provider**: [Contact Info]

### Recovery Checklist
- [ ] Identify failure scope
- [ ] Notify stakeholders
- [ ] Access backup storage
- [ ] Verify backup integrity
- [ ] Prepare recovery environment
- [ ] Restore databases
- [ ] Restore application files
- [ ] Verify system functionality
- [ ] Notify users of restoration
- [ ] Document incident
- [ ] Review and improve procedures

---

## Monitoring Backup Health

### Check Backup Status

```bash
# Check recent backups
ls -lh /var/backups/attendance/databases/ | tail -n 20

# Check backup disk usage
du -sh /var/backups/attendance/*

# Check backup age
find /var/backups/attendance/databases/ -name "central_*.sql.gz" -type f -mtime -1
```

### Automated Monitoring

Add to crontab:
```bash
# Alert if backup is older than 25 hours
0 6 * * * /usr/local/bin/check-backup-age.sh
```

Create `/usr/local/bin/check-backup-age.sh`:
```bash
#!/bin/bash

LATEST_BACKUP=$(ls -t /var/backups/attendance/databases/central_*.sql.gz | head -1)
BACKUP_AGE=$(( ($(date +%s) - $(stat -c %Y "$LATEST_BACKUP")) / 3600 ))

if [ $BACKUP_AGE -gt 25 ]; then
    echo "WARNING: Latest backup is $BACKUP_AGE hours old" | \
        mail -s "Attendance Backup Alert" admin@yourdomain.com
fi
```

---

## Best Practices

1. **Test Restorations Regularly**: Monthly for critical databases
2. **Store Backups Off-Site**: Use S3, remote server, or cloud storage
3. **Encrypt Sensitive Backups**: Use GPG for .env and database backups
4. **Document Procedures**: Keep this document updated
5. **Automate Everything**: Use cron jobs and scripts
6. **Monitor Backup Health**: Set up alerts for failures
7. **Version Control Backups**: Keep multiple versions
8. **Secure Backup Access**: Limit who can access backups
9. **Test Disaster Recovery**: Full DR test quarterly
10. **Review Retention Policy**: Adjust based on compliance requirements

---

For additional support, refer to the deployment guide or contact the system administrator.
