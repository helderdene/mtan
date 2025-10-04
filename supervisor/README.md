# Queue Worker Supervisor Configuration

This directory contains Supervisor configuration files for running Laravel queue workers in production.

## Installation

### 1. Install Supervisor

**Ubuntu/Debian:**
```bash
sudo apt-get install supervisor
```

**CentOS/RHEL:**
```bash
sudo yum install supervisor
```

**macOS (via Homebrew):**
```bash
brew install supervisor
```

### 2. Configure Supervisor

1. Copy the configuration file to Supervisor's config directory:

```bash
sudo cp supervisor/attendance-queue-worker.conf /etc/supervisor/conf.d/
```

2. Update the configuration file with your actual paths and credentials:

```bash
sudo nano /etc/supervisor/conf.d/attendance-queue-worker.conf
```

Update the following:
- `/path/to/mtan` → Actual path to your application
- `www-data` → Your web server user (or `forge`, `ubuntu`, etc.)
- Database credentials in the `environment` section

3. Reload Supervisor configuration:

```bash
sudo supervisorctl reread
sudo supervisorctl update
```

4. Start the queue workers:

```bash
sudo supervisorctl start attendance-queue-worker:*
```

## Queue Worker Configuration

The configuration runs **3 queue worker processes** for the `attendance` queue with the following settings:

- **Queue**: `attendance` (dedicated queue for MQTT attendance events)
- **Connection**: `database` (uses central database for job queue)
- **Sleep**: 3 seconds between polling for new jobs
- **Tries**: 3 attempts before marking a job as failed
- **Max Time**: 3600 seconds (1 hour) before restarting worker
- **Timeout**: 60 seconds per job execution

## Supervisor Commands

```bash
# Check status
sudo supervisorctl status attendance-queue-worker:*

# Start workers
sudo supervisorctl start attendance-queue-worker:*

# Stop workers
sudo supervisorctl stop attendance-queue-worker:*

# Restart workers (after code deployment)
sudo supervisorctl restart attendance-queue-worker:*

# View logs
sudo tail -f /path/to/mtan/storage/logs/queue-worker.log
```

## After Code Deployment

After deploying new code, restart the queue workers to load the latest changes:

```bash
sudo supervisorctl restart attendance-queue-worker:*
```

Or use the Laravel queue restart command:

```bash
php artisan queue:restart
```

This will gracefully restart workers after they finish processing current jobs.

## Monitoring

### Failed Jobs

Check failed jobs:

```bash
php artisan queue:failed
```

Retry failed jobs:

```bash
php artisan queue:retry all
```

Retry a specific failed job:

```bash
php artisan queue:retry <job-id>
```

### Queue Statistics

Monitor queue in real-time:

```bash
php artisan queue:work database --queue=attendance --verbose
```

### Logs

Queue worker logs are stored in:
- Supervisor: `/path/to/mtan/storage/logs/queue-worker.log`
- Laravel: `/path/to/mtan/storage/logs/laravel.log`
- MQTT: `/path/to/mtan/storage/logs/mqtt.log`

## Troubleshooting

### Workers not starting

1. Check Supervisor status:
```bash
sudo supervisorctl status
```

2. Check Supervisor logs:
```bash
sudo tail -f /var/log/supervisor/supervisord.log
```

3. Check worker logs:
```bash
sudo tail -f /path/to/mtan/storage/logs/queue-worker.log
```

### Database connection errors

Ensure the environment variables in the config file match your `.env` settings:
- `DB_CONNECTION`
- `DB_HOST`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

### Jobs not processing

1. Verify queue connection:
```bash
php artisan queue:work database --queue=attendance --once
```

2. Check for jobs in the database:
```bash
mysql -u root -p -e "SELECT * FROM jobs WHERE queue = 'attendance';" attendance_central
```

3. Check failed jobs:
```bash
php artisan queue:failed
```

## Scaling Workers

To increase the number of worker processes, edit the config file:

```bash
sudo nano /etc/supervisor/conf.d/attendance-queue-worker.conf
```

Change `numprocs` from `3` to your desired number:

```ini
numprocs=5  ; Run 5 worker processes
```

Then reload and update Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart attendance-queue-worker:*
```

## Production Best Practices

1. **Monitoring**: Set up monitoring alerts for failed jobs and worker health
2. **Logging**: Use log rotation for queue worker logs
3. **Scaling**: Adjust `numprocs` based on MQTT message volume
4. **Timeouts**: Tune `max-time` and `timeout` based on job complexity
5. **Database**: Ensure the jobs table is indexed properly
6. **Graceful Restarts**: Always use `queue:restart` before restarting workers manually

## Multiple Queues

If you need to process multiple queues with different priorities:

```bash
php artisan queue:work database --queue=high-priority,attendance,default
```

Update the Supervisor config command accordingly.
