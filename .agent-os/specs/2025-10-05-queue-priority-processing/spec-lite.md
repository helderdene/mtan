# Queue-based Processing with Priority Levels - Lite Summary

Configure Redis-backed queue system with 4 priority levels (high-priority, default, reporting, notifications) ensuring sub-2-second processing for critical attendance events. Includes Supervisor configuration, queue monitoring, and production deployment guide.

## Key Points
- Redis-backed queues with 4 priority levels for optimal performance
- Dedicated workers per queue with automatic restart via Supervisor
- Queue monitoring command and API endpoint for health checks
