<?php

declare(strict_types=1);

use BAGArt\TelegramBotAccess\Permission\NonGrantableCapabilityException;
use BAGArt\TelegramBotAccess\Permission\PermissionCatalog;
use BAGArt\TelegramBotAccess\Permission\Tier;
use BAGArt\TelegramBotMenu\Manifest\EffectiveRole;
use BAGArt\TelegramBotMenu\WebApi\MutationRoleGate;
use BAGArt\TelegramBotManagement\Models\Workspace;
use BAGArt\TelegramBotManagement\Services\TelegramIdentityService;
use BAGArt\TelegramBotManagement\Services\WorkspacePermissionService;
use Illuminate\Support\Facades\DB;

/**
 * Phase 6 (management-admin-rbac): T1 mirror revocation — every mirrored
 * Telegram group-admin capability (ADR-002 "split authority") stays outside
 * the platform grant store no matter which entry point is used, and the
 * authority that DOES answer T1 questions reports its provenance honestly.
 */

function t1MirrorWorkspace(string $slug): int
{
    return Workspace::query()->create(['name' => $slug, 'slug' => $slug])->id;
}

describe('T1 capabilities never reach access_grants', function () {
    it('refuses grant, deny and revoke for every catalog T1 capability at both scopes', function () {
        $catalog = app(PermissionCatalog::class);
        $t1 = $catalog->capabilities(Tier::T1);
        $workspaceId = t1MirrorWorkspace('t1-mirror');
        $permissions = app(WorkspacePermissionService::class);
        $subjectId = 4242;

        expect($t1)->not->toBeEmpty();

        foreach ($t1 as $capability) {
            expect($catalog->tier($capability))->toBe(Tier::T1);

            // workspace scope + chat scope, both write paths
            expect(fn () => $permissions->grantT2($workspaceId, 'mirror-bot', $subjectId, $capability))
                ->toThrow(NonGrantableCapabilityException::class, 'cannot be granted or denied by the platform')
                ->and(fn () => $permissions->grantT2($workspaceId, 'mirror-bot', $subjectId, $capability, chatId: -1001))
                ->toThrow(NonGrantableCapabilityException::class)
                ->and(fn () => $permissions->denyT2($workspaceId, 'mirror-bot', $subjectId, $capability))
                ->toThrow(NonGrantableCapabilityException::class)
                ->and(fn () => $permissions->denyT2($workspaceId, 'mirror-bot', $subjectId, $capability, chatId: -1001))
                ->toThrow(NonGrantableCapabilityException::class);

            // revoke has nothing to delete and must not create anything
            expect($permissions->revokeT2($workspaceId, 'mirror-bot', $subjectId, $capability))->toBeFalse()
                ->and($permissions->revokeT2($workspaceId, 'mirror-bot', $subjectId, $capability, chatId: -1001))
                ->toBeFalse();
        }

        expect(DB::table('access_grants')->count())->toBe(0);
    });

    it('keeps every built-in T1 key non-grantable even when config extends the tier', function () {
        $catalog = app(PermissionCatalog::class);

        $missing = array_diff(PermissionCatalog::DEFAULTS['t1'], $catalog->capabilities(Tier::T1));

        expect($missing)->toBe([])
            ->and(PermissionCatalog::DEFAULTS['t1'])->toBe([
                'ban',
                'kick',
                'unban',
                'bots.add',
                'bots.remove',
                'modules.manage',
                'settings.manage',
                'grant.t2',
            ]);
    });

    it('stores the chat.role.admin T2 marker as a normal platform grant (sanity)', function () {
        $workspaceId = t1MirrorWorkspace('t1-sanity');
        $permissions = app(WorkspacePermissionService::class);

        $grant = $permissions->grantT2($workspaceId, 'mirror-bot', 4242, 'chat.role.admin');
        $decision = $permissions->can($workspaceId, 'mirror-bot', 4242, 'chat.role.admin');

        expect(app(PermissionCatalog::class)->tier('chat.role.admin'))->toBe(Tier::T2)
            ->and($grant->capability)->toBe('chat.role.admin')
            ->and(DB::table('access_grants')->count())->toBe(1)
            ->and($decision->allowed)->toBeTrue()
            ->and($decision->reason)->toContain('workspace-scoped ALLOW');
    });
});

describe('T1 provenance reported by the mutation tier gate', function () {
    it('never reports a platform grant as telegram-verified', function () {
        // ADR-002: chat.role.admin is a T2 marker the menu bridge reads; a
        // subject holding it must still NOT be treated as a mirrored
        // Telegram admin, otherwise platform grants would unlock the strictly
        // T1 capabilities (unban / bots.add / modules.manage …).
        $botId = '100000001';
        $telegramId = 99117722;
        $workspaceId = t1MirrorWorkspace('t1-provenance');

        $userId = app(TelegramIdentityService::class)->provision($telegramId, 'Mirror Subject');
        app(WorkspacePermissionService::class)->grantT2(
            $workspaceId,
            $botId,
            $userId,
            'chat.role.admin',
            chatId: -100888777,
        );

        $context = app(MutationRoleGate::class)->freshTierContext($botId, null, $telegramId);

        expect($context->role)->toBe(EffectiveRole::Admin)
            ->and($context->telegramAdminVerified)->toBeFalse();

        // Full provenance ladder (superadmin/owner/standing/degrade) is unit-
        // covered by the menu package:
        // misc/BAGArt/telegram-platform-menu/tests/Unit/WebApi/MutationRoleGateTest.php
    });
});
