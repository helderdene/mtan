# Historical Pattern Analysis - Lite Summary

Analyze employee attendance patterns from last 30 days to calculate typical check-in/check-out times and standard deviations. Integrates with direction detection as 20% scoring weight, improving accuracy for consistent schedules while gracefully handling new employees and schedule changes through rolling window analysis.

## Key Points
- Calculates average check-in/check-out times and standard deviations from 30-day history
- Provides 20% scoring weight to direction detection algorithm
- Caches patterns per employee with automatic invalidation on new records
