<!--
Sync Impact Report:
Version change: 0.0.0 → 1.0.0 (initial constitution)
Modified principles: N/A (initial creation)
Added sections: Core Principles (5 principles), Development Standards, Testing Requirements, Governance
Removed sections: N/A
Templates requiring updates: ✅ plan-template.md, ✅ spec-template.md, ✅ tasks-template.md (all aligned with new constitution)
Follow-up TODOs: None
-->

# Personal Podcasts Constitution

## Core Principles

### I. API-First Architecture
Every feature MUST be implemented as a backend API first; Frontend components consume API endpoints; All business logic resides in Laravel backend; React frontend is a thin client layer for UI interactions.

### II. Media Processing Excellence
All media files MUST be processed asynchronously through queued jobs; Media processing must be resilient with proper error handling and cleanup; Duplicate detection is mandatory before storing media files; File validation and security scanning required for all uploads.

### III. Test-Driven Development (NON-NEGOTIABLE)
Feature tests MUST be written before implementation; All user stories require independent test coverage; Integration tests mandatory for API endpoints; Unit tests required for services and business logic; Red-Green-Refactor cycle strictly enforced.

### IV. Feed Standards Compliance
RSS feeds MUST validate against RSS 2.0 specification; All feed items require proper media enclosures; Feed generation must be efficient and cacheable; Support for podcast namespaces and iTunes extensions required.

### V. Security & Data Integrity
All user uploads MUST be scanned and validated; Authentication required for all feed management operations; Proper authorization checks enforced via policies; Database transactions required for data consistency; Input validation on all user inputs.

## Development Standards

### Technology Stack Requirements
- Backend: Laravel 12.0+ with PHP 8.2+
- Frontend: React 19+ with TypeScript
- Testing: Pest PHP for backend, React Testing Library for frontend
- Database: MySQL 8.0+ or SQLite with proper migrations
- Queue: Database-backed job processing
- Containerization: Docker with docker-compose

### Code Quality Standards
- All PHP code MUST follow Laravel conventions and PSR-12
- TypeScript MUST be used for all frontend code
- ESLint and Prettier configuration mandatory
- All API endpoints MUST return proper HTTP status codes
- Error handling must be consistent across application

### Performance Requirements
- Feed generation MUST complete within 5 seconds
- Media processing jobs MUST complete within 10 minutes
- API response times MUST be under 500ms for cached endpoints
- Database queries MUST be optimized to prevent N+1 problems

## Testing Requirements

### Test Coverage Requirements
- Minimum 90% code coverage for all business logic
- Feature tests required for all user-facing functionality
- Integration tests required for all API endpoints
- Browser tests required for critical user journeys

### Test Organization
- Unit tests in `tests/Unit/` for isolated business logic
- Feature tests in `tests/Feature/` for user workflows
- Integration tests for API contracts and data flow
- Browser tests for complete user journeys

### Test Data Management
- Database factories required for all models
- Test data must be isolated between tests
- Cleanup procedures mandatory for media files in tests
- Seeders only for integration test setup

## Governance

This constitution supersedes all other development practices and guidelines. Amendments require:

1. Documentation of proposed changes with rationale
2. Review and approval by project maintainers
3. Version increment according to semantic versioning
4. Migration plan for existing code if breaking changes introduced
5. Update of all dependent templates and documentation

All pull requests MUST verify compliance with constitution principles. Complexity beyond these principles MUST be explicitly justified in pull request descriptions. Use this constitution as the primary reference for all development decisions.

**Version**: 1.0.0 | **Ratified**: 2025-11-17 | **Last Amended**: 2025-11-17
