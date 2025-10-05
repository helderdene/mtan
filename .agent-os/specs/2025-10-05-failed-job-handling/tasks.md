# Spec Tasks

These are the tasks to be completed for the spec detailed in @.agent-os/specs/2025-10-05-failed-job-handling/spec.md

> Created: 2025-10-05
> Status: Ready for Implementation

## Tasks

### Phase 1: Retry Configuration
- [ ] Update job classes with retry properties (`$tries`, `$backoff`, `$timeout`, `$maxExceptions`)
- [ ] Implement `backoff()` method returning exponential backoff array [60, 300, 900]
- [ ] Add error handling in `handle()` method with retry logic
- [ ] Implement `failed()` method for permanent failure handling
- [ ] Add comprehensive logging for retry attempts
- [ ] Test retry behavior with forced failures

### Phase 2: Failed Job Model
- [ ] Create `FailedJob` model at `app/Models/FailedJob.php`
- [ ] Configure table, fillable, and casts properties
- [ ] Implement `scopeQueue()` scope for filtering by queue name
- [ ] Implement `scopeFailedBetween()` scope for date range filtering
- [ ] Add `getJobClassAttribute()` accessor to extract job class from payload
- [ ] Add `getDecodedPayloadAttribute()` accessor for JSON payload parsing
- [ ] Write unit tests for model scopes and accessors

### Phase 3: Retry Commands
- [ ] Create `RetryFailedJobCommand` at `app/Console/Commands/RetryFailedJobCommand.php`
- [ ] Define command signature with id argument and queue/all options
- [ ] Implement `retryJob()` method for individual job retry
- [ ] Implement `retryQueue()` method for queue-specific bulk retry
- [ ] Implement `retryAll()` method for retrying all failed jobs
- [ ] Add confirmation prompts for bulk operations
- [ ] Register command in `app/Console/Kernel.php`
- [ ] Write feature tests for command execution

### Phase 4: API Endpoints
- [ ] Create `FailedJobController` at `app/Http/Controllers/Api/FailedJobController.php`
- [ ] Implement `index()` method with filtering and pagination
- [ ] Implement `show()` method for detailed job view
- [ ] Implement `retry()` method for single job retry
- [ ] Implement `retryAll()` method for bulk retry
- [ ] Implement `destroy()` method for deleting failed jobs
- [ ] Implement `prune()` method for deleting old failed jobs
- [ ] Add routes to `routes/api.php`
- [ ] Add authorization middleware (admin only)
- [ ] Write API tests for all endpoints

### Phase 5: Alerting and Logging
- [ ] Create `CriticalJobFailedNotification` at `app/Notifications/CriticalJobFailedNotification.php`
- [ ] Implement `via()` method returning ['mail']
- [ ] Implement `toMail()` method with error details
- [ ] Add `ADMIN_EMAIL` to `.env` and `.env.example`
- [ ] Create `failed_jobs` log channel in `config/logging.php`
- [ ] Update job `failed()` methods to send notifications
- [ ] Add logging to job failure handling
- [ ] Test notification delivery on job failure

### Phase 6: Scheduled Maintenance
- [ ] Add `queue:prune-failed` command to schedule in `app/Console/Kernel.php`
- [ ] Configure weekly pruning of jobs older than 168 hours (7 days)
- [ ] Test scheduled task execution
- [ ] Document maintenance schedule in deployment docs

### Phase 7: Testing
- [ ] Create `tests/Unit/JobRetryTest.php` for testing retry logic
- [ ] Test exponential backoff calculation
- [ ] Test retry limit enforcement
- [ ] Test failed job model scopes
- [ ] Create `tests/Feature/FailedJobHandlingTest.php` for integration tests
- [ ] Test job fails after max retries
- [ ] Test manual retry via command
- [ ] Test API endpoints for failed job management
- [ ] Test notification on critical job failure
- [ ] Achieve 90%+ test coverage for retry logic

### Phase 8: Documentation
- [ ] Document retry configuration in CLAUDE.md
- [ ] Document manual retry commands and API usage
- [ ] Add troubleshooting guide for failed jobs
- [ ] Update deployment documentation with queue worker configuration
- [ ] Create runbook for handling failed job alerts
