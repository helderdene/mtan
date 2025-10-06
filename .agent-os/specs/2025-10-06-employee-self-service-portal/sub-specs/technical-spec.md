# Technical Specification

This is the technical specification for the spec detailed in @.agent-os/specs/2025-10-06-employee-self-service-portal/spec.md

## Technical Requirements

### Backend Requirements

- **Controller:** `EmployeePortalController` with methods:
  - `index()` - Main portal page with current month data
  - `calendar()` - Calendar data for specific month
  - `dailyDetail()` - Detailed data for specific date
  - `violations()` - List violations with filtering
  - `corrections()` - List correction requests
- **API Endpoints:**
  - `GET /api/v1/employee/attendance/calendar?month=2025-10` - Calendar data
  - `GET /api/v1/employee/attendance/daily/{date}` - Daily detail
  - `GET /api/v1/employee/violations?from=X&to=Y&type=Z` - Violations list
  - `POST /api/v1/employee/violations/{id}/acknowledge` - Acknowledge violation
  - `POST /api/v1/employee/violations/{id}/dispute` - Dispute violation
  - `GET /api/v1/employee/corrections` - Correction requests list
- **Authorization:** Employee can only access their own data (`employee_id = auth()->user()->employee->id`)
- **Data Aggregation:** Use `DailyAttendanceSummary` model with eager loading for efficient queries

### Frontend Requirements

- **Page Components:**
  - `resources/js/pages/Employee/Portal.vue` - Main portal layout with tabs
  - `resources/js/pages/Employee/AttendanceCalendar.vue` - Calendar view component
  - `resources/js/pages/Employee/DailyDetail.vue` - Daily detail modal/page
  - `resources/js/pages/Employee/Violations.vue` - Violations list and management
  - `resources/js/pages/Employee/Corrections.vue` - Correction requests list
- **UI Components:**
  - `AttendanceCalendar.vue` - Calendar grid with date cells
  - `DailyDetailCard.vue` - Card showing check-in/out, work hours breakdown
  - `ViolationCard.vue` - Violation display with acknowledge/dispute actions
  - `WorkHoursSummaryCards.vue` - Summary statistics cards
  - `CorrectionRequestModal.vue` - Modal for creating correction requests
- **Layout:** Use existing `AppLayout.vue` with tab navigation for sections

### UI/UX Specifications

- **Calendar View:**
  - Month selector with previous/next navigation
  - Color coding: Green (present), Red (absent), Yellow (half-day), Blue (on-leave), Gray (weekend/holiday)
  - Clickable dates to open daily detail modal
- **Daily Detail Modal:**
  - Display check-in time, check-out time, break periods, total work hours, overtime
  - Show violations for the day with severity badges
  - Show applied corrections with "Corrected" badge
- **Violations Tab:**
  - Filterable table with columns: Date | Type | Severity | Status | Actions
  - Quick acknowledge button for pending violations
  - Dispute button opens dispute form with reason textarea
- **Work Hours Summary:**
  - Cards showing: Total Hours | Overtime Hours | Days Present | Average Daily Hours
  - Progress bar comparing actual vs expected hours
  - Month-to-date statistics

### Performance Requirements

- Calendar loads in < 1 second for a full month
- Daily detail modal opens in < 300ms
- Violations list supports pagination (20 per page)
- Calendar uses client-side caching for previously loaded months

## External Dependencies

None - uses existing Laravel, Vue 3, Inertia.js, and Reka UI components.
