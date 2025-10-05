# Spec Tasks

These are the tasks to be completed for the spec detailed in @.agent-os/specs/2025-10-05-queue-priority-processing/spec.md

> Created: 2025-10-05
> Status: Ready for Implementation

## Tasks

### 1. Queue Configuration
- [ ] Update `.env.example` with Redis queue configuration
- [ ] Update `config/queue.php` with optimized Redis settings
- [ ] Add Redis connection configuration for queues
- [ ] Document queue configuration in deployment guide

### 2. Job Queue Assignment
- [ ] Create base job classes for each priority level
- [ ] Update `ProcessAttendanceEvent` to use `attendance-high-priority` queue
- [ ] Create `SyncEmployeeToDevice` job with `attendance-default` queue
- [ ] Create notification jobs with `notifications` queue
- [ ] Create report generation jobs with `reporting` queue
- [ ] Add queue assignment tests for all job types

### 3. Supervisor Configuration
- [ ] Update `supervisor/attendance-high-priority-worker.conf` with correct paths
- [ ] Update `supervisor/attendance-default-worker.conf` with correct paths
- [ ] Update `supervisor/reporting-worker.conf` with correct paths
- [ ] Update `supervisor/notification-worker.conf` with correct paths
- [ ] Create Supervisor deployment script for production
- [ ] Document Supervisor setup in deployment guide

### 4. Queue Monitoring
- [ ] Create `MonitorQueuesCommand` artisan command
- [ ] Implement queue size calculation methods
- [ ] Add threshold-based alerting to monitoring command
- [ ] Schedule monitoring command to run every 5 minutes
- [ ] Add monitoring command tests

### 5. Queue Metrics API
- [ ] Create `QueueMetricsController`
- [ ] Add `/queue/metrics` API endpoint
- [ ] Implement queue size and worker count metrics
- [ ] Add authentication middleware to metrics endpoint
- [ ] Add API endpoint tests

### 6. Health Check Integration
- [ ] Update health check endpoint with queue worker status
- [ ] Add failed jobs count to health check
- [ ] Add Redis connection check to health check
- [ ] Document health check endpoint usage

### 7. Testing
- [ ] Create `QueuePriorityTest` unit tests
- [ ] Create `QueueProcessingTest` feature tests
- [ ] Test job queue assignment for all job types
- [ ] Test queue monitoring command output
- [ ] Test queue metrics API endpoint
- [ ] Test health check with queue status

### 8. Documentation
- [ ] Create queue deployment guide
- [ ] Document queue configuration for development
- [ ] Document Supervisor setup for production
- [ ] Document queue monitoring and troubleshooting
- [ ] Add queue architecture diagram to technical docs
