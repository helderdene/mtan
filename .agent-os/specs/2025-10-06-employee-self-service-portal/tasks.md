# Spec Tasks

These are the tasks to be completed for the spec detailed in @.agent-os/specs/2025-10-06-employee-self-service-portal/spec.md

> Created: 2025-10-06
> Status: Ready for Implementation

## Tasks

- [ ] 1. Backend API Endpoints
  - [ ] 1.1 Write tests for EmployeePortalController endpoints
  - [ ] 1.2 Create EmployeePortalController with index, calendar, dailyDetail, violations, corrections methods
  - [ ] 1.3 Create GET /api/v1/employee/attendance/calendar endpoint with month filtering
  - [ ] 1.4 Create GET /api/v1/employee/attendance/daily/{date} endpoint
  - [ ] 1.5 Create POST /api/v1/employee/violations/{id}/acknowledge endpoint
  - [ ] 1.6 Create POST /api/v1/employee/violations/{id}/dispute endpoint with reason validation
  - [ ] 1.7 Add authorization (employee can only access own data)
  - [ ] 1.8 Verify all backend tests pass

- [ ] 2. Calendar View Components
  - [ ] 2.1 Write tests for AttendanceCalendar component
  - [ ] 2.2 Create resources/js/pages/Employee/Portal.vue main layout with tabs
  - [ ] 2.3 Create AttendanceCalendar.vue with month grid and date cells
  - [ ] 2.4 Implement color coding (green=present, red=absent, yellow=half-day, blue=on-leave)
  - [ ] 2.5 Add month navigation (previous/next buttons)
  - [ ] 2.6 Implement clickable dates to open daily detail modal
  - [ ] 2.7 Add client-side caching for loaded months
  - [ ] 2.8 Verify calendar tests pass

- [ ] 3. Daily Detail and Work Hours Summary
  - [ ] 3.1 Write tests for DailyDetailCard component
  - [ ] 3.2 Create DailyDetail.vue modal/page component
  - [ ] 3.3 Display check-in/out times, break periods, work hours breakdown
  - [ ] 3.4 Show violations for the day with severity badges
  - [ ] 3.5 Show applied corrections with "Corrected" badge
  - [ ] 3.6 Create WorkHoursSummaryCards.vue with monthly statistics
  - [ ] 3.7 Add progress bar comparing actual vs expected hours
  - [ ] 3.8 Verify detail view tests pass

- [ ] 4. Violations Management Tab
  - [ ] 4.1 Write tests for violations list and actions
  - [ ] 4.2 Create resources/js/pages/Employee/Violations.vue
  - [ ] 4.3 Implement filterable table (date range, type, severity, status)
  - [ ] 4.4 Add quick acknowledge button for pending violations
  - [ ] 4.5 Create dispute form modal with reason textarea (min 10 chars)
  - [ ] 4.6 Implement ViolationCard.vue component
  - [ ] 4.7 Add pagination (20 per page)
  - [ ] 4.8 Verify violations management tests pass

- [ ] 5. Performance and User Experience
  - [ ] 5.1 Test calendar load time (target < 1 second for full month)
  - [ ] 5.2 Test daily detail modal (target < 300ms to open)
  - [ ] 5.3 Implement loading states for all async operations
  - [ ] 5.4 Add error handling and user-friendly error messages
  - [ ] 5.5 Test with large datasets (6+ months of attendance)
  - [ ] 5.6 Verify responsive design on tablet/mobile
  - [ ] 5.7 Verify all tests pass and feature is production-ready
