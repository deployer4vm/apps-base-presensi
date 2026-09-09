<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use ReflectionMethod;
use Tests\TestCase;

class SecurityRoutesTest extends TestCase
{
    /** @test */
    public function etask_otp_throttles_are_scoped_to_the_login_flow(): void
    {
        $limiter = RateLimiter::limiter('etask-verify');
        $first = $limiter(Request::create('/api/employee/etask/verify-login', 'POST', [
            'flow_token' => str_repeat('a', 64),
        ]));
        $second = $limiter(Request::create('/api/employee/etask/verify-login', 'POST', [
            'flow_token' => str_repeat('b', 64),
        ]));

        $this->assertCount(2, $first);
        $this->assertSame(10, $first[0]->maxAttempts);
        $this->assertNotSame($first[0]->key, $second[0]->key);
        $this->assertSame($first[1]->key, $second[1]->key);
    }

    /** @test */
    public function etask_routes_use_the_named_limiters(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertContains(
            'throttle:etask-login',
            $routes->getByName('employee.api.etask.login')->gatherMiddleware()
        );
        $this->assertContains(
            'throttle:etask-verify',
            $routes->getByName('employee.api.etask.verifyLogin')->gatherMiddleware()
        );
        $this->assertContains(
            'throttle:etask-otp',
            $routes->getByName('employee.api.etask.otp')->gatherMiddleware()
        );
    }

    /** @test */
    public function presence_writes_are_throttled_per_authenticated_session(): void
    {
        $limiter = RateLimiter::limiter('presence-write');
        $firstRequest = Request::create('/api/dashboard/presence', 'POST');
        $firstRequest->setUserResolver(fn () => new class {
            public function getAuthIdentifier(): int
            {
                return 101;
            }
        });
        $secondRequest = Request::create('/api/dashboard/presence', 'POST');
        $secondRequest->setUserResolver(fn () => new class {
            public function getAuthIdentifier(): int
            {
                return 202;
            }
        });

        $first = $limiter($firstRequest);
        $second = $limiter($secondRequest);

        $this->assertSame(10, $first[0]->maxAttempts);
        $this->assertNotSame($first[0]->key, $second[0]->key);
        $this->assertSame($first[1]->key, $second[1]->key);

        $routes = app('router')->getRoutes();
        $this->assertContains(
            'throttle:presence-write',
            $routes->getByName('dashboard.presence.create')->gatherMiddleware()
        );
        $this->assertContains(
            'throttle:presence-write',
            $routes->getByName('dashboard.presence.checkout')->gatherMiddleware()
        );
    }

    /** @test */
    public function etask_sync_keeps_user_and_employee_columns_separate(): void
    {
        $controller = app(\App\MainApp\Modules\employee\Controllers\EmployeeController::class);
        $externalUser = [
            'id' => 56,
            'username' => 'Nisa Agustina M',
            'email' => 'nisa@example.test',
            'jabatan' => ['nama' => 'Programmer'],
            'divisi' => ['nama' => 'Project'],
        ];

        $userMethod = new ReflectionMethod($controller, 'buildUserPayload');
        $userMethod->setAccessible(true);
        $employeeMethod = new ReflectionMethod($controller, 'buildEmployeePayload');
        $employeeMethod->setAccessible(true);

        $userPayload = $userMethod->invoke($controller, $externalUser);
        $employeePayload = $employeeMethod->invoke($controller, $externalUser);

        $expectedUserKeys = ['username', 'name', 'email', 'role_code', 'user_type'];
        $actualUserKeys = array_keys($userPayload);
        sort($expectedUserKeys);
        sort($actualUserKeys);

        $expectedEmployeeKeys = ['api_id', 'name', 'position', 'division'];
        $actualEmployeeKeys = array_keys($employeePayload);
        sort($expectedEmployeeKeys);
        sort($actualEmployeeKeys);

        $this->assertSame($expectedUserKeys, $actualUserKeys);
        $this->assertSame($expectedEmployeeKeys, $actualEmployeeKeys);
        $this->assertArrayNotHasKey('api_token', $userPayload);
        $this->assertArrayNotHasKey('api_token', $employeePayload);
    }

    /** @test */
    public function language_endpoint_returns_login_labels(): void
    {
        $this->withoutMiddleware();

        $this->getJson('/api/sys/lang?lang=id')
            ->assertOk()
            ->assertJsonPath('auth.login.usernamecaption', 'Username/Email')
            ->assertJsonPath('auth.login.passwordcaption', 'Password')
            ->assertJsonPath('auth.login.remember_me', 'Ingat Aku')
            ->assertJsonPath('auth.login.sigincaption', 'Sign In')
            ->assertJsonPath('auth.login.forgotpassword', 'Lupa Password ?');
    }

    /** @test */
    public function etask_login_rejects_an_incomplete_request_before_contacting_upstream(): void
    {
        $this->withoutMiddleware();

        $this->postJson('/api/employee/etask/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    /** @test */
    public function etask_pending_bearer_token_is_never_exposed_to_the_browser(): void
    {
        $this->withoutMiddleware();
        config(['services.etask.base_url' => 'https://etask.example.test/api']);
        Http::fake([
            'https://etask.example.test/api/data/login' => Http::response([
                'success' => true,
                'requires_second_factor' => true,
                'method' => 'totp',
                'token' => ['token' => 'Bearer upstream-pending-secret'],
                'user' => ['id' => 42, 'email' => 'person@example.test'],
            ]),
        ]);

        $response = $this->postJson('/api/employee/etask/login', [
            'email' => 'person@example.test',
            'password' => 'correct horse battery staple',
        ])->assertOk()
            ->assertJsonPath('requires_second_factor', true)
            ->assertJsonMissing(['token' => 'Bearer upstream-pending-secret']);

        $flowToken = $response->json('flow_token');
        $cached = Cache::get('etask_sso_flow:' . hash('sha256', $flowToken));

        $this->assertIsArray($cached);
        $this->assertNotSame('Bearer upstream-pending-secret', $cached['pending_token']);
    }

    /** @test */
    public function attendance_proxy_requires_authentication(): void
    {
        $this->postJson('/api/dashboard/presence', [])
            ->assertUnauthorized();
    }

    /** @test */
    public function application_attachments_require_authentication(): void
    {
        $this->getJson('/api/application/attachment/1/0')
            ->assertUnauthorized();
    }

    /** @test */
    public function direct_public_application_attachment_urls_are_blocked(): void
    {
        $this->withoutMiddleware();

        $this->get('/storage/attachment/1/example.pdf')
            ->assertNotFound();
    }

    /** @test */
    public function system_configuration_and_editor_upload_require_authentication(): void
    {
        $this->getJson('/api/sys/config')->assertUnauthorized();
        $this->postJson('/api/sys/editor/upload')->assertUnauthorized();
    }
}
