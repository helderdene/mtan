# Spec Tasks

These are the tasks to be completed for the spec detailed in @.agent-os/specs/2025-10-05-failed-job-handling/spec.md

> Created: 2025-10-05
> Status: ✅ **87.5% COMPLETE** - Phases 1-6 + 8 Implemented (7/8)

## Tasks

### Phase 1: Retry Configuration ✅ COMPLETE
- [x] Update job classes with retry properties (`$tries`, `$backoff`, `$timeout`, `$maxExceptions`)
- [x] Implement `backoff()` method returning exponential backoff array [60, 300, 900]
- [x] Add error handling in `handle()` method with retry logic
- [x] Implement `failed()` method for permanent failure handling
- [x] Add comprehensive logging for retry attempts
- [x] Create CriticalJobFailedNotification for admin alerts
- [x] Add failed_jobs log channel to config/logging.php
- [x] Add MAIL_ADMIN_EMAIL to .env.example and config/mail.php
- [ ] Test retry behavior with forced failures (deferred to Phase 7)

### Phase 2: Failed Job Model ✅ COMPLETE
- [x] Create `FailedJob` model at `app/Models/FailedJob.php`
- [x] Configure table, fillable, and casts properties
- [x] Implement `scopeQueue()` scope for filtering by queue name
- [x] Implement `scopeFailedBetween()` scope for date range filtering
- [x] Add `getJobClassAttribute()` accessor to extract job class from payload
- [x] Add `getDecodedPayloadAttribute()` accessor for JSON payload parsing
- [x] Add `getJobDataAttribute()` accessor for extracting job data
- [x] Add `getFailedTimeAgoAttribute()` accessor for human-readable time
- [x] Create migration for failed_jobs table with UUID primary key
- [ ] Write unit tests for model scopes and accessors (deferred to Phase 7)

### Phase 3: Retry Commands ✅ COMPLETE
- [x] Create `RetryFailedJobCommand` at `app/Console/Commands/RetryFailedJobCommand.php`
- [x] Define command signature with id argument and queue/all options
- [x] Implement `retryJob()` method for individual job retry
- [x] Implement `retryQueue()` method for queue-specific bulk retry
- [x] Implement `retryAll()` method for retrying all failed jobs
- [x] Add confirmation prompts for bulk operations
- [x] Add progress bars for bulk operations
- [x] Command auto-registers in Laravel 11 (no manual registration needed)
- [ ] Write feature tests for command execution (deferred to Phase 7)

### Phase 4: API Endpoints ✅ COMPLETE
- [x] Create `FailedJobController` at `app/Http/Controllers/Api/FailedJobController.php`
- [x] Implement `index()` method with filtering and pagination
- [x] Implement `show()` method for detailed job view
- [x] Implement `retry()` method for single job retry
- [x] Implement `retryAll()` method for bulk retry (with optional queue filter)
- [x] Implement `destroy()` method for deleting failed jobs
- [x] Implement `prune()` method for deleting old failed jobs
- [x] Add routes to `routes/api.php` (nested under v1 prefix)
- [x] Add authorization middleware (admin only via auth:sanctum)
- [ ] Write API tests for all endpoints (deferred to Phase 7)

### Phase 5: Alerting and Logging ✅ COMPLETE (implemented in Phase 1)
- [x] Create `CriticalJobFailedNotification` at `app/Notifications/CriticalJobFailedNotification.php`
- [x] Implement `via()` method returning ['mail']
- [x] Implement `toMail()` method with error details
- [x] Add `MAIL_ADMIN_EMAIL` to `.env.example` and `config/mail.php`
- [x] Create `failed_jobs` log channel in `config/logging.php`
- [x] Update job `failed()` methods to send notifications (ProcessAttendanceEvent, SyncEmployeeToDevices)
- [x] Add comprehensive logging to job failure handling (retry attempts, backoff times)
- [ ] Test notification delivery on job failure (deferred to Phase 7)

### Phase 6: Scheduled Maintenance ✅ COMPLETE
- [x] Add `queue:prune-failed` command to schedule in `routes/console.php` (Laravel 11)
- [x] Configure weekly pruning of jobs older than 168 hours (7 days) - Sunday 2:00 AM
- [ ] Test scheduled task execution (deferred to Phase 7)
- [ ] Document maintenance schedule in deployment docs (Phase 8)

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

### Phase 8: Documentation ✅ COMPLETE
- [x] Document retry configuration in CLAUDE.md
- [x] Document manual retry commands and API usage
- [x] Add troubleshooting guide for failed jobs
- [x] Document queue worker configuration
- [x] Add admin notification examples
- [x] Document API endpoints with examples
- [x] Add best practices section
