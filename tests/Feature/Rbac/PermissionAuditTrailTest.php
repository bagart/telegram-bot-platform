<?php

declare(strict_types=1);

use BAGArt\TelegramBotAudit\AuditEntry;
use BAGArt\TelegramBotAudit\AuditSinkContract;
use BAGArt\TelegramBotManagement\Enums\WorkspaceRole;
use BAGArt\TelegramBotManagement\Models\Workspace;
use BAGArt\TelegramBotManagement\Services\WorkspaceAdminService;
use BAGArt\TelegramBotManagement\Services\WorkspacePermissionService;
use Illuminate\Support\Facades\DB;

/**
 * Phase 6 (management-admin-rbac, ADR-003): permission and membership
 * changes reach AuditSinkContract end-to-end at the host level — the two
 * producers are WorkspaceAdminService (direct appends through
 * WorkspaceAuditRecorder) and WorkspacePermissionService (GrantCreated /
 * GrantRevoked domain events consumed by the audit module's listener).
 *
 * Spy pattern follows the access/audit package Feature precedent
 * (RecordAccessControlEventsTest, WorkspaceAdminServiceTest audit section):
 * rebind the sink before the first append, read the recorded operations back.
 */

/**
 * Spy over the audit sink: records every appended entry for assertions.
 */
final class PermissionAuditTrailSpySink implements AuditSinkContract
{
    /** @var list<AuditEntry> */
    public array $entries = [];

    public function append(AuditEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    /** @return list<string> */
    public function operations(): array
    {
        return array_map(static fn (AuditEntry $entry): string => $entry->operationString(), $this->entries);
    }
}

function auditTrailUser(string $name): int
{
    return (int) DB::table('users')->insertGetId([
        'name' => $name,
        'email' => null,
        'password' => null,
        'telegram_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function auditTrailSpy(): PermissionAuditTrailSpySink
{
    $sink = new PermissionAuditTrailSpySink();
    app()->instance(AuditSinkContract::class, $sink);

    return $sink;
}

describe('membership mutations are audited', function () {
    it('produces the four workspace/membership operations in mutation order', function () {
        $sink = auditTrailSpy();
        $service = app(WorkspaceAdminService::class);
        $owner = auditTrailUser('Audit Owner');
        $member = auditTrailUser('Audit Member');

        $workspace = $service->createWorkspace($owner, 'Audit Trail Co');
        $service->addMember($workspace->id, $owner, $member);
        $service->changeRole($workspace->id, $owner, $member, WorkspaceRole::Owner);
        $service->removeMember($workspace->id, $owner, $member);

        expect($sink->operations())->toBe([
            'workspace.created',
            'workspace.member.added',
            'workspace.member.role.changed',
            'workspace.member.removed',
        ]);

        foreach ($sink->entries as $entry) {
            expect($entry->source)->toBe('management')
                ->and($entry->metadata['workspace_id'] ?? null)->toBe($workspace->id)
                ->and($entry->actor->id)->toBe((string) $owner);
        }

        expect($sink->entries[0]->target->subjectType)->toBe('workspace')
            ->and($sink->entries[0]->target->subjectId)->toBe((string) $workspace->id)
            ->and($sink->entries[1]->target->subjectType)->toBe('workspace_member')
            ->and($sink->entries[1]->target->subjectId)->toBe("{$workspace->id}:{$member}")
            ->and($sink->entries[3]->oldState)->toBe(['role' => 'owner']);
    });

    it('skips a membership mutation that never happened', function () {
        $sink = auditTrailSpy();
        $service = app(WorkspaceAdminService::class);
        $owner = auditTrailUser('Audit Owner');

        $workspace = $service->createWorkspace($owner, 'Audit Quiet Co');

        expect(fn () => $service->addMember($workspace->id, $owner, 424242))
            ->toThrow(\BAGArt\TelegramBotManagement\Exceptions\WorkspaceAdminException::class)
            ->and($sink->operations())->toBe(['workspace.created']);
    });
});

describe('T2 grant mutations are audited', function () {
    it('records access.grant.created for a workspace-scope grantT2', function () {
        $sink = auditTrailSpy();
        $workspace = Workspace::query()->create(['name' => 'Audit Grant Co', 'slug' => 'audit-grant-co']);
        $permissions = app(WorkspacePermissionService::class);

        $permissions->grantT2($workspace->id, 'audit-bot', 4242, 'game.initiate', actorUserId: 7);

        expect($sink->operations())->toBe(['access.grant.created']);

        $entry = $sink->entries[0];
        expect($entry->source)->toBe('access-module')
            ->and($entry->target->subjectType)->toBe('grant')
            ->and($entry->target->subjectId)->toBe('4242:game.initiate')
            ->and($entry->actor->id)->toBe('7')
            ->and($entry->newState)->toMatchArray([
                'effect' => 'allow',
                'capability' => 'game.initiate',
                'scope' => 'workspace',
                'workspace_id' => $workspace->id,
            ]);
    });

    it('records created then revoked across the grant lifecycle', function () {
        $sink = auditTrailSpy();
        $workspace = Workspace::query()->create(['name' => 'Audit Lifecycle Co', 'slug' => 'audit-lifecycle-co']);
        $permissions = app(WorkspacePermissionService::class);

        $permissions->grantT2($workspace->id, 'audit-bot', 4242, 'moderation.reason.view', actorUserId: 7);
        $permissions->revokeT2($workspace->id, 'audit-bot', 4242, 'moderation.reason.view', actorUserId: 7);

        expect($sink->operations())->toBe(['access.grant.created', 'access.grant.revoked'])
            ->and($sink->entries[1]->oldState)->not->toBeNull()
            ->and(DB::table('access_grants')->count())->toBe(0);
    });
});
