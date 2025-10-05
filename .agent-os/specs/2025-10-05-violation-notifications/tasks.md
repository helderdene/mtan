# Spec Tasks

These are the tasks to be completed for the spec detailed in @.agent-os/specs/2025-10-05-violation-notifications/spec.md

> Created: 2025-10-05
> Status: Ready for Implementation

## Tasks

### Phase 1: Core Notification Infrastructure

- [ ] Create `notification_preferences` migration for tenant databases
  - Table with user_id, notification_type, settings (JSON), enabled columns
  - Unique constraint on [user_id, notification_type]

- [ ] Create `NotificationPreference` model
  - Define fillable fields and casts
  - Add relationship to User model
  - Add accessor for settings JSON field

- [ ] Add `manager_id` to employees table
  - Create migration to add nullable foreign key to users table
  - Update Employee model with manager() relationship
  - Update User model with employees() relationship

- [ ] Create `ViolationNotification` notification class
  - Implement queued notification with ViolationNotification class
  - Use 'notifications' queue
  - Create toMail() method with proper message structure

- [ ] Create `DailyViolationDigest` notification class
  - Accept collection of violations and date
  - Calculate statistics (total, by type, by severity)
  - Generate summary email with table

### Phase 2: Email Templates

- [ ] Create `resources/views/emails/violations/` directory structure

- [ ] Create `violation-detected.blade.php` template
  - Use mail::message component
  - Display violation details in panel
  - Include conditional content based on violation type
  - Add "View Violation Details" button with route

- [ ] Create `daily-digest.blade.php` template
  - Display summary statistics in panel
  - Show breakdown by type
  - Include table with up to 20 violations
  - Add "View All Violations" button

- [ ] Test email templates with `php artisan mail:preview` (if available) or manual testing

### Phase 3: Event Integration

- [ ] Create `ViolationDetected` event class
  - Accept AttendanceViolation model
  - Implement ShouldBroadcast if real-time UI updates needed

- [ ] Create `SendViolationNotification` listener
  - Resolve employee's manager
  - Check notification preferences
  - Filter by severity threshold
  - Dispatch ViolationNotification

- [ ] Register event-listener mapping in EventServiceProvider

- [ ] Update violation creation logic to dispatch ViolationDetected event
  - Find where violations are created in codebase
  - Add `event(new ViolationDetected($violation))` after creation

### Phase 4: Daily Digest Command

- [ ] Create `SendDailyViolationDigestCommand` artisan command
  - Signature: `notifications:send-daily-violation-digest {--date=}`
  - Load managers with digest preferences enabled
  - Query violations for each manager's team
  - Send DailyViolationDigest notification

- [ ] Schedule command in `app/Console/Kernel.php`
  - Add to schedule() method
  - Set to run dailyAt('08:00')

- [ ] Test command manually with different date options

### Phase 5: Production Email Setup

- [ ] Choose production email service (SES/Postmark/SMTP)

- [ ] Configure `.env` with production credentials
  - Update MAIL_MAILER, MAIL_HOST, etc.
  - Set MAIL_FROM_ADDRESS and MAIL_FROM_NAME

- [ ] Verify email service configuration
  - Send test email
  - Check deliverability and spam score

- [ ] Set up domain authentication (SPF, DKIM, DMARC)

### Phase 6: Queue Configuration

- [ ] Add notifications queue to queue worker configuration

- [ ] Update Supervisor config (if using Supervisor)
  - Add notifications queue worker process
  - Set appropriate tries and timeout values

- [ ] Test queue processing
  - Trigger notification manually
  - Verify job is queued and processed
  - Check email delivery

### Phase 7: Testing

- [ ] Create `tests/Unit/ViolationNotificationTest.php`
  - Test notification content generation
  - Test toMail() method returns correct MailMessage
  - Test queue assignment

- [ ] Create `tests/Unit/DailyViolationDigestTest.php`
  - Test statistics calculation
  - Test violation grouping logic

- [ ] Create `tests/Feature/ViolationNotificationTest.php`
  - Test manager receives notification on violation creation
  - Test severity filtering
  - Test notification preferences respected
  - Test no notification when preferences disabled
  - Test missing manager scenario

- [ ] Create `tests/Feature/DailyDigestCommandTest.php`
  - Test command execution
  - Test date filtering
  - Test multiple managers receive correct violations
  - Test empty violation handling

- [ ] Run full test suite and verify all tests pass

### Phase 8: Documentation

- [ ] Update `.env.example` with email configuration options

- [ ] Create notification preference UI (future task - out of scope for this spec)

- [ ] Document notification system in CLAUDE.md

- [ ] Add notification monitoring and alerting setup instructions
