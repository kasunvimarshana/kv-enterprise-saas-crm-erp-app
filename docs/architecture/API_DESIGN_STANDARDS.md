# API Design Standards & RESTful Best Practices

## Overview

This document defines the API design standards for the multi-tenant enterprise ERP/CRM SaaS platform. All APIs must follow RESTful principles, maintain consistency, and provide excellent developer experience.

## Core Principles

### 1. API-First Development

- Design APIs before implementation
- Use OpenAPI/Swagger for specification
- APIs are contracts between systems
- Backward compatibility is mandatory
- Breaking changes require new API version

### 2. RESTful Design

- Use HTTP methods correctly
- Resources are nouns, not verbs
- Hierarchical URL structure
- Stateless requests
- Standard HTTP status codes

### 3. Consistency

- Uniform naming conventions
- Consistent error responses
- Standard pagination format
- Predictable behavior across endpoints

## API Versioning

### URL-Based Versioning

All APIs are versioned in the URL:

```
https://api.example.com/api/v1/tenants
https://api.example.com/api/v2/tenants
```

**Version Format**: `v{major}`

**When to Create New Version:**
- Breaking changes to request/response format
- Removing endpoints
- Changing authentication mechanism
- Significant behavioral changes

**Backward Compatibility:**
- Additive changes (new fields, new endpoints) don't require versioning
- Old versions supported for minimum 12 months after deprecation notice

## HTTP Methods

### Standard Methods

| Method | Purpose | Idempotent | Safe |
|--------|---------|------------|------|
| GET | Retrieve resource(s) | Yes | Yes |
| POST | Create new resource | No | No |
| PUT | Replace entire resource | Yes | No |
| PATCH | Update partial resource | No | No |
| DELETE | Delete resource | Yes | No |

### Usage Examples

```http
GET    /api/v1/products           # List all products
GET    /api/v1/products/{id}      # Get single product
POST   /api/v1/products           # Create new product
PUT    /api/v1/products/{id}      # Replace product (all fields)
PATCH  /api/v1/products/{id}      # Update product (some fields)
DELETE /api/v1/products/{id}      # Delete product
```

### Special Actions

For actions that don't fit CRUD:

```http
POST   /api/v1/tenants/{id}/activate
POST   /api/v1/orders/{id}/confirm
POST   /api/v1/invoices/{id}/send
POST   /api/v1/products/{id}/publish
```

## URL Structure

### Resource Naming

**Rules:**
- Use plural nouns for resources: `/products`, `/customers`, `/orders`
- Use kebab-case for multi-word resources: `/sales-orders`, `/purchase-orders`
- Avoid verbs in URLs (use HTTP methods instead)
- Maximum 3 levels deep: `/resources/{id}/sub-resources/{id}/items`

**Examples:**

```http
✅ Good:
GET /api/v1/customers
GET /api/v1/customers/{id}/orders
GET /api/v1/products/{id}/variants

❌ Bad:
GET /api/v1/getCustomers
GET /api/v1/customer
GET /api/v1/customers/{id}/orders/{orderId}/items/{itemId}/details
```

### Hierarchical Resources

```http
GET /api/v1/tenants/{tenantId}/organizations
GET /api/v1/tenants/{tenantId}/organizations/{orgId}/users
GET /api/v1/orders/{orderId}/items
GET /api/v1/products/{productId}/variants
```

### Filtering, Sorting, Pagination

Use query parameters:

```http
# Filtering
GET /api/v1/products?status=active&category=electronics

# Sorting
GET /api/v1/products?sort=name:asc,price:desc

# Pagination
GET /api/v1/products?page=2&per_page=20

# Combining
GET /api/v1/products?status=active&sort=price:desc&page=1&per_page=25
```

## Request Format

### Headers

**Required Headers:**

```http
Content-Type: application/json
Accept: application/json
Authorization: Bearer {token}
X-Tenant-Id: {tenant-uuid}
```

**Optional Headers:**

```http
X-Organization-Id: {org-uuid}
X-Request-Id: {unique-request-id}
Accept-Language: en-US
```

### Request Body

**JSON Format:**

```json
{
  "name": "Acme Corporation",
  "domain": "acme",
  "contact": {
    "email": "admin@acme.com",
    "phone": "+1-555-0100"
  },
  "settings": {
    "timezone": "America/New_York",
    "currency": "USD"
  }
}
```

**Field Naming:**
- Use snake_case for field names
- Be consistent across all endpoints
- Use clear, descriptive names

### Example Requests

#### Create Resource (POST)

```http
POST /api/v1/products
Content-Type: application/json

{
  "name": "Wireless Mouse",
  "sku": "WM-001",
  "category_id": "cat-123",
  "price": {
    "amount": 29.99,
    "currency": "USD"
  },
  "stock_level": 100,
  "attributes": {
    "color": "black",
    "connectivity": "bluetooth"
  }
}
```

#### Update Resource (PATCH)

```http
PATCH /api/v1/products/prod-456
Content-Type: application/json

{
  "price": {
    "amount": 24.99,
    "currency": "USD"
  },
  "stock_level": 150
}
```

## Response Format

### Success Response Structure

```json
{
  "data": {
    "id": "prod-456",
    "name": "Wireless Mouse",
    "sku": "WM-001",
    "price": {
      "amount": 29.99,
      "currency": "USD"
    },
    "created_at": "2024-02-10T10:30:00Z",
    "updated_at": "2024-02-10T10:30:00Z"
  },
  "meta": {
    "request_id": "req-789"
  }
}
```

### Collection Response

```json
{
  "data": [
    {
      "id": "prod-456",
      "name": "Wireless Mouse",
      ...
    },
    {
      "id": "prod-457",
      "name": "Mechanical Keyboard",
      ...
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "total_pages": 8,
    "request_id": "req-790"
  },
  "links": {
    "first": "/api/v1/products?page=1",
    "last": "/api/v1/products?page=8",
    "prev": null,
    "next": "/api/v1/products?page=2"
  }
}
```

### Error Response Structure

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "details": [
      {
        "field": "email",
        "message": "The email field is required."
      },
      {
        "field": "price.amount",
        "message": "The price amount must be greater than 0."
      }
    ]
  },
  "meta": {
    "request_id": "req-791"
  }
}
```

## HTTP Status Codes

### Success Codes (2xx)

| Code | Meaning | Usage |
|------|---------|-------|
| 200 | OK | GET, PATCH, PUT successful |
| 201 | Created | POST successful, resource created |
| 202 | Accepted | Request accepted, processing async |
| 204 | No Content | DELETE successful |

### Client Error Codes (4xx)

| Code | Meaning | Usage |
|------|---------|-------|
| 400 | Bad Request | Invalid request format |
| 401 | Unauthorized | Missing or invalid authentication |
| 403 | Forbidden | Authenticated but not authorized |
| 404 | Not Found | Resource doesn't exist |
| 409 | Conflict | Duplicate or constraint violation |
| 422 | Unprocessable Entity | Validation failed |
| 429 | Too Many Requests | Rate limit exceeded |

### Server Error Codes (5xx)

| Code | Meaning | Usage |
|------|---------|-------|
| 500 | Internal Server Error | Unexpected server error |
| 502 | Bad Gateway | Upstream service error |
| 503 | Service Unavailable | Temporary unavailability |
| 504 | Gateway Timeout | Upstream service timeout |

## Error Handling

### Error Response Format

```json
{
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error message",
    "details": [
      {
        "field": "field_name",
        "message": "Field-specific error"
      }
    ],
    "trace_id": "trace-123" // Only in development
  },
  "meta": {
    "request_id": "req-792",
    "timestamp": "2024-02-10T10:30:00Z"
  }
}
```

### Standard Error Codes

```
VALIDATION_ERROR          - Input validation failed
AUTHENTICATION_ERROR      - Authentication failed
AUTHORIZATION_ERROR       - Insufficient permissions
RESOURCE_NOT_FOUND        - Resource doesn't exist
DUPLICATE_RESOURCE        - Resource already exists
RATE_LIMIT_EXCEEDED       - Too many requests
INTERNAL_ERROR            - Server error
SERVICE_UNAVAILABLE       - Service temporarily down
TENANT_NOT_FOUND          - Tenant doesn't exist
TENANT_INACTIVE           - Tenant not active
ORGANIZATION_NOT_FOUND    - Organization doesn't exist
```

### Validation Errors (422)

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": [
      {
        "field": "email",
        "code": "REQUIRED",
        "message": "The email field is required."
      },
      {
        "field": "price",
        "code": "MIN_VALUE",
        "message": "The price must be at least 0.01.",
        "params": {
          "min": 0.01
        }
      }
    ]
  }
}
```

## Pagination

### Offset-Based Pagination

**Request:**

```http
GET /api/v1/products?page=2&per_page=20
```

**Response:**

```json
{
  "data": [...],
  "meta": {
    "current_page": 2,
    "per_page": 20,
    "total": 150,
    "total_pages": 8,
    "from": 21,
    "to": 40
  },
  "links": {
    "first": "/api/v1/products?page=1&per_page=20",
    "last": "/api/v1/products?page=8&per_page=20",
    "prev": "/api/v1/products?page=1&per_page=20",
    "next": "/api/v1/products?page=3&per_page=20"
  }
}
```

### Cursor-Based Pagination

For large datasets or real-time data:

**Request:**

```http
GET /api/v1/events?cursor=eyJpZCI6MTAwfQ&limit=50
```

**Response:**

```json
{
  "data": [...],
  "meta": {
    "next_cursor": "eyJpZCI6MTUwfQ",
    "prev_cursor": "eyJpZCI6NTB9",
    "has_more": true
  }
}
```

## Filtering

### Query Parameters

```http
GET /api/v1/products?status=active&category=electronics&min_price=10&max_price=100
```

### Supported Operators

```http
# Equality
?status=active

# In
?status[]=active&status[]=pending

# Range
?price[gte]=10&price[lte]=100
?created_at[gte]=2024-01-01

# Like/Contains
?name[like]=mouse

# Null
?description[null]=true
```

### Advanced Filtering

```http
# Combined filters with AND logic
GET /api/v1/orders?status=pending&created_at[gte]=2024-01-01&total[gte]=100

# Relationship filters
GET /api/v1/products?category.name=Electronics&brand.country=US
```

## Sorting

### Single Field

```http
GET /api/v1/products?sort=name
GET /api/v1/products?sort=-price  # Descending
```

### Multiple Fields

```http
GET /api/v1/products?sort=category:asc,price:desc,name:asc
```

## Field Selection

### Sparse Fieldsets

Request only needed fields:

```http
GET /api/v1/products?fields=id,name,price
```

**Response:**

```json
{
  "data": [
    {
      "id": "prod-456",
      "name": "Wireless Mouse",
      "price": {
        "amount": 29.99,
        "currency": "USD"
      }
    }
  ]
}
```

## Including Related Resources

### Include Parameter

```http
GET /api/v1/orders/{id}?include=customer,items.product,shipping_address
```

**Response:**

```json
{
  "data": {
    "id": "ord-123",
    "total": 199.99,
    "customer": {
      "id": "cust-456",
      "name": "John Doe"
    },
    "items": [
      {
        "id": "item-789",
        "quantity": 2,
        "product": {
          "id": "prod-456",
          "name": "Wireless Mouse"
        }
      }
    ],
    "shipping_address": {
      "street": "123 Main St",
      "city": "New York"
    }
  }
}
```

## Rate Limiting

### Headers

```http
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1676556000
```

### Rate Limit Exceeded Response

```http
HTTP/1.1 429 Too Many Requests
Retry-After: 60

{
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Too many requests. Please try again in 60 seconds.",
    "details": {
      "limit": 1000,
      "remaining": 0,
      "reset_at": "2024-02-10T11:00:00Z"
    }
  }
}
```

## Authentication

### Bearer Token

```http
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

### Token Refresh

```http
POST /api/v1/auth/refresh
Content-Type: application/json

{
  "refresh_token": "refresh_token_here"
}
```

**Response:**

```json
{
  "access_token": "new_access_token",
  "refresh_token": "new_refresh_token",
  "expires_in": 900
}
```

## Bulk Operations

### Bulk Create

```http
POST /api/v1/products/bulk
Content-Type: application/json

{
  "items": [
    {"name": "Product 1", "sku": "SKU-001"},
    {"name": "Product 2", "sku": "SKU-002"},
    {"name": "Product 3", "sku": "SKU-003"}
  ]
}
```

**Response:**

```json
{
  "data": {
    "created": 3,
    "failed": 0,
    "items": [...]
  }
}
```

### Bulk Update

```http
PATCH /api/v1/products/bulk
Content-Type: application/json

{
  "items": [
    {"id": "prod-1", "price": 29.99},
    {"id": "prod-2", "price": 39.99}
  ]
}
```

### Bulk Delete

```http
DELETE /api/v1/products/bulk
Content-Type: application/json

{
  "ids": ["prod-1", "prod-2", "prod-3"]
}
```

## Asynchronous Operations

### Long-Running Operations

```http
POST /api/v1/reports/generate
Content-Type: application/json

{
  "type": "sales_report",
  "date_range": {
    "start": "2024-01-01",
    "end": "2024-01-31"
  }
}
```

**Response (202 Accepted):**

```json
{
  "data": {
    "job_id": "job-456",
    "status": "processing",
    "created_at": "2024-02-10T10:30:00Z"
  },
  "links": {
    "status": "/api/v1/jobs/job-456"
  }
}
```

### Check Status

```http
GET /api/v1/jobs/job-456
```

**Response:**

```json
{
  "data": {
    "job_id": "job-456",
    "status": "completed",
    "progress": 100,
    "result": {
      "file_url": "https://cdn.example.com/reports/report-789.pdf"
    },
    "created_at": "2024-02-10T10:30:00Z",
    "completed_at": "2024-02-10T10:35:00Z"
  }
}
```

## Webhooks

### Webhook Configuration

```http
POST /api/v1/webhooks
Content-Type: application/json

{
  "url": "https://your-app.com/webhooks/orders",
  "events": ["order.created", "order.updated", "order.cancelled"],
  "secret": "webhook_secret_key"
}
```

### Webhook Payload

```json
{
  "event": "order.created",
  "data": {
    "id": "ord-123",
    "status": "pending",
    ...
  },
  "timestamp": "2024-02-10T10:30:00Z",
  "signature": "sha256_signature_here"
}
```

## API Documentation

### OpenAPI/Swagger

All endpoints documented using OpenAPI 3.0:

```yaml
openapi: 3.0.0
info:
  title: ERP/CRM API
  version: 1.0.0
  description: Enterprise ERP/CRM SaaS Platform API

paths:
  /api/v1/products:
    get:
      summary: List products
      parameters:
        - name: page
          in: query
          schema:
            type: integer
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ProductCollection'
```

## Testing APIs

### Example with cURL

```bash
# Create product
curl -X POST https://api.example.com/api/v1/products \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "X-Tenant-Id: $TENANT_ID" \
  -d '{
    "name": "Wireless Mouse",
    "sku": "WM-001",
    "price": {"amount": 29.99, "currency": "USD"}
  }'

# List products with filtering
curl -X GET "https://api.example.com/api/v1/products?status=active&sort=-created_at&page=1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-Id: $TENANT_ID"
```

## Best Practices

### 1. API Consistency
- Maintain consistent naming across all endpoints
- Use same patterns for similar operations
- Keep response structures uniform

### 2. Performance
- Implement caching with ETags
- Use pagination for large datasets
- Support field selection to reduce payload size
- Optimize database queries

### 3. Security
- Always require authentication
- Implement rate limiting
- Validate all input
- Sanitize error messages (don't leak sensitive info)
- Use HTTPS only

### 4. Versioning
- Plan for backward compatibility
- Document breaking changes
- Provide migration guides
- Support old versions for reasonable period

### 5. Documentation
- Keep documentation up to date
- Provide examples for all endpoints
- Include error scenarios
- Document rate limits and quotas

## References

- REST API Design Best Practices: https://restfulapi.net/
- OpenAPI Specification: https://swagger.io/specification/
- HTTP Status Codes: https://httpstatuses.com/
- Laravel API Resources: https://laravel.com/docs/12.x/eloquent-resources

---

**Related Documentation:**
- [JWT Authentication](./JWT_AUTHENTICATION.md)
- [API V1 Reference](../api/API_V1.md)
- [Error Handling](./ERROR_HANDLING.md)
