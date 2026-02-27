# PRFlow

PRFlow is a corporate Purchase Request workflow system designed to accelerate approvals and improve internal process transparency.

## Laravel 10 + Sanctum API Setup (PHP 8.2, MySQL)

This section provides production-ready setup steps using clean structure, form requests, service layer, and token-based API authentication with Laravel Sanctum.

### 1) Install Laravel 10

```bash
# Ensure PHP 8.2+, Composer, and MySQL are installed
php -v
composer -V
mysql --version

# Create project
composer create-project laravel/laravel prflow-api "^10.0"
cd prflow-api

# Generate app key
php artisan key:generate
```

### 2) Configure MySQL connection

Update `.env`:

```env
APP_NAME=PRFlow
APP_ENV=local
APP_KEY=base64:generated-by-laravel
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=prflow
DB_USERNAME=root
DB_PASSWORD=secret
```

Create database and run initial migrations:

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS prflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate
```

### 3) Install and configure Laravel Sanctum

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

`app/Models/User.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
}
```

For API token authentication only, no extra middleware changes are required. If using SPA cookie auth, ensure `stateful` domains and CORS/session settings are configured.

### 4) Setup API authentication routes

`routes/api.php`

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});
```

### 5) Example production-style Auth module

#### Enum for token names / auth context

`app/Enums/TokenAbility.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum TokenAbility: string
{
    case ACCESS_API = 'access-api';
}
```

#### Form Requests

`app/Http/Requests/Api/RegisterRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
```

`app/Http/Requests/Api/LoginRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
```

#### Service Layer

`app/Services/Auth/AuthService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\TokenAbility;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * @param array{name: string, email: string, password: string} $payload
     * @return array{user: User, token: string}
     */
    public function register(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $user = User::query()->create([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'password' => Hash::make($payload['password']),
            ]);

            $token = $user->createToken('auth-token', [TokenAbility::ACCESS_API->value])->plainTextToken;

            return ['user' => $user, 'token' => $token];
        });
    }

    /**
     * @return array{user: User, token: string}
     */
    public function login(string $email, string $password): array
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth-token', [TokenAbility::ACCESS_API->value])->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
```

#### Example AuthController (thin controller)

`app/Http/Controllers/Api/AuthController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return response()->json($result, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            (string) $request->string('email'),
            (string) $request->string('password')
        );

        return response()->json($result);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authService->logout($user);

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }
}
```

### Quick API test commands

```bash
# Register
curl --request POST 'http://127.0.0.1:8000/api/auth/register' \
  --header 'Content-Type: application/json' \
  --data '{"name":"Admin","email":"admin@example.com","password":"password123","password_confirmation":"password123"}'

# Login
curl --request POST 'http://127.0.0.1:8000/api/auth/login' \
  --header 'Content-Type: application/json' \
  --data '{"email":"admin@example.com","password":"password123"}'

# Me (replace TOKEN)
curl --request GET 'http://127.0.0.1:8000/api/auth/me' \
  --header 'Authorization: Bearer TOKEN' \
  --header 'Accept: application/json'
```
