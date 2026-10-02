<?php

declare(strict_types=1);

use BAGArt\TelegramBotAccess\AccessControlContract;
use BAGArt\TelegramBotAccess\AccessRequest;
use BAGArt\TelegramBotAccess\Permission\NonGrantableCapabilityException;
use BAGArt\TelegramBotAccess\Permission\PermissionCatalog;
use BAGArt\TelegramBotAccess\Permission\Tier;
use BAGArt\TelegramBotManagement\Models\Workspace;
use BAGArt\TelegramBotManagement\Services\WorkspacePermissionService;
use Illuminate\Support\Facades\DB;

/**
 * Phase 6 (management-admin-rbac): the permission deny matrix —
 * tier (T0/T1/T2) × capability × grant scope (workspace / chat / deny / none)
 * × subject (member / provisioned / unprovisioned), resolved through
 * WorkspacePermissionService::can() → AccessControlContract::decide().
 *
 * Axes meaning:
 *  - scope 'workspace' asks WITHOUT a chat id (workspace-scope ask);
 *  - scope 'chat' asks WITH the chat id (chat-scope ask);
 *  - scope 'deny' = workspace-scope ALLOW plus a chat-scope DENY, asked at
 *    the chat scope where the narrowing applies;
 *  - scope 'none' asks at the workspace scope with no rows at all.
 *  Every row therefore asks at the scope it declares.
 *
 * Subject meaning:
 *  - 'member'       = platform user WITH an active wsA membership (T0);
 *  - 'provisioned'  = platform user (telegram-linked), NOT a member;
 *  - 'unprovisioned'= no platform user at all — a telegram id the identity
 *                     plane never resolved. decide() is keyed on the subject
 *     id only, so its verdict is identical for every subject: T0 membership and
 *     identity presence are NOT rungs of decide() (see the
 *     WorkspacePermissionService PHPDoc "MEMBERSHIP INTERACTION"), and the
 *     legacy-open rule for unprovisioned telegram ids lives in the gates
 *     (tgbot-game-mafia/src/Auth/T2Gate.php:50), not here.
 */

/**
 * tier key => [capability under test, grant-path error class or null].
 *
 * @var array<string, array{capability: string, grantError: class-string<Throwable>|null}>
 */
$rbacMatrixTiers = [
    'T0' => ['capability' => 'menu.invoke', 'grantError' => InvalidArgumentException::class],
    'T1' => ['capability' => 'ban', 'grantError' => NonGrantableCapabilityException::class],
    'T2' => ['capability' => 'game.initiate', 'grantError' => null],
];

$rbacMatrixScopes = ['workspace', 'chat', 'deny', 'none'];
$rbacMatrixSubjects = ['member', 'provisioned', 'unprovisioned'];

$rbacMatrixRows = [];
foreach ($rbacMatrixTiers as $tierKey => $spec) {
    foreach ($rbacMatrixScopes as $scope) {
        foreach ($rbacMatrixSubjects as $subject) {
            $expectAllow = match ($tierKey) {
                // T0: default access-control.role_capabilities maps
                // menu.invoke onto every role and decide() falls through to
                // the role fallback when no row matches — actual behavior of
                // the shipped default config (asserted explicitly below too).
                'T0' => true,
                // T1: never platform-grantable, so no row can exist and the
                // resolver denies (mirror authority is a surface concern —
                // freshRole / TierContext, ADR-002).
                'T1' => false,
                // T2: allow only when the declared scope holds an ALLOW.
                'T2' => in_array($scope, ['workspace', 'chat'], true),
            };

            $rbacMatrixRows["{$tierKey}|{$spec['capability']}|{$scope}|{$subject}"] = [
                $tierKey,
                $spec['capability'],
                $scope,
                $subject,
                // scope 'none' performs no grant attempt at all, so there is
                // nothing that could throw for T0/T1 either.
                $scope === 'none' ? null : $spec['grantError'],
                $expectAllow,
            ];
        }
    }
}

dataset('rbac deny matrix', $rbacMatrixRows);

function matrixWorkspace(): int
{
    return Workspace::query()->create(['name' => 'Matrix WS', 'slug' => 'matrix-ws'])->id;
}

function matrixSubjectId(int $workspaceId, string $subject): int
{
    if ($subject === 'unprovisioned') {
        return 987654321;
    }

    $userId = (int) DB::table('users')->insertGetId([
        'name' => $subject === 'member' ? 'Matrix Member' : 'Matrix Provisioned',
        'email' => null,
        'password' => null,
        'telegram_id' => $subject === 'member' ? 111222333 : 444555666,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    if ($subject === 'member') {
        DB::table('workspace_members')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    return $userId;
}

it('resolves a tier × capability × scope × subject row of the deny matrix', function (
    string $tier,
    string $capability,
    string $scope,
    string $subject,
    ?string $grantError,
    bool $expectAllow,
): void {
    $workspaceId = matrixWorkspace();
    $botId = 'matrix-bot-1';
    $subjectId = matrixSubjectId($workspaceId, $subject);
    $chatId = -100424242;
    $permissions = app(WorkspacePermissionService::class);

    $applyGrant = function () use ($permissions, $workspaceId, $botId, $subjectId, $capability, $scope, $chatId): void {
        if ($scope === 'workspace') {
            $permissions->grantT2($workspaceId, $botId, $subjectId, $capability);

            return;
        }

        if ($scope === 'chat') {
            $permissions->grantT2($workspaceId, $botId, $subjectId, $capability, chatId: $chatId);

            return;
        }

        if ($scope === 'deny') {
            $permissions->grantT2($workspaceId, $botId, $subjectId, $capability);
            $permissions->denyT2($workspaceId, $botId, $subjectId, $capability, chatId: $chatId);
        }
    };

    if ($grantError !== null) {
        expect($applyGrant)->toThrow($grantError)
            ->and(DB::table('access_grants')->count())->toBe(0);
    } else {
        $applyGrant();

        expect(DB::table('access_grants')->count())->toBe(match ($scope) {
            'deny' => 2,
            'none' => 0,
            default => 1,
        });

        if ($scope !== 'none') {
            $row = DB::table('access_grants')->orderBy('id')->get();

            // ADR-003: chat and workspace are disjoint — a chat grant never
            // carries workspace_id, a workspace grant never carries chat_id.
            expect($row->firstWhere('scope', 'chat')?->workspace_id)->toBeNull()
                ->and($row->firstWhere('scope', 'workspace')?->chat_id)->toBeNull();
        }
    }

    $askChatId = in_array($scope, ['chat', 'deny'], true) ? $chatId : null;
    $decision = $permissions->can($workspaceId, $botId, $subjectId, $capability, chatId: $askChatId);

    if ($expectAllow) {
        expect($decision->allowed)->toBeTrue()
            ->and($decision->reason)->toContain($tier === 'T0' ? 'role-based fallback' : 'ALLOW');
    } else {
        $expectReason = $grantError === null && $scope === 'deny' ? 'DENY' : 'no active grant';

        expect($decision->allowed)->toBeFalse()
            ->and($decision->reason)->toContain($expectReason);
    }
})->with('rbac deny matrix');

describe('deny beats allow at any scope', function () {
    it('denies a chat-scope allow when a workspace-scope deny exists', function () {
        $workspaceId = matrixWorkspace();
        $botId = 'matrix-bot-1';
        $subjectId = matrixSubjectId($workspaceId, 'provisioned');
        $permissions = app(WorkspacePermissionService::class);

        $permissions->grantT2($workspaceId, $botId, $subjectId, 'game.initiate', chatId: -100424242);
        $permissions->denyT2($workspaceId, $botId, $subjectId, 'game.initiate');

        // The whole DENY phase runs before the whole ALLOW phase, so a deny
        // at ANY scope beats an allow at ANY scope (DatabaseAccessControl).
        $decision = app(AccessControlContract::class)->decide(new AccessRequest(
            botId: $botId,
            subjectId: (string) $subjectId,
            capability: 'game.initiate',
            chatId: -100424242,
            workspaceId: $workspaceId,
        ));

        expect($decision->allowed)->toBeFalse()
            ->and($decision->reason)->toContain('workspace-scoped DENY');
    });

    it('narrows only: a chat-scope deny leaves the workspace ask allowed', function () {
        $workspaceId = matrixWorkspace();
        $botId = 'matrix-bot-1';
        $subjectId = matrixSubjectId($workspaceId, 'member');
        $permissions = app(WorkspacePermissionService::class);

        $permissions->grantT2($workspaceId, $botId, $subjectId, 'game.initiate');
        $permissions->denyT2($workspaceId, $botId, $subjectId, 'game.initiate', chatId: -100424242);

        // ADR-001 D5: inheritance = narrowing only — the deny covers its own
        // chat, it does not revoke the workspace-wide allow.
        expect($permissions->can($workspaceId, $botId, $subjectId, 'game.initiate')->allowed)->toBeTrue()
            ->and($permissions->can($workspaceId, $botId, $subjectId, 'game.initiate', chatId: -100424242)->allowed)
            ->toBeFalse();

        // can() asks ONE scope per request (ADR-003 designed single-scope
        // behavior): a bare chat ask does not see the workspace grant, so the
        // chat the deny does NOT cover is answered at the contract level with
        // both ids — which is exactly what the enforcement surfaces do
        // (tgbot-game-mafia/src/Auth/T2Gate.php:56).
        $otherChat = app(AccessControlContract::class)->decide(new AccessRequest(
            botId: $botId,
            subjectId: (string) $subjectId,
            capability: 'game.initiate',
            chatId: -100424243,
            workspaceId: $workspaceId,
        ));

        expect($otherChat->allowed)->toBeTrue()
            ->and($permissions->can($workspaceId, $botId, $subjectId, 'game.initiate', chatId: -100424243)->allowed)
            ->toBeFalse();
    });
});

describe('T2 cross-workspace denial', function () {
    it('never answers from another workspace’s grant', function () {
        $wsA = matrixWorkspace();
        $wsB = Workspace::query()->create(['name' => 'Matrix WS B', 'slug' => 'matrix-ws-b'])->id;
        $botId = 'matrix-bot-1';
        $subjectId = matrixSubjectId($wsA, 'member');
        $permissions = app(WorkspacePermissionService::class);

        $permissions->grantT2($wsA, $botId, $subjectId, 'game.initiate');

        expect($permissions->can($wsA, $botId, $subjectId, 'game.initiate')->allowed)->toBeTrue()
            ->and($permissions->can($wsB, $botId, $subjectId, 'game.initiate')->allowed)->toBeFalse()
            ->and($permissions->can($wsB, $botId, $subjectId, 'game.initiate')->reason)
            ->toContain('no active grant');
    });
});

describe('T0 menu.invoke under the shipped default config', function () {
    it('falls through to the default role_capabilities map instead of a grant row', function () {
        $workspaceId = matrixWorkspace();
        $subjectId = matrixSubjectId($workspaceId, 'provisioned');
        $permissions = app(WorkspacePermissionService::class);

        // No grant is ever written for the T0 baseline…
        expect(fn () => $permissions->grantT2($workspaceId, 'matrix-bot-1', $subjectId, 'menu.invoke'))
            ->toThrow(InvalidArgumentException::class, 'membership lives in workspace_members')
            ->and(DB::table('access_grants')->count())->toBe(0);

        // …yet the default config's role fallback answers it (actual default
        // config behavior: access-control.role_capabilities maps menu.invoke
        // onto every role and decide() merges ALL role capabilities when the
        // request carries no role context).
        $decision = $permissions->can($workspaceId, 'matrix-bot-1', $subjectId, 'menu.invoke');
        expect($decision->allowed)->toBeTrue()
            ->and($decision->reason)->toContain('role-based fallback allows menu.invoke');
    });

    it('denies when the request carries a role with no such capability', function () {
        $workspaceId = matrixWorkspace();
        $subjectId = matrixSubjectId($workspaceId, 'member');

        $decision = app(AccessControlContract::class)->decide(new AccessRequest(
            botId: 'matrix-bot-1',
            subjectId: (string) $subjectId,
            capability: 'menu.invoke',
            workspaceId: $workspaceId,
            context: ['role' => 'restricted'],
        ));

        expect($decision->allowed)->toBeFalse()
            ->and($decision->reason)->toContain('no active grant');
    });

    it('keeps membership (T0) out of decide(): a non-member is answered identically', function () {
        $workspaceId = matrixWorkspace();
        $memberId = matrixSubjectId($workspaceId, 'member');
        $outsider = (int) DB::table('users')->insertGetId([
            'name' => 'Outsider',
            'email' => null,
            'password' => null,
            'telegram_id' => 777888999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissions = app(WorkspacePermissionService::class);

        // Membership grants nothing on its own — T2 stays deny-by-default for
        // a member without a grant, and surfaces must AND isMember() with
        // can() for the T0 rung (WorkspacePermissionService PHPDoc).
        expect($permissions->can($workspaceId, 'matrix-bot-1', $memberId, 'game.initiate')->allowed)
            ->toBeFalse()
            ->and($permissions->can($workspaceId, 'matrix-bot-1', $outsider, 'game.initiate')->allowed)
            ->toBeFalse();
    });
});

describe('T1 mirrored authority is decided by surfaces, not by grants', function () {
    it('denies decide() for a provisioned subject with no mirror data', function () {
        // ADR-002: T1 capability rows can never exist (grantT2/denyT2 throw
        // NonGrantableCapabilityException, asserted across every T1 row of
        // the matrix above), so decide() has nothing to answer with. The
        // positive T1 path is the surfaces' fail-closed freshRole /
        // TierContext ladder — menu MutationRoleGate + TelegramStandingSource
        // (misc/BAGArt/telegram-platform-menu/tests/Unit/WebApi/MutationRoleGateTest.php),
        // not an access_grants row.
        $workspaceId = matrixWorkspace();
        $subjectId = matrixSubjectId($workspaceId, 'provisioned');
        $permissions = app(WorkspacePermissionService::class);

        $decision = $permissions->can($workspaceId, 'matrix-bot-1', $subjectId, 'unban');

        expect(DB::table('access_grants')->count())->toBe(0)
            ->and($decision->allowed)->toBeFalse()
            ->and($decision->reason)->toContain('no active grant')
            ->and(app(PermissionCatalog::class)->tier('unban'))->toBe(Tier::T1);
    });
});
