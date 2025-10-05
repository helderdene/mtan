# Spec Requirements Document

> Spec: Queue-based Processing with Priority Levels
> Created: 2025-10-05
> Status: Planning

## Overview

Implement a robust queue-based processing system with priority levels to ensure critical attendance events are processed faster than lower-priority tasks. The system uses Redis-backed queues with dedicated workers for high-priority attendance events, standard attendance tasks, long-running reports, and notifications, ensuring sub-2-second processing for real-time events.

## User Stories

1. **Priority-Based Processing**
   - As a system administrator, I want critical attendance events processed before low-priority tasks, so that real-time attendance tracking is never delayed.
   - The system routes MQTT attendance events to high-priority queue (QoS 2), processes them within 2 seconds, while report generation and notifications run on separate lower-priority queues.

2. **Scalable Worker Management**
   - As a DevOps engineer, I want to configure multiple workers per queue with automatic restarts, so that queue processing is reliable and scalable.
   - Using Supervisor, the system runs 2 high-priority workers, 2 default workers, 1 reporting worker, and 1 notification worker, with automatic restart on failure.

3. **Queue Monitoring**
   - As a system administrator, I want to monitor queue sizes and failed jobs, so that I can identify and resolve processing bottlenecks.
   - The system provides queue metrics (pending jobs, processing jobs, failed jobs) via API endpoint and logs warnings when queues exceed thresholds.

## Spec Scope

1. **Queue Configuration** - Switch from database to Redis queue for production performance
2. **Priority Queue Setup** - Configure 4 priority levels (attendance-high-priority, attendance-default, reporting, notifications)
3. **Job Assignment** - Update existing jobs to use appropriate queue priorities
4. **Supervisor Configuration** - Production-ready Supervisor configs with correct paths and worker counts
5. **Queue Monitoring** - Commands and endpoints to monitor queue health
6. **Worker Deployment** - Documentation for deploying and managing queue workers

## Out of Scope

- Custom queue drivers beyond Redis
- Job prioritization within a single queue
- Queue-based rate limiting
- Distributed queue workers across multiple servers
- Queue worker auto-scaling based on load

## Expected Deliverable

1. Updated `.env` configuration for Redis queues
2. Job classes updated with queue assignments
3. Production Supervisor configuration files with actual paths
4. Queue monitoring command and API endpoint
5. Deployment documentation for queue workers
6. Health check integration for queue workers
7. Unit tests for job queue assignment

## Spec Documentation

- Tasks: @.agent-os/specs/2025-10-05-queue-priority-processing/tasks.md
- Technical Specification: @.agent-os/specs/2025-10-05-queue-priority-processing/sub-specs/technical-spec.md
