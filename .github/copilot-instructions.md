# Copilot Instructions for ERP/CRM SaaS Platform

## Project Overview

This is a multi-tenant, enterprise-grade ERP/CRM SaaS platform built with Laravel and Vue.js. The platform is designed to be fully dynamic, configurable, extensible, and reusable with metadata-driven, plugin-style architecture that allows customization without code changes.

**Key characteristics:**
- Multi-tenant SaaS architecture with strict tenant isolation
- Hierarchical multi-level organizational structures
- Comprehensive ERP and CRM functionality
- Modular, plugin-based design enabling dynamic module installation/removal
- API-first development approach
- Enterprise-grade security and compliance

## Tech Stack & Key Dependencies

### Backend
- **PHP 8.4+** with Laravel 12.x
- **Database:** MySQL/PostgreSQL with strict schema management
- **Authentication:** JWT-based stateless authentication
- **Queue System:** Native Laravel queues for event-driven workflows
- **API:** RESTful APIs with OpenAPI/Swagger documentation

### Frontend
- **Vue.js 3.x** with Composition API
- **Styling:** Tailwind CSS and AdminLTE components
- **Build Tools:** Vite

### Core Principles
- **Use ONLY native Laravel and Vue features** - avoid third-party packages unless LTS, stable, essential, and officially supported
- **No experimental, deprecated, or abandoned dependencies**
- Manual implementation preferred over external libraries

## Architectural Principles

### Clean Architecture & Domain-Driven Design
- Enforce **Clean Architecture** with clear separation of concerns
- Apply **Domain-Driven Design (DDD)** with well-defined bounded contexts
- Implement **SOLID principles** (Single Responsibility, Open/Closed, Liskov Substitution, Interface Segregation, Dependency Inversion)
- Follow **DRY** (Don't Repeat Yourself) and **KISS** (Keep It Simple, Stupid) principles
- Maintain **API-first development** approach

### Modular Architecture
- Design all modules as **fully loosely coupled, plugin-style components**
- Enable **dynamic module installation and removal** without code changes
- Use **metadata-driven configuration** stored in database or configuration files
- Implement **runtime-configurable behavior** for all business rules and workflows
- Ensure **clear domain boundaries** between modules

### Module Categories
Implement comprehensive modules including but not limited to:
- Tenant & Organization Management
- User Management & Authentication
- Role-Based Access Control (RBAC) & Attribute-Based Access Control (ABAC)
- Product & Service Management
- Inventory & Warehouse Management
- Sales & CRM
- Purchasing & Procurement
- Accounting & Finance
- Human Resources & Payroll
- Manufacturing & Production
- Project Management
- Reporting & Analytics

## Coding Guidelines

### Code Quality Standards
- Write **clean, readable, maintainable, and production-ready** code
- Use **meaningful and consistent naming conventions**
- Include comprehensive **inline documentation** for complex logic
- No placeholders, TODOs, or partial implementations in production code
- All code must be **enterprise-grade and scalable**

### Naming Conventions
- Classes: PascalCase (e.g., `ProductService`, `TenantRepository`)
- Methods/Functions: camelCase (e.g., `calculatePrice`, `getTenantById`)
- Variables: camelCase (e.g., `userId`, `tenantConfig`)
- Constants: UPPER_SNAKE_CASE (e.g., `MAX_RETRY_ATTEMPTS`, `DEFAULT_CURRENCY`)
- Database tables: plural snake_case (e.g., `products`, `tenant_organizations`)
- Database columns: snake_case (e.g., `created_at`, `tenant_id`)

### Configuration Management
- Use **enums** for fixed value sets instead of hardcoded strings
- Store configuration in **environment variables** (.env) and config files
- Use **database-driven metadata** for runtime configuration
- Never hardcode business rules, prices, or workflow logic

### File Organization
```
app/
├── Domain/           # Domain layer (business logic, entities, repositories)
│   ├── Tenant/
│   ├── Product/
│   ├── Sales/
│   └── ...
├── Application/      # Application layer (use cases, services)
│   ├── Tenant/
│   ├── Product/
│   └── ...
├── Infrastructure/   # Infrastructure layer (implementations, external services)
│   ├── Persistence/
│   ├── Cache/
│   └── ...
└── Presentation/     # Presentation layer (controllers, resources)
    ├── Http/
    └── Api/
```

## Security & Multi-Tenancy

### Tenant Isolation
- Implement **strict tenant and organizational isolation** at all layers
- Use tenant-scoped queries with global scopes or middleware
- Never leak data between tenants
- Validate tenant context on every request
- Implement row-level security where applicable

### Authentication & Authorization
- Use **JWT-based stateless authentication** with secure token lifecycle management
- Implement **secure token refresh** and revocation mechanisms
- Enforce **RBAC (Role-Based Access Control)** using native Laravel policies
- Enforce **ABAC (Attribute-Based Access Control)** for fine-grained permissions
- Use Laravel middleware for authentication and authorization checks
- Never trust client-side data - always validate on server

### Security Best Practices
- Implement **comprehensive audit logging** for all critical operations
- Use **pessimistic and optimistic locking** for concurrency control
- Implement **input validation and sanitization** at all entry points
- Use **parameterized queries** to prevent SQL injection
- Implement **rate limiting** on APIs
- Use **CSRF protection** for web forms
- Encrypt sensitive data at rest and in transit
- Follow **OWASP security guidelines**

## Data Management

### Database Design
- Use **proper indexing** for performance
- Implement **foreign key constraints** for referential integrity
- Use **database transactions** for atomic operations
- Implement **soft deletes** for audit trails
- Use **UUID or ULID** for tenant-scoped entities when appropriate
- Design for **horizontal scalability** from the start

### Decimal Calculations
- Use **BCMath** for precise decimal calculations (pricing, accounting)
- Never use floating-point arithmetic for monetary values
- Store monetary values as integers (cents) when appropriate

### Concurrency Control
- Implement **pessimistic locking** for critical updates
- Use **optimistic locking** with version columns where appropriate
- Handle **database deadlocks** gracefully with retry logic
- Use Laravel's database locking features natively

## Product & Pricing System

### Product Model
- Support **products, services, bundles, and composite offerings**
- Implement **configurable buying and selling units** (UOM)
- Support **location-based pricing**
- Enable **variant management** (size, color, etc.)

### Pricing Engine
- Design as **extensible, rule-driven pricing engine**
- Support **multiple calculation methods:**
  - Flat pricing
  - Percentage-based pricing
  - Tiered pricing
  - Volume discounts
  - Dynamic pricing rules
- Make pricing rules **metadata-driven and configurable**
- Support **multi-currency** with exchange rate management

## Event-Driven Architecture

### Event System
- Use **native Laravel events and listeners**
- Implement **event sourcing** where appropriate for audit trails
- Use **queues** for asynchronous processing
- Implement **job pipelines** for complex workflows
- Use **Laravel processes** for external command execution
- Design events for **loose coupling** between modules

### Queue Management
- Use appropriate queue drivers (database, Redis, SQS)
- Implement **job retry logic** with exponential backoff
- Monitor queue health and performance
- Handle job failures gracefully

## API Development

### API Standards
- Follow **RESTful principles** for API design
- Use appropriate HTTP methods (GET, POST, PUT, PATCH, DELETE)
- Return proper HTTP status codes
- Implement **API versioning** (e.g., `/api/v1/`)
- Use **resource transformers** for consistent responses
- Implement **pagination** for list endpoints
- Provide **comprehensive API documentation** using OpenAPI/Swagger

### API Security
- Require authentication for all protected endpoints
- Implement **API rate limiting**
- Validate all input data with **form requests**
- Return consistent error responses
- Never expose internal errors to clients

## Testing Standards

### Test Coverage
- Write **unit tests** for business logic
- Write **feature tests** for API endpoints
- Write **integration tests** for critical workflows
- Aim for **high test coverage** (>80%) on critical paths
- Use **database transactions** in tests for isolation

### Testing Principles
- Use **factories** for test data generation
- Mock external services
- Test edge cases and error conditions
- Keep tests **fast and reliable**
- Follow **Arrange-Act-Assert** pattern

## Documentation Requirements

### Code Documentation
- Document **complex business logic** with inline comments
- Use **PHPDoc blocks** for classes and methods
- Include **parameter types** and **return types**
- Document **exceptions** that may be thrown

### Module Documentation
- Each module must have **clear documentation** including:
  - Purpose and scope
  - Domain entities and relationships
  - API endpoints
  - Events published/consumed
  - Configuration options
  - Dependencies

### API Documentation
- Generate **OpenAPI/Swagger documentation** for all APIs
- Include **request/response examples**
- Document **authentication requirements**
- Document **rate limits**

## Development Workflow

### Code Review Standards
- All code must be **reviewed** before merging
- Check for **security vulnerabilities**
- Verify **test coverage**
- Ensure **code quality** and adherence to standards
- Verify **documentation** completeness

### Git Workflow
- Use **feature branches** for development
- Write **clear, descriptive commit messages**
- Keep commits **atomic and focused**
- Squash commits before merging when appropriate

### Performance Considerations
- Optimize database queries (use eager loading)
- Implement **caching** where appropriate using native Laravel cache
- Monitor **query performance** with database query log
- Use **queue jobs** for time-consuming operations
- Implement **pagination** for large datasets

## Key Resources

### Reference Repositories
- [kasunvimarshana/kv-saas-crm-erp](https://github.com/kasunvimarshana/kv-saas-crm-erp)
- [kasunvimarshana/kv-saas-erp-crm](https://github.com/kasunvimarshana/kv-saas-erp-crm)
- [kasunvimarshana/AutoERP](https://github.com/kasunvimarshana/AutoERP)
- [kasunvimarshana/PHP_POS](https://github.com/kasunvimarshana/PHP_POS)
- [kasunvimarshana/kv-erp](https://github.com/kasunvimarshana/kv-erp)

### Laravel Documentation
- [Laravel 12.x Documentation](https://laravel.com/docs/12.x)
- [Laravel Packages](https://laravel.com/docs/12.x/packages)
- [Laravel Authentication](https://laravel.com/docs/12.x/authentication)
- [Laravel Filesystem](https://laravel.com/docs/12.x/filesystem)
- [Laravel Processes](https://laravel.com/docs/12.x/processes)
- [Laravel Pipelines](https://laravel.com/docs/12.x/helpers#pipeline)

### Architecture & Design
- [Clean Architecture Blog](https://blog.cleancoder.com/atom.xml)
- [SOLID Principles](https://en.wikipedia.org/wiki/SOLID)
- [Modular Design](https://en.wikipedia.org/wiki/Modular_design)
- [Plugin Architecture](https://en.wikipedia.org/wiki/Plug-in_(computing))
- [Enterprise Resource Planning](https://en.wikipedia.org/wiki/Enterprise_resource_planning)

### Best Practices Articles
- [Building Multi-Tenant Architecture (Laravel)](https://laravel.com/blog/building-a-multi-tenant-architecture-platform-to-scale-the-emmys)
- [Building Modular Systems in Laravel](https://sevalla.com/blog/building-modular-systems-laravel)
- [Polymorphic Translatable Models in Laravel](https://dev.to/rafaelogic/building-a-polymorphic-translatable-model-in-laravel-with-autoloaded-translations-3d99)
- [Database Locking and Concurrency in Laravel](https://dev.to/bhaidar/understanding-database-locking-and-concurrency-in-laravel-a-deep-dive-2k4m)
- [Managing Data Races with Pessimistic Locking](https://laravel-news.com/managing-data-races-with-pessimistic-locking-in-laravel)
- [Pessimistic & Optimistic Locking](https://dev.to/tegos/pessimistic-optimistic-locking-in-laravel-23dk)
- [Decimal Calculations with BCMath](https://dev.to/takeshiyu/handling-decimal-calculations-in-php-84-with-the-new-bcmath-object-api-442j)
- [Multi-Guard Authentication in Laravel 12](https://dev.to/preciousaang/multi-guard-authentication-with-laravel-12-1jg3)
- [Uploading Files in Laravel](https://laravel-news.com/uploading-files-laravel)

### UI/Design Resources
- [AdminLTE](https://adminlte.io)
- [Tailwind CSS](https://tailwindcss.com)
- [Swagger/OpenAPI](https://swagger.io)

### Reference Platforms
- [Odoo ERP System](https://github.com/odoo/odoo) - Study modular architecture and domain design

## Important Notes

### What NOT to Do
- ❌ Do not use third-party packages when native Laravel/Vue features exist
- ❌ Do not use experimental, deprecated, or abandoned dependencies
- ❌ Do not hardcode business logic, prices, or configuration values
- ❌ Do not create tight coupling between modules
- ❌ Do not skip validation or security checks
- ❌ Do not leave placeholders or TODOs in production code
- ❌ Do not commit code without tests
- ❌ Do not violate tenant isolation
- ❌ Do not use floating-point arithmetic for monetary calculations

### What TO Do
- ✅ Use native Laravel and Vue features exclusively
- ✅ Implement features manually with clean, readable code
- ✅ Design for configurability and extensibility
- ✅ Maintain strict tenant isolation
- ✅ Enforce security at all layers
- ✅ Write comprehensive tests
- ✅ Document thoroughly
- ✅ Follow SOLID principles
- ✅ Use metadata-driven configuration
- ✅ Design for scalability and maintainability

## Summary

When contributing to this project, always act as a Full-Stack Engineer and Principal Systems Architect. Thoroughly audit and analyze all existing code, documentation, and resources to maintain a complete understanding of the system. Design and implement features that are fully dynamic, configurable, extensible, and reusable. Ensure all implementations use native Laravel and Vue features, follow clean architecture principles, maintain strict security and isolation, and produce enterprise-grade, production-ready code without shortcuts or technical debt.
