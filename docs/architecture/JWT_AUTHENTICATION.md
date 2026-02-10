# JWT Authentication & Security Architecture

## Overview

This document describes the implementation of JWT-based stateless authentication, authorization (RBAC/ABAC), security best practices, and audit logging for the multi-tenant ERP/CRM SaaS platform.

## JWT Authentication System

### Architecture

The platform implements **fully stateless JWT authentication** using native Laravel capabilities without third-party JWT libraries.

### Token Structure

```json
{
  "header": {
    "alg": "HS256",
    "typ": "JWT"
  },
  "payload": {
    "sub": "user-uuid",
    "tenant_id": "tenant-uuid",
    "org_id": "organization-uuid",
    "roles": ["admin", "manager"],
    "permissions": ["users.create", "products.view"],
    "iat": 1676556000,
    "exp": 1676559600,
    "jti": "token-unique-id"
  },
  "signature": "..."
}
```

### Token Types

1. **Access Token**: Short-lived (15 minutes), used for API requests
2. **Refresh Token**: Long-lived (7 days), used to obtain new access tokens
3. **API Token**: Long-lived personal access tokens for API integrations

### Implementation

#### 1. Token Generation

```php
<?php

namespace App\Infrastructure\Auth;

use App\Domain\User\Entities\User;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;

class JWTTokenService
{
    private string $secret;
    private int $accessTokenTTL;  // 15 minutes
    private int $refreshTokenTTL; // 7 days

    public function __construct()
    {
        $this->secret = config('app.key');
        $this->accessTokenTTL = config('auth.jwt.access_ttl', 900);
        $this->refreshTokenTTL = config('auth.jwt.refresh_ttl', 604800);
    }

    public function generateAccessToken(User $user): string
    {
        $payload = [
            'sub' => $user->getId()->toString(),
            'tenant_id' => $user->getTenantId()->toString(),
            'org_id' => $user->getOrganizationId()?->toString(),
            'roles' => $user->getRoles()->pluck('name')->toArray(),
            'permissions' => $this->getUserPermissions($user),
            'iat' => time(),
            'exp' => time() + $this->accessTokenTTL,
            'jti' => Uuid::uuid4()->toString(),
            'type' => 'access'
        ];

        return $this->encode($payload);
    }

    public function generateRefreshToken(User $user): string
    {
        $payload = [
            'sub' => $user->getId()->toString(),
            'tenant_id' => $user->getTenantId()->toString(),
            'iat' => time(),
            'exp' => time() + $this->refreshTokenTTL,
            'jti' => Uuid::uuid4()->toString(),
            'type' => 'refresh'
        ];

        return $this->encode($payload);
    }

    public function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signature] = $parts;

        // Verify signature
        $expectedSignature = $this->sign($headerEncoded . '.' . $payloadEncoded);
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        // Decode payload
        $payload = json_decode(base64_decode($payloadEncoded), true);

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    private function encode(array $payload): string
    {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT'
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        $signature = $this->sign($headerEncoded . '.' . $payloadEncoded);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signature;
    }

    private function sign(string $data): string
    {
        $hash = hash_hmac('sha256', $data, $this->secret, true);
        return $this->base64UrlEncode($hash);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function getUserPermissions(User $user): array
    {
        $permissions = [];
        
        foreach ($user->getRoles() as $role) {
            foreach ($role->getPermissions() as $permission) {
                $permissions[] = $permission->getName();
            }
        }

        return array_unique($permissions);
    }
}
```

#### 2. Authentication Guard

```php
<?php

namespace App\Infrastructure\Auth\Guards;

use App\Infrastructure\Auth\JWTTokenService;
use App\Domain\User\Repositories\UserRepositoryInterface;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;

class JWTGuard implements Guard
{
    use GuardHelpers;

    private JWTTokenService $tokenService;
    private UserRepositoryInterface $userRepository;
    private Request $request;

    public function __construct(
        JWTTokenService $tokenService,
        UserRepositoryInterface $userRepository,
        Request $request
    ) {
        $this->tokenService = $tokenService;
        $this->userRepository = $userRepository;
        $this->request = $request;
    }

    public function user()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->getTokenFromRequest();
        
        if (!$token) {
            return null;
        }

        $payload = $this->tokenService->decode($token);
        
        if (!$payload || $payload['type'] !== 'access') {
            return null;
        }

        $userId = Uuid::fromString($payload['sub']);
        $this->user = $this->userRepository->findById($userId);

        return $this->user;
    }

    public function validate(array $credentials = []): bool
    {
        if (empty($credentials['email']) || empty($credentials['password'])) {
            return false;
        }

        $user = $this->userRepository->findByEmail($credentials['email']);

        if (!$user) {
            return false;
        }

        return password_verify($credentials['password'], $user->getPasswordHash());
    }

    private function getTokenFromRequest(): ?string
    {
        $header = $this->request->header('Authorization', '');

        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
```

#### 3. Authentication Middleware

```php
<?php

namespace App\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;

class JWTAuthenticate
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth('jwt')->user();

        if (!$user) {
            throw new AuthenticationException('Unauthenticated');
        }

        // Set user in request
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
```

#### 4. Token Refresh Flow

```php
<?php

namespace App\Application\Auth\UseCases;

use App\Infrastructure\Auth\JWTTokenService;
use App\Infrastructure\Auth\TokenBlacklist;
use App\Domain\User\Repositories\UserRepositoryInterface;
use Ramsey\Uuid\Uuid;

class RefreshTokenUseCase
{
    public function __construct(
        private JWTTokenService $tokenService,
        private TokenBlacklist $blacklist,
        private UserRepositoryInterface $userRepository
    ) {}

    public function execute(string $refreshToken): array
    {
        // Decode refresh token
        $payload = $this->tokenService->decode($refreshToken);

        if (!$payload || $payload['type'] !== 'refresh') {
            throw new InvalidTokenException('Invalid refresh token');
        }

        // Check if token is blacklisted
        if ($this->blacklist->isBlacklisted($payload['jti'])) {
            throw new TokenRevokedException('Token has been revoked');
        }

        // Get user
        $userId = Uuid::fromString($payload['sub']);
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new UserNotFoundException();
        }

        // Generate new tokens
        $newAccessToken = $this->tokenService->generateAccessToken($user);
        $newRefreshToken = $this->tokenService->generateRefreshToken($user);

        // Blacklist old refresh token
        $this->blacklist->add($payload['jti'], $payload['exp']);

        return [
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => config('auth.jwt.access_ttl', 900)
        ];
    }
}
```

#### 5. Token Blacklist (Revocation)

```php
<?php

namespace App\Infrastructure\Auth;

use Illuminate\Support\Facades\Cache;

class TokenBlacklist
{
    private const PREFIX = 'jwt_blacklist:';

    public function add(string $jti, int $expiresAt): void
    {
        $ttl = max(0, $expiresAt - time());
        Cache::put(self::PREFIX . $jti, true, $ttl);
    }

    public function isBlacklisted(string $jti): bool
    {
        return Cache::has(self::PREFIX . $jti);
    }

    public function remove(string $jti): void
    {
        Cache::forget(self::PREFIX . $jti);
    }
}
```

### Login/Logout Flow

#### Login Controller

```php
<?php

namespace App\Presentation\Api\V1\Controllers;

use App\Application\Auth\UseCases\LoginUseCase;
use App\Application\Auth\DTOs\LoginDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController
{
    public function __construct(
        private LoginUseCase $loginUseCase
    ) {}

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'tenant_id' => 'required|uuid'
        ]);

        $dto = new LoginDTO(
            email: $request->input('email'),
            password: $request->input('password'),
            tenantId: $request->input('tenant_id')
        );

        try {
            $result = $this->loginUseCase->execute($dto);

            return response()->json([
                'access_token' => $result['access_token'],
                'refresh_token' => $result['refresh_token'],
                'token_type' => 'Bearer',
                'expires_in' => $result['expires_in'],
                'user' => $result['user']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        // Extract token and blacklist it
        $token = $request->bearerToken();
        $payload = app(JWTTokenService::class)->decode($token);
        
        if ($payload) {
            app(TokenBlacklist::class)->add($payload['jti'], $payload['exp']);
        }

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->validate([
            'refresh_token' => 'required|string'
        ]);

        $result = app(RefreshTokenUseCase::class)->execute(
            $request->input('refresh_token')
        );

        return response()->json($result);
    }
}
```

## Role-Based Access Control (RBAC)

### Domain Model

```php
<?php

namespace App\Domain\Role\Entities;

use App\Domain\Permission\Entities\Permission;
use Ramsey\Uuid\UuidInterface;

class Role
{
    private UuidInterface $id;
    private UuidInterface $tenantId;
    private string $name;
    private string $description;
    private array $permissions = [];
    private RoleStatus $status;

    public function attachPermission(Permission $permission): void
    {
        $permissionId = $permission->getId()->toString();
        
        if (!isset($this->permissions[$permissionId])) {
            $this->permissions[$permissionId] = $permission;
            $this->recordEvent(new PermissionAttachedToRole($this->id, $permission->getId()));
        }
    }

    public function detachPermission(Permission $permission): void
    {
        $permissionId = $permission->getId()->toString();
        
        if (isset($this->permissions[$permissionId])) {
            unset($this->permissions[$permissionId]);
            $this->recordEvent(new PermissionDetachedFromRole($this->id, $permission->getId()));
        }
    }

    public function hasPermission(string $permissionName): bool
    {
        foreach ($this->permissions as $permission) {
            if ($permission->getName() === $permissionName) {
                return true;
            }
        }
        return false;
    }

    public function getPermissions(): array
    {
        return array_values($this->permissions);
    }
}
```

### Authorization Policies

```php
<?php

namespace App\Infrastructure\Auth\Policies;

use App\Domain\User\Entities\User;
use App\Domain\Product\Entities\Product;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('products.view');
    }

    public function view(User $user, Product $product): bool
    {
        // Check permission and tenant isolation
        return $user->hasPermission('products.view')
            && $user->getTenantId()->equals($product->getTenantId());
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('products.create');
    }

    public function update(User $user, Product $product): bool
    {
        // Check permission, tenant, and organization
        return $user->hasPermission('products.update')
            && $user->getTenantId()->equals($product->getTenantId())
            && $user->canAccessOrganization($product->getOrganizationId());
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasPermission('products.delete')
            && $user->getTenantId()->equals($product->getTenantId());
    }
}
```

### Authorization Middleware

```php
<?php

namespace App\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Auth\Access\AuthorizationException;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = auth()->user();

        if (!$user || !$user->hasPermission($permission)) {
            throw new AuthorizationException('Unauthorized');
        }

        return $next($request);
    }
}
```

## Attribute-Based Access Control (ABAC)

### ABAC Engine

```php
<?php

namespace App\Infrastructure\Auth\ABAC;

use App\Domain\User\Entities\User;

class ABACEngine
{
    public function can(User $user, string $action, $resource, array $context = []): bool
    {
        $rules = $this->getRulesForAction($action);

        foreach ($rules as $rule) {
            if ($rule->evaluate($user, $resource, $context)) {
                return true;
            }
        }

        return false;
    }

    private function getRulesForAction(string $action): array
    {
        // Load rules from database or configuration
        return [];
    }
}

interface ABACRule
{
    public function evaluate(User $user, $resource, array $context): bool;
}

class TimeBasedAccessRule implements ABACRule
{
    public function evaluate(User $user, $resource, array $context): bool
    {
        $currentHour = (int) date('H');
        
        // Allow access only during business hours (9 AM - 5 PM)
        return $currentHour >= 9 && $currentHour < 17;
    }
}

class OrganizationHierarchyRule implements ABACRule
{
    public function evaluate(User $user, $resource, array $context): bool
    {
        // User can access resources in their organization or child organizations
        return $user->canAccessOrganization($resource->getOrganizationId());
    }
}

class LocationBasedRule implements ABACRule
{
    public function evaluate(User $user, $resource, array $context): bool
    {
        $userLocation = $context['user_location'] ?? null;
        $allowedLocations = $context['allowed_locations'] ?? [];
        
        return in_array($userLocation, $allowedLocations);
    }
}
```

## Audit Logging

### Audit Log Entity

```php
<?php

namespace App\Domain\Audit\Entities;

use Ramsey\Uuid\UuidInterface;
use DateTimeImmutable;

class AuditLog
{
    private UuidInterface $id;
    private UuidInterface $tenantId;
    private ?UuidInterface $userId;
    private string $action;
    private string $entityType;
    private ?UuidInterface $entityId;
    private array $oldValues;
    private array $newValues;
    private string $ipAddress;
    private string $userAgent;
    private DateTimeImmutable $createdAt;

    public static function create(
        UuidInterface $tenantId,
        ?UuidInterface $userId,
        string $action,
        string $entityType,
        ?UuidInterface $entityId,
        array $oldValues,
        array $newValues,
        string $ipAddress,
        string $userAgent
    ): self {
        $log = new self();
        $log->id = Uuid::uuid4();
        $log->tenantId = $tenantId;
        $log->userId = $userId;
        $log->action = $action;
        $log->entityType = $entityType;
        $log->entityId = $entityId;
        $log->oldValues = $oldValues;
        $log->newValues = $newValues;
        $log->ipAddress = $ipAddress;
        $log->userAgent = $userAgent;
        $log->createdAt = new DateTimeImmutable();

        return $log;
    }
}
```

### Audit Logger Service

```php
<?php

namespace App\Infrastructure\Audit;

use App\Domain\Audit\Entities\AuditLog;
use App\Domain\Audit\Repositories\AuditLogRepositoryInterface;
use Illuminate\Http\Request;
use Ramsey\Uuid\UuidInterface;

class AuditLogger
{
    public function __construct(
        private AuditLogRepositoryInterface $repository,
        private Request $request
    ) {}

    public function log(
        UuidInterface $tenantId,
        ?UuidInterface $userId,
        string $action,
        string $entityType,
        ?UuidInterface $entityId,
        array $oldValues = [],
        array $newValues = []
    ): void {
        $log = AuditLog::create(
            tenantId: $tenantId,
            userId: $userId,
            action: $action,
            entityType: $entityType,
            entityId: $entityId,
            oldValues: $oldValues,
            newValues: $newValues,
            ipAddress: $this->request->ip(),
            userAgent: $this->request->userAgent()
        );

        $this->repository->save($log);
    }
}
```

### Audit Middleware

```php
<?php

namespace App\Presentation\Http\Middleware;

use App\Infrastructure\Audit\AuditLogger;
use Closure;
use Illuminate\Http\Request;

class AuditRequests
{
    public function __construct(
        private AuditLogger $auditLogger
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Log successful state-changing operations
        if ($response->isSuccessful() && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $user = auth()->user();
            
            $this->auditLogger->log(
                tenantId: $user?->getTenantId(),
                userId: $user?->getId(),
                action: $request->method() . ' ' . $request->path(),
                entityType: $this->extractEntityType($request),
                entityId: $this->extractEntityId($request),
                oldValues: [],
                newValues: $request->all()
            );
        }

        return $response;
    }

    private function extractEntityType(Request $request): string
    {
        // Extract from route or path
        return 'unknown';
    }

    private function extractEntityId(Request $request): ?UuidInterface
    {
        // Extract from route parameters
        return null;
    }
}
```

## Security Best Practices

### 1. Password Hashing

```php
<?php

// Use bcrypt with configurable rounds
$hashedPassword = password_hash($password, PASSWORD_BCRYPT, [
    'cost' => config('hashing.bcrypt.rounds', 12)
]);

// Verify password
if (password_verify($inputPassword, $hashedPassword)) {
    // Password is correct
}
```

### 2. Rate Limiting

```php
<?php

// routes/api.php
Route::middleware(['throttle:60,1'])->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware(['throttle:10,1'])->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
});
```

### 3. CSRF Protection

For web routes, CSRF protection is automatic via middleware:

```php
<?php

// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        \App\Http\Middleware\VerifyCsrfToken::class,
    ],
];
```

### 4. Input Validation

Always validate and sanitize input:

```php
<?php

$request->validate([
    'email' => 'required|email|max:255',
    'password' => 'required|string|min:8|confirmed',
    'name' => 'required|string|max:255',
]);
```

### 5. SQL Injection Prevention

Use parameterized queries (Eloquent ORM handles this):

```php
<?php

// ✅ Safe - parameterized
$users = DB::table('users')->where('email', $email)->get();

// ❌ Unsafe - avoid raw queries with user input
$users = DB::select("SELECT * FROM users WHERE email = '$email'");
```

### 6. XSS Prevention

Escape output in views:

```blade
{{-- ✅ Safe - escaped by default --}}
{{ $userInput }}

{{-- ❌ Unsafe - raw output --}}
{!! $userInput !!}
```

## Configuration

### JWT Configuration

```php
<?php

// config/auth.php

return [
    'defaults' => [
        'guard' => 'jwt',
    ],

    'guards' => [
        'jwt' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Infrastructure\Persistence\Eloquent\UserModel::class,
        ],
    ],

    'jwt' => [
        'secret' => env('JWT_SECRET', env('APP_KEY')),
        'access_ttl' => env('JWT_ACCESS_TTL', 900), // 15 minutes
        'refresh_ttl' => env('JWT_REFRESH_TTL', 604800), // 7 days
        'algorithm' => 'HS256',
    ],
];
```

## Testing Authentication

```php
<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Domain\User\Entities\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class JWTAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
            'tenant_id' => $user->tenant_id,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
            ]);
    }

    public function test_user_can_access_protected_route_with_token(): void
    {
        $user = User::factory()->create();
        $token = app(JWTTokenService::class)->generateAccessToken($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/user/profile');

        $response->assertStatus(200);
    }
}
```

## References

- Laravel Authentication: https://laravel.com/docs/12.x/authentication
- JWT RFC 7519: https://tools.ietf.org/html/rfc7519
- OWASP Authentication Cheat Sheet
- Multi-Guard Authentication: https://dev.to/preciousaang/multi-guard-authentication-with-laravel-12-1jg3

---

**Related Documentation:**
- [Multi-Tenancy](./MULTI_TENANCY.md)
- [RBAC/ABAC Implementation](../modules/RBAC_ABAC.md)
- [Security Best Practices](./SECURITY.md)
