<?php

declare(strict_types=1);

use BAGArt\TelegramBotAntispam\Events\AntispamAuditEvent;
use BAGArt\TelegramBot\Modules\TgAuditEventRegistry;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/*
 * W-D3 end-to-end seam check: the registry populated by module register()
 * calls is consumed by the audit provider inside its app->booted() callback,
 * so a module event dispatched through the real container must reach the
 * GenericModuleAuditListener and land as one chained audit_entries row.
 */

it('registers every platform module audit-event class through the registrar verb', function () {
    $declared = collect(app(TgAuditEventRegistry::class)->all());

    expect($declared->pluck('class')->all())->toContain(
        \BAGArt\TelegramBotAntispam\Events\AntispamAuditEvent::class,
        \BAGArt\TelegramBotSummarizer\Events\SummarizerAuditEvent::class,
        \BAGArt\TelegramBotTts\Events\TtsAuditEvent::class,
        \BAGArt\TelegramBotStt\Events\SttAuditEvent::class,
        \BAGArt\TelegramBotNettools\Events\NettoolsAuditEvent::class,
        \BAGArt\ProxyOperations\Events\ProxyAuditEvent::class,
        \BAGArt\TelegramBotMafia\Events\MafiaAuditEvent::class,
    );

    expect($declared->pluck('module')->all())->toContain(
        'antispam',
        'summarizer',
        'tts',
        'stt',
        'nettools',
        'proxy',
        'mafia',
    );
});

it('listens for module audit events and writes one hash-chained audit row', function () {
    $events = app(Dispatcher::class);

    expect($events->hasListeners(AntispamAuditEvent::class))->toBeTrue();

    event(new AntispamAuditEvent(
        AntispamAuditEvent::RULE_CREATED,
        botId: 'seam_bot',
        subjectType: 'antispam_rule',
        subjectId: 'rule-1',
        actorId: '42',
        context: ['name' => 'no.crypto'],
    ));

    $row = DB::table('audit_entries')
        ->where('operation', AntispamAuditEvent::RULE_CREATED)
        ->orderByDesc('sequence')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->source)->toBe('antispam')
        ->and($row->subject_type)->toBe('antispam_rule')
        ->and($row->subject_id)->toBe('rule-1')
        ->and($row->actor_id)->toBe('42')
        ->and($row->hash)->not->toBeNull()
        ->and(json_decode((string) $row->metadata, true))->toBe(['name' => 'no.crypto'])
        ->and(DB::table('audit_entries')->where('operation', AntispamAuditEvent::RULE_CREATED)->count())->toBe(1);

    $this->artisan('audit:verify')->assertExitCode(0);
});
