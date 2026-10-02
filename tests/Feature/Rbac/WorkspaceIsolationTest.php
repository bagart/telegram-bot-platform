<?php

declare(strict_types=1);

use BAGArt\TelegramBotAccess\AccessControlContract;
use BAGArt\TelegramBotAccess\AccessRequest;
use BAGArt\TelegramBotManagement\Enums\EntityKind;
use BAGArt\TelegramBotManagement\Exceptions\EntityException;
use BAGArt\TelegramBotManagement\Exceptions\WorkspaceAdminException;
use BAGArt\TelegramBotManagement\Models\TgBot;
use BAGArt\TelegramBotManagement\Models\TgEntity;
use BAGArt\TelegramBotManagement\Models\Workspace;
use BAGArt\TelegramBotManagement\Services\EntityLinkService;
use BAGArt\TelegramBotManagement\Services\WorkspaceAdminService;
use BAGArt\TelegramBotManagement\Services\WorkspacePermissionService;
use BAGArt\TelegramBotMenu\Auth\WorkspaceInviteService;
use Illuminate\Support\Facades\DB;

/**
 * Phase 6 (management-admin-rbac): negative cross-workspace cases on every
 * new management surface (ADR-001 tenancy: workspace = tenant, all rights
 * scoped to workspace objects).
 *
 * Fixture shape: wsA (owner A) holds a member, a channel entity, a linked
 * bot and a T2 grant; wsB belongs to a distinct owner and starts empty.
 * Global helpers are prefixed `iso` to stay unique across the combined
 * host + package test process.
 */

function isoUser(string $name, ?int $telegramId = null): int
{
    return (int) DB::table('users')->insertGetId([
        'name' => $name,
        'email' => null,
        'password' => null,
        'telegram_id' => $telegramId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * @return array{admins: WorkspaceAdminService, permissions: WorkspacePermissionService, entities: EntityLinkService, wsA: int, wsB: int, ownerA: int, ownerB: int, member: int, bot: TgBot, channel: TgEntity, botEntity: TgEntity}
 */
function isoFixture(): array
{
    $admins = app(WorkspaceAdminService::class);
    $ownerA = isoUser('Owner A');
    $ownerB = isoUser('Owner B');
    $member = isoUser('Member M');

    $wsA = $admins->createWorkspace($ownerA, 'Isolation A');
    $wsB = $admins->createWorkspace($ownerB, 'Isolation B');
    $admins->addMember($wsA->id, $ownerA, $member);

    $entities = new EntityLinkService();
    $bot = TgBot::factory()->create();
    $channel = $entities->link($wsA->id, EntityKind::Channel, -1005550001, null, $ownerA, 'A Channel');
    $botEntity = $entities->link($wsA->id, EntityKind::Bot, null, $bot->bot_id, $ownerA, 'A Bot');

    app(WorkspacePermissionService::class)->grantT2(
        $wsA->id,
        $bot->bot_id,
        $member,
        'game.initiate',
    );

    return [
        'admins' => $admins,
        'permissions' => app(WorkspacePermissionService::class),
        'entities' => $entities,
        'wsA' => $wsA->id,
        'wsB' => $wsB->id,
        'ownerA' => $ownerA,
        'ownerB' => $ownerB,
        'member' => $member,
        'bot' => $bot,
        'channel' => $channel,
        'botEntity' => $botEntity,
    ];
}

describe('WorkspaceAdminService workspace isolation', function () {
    it('never lists another owner’s workspace and reports no cross-membership', function () {
        $f = isoFixture();

        expect($f['admins']->listFor($f['ownerB'])->pluck('id')->all())->toBe([$f['wsB']])
            ->and($f['admins']->listFor($f['ownerA'])->pluck('id')->all())->toBe([$f['wsA']])
            ->and($f['admins']->isMember($f['wsA'], $f['ownerB']))->toBeFalse()
            ->and($f['admins']->isMember($f['wsB'], $f['ownerA']))->toBeFalse()
            ->and($f['admins']->isMember($f['wsA'], $f['member']))->toBeTrue();
    });

    it('rejects a foreign owner on every membership mutation surface', function () {
        $f = isoFixture();
        $stranger = isoUser('Stranger');

        expect(fn () => $f['admins']->members($f['wsA'], $f['ownerB']))
            ->toThrow(WorkspaceAdminException::class, 'is not an active member')
            ->and(fn () => $f['admins']->addMember($f['wsA'], $f['ownerB'], $stranger))
            ->toThrow(WorkspaceAdminException::class, 'is not an Owner')
            ->and(fn () => $f['admins']->changeRole($f['wsA'], $f['ownerB'], $f['member'], \BAGArt\TelegramBotManagement\Enums\WorkspaceRole::Owner))
            ->toThrow(WorkspaceAdminException::class, 'is not an Owner')
            ->and(fn () => $f['admins']->removeMember($f['wsA'], $f['ownerB'], $f['member']))
            ->toThrow(WorkspaceAdminException::class, 'is not an Owner');

        // Nothing changed in wsA: the member row survived every attempt.
        expect($f['admins']->isMember($f['wsA'], $f['member']))->toBeTrue()
            ->and(DB::table('workspace_members')->where('workspace_id', $f['wsA'])->count())->toBe(2);
    });
});

describe('EntityLinkService workspace isolation', function () {
    it('never lists another workspace’s entity and cannot unlink it', function () {
        $f = isoFixture();

        expect($f['entities']->list($f['wsB'])->pluck('id')->all())->toBeEmpty()
            ->and($f['entities']->list($f['wsA'])->pluck('id')->all())
            ->toBe([$f['channel']->id, $f['botEntity']->id])
            ->and($f['entities']->unlink($f['wsB'], $f['channel']->id))->toBeFalse()
            ->and($f['entities']->unlink($f['wsB'], $f['botEntity']->id))->toBeFalse()
            ->and(TgEntity::query()->where('id', $f['channel']->id)->exists())->toBeTrue()
            ->and($f['entities']->unlink($f['wsA'], $f['channel']->id))->toBeTrue();
    });

    it('keeps the same bot target as a separate workspace-scoped row per workspace', function () {
        $f = isoFixture();

        $linkB = $f['entities']->link($f['wsB'], EntityKind::Bot, null, $f['bot']->bot_id, $f['ownerB'], 'B Bot');

        $rows = TgEntity::query()
            ->where('kind', EntityKind::Bot->value)
            ->where('bot_id', $f['bot']->bot_id)
            ->get();

        expect($rows)->toHaveCount(2)
            ->and($rows->pluck('id')->unique()->count())->toBe(2)
            ->and($rows->pluck('id')->all())->toContain($f['botEntity']->id, $linkB->id)
            ->and($rows->firstWhere('id', $f['botEntity']->id)->workspace_id)->toBe($f['wsA'])
            ->and($rows->firstWhere('id', $linkB->id)->workspace_id)->toBe($f['wsB'])
            ->and($f['entities']->list($f['wsA']))->toHaveCount(2)
            ->and($f['entities']->list($f['wsB']))->toHaveCount(1);
    });

    it('claims a chat external id in exactly one workspace (single-tenant chat claim)', function () {
        // Design note (NOT an ADR violation): chat entities are exclusively
        // claimed — two tenants mirroring T1 rights over the same chat would
        // produce divergent grants and ban decisions. See
        // misc/BAGArt/telegram-platform-management/src/Services/EntityLinkService.php:144
        // and its package test EntityLinkServiceTest.php:132. Bot entities
        // (shared actors, ADR-004) are the linkable-across-workspaces case.
        $f = isoFixture();

        expect(fn () => $f['entities']->link($f['wsB'], EntityKind::Channel, -1005550001, null, $f['ownerB']))
            ->toThrow(EntityException::class, 'already linked in another workspace')
            ->and(TgEntity::query()->where('external_id', -1005550001)->count())->toBe(1);
    });
});

describe('BotCatalogService workspace isolation', function () {
    it('applies the private-bot entitlement per workspace only', function () {
        $f = isoFixture();

        $wsA = Workspace::query()->find($f['wsA']);
        $wsA->settings = ['entitlements' => ['private_bots']];
        $wsA->save();

        $private = TgBot::factory()->privateCatalog()->create();
        $public = TgBot::factory()->create();

        $catalog = new \BAGArt\TelegramBotManagement\Services\BotCatalogService(new EntityLinkService());

        $inA = $catalog->addableBots($f['wsA'])->pluck('bot_id')->all();
        $inB = $catalog->addableBots($f['wsB'])->pluck('bot_id')->all();

        // wsA: entitled → private visible; its own linked bot excluded.
        expect($inA)->toContain($private->bot_id, $public->bot_id)
            ->and($inA)->not->toContain($f['bot']->bot_id);

        // wsB: no entitlement → private hidden; A's link does not hide the
        // shared catalog actor from B (links are workspace-scoped).
        expect($inB)->not->toContain($private->bot_id)
            ->and($inB)->toContain($public->bot_id, $f['bot']->bot_id)
            ->and($catalog->hasEntitlement($f['wsB'], 'private_bots'))->toBeFalse();
    });
});

describe('WorkspacePermissionService workspace isolation', function () {
    it('keeps workspace-scope grants inside their workspace', function () {
        $f = isoFixture();

        expect($f['permissions']->can($f['wsA'], $f['bot']->bot_id, $f['member'], 'game.initiate')->allowed)
            ->toBeTrue()
            ->and($f['permissions']->can($f['wsB'], $f['bot']->bot_id, $f['member'], 'game.initiate')->allowed)
            ->toBeFalse()
            ->and($f['permissions']->can($f['wsB'], $f['bot']->bot_id, $f['member'], 'game.initiate')->reason)
            ->toContain('no active grant');
    });

    it('pins chat-scope grants to the chat with a NULL workspace and answers single-scope', function () {
        $f = isoFixture();
        $chatId = -1007770001;

        $f['permissions']->grantT2(
            $f['wsA'],
            $f['bot']->bot_id,
            $f['member'],
            'moderation.log.view',
            chatId: $chatId,
        );

        $row = DB::table('access_grants')->where('capability', 'moderation.log.view')->sole();
        expect($row->scope)->toBe('chat')
            ->and((int) $row->chat_id)->toBe($chatId)
            ->and($row->workspace_id)->toBeNull();

        // ADR-003 designed behavior: can() asks ONE scope per request —
        // a chat request carries chatId only (workspaceId is dropped,
        // WorkspacePermissionService.php:163), so it sees chat + bot rows;
        // a workspace request sees workspace + bot rows. The chat grant is
        // therefore visible for its chat and invisible for workspace asks
        // and for other chats.
        expect($f['permissions']->can($f['wsA'], $f['bot']->bot_id, $f['member'], 'moderation.log.view', chatId: $chatId)->allowed)
            ->toBeTrue()
            ->and($f['permissions']->can($f['wsA'], $f['bot']->bot_id, $f['member'], 'moderation.log.view', chatId: -1007770002)->allowed)
            ->toBeFalse()
            ->and($f['permissions']->can($f['wsA'], $f['bot']->bot_id, $f['member'], 'moderation.log.view')->allowed)
            ->toBeFalse();
    });

    it('covers entity asks with the workspace grant when the surface carries both ids (T2Gate pattern)', function () {
        // ADR-001: a workspace-level grant is dynamic — it covers current
        // and future entities. That coverage happens at the contract level
        // when the request carries chatId AND workspaceId, which is what the
        // enforcement surfaces do (tgbot-game-mafia/src/Auth/T2Gate.php:56
        // resolves the chat's workspace from tg_entities). can() stays
        // single-scope by design; this test pins the contract half.
        $f = isoFixture();
        $chatId = -1009990001;
        $control = app(AccessControlContract::class);

        $chatAsk = static fn () => $control->decide(new AccessRequest(
            botId: $f['bot']->bot_id,
            subjectId: (string) $f['member'],
            capability: 'game.initiate',
            chatId: $chatId,
            workspaceId: $f['wsA'],
        ));

        expect($chatAsk()->allowed)->toBeTrue()
            ->and($chatAsk()->reason)->toContain('workspace-scoped ALLOW');

        // Entity-level deny narrows the workspace allow (D5: inheritance =
        // narrowing only).
        $f['permissions']->denyT2($f['wsA'], $f['bot']->bot_id, $f['member'], 'game.initiate', chatId: $chatId);

        expect($chatAsk()->allowed)->toBeFalse()
            ->and($chatAsk()->reason)->toContain('chat-scoped DENY');
    });
});

describe('ConnectedBotRoster workspace isolation', function () {
    it('returns only the bots linked into the requested workspace', function () {
        $f = isoFixture();
        $roster = new \BAGArt\TelegramBotManagement\Services\ConnectedBotRoster(new EntityLinkService());

        expect($roster->botsForWorkspace($f['wsA'])->pluck('bot_id')->all())
            ->toBe([$f['bot']->bot_id])
            ->and($roster->botsForWorkspace($f['wsB'])->pluck('bot_id')->all())->toBeEmpty();
    });
});

describe('WorkspaceInviteService workspace isolation', function () {
    it('adds an accepted invitee only to the invited workspace', function () {
        $f = isoFixture();
        $invites = app(WorkspaceInviteService::class);

        [$invite, $code] = $invites->issue($f['wsA'], $f['ownerA']);
        $outcome = $invites->accept($code, 99112233, 'Invitee');

        expect($outcome->joined)->toBeTrue()
            ->and($outcome->workspaceId)->toBe($f['wsA'])
            ->and($invite->workspace_id)->toBe($f['wsA']);

        $invitee = (int) app(\BAGArt\TelegramBotManagement\Services\TelegramIdentityService::class)
            ->resolveByTelegramId(99112233);

        expect($f['admins']->isMember($f['wsA'], $invitee))->toBeTrue()
            ->and($f['admins']->isMember($f['wsB'], $invitee))->toBeFalse()
            ->and($f['admins']->listFor($invitee)->pluck('id')->all())->toBe([$f['wsA']])
            ->and(DB::table('workspace_members')->where('workspace_id', $f['wsB'])->count())->toBe(1);
    });

    it('consumes the invite code once — a replay never grants a second membership', function () {
        $f = isoFixture();
        $invites = app(WorkspaceInviteService::class);

        [, $code] = $invites->issue($f['wsA'], $f['ownerA']);

        expect($invites->accept($code, 99445566, 'First')->joined)->toBeTrue()
            ->and($invites->accept($code, 99445566, 'First')->joined)->toBeFalse();

        $invitee = (int) app(\BAGArt\TelegramBotManagement\Services\TelegramIdentityService::class)
            ->resolveByTelegramId(99445566);

        expect(DB::table('workspace_members')
            ->where('workspace_id', $f['wsA'])
            ->where('user_id', $invitee)
            ->count())->toBe(1);
    });
});
