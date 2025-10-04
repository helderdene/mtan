# Technical Stack

## Backend Framework
- **Application Framework:** Laravel 12+ (PHP 8.3+)
- **Authentication:** Laravel Fortify with Inertia.js integration
- **ORM:** Eloquent ORM with multi-database connection support
- **Queue System:** Redis-backed queues with multiple priority levels
- **Task Scheduling:** Laravel Scheduler for automated jobs

## Frontend Framework
- **JavaScript Framework:** Vue 3 with TypeScript
- **SPA Framework:** Inertia.js for seamless server-side/client-side integration
- **UI Component Library:** Reka UI (headless components)
- **State Management:** Vue 3 Composition API with composables
- **Icons:** Lucide Vue Next

## CSS Framework
- **CSS Framework:** Tailwind CSS 4
- **CSS Strategy:** Utility-first approach with custom components
- **Responsive Design:** Mobile-first responsive design patterns

## Database System
- **Primary Database:** MySQL 8.0+ with InnoDB storage engine
- **Multi-Tenancy Strategy:** Database-per-tenant with automatic provisioning
- **Character Set:** utf8mb4_unicode_ci for full Unicode support
- **Database Features:** Foreign keys, indexes, JSON columns, full-text search

## Caching & Session
- **Cache Driver:** Redis Cluster
- **Session Driver:** Redis
- **Cache Strategy:** Multi-layer caching (Redis + query caching)
- **Cache Key Strategy:** Tenant-isolated cache namespaces

## Import Strategy
- **Module Strategy:** Node.js modules (via Vite)
- **Build Tool:** Vite with Laravel plugin
- **Asset Management:** Laravel Vite plugin for hot module replacement

## Real-Time Communication
- **MQTT Protocol:** MQTT 5.0 with TLS support
- **MQTT Client Library:** PHP-MQTT/Client (PhpMqtt)
- **QoS Level:** QoS 2 for critical attendance messages, QoS 1 for device status
- **Topic Structure:** `mqtt/face/{device_id}/{event_type}`

## Queue & Background Processing
- **Queue Driver:** Redis
- **Queue Priority Levels:** attendance-high, attendance-default, reporting, notifications
- **Worker Management:** Supervisor for process monitoring
- **Failed Job Handling:** Database-backed failed job tracking with retry logic

## Build & Development
- **Build Tool:** Vite 5+
- **Package Manager:** npm
- **Type Checking:** TypeScript for frontend, PHPStan for backend
- **Code Formatting:** PHP CS Fixer (Pint), Prettier for frontend
- **Linting:** ESLint for JavaScript/TypeScript

## Testing
- **Backend Testing:** Pest PHP (behavior-driven testing)
- **Test Database:** SQLite in-memory for unit tests
- **Test Coverage:** PHPUnit code coverage reports
- **Feature Testing:** Laravel HTTP testing with database transactions

## Fonts Provider
- **Font System:** System fonts (native font stack)
- **Fallback Strategy:** -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif

## Icon Library
- **Icon Library:** Lucide Vue Next
- **Icon Count:** 1000+ icons
- **Icon Format:** SVG components
- **Icon Customization:** Size, color, stroke width configurable

## Application Hosting
- **Recommended:** AWS EC2, DigitalOcean Droplets, or Laravel Forge-managed VPS
- **Web Server:** Nginx with PHP-FPM
- **Process Manager:** Supervisor for queue workers and MQTT consumers
- **SSL/TLS:** Let's Encrypt with automatic renewal

## Database Hosting
- **Recommended:** Managed MySQL (AWS RDS, DigitalOcean Managed Databases)
- **Replication:** Master-replica setup for read scaling
- **Backup Strategy:** Automated daily backups with 30-day retention
- **Connection Pooling:** PgBouncer or ProxySQL for connection management

## Asset Hosting
- **Static Assets:** CDN (CloudFlare, AWS CloudFront)
- **Photo Storage:** S3-compatible object storage (AWS S3, DigitalOcean Spaces, Minio)
- **Storage Driver:** Laravel Filesystem with Flysystem adapters

## Cache & Session Hosting
- **Redis Hosting:** Managed Redis (AWS ElastiCache, Redis Cloud)
- **Redis Cluster:** Multi-node cluster for high availability
- **Redis Persistence:** AOF + RDB for data durability

## MQTT Broker Hosting
- **MQTT Broker:** EMQX, Mosquitto, or AWS IoT Core
- **Broker Configuration:** TLS 1.2+, certificate-based authentication
- **Scalability:** Clustered MQTT brokers for horizontal scaling

## Deployment Solution
- **CI/CD:** GitHub Actions or GitLab CI
- **Deployment Strategy:** Rolling deployment with zero downtime
- **Container Support:** Docker/Docker Compose for local development
- **Infrastructure as Code:** Terraform or AWS CloudFormation (optional)

## Monitoring & Logging
- **Application Monitoring:** Laravel Telescope for local debugging
- **Log Management:** Laravel Log with daily rotation
- **Error Tracking:** Sentry or Bugsnag for production error monitoring
- **Performance Monitoring:** New Relic or Datadog (optional)
- **Uptime Monitoring:** UptimeRobot or Pingdom

## Security
- **Authentication:** Laravel Fortify with two-factor authentication
- **Authorization:** Laravel Gates and Policies
- **CSRF Protection:** Built-in Laravel CSRF tokens
- **XSS Protection:** Automatic escaping in Blade and Vue
- **SQL Injection Protection:** Eloquent ORM parameter binding
- **Rate Limiting:** Laravel rate limiter middleware
- **API Security:** Bearer token authentication (Sanctum)

## Code Repository URL
- **Git Hosting:** To be configured (GitHub, GitLab, or Bitbucket)
- **Repository Structure:** Monorepo with backend and frontend in single repository
- **Branch Strategy:** GitFlow (main, develop, feature/*, hotfix/*)
- **Code Review:** Pull request workflow with required reviews
