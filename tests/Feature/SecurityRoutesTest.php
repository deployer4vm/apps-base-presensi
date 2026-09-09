<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityRoutesTest extends TestCase
{
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
