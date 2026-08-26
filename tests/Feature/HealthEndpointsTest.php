<?php

use App\Models\User;

it('reports liveness without touching dependencies', function () {
    $this->getJson('/health/live')
        ->assertOk()
        ->assertJson(['status' => 'live']);
});

it('reports readiness with dependency checks structure', function () {
    $response = $this->getJson('/health/ready');

    expect(in_array($response->status(), [200, 503], true))->toBeTrue()
        ->and($response->json())->toHaveKey('status')
        ->and($response->json('checks'))->toHaveKeys(['database', 'redis']);
});

it('forbids deep diagnostics for guests', function () {
    $this->getJson('/health')->assertForbidden();
});

it('exposes deep diagnostics for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/health');

    $response->assertOk()->assertJsonStructure([
        'status',
        'checks' => ['database', 'redis'],
        'runtime' => ['php', 'laravel', 'environment'],
    ]);
});

it('forbids metrics for guests', function () {
    $this->get('/health/metrics')->assertForbidden();
});

it('exposes prometheus-format metrics for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/health/metrics');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');

    expect($response->getContent())->toContain('tg_db_up')
        ->toContain('tg_redis_up')
        ->toContain('tg_outbound_queue_depth')
        ->toContain('tg_outbound_degradation')
        ->toContain('tg_dlq_depth_total')
        ->toContain('tg_sent_last24h')
        ->toContain('tg_retry_rate_limit_last24h')
        ->toContain('tg_dlq_pushed_last24h')
        // Saturation series (09 §38–§43)
        ->toContain('tg_db_latency_ms')
        ->toContain('tg_redis_latency_ms')
        ->toContain('tg_php_memory_bytes');
});

it('exposes the outbound degradation gauge with normal rank by default', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/health/metrics');

    $line = collect(explode("\n", $response->getContent()))
        ->first(fn (string $l): bool => str_starts_with($l, 'tg_outbound_degradation '));

    expect($line)->toBe('tg_outbound_degradation 0');
});
