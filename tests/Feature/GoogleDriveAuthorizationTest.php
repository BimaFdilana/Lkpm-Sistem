<?php

namespace Tests\Feature;

use App\GoogleDriveStorage;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class GoogleDriveAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_kepala_bagian_can_start_google_drive_authorization(): void
    {
        $user = User::factory()->create(['role' => 'kepala_bagian']);

        $this->mock(GoogleDriveStorage::class, function (MockInterface $mock): void {
            $mock->shouldReceive('authorizationUrl')
                ->once()
                ->withArgs(fn (string $state): bool => mb_strlen($state) === 64)
                ->andReturn('https://accounts.google.com/o/oauth2/auth');
        });

        $this->actingAs($user)
            ->get(route('google-drive.connect'))
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth')
            ->assertSessionHas('google_drive.oauth_state');
    }

    public function test_google_drive_callback_stores_authorization_for_kepala_bagian(): void
    {
        $user = User::factory()->create(['role' => 'kepala_bagian']);

        $this->mock(GoogleDriveStorage::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('completeAuthorization')
                ->once()
                ->with('authorization-code', $user);
        });

        $this->actingAs($user)
            ->withSession(['google_drive.oauth_state' => 'expected-state'])
            ->get(route('google-drive.callback', ['state' => 'expected-state', 'code' => 'authorization-code']))
            ->assertRedirect(route('imports.index'))
            ->assertSessionHas('status');
    }

    public function test_pic_receives_forbidden_response_for_google_drive_authorization(): void
    {
        $pic = User::factory()->create(['role' => 'pic']);

        $this->actingAs($pic)->get(route('google-drive.connect'))->assertForbidden();
    }

    public function test_google_drive_callback_rejects_an_unexpected_state(): void
    {
        $user = User::factory()->create(['role' => 'programmer']);

        $this->actingAs($user)
            ->withSession(['google_drive.oauth_state' => 'expected-state'])
            ->get(route('google-drive.callback', ['state' => 'unexpected-state', 'code' => 'authorization-code']))
            ->assertForbidden();
    }
}
