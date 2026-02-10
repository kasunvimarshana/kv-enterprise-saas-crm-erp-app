# API Documentation - Version 1.0

## Overview

This document describes the RESTful API for the Multi-Tenant Enterprise ERP/CRM SaaS Platform. The API follows Clean Architecture principles and implements strict tenant isolation.

## Base URL

```
http://localhost:8000/api/v1
```

## Authentication

Most endpoints require tenant context via one of:
- Subdomain: `tenant1.example.com`
- Header: `X-Tenant-Id: {tenant_id}`
- JWT Token with embedded tenant_id (future implementation)

## Response Format

All responses follow a consistent JSON format:

**Success Response:**
```json
{
  "data": { ... },
  "message": "Optional success message"
}
```

**Error Response:**
```json
{
  "message": "Error description",
  "errors": {
    "field": ["Validation error message"]
  }
}
```

## HTTP Status Codes

- `200 OK` - Request succeeded
- `201 Created` - Resource created successfully
- `204 No Content` - Request succeeded with no content
- `400 Bad Request` - Invalid request data
- `401 Unauthorized` - Authentication required
- `403 Forbidden` - Insufficient permissions
- `404 Not Found` - Resource not found
- `422 Unprocessable Entity` - Validation failed
- `500 Internal Server Error` - Server error

---

## Tenant Management API

### List Tenants

Get a list of all tenants.

**Endpoint:** `GET /v1/tenants`

**Headers:**
- `Accept: application/json`
- `Content-Type: application/json`

**Response:** `200 OK`
```json
{
  "data": [
    {
      "id": "9c5f3b88-1234-5678-90ab-cdef12345678",
      "name": "Acme Corporation",
      "domain": "acme",
      "status": "active",
      "created_at": "2026-02-10 10:30:00"
    }
  ]
}
```

---

### Create Tenant

Create a new tenant with optional trial period.

**Endpoint:** `POST /v1/tenants`

**Headers:**
- `Accept: application/json`
- `Content-Type: application/json`

**Request Body:**
```json
{
  "name": "Acme Corporation",
  "domain": "acme",
  "trial_days": 30,
  "admin_name": "John Doe",
  "admin_email": "john@acme.com",
  "admin_password": "SecurePassword123"
}
```

**Validation Rules:**
- `name`: required, string, max 255 characters
- `domain`: required, string, max 255, unique
- `trial_days`: optional, integer, 1-365
- `admin_name`: optional, string, max 255
- `admin_email`: optional, valid email
- `admin_password`: optional, string, min 8 characters

**Response:** `201 Created`
```json
{
  "message": "Tenant created successfully",
  "data": {
    "id": "9c5f3b88-1234-5678-90ab-cdef12345678",
    "name": "Acme Corporation",
    "domain": "acme",
    "status": "pending"
  }
}
```

---

### Get Tenant

Get details of a specific tenant.

**Endpoint:** `GET /v1/tenants/{id}`

**Path Parameters:**
- `id` (UUID) - Tenant ID

**Headers:**
- `Accept: application/json`

**Response:** `200 OK`
```json
{
  "data": {
    "id": "9c5f3b88-1234-5678-90ab-cdef12345678",
    "name": "Acme Corporation",
    "domain": "acme",
    "status": "active",
    "settings": {},
    "trial_ends_at": "2026-03-12 10:30:00",
    "created_at": "2026-02-10 10:30:00",
    "updated_at": "2026-02-10 10:30:00"
  }
}
```

**Error Response:** `404 Not Found`
```json
{
  "message": "Tenant not found"
}
```

---

### Activate Tenant

Activate a tenant that is in pending, inactive, or suspended status.

**Endpoint:** `POST /v1/tenants/{id}/activate`

**Path Parameters:**
- `id` (UUID) - Tenant ID

**Headers:**
- `Accept: application/json`
- `Content-Type: application/json`

**Response:** `200 OK`
```json
{
  "message": "Tenant activated successfully",
  "data": {
    "id": "9c5f3b88-1234-5678-90ab-cdef12345678",
    "status": "active"
  }
}
```

**Error Response:** `400 Bad Request`
```json
{
  "message": "Tenant 9c5f3b88-1234-5678-90ab-cdef12345678 cannot be activated in its current state."
}
```

---

## Organization Management API

All organization endpoints require tenant context.

### List Organizations

Get all organizations for the current tenant.

**Endpoint:** `GET /v1/organizations`

**Headers:**
- `Accept: application/json`
- `X-Tenant-Id: {tenant_id}` (or use subdomain)

**Response:** `200 OK`
```json
{
  "data": [
    {
      "id": "9c5f3b88-5678-1234-90ab-cdef12345678",
      "name": "Head Office",
      "code": "HQ",
      "level": 0,
      "parent_id": null,
      "status": "active",
      "created_at": "2026-02-10 10:30:00"
    },
    {
      "id": "9c5f3b88-9012-3456-78ab-cdef12345678",
      "name": "Sales Department",
      "code": "SALES",
      "level": 1,
      "parent_id": "9c5f3b88-5678-1234-90ab-cdef12345678",
      "status": "active",
      "created_at": "2026-02-10 11:00:00"
    }
  ]
}
```

---

### Create Organization

Create a new organization or sub-organization.

**Endpoint:** `POST /v1/organizations`

**Headers:**
- `Accept: application/json`
- `Content-Type: application/json`
- `X-Tenant-Id: {tenant_id}` (or use subdomain)

**Request Body (Root Organization):**
```json
{
  "name": "Head Office",
  "code": "HQ"
}
```

**Request Body (Child Organization):**
```json
{
  "name": "Sales Department",
  "code": "SALES",
  "parent_id": "9c5f3b88-5678-1234-90ab-cdef12345678"
}
```

**Validation Rules:**
- `name`: required, string, max 255
- `code`: required, string, max 50, unique per tenant
- `parent_id`: optional, valid UUID, must exist in organizations

**Response:** `201 Created`
```json
{
  "message": "Organization created successfully",
  "data": {
    "id": "9c5f3b88-9012-3456-78ab-cdef12345678",
    "name": "Sales Department",
    "code": "SALES",
    "level": 1,
    "parent_id": "9c5f3b88-5678-1234-90ab-cdef12345678",
    "status": "active"
  }
}
```

---

### Get Organization

Get details of a specific organization.

**Endpoint:** `GET /v1/organizations/{id}`

**Path Parameters:**
- `id` (UUID) - Organization ID

**Headers:**
- `Accept: application/json`
- `X-Tenant-Id: {tenant_id}` (or use subdomain)

**Response:** `200 OK`
```json
{
  "data": {
    "id": "9c5f3b88-9012-3456-78ab-cdef12345678",
    "name": "Sales Department",
    "code": "SALES",
    "level": 1,
    "parent_id": "9c5f3b88-5678-1234-90ab-cdef12345678",
    "path": "/9c5f3b88-5678-1234-90ab-cdef12345678/9c5f3b88-9012-3456-78ab-cdef12345678",
    "status": "active",
    "settings": {},
    "created_at": "2026-02-10 11:00:00",
    "updated_at": "2026-02-10 11:00:00"
  }
}
```

---

### Get Organization Children

Get all direct child organizations.

**Endpoint:** `GET /v1/organizations/{id}/children`

**Path Parameters:**
- `id` (UUID) - Parent organization ID

**Headers:**
- `Accept: application/json`
- `X-Tenant-Id: {tenant_id}` (or use subdomain)

**Response:** `200 OK`
```json
{
  "data": [
    {
      "id": "9c5f3b88-9012-3456-78ab-cdef12345678",
      "name": "Sales Department",
      "code": "SALES",
      "level": 1,
      "status": "active"
    },
    {
      "id": "9c5f3b88-3456-7890-12ab-cdef12345678",
      "name": "Finance Department",
      "code": "FINANCE",
      "level": 1,
      "status": "active"
    }
  ]
}
```

---

## Common Error Responses

### Validation Error

**Status:** `422 Unprocessable Entity`
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name field is required."],
    "domain": ["The domain has already been taken."]
  }
}
```

### Tenant Not Found

**Status:** `404 Not Found`
```json
{
  "message": "Tenant not found"
}
```

### Tenant Context Missing

**Status:** `400 Bad Request`
```json
{
  "message": "Tenant not found."
}
```

### Tenant Inactive

**Status:** `403 Forbidden`
```json
{
  "message": "Tenant is not active",
  "status": "inactive"
}
```

---

## Pagination

List endpoints support pagination using query parameters:

**Query Parameters:**
- `page` (integer) - Page number (default: 1)
- `per_page` (integer) - Items per page (default: 15, max: 100)

**Example:**
```
GET /v1/organizations?page=2&per_page=20
```

**Response with Pagination:**
```json
{
  "data": [...],
  "meta": {
    "current_page": 2,
    "last_page": 5,
    "per_page": 20,
    "total": 95
  },
  "links": {
    "first": "/v1/organizations?page=1",
    "last": "/v1/organizations?page=5",
    "prev": "/v1/organizations?page=1",
    "next": "/v1/organizations?page=3"
  }
}
```

---

## Filtering and Sorting

List endpoints support filtering and sorting:

**Query Parameters:**
- `filter[field]` - Filter by field value
- `sort` - Sort field (prefix with `-` for descending)

**Example:**
```
GET /v1/organizations?filter[status]=active&sort=-created_at
```

---

## Rate Limiting

API requests are rate limited per tenant:
- **Limit:** 1000 requests per hour per tenant
- **Headers:** 
  - `X-RateLimit-Limit: 1000`
  - `X-RateLimit-Remaining: 999`
  - `X-RateLimit-Reset: 1675425600`

**Rate Limit Exceeded:**

**Status:** `429 Too Many Requests`
```json
{
  "message": "Too many requests. Please try again later."
}
```

---

## Examples

### cURL Examples

**Create Tenant:**
```bash
curl -X POST http://localhost:8000/api/v1/tenants \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Acme Corporation",
    "domain": "acme",
    "trial_days": 30
  }'
```

**Create Organization:**
```bash
curl -X POST http://localhost:8000/api/v1/organizations \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-Tenant-Id: 9c5f3b88-1234-5678-90ab-cdef12345678" \
  -d '{
    "name": "Head Office",
    "code": "HQ"
  }'
```

---

## Changelog

### Version 1.0 (2026-02-10)
- Initial API release
- Tenant management endpoints
- Organization management endpoints
- Tenant scoping and isolation
- Domain event system

---

## Support

For API support, please contact:
- Email: api-support@example.com
- Documentation: https://docs.example.com
- Status Page: https://status.example.com
