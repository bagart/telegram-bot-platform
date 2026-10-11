<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Telegram Module Engine — platform module policy
|--------------------------------------------------------------------------
|
| Declarative list of modules connected to the platform. This file is
| platform POLICY only: it decides which modules are available and enabled
| at platform level. Module identity, metadata and component declarations
| live inside each module package (TgModuleContract::descriptor()).
|
| Rules (docs: misc/BAGArt/telegram-platform-module/docs/architecture/32.md):
|  - the array key MUST equal the module's descriptor id;
|  - entries MUST be BAGArt\TelegramModuleEngine\Config\TgModuleConfig DTOs;
|  - no secrets here (the file is git-versioned);
|  - platform `enabled` does NOT mean enabled for every bot — bot-level
|    activation is engine-owned runtime state.
|  - `routes` mirror each module's registered bot commands (the modules
|    still self-register them into the flat registry; the route rows make
|    them per-bot dispatchable and drift-checkable via tg:modules:routes:*).
|    Route type convention: use 'command' (not 'telegram.command') —
|    CommandRouteLookup accepts both for backward compatibility.
|  - `commands` declares the module's Artisan console commands; the engine
|    registers them for platform-enabled modules (replaces provider-level
|    ->commands() pushes).
|  - `schedule` declares the module's cron tasks (TgModuleSchedule); the
|    engine registers them with schedule-overrides.php user overrides
|    applied (replaces the telegram.modules_schedule side-channel).
|  - `httpRoutes` / `routeMiddleware` / `exceptionRenderables` / `frontendPages`
|    / `pageGenerators` declaratively replace the providers' own
|    loadRoutesFrom() / aliasMiddleware() / renderable() registrations and
|    the modules_frontend_pages / modules_page_generators side-channels.
|
| `strict` => true fails platform boot on any invalid entry (fail-fast);
| default (false) collects errors, registers valid modules and logs.
|
| Env override convention:
|   TG_MODULE_ENABLED_<module-key>=true|false
|   e.g. TG_MODULE_ENABLED_antispam=false disables the antispam module
|   without editing this file. When the env var is absent, the config
|   value is used as-is. Operators must clear config cache after changing
|   env vars: `php artisan config:clear`.
*/

use BAGArt\TelegramModuleEngine\Config\TgModuleConfig;
use BAGArt\TelegramModuleEngine\Config\TgModuleSchedule;
use BAGArt\TelegramModuleEngine\Routing\RouteDeclaration;

return [
    'strict' => false,

    // Dispatch driver for the lib ModuleEnablementContract (doc 05):
    // 'engine' = engine adapter over bot_module_activations (shipped
    // default); 'legacy' = management service over tg_module_enablements —
    // test-fixture support for unmigrated dev DBs only.
    'enablement_driver' => 'engine',

    'modules' => [
        // Local dev fixture module (misc/BAGArt/tgbot-module-example):
        // registers a demo processor, command, validation rule and outbound
        // middleware without core edits. Disable outside dev with
        // TG_MODULE_ENABLED_example=false.
        'example' => new TgModuleConfig(
            enabled: env('TG_MODULE_ENABLED_example', true),
            provider: BAGArt\TelegramBotExample\ExampleModule::class,
        ),
        'antispam' => new TgModuleConfig(
            enabled: env('TG_MODULE_ENABLED_antispam', true),
            provider: BAGArt\TelegramBotAntispam\AntispamModule::class,
            laravelProvider: BAGArt\TelegramBotAntispam\TelegramBotAntispamServiceProvider::class,
            seeders: [BAGArt\TelegramBotAntispam\Database\Seeders\AntispamDefaultsSeeder::class],
            commands: [
                BAGArt\TelegramBotAntispam\Commands\BlocklistSyncCommand::class,
                BAGArt\TelegramBotAntispam\Commands\ValidateDatasetCommand::class,
            ],
            httpRoutes: [
                base_path('misc/BAGArt/tgbot-module-antispam/routes/web.php'),
            ],
            frontendPages: [
                base_path('misc/BAGArt/tgbot-module-antispam/resources/js/pages'),
            ],
            sourcePath: base_path('misc/BAGArt/tgbot-module-antispam'),
            routes: [
                new RouteDeclaration('command', '/antispam', payload: [
                    'processor' => BAGArt\TelegramBotAntispam\Processors\AntispamStatusCommand::class,
                    'description' => 'Anti-spam status and settings',
                ]),
                // Q5 (2026-10-07): antispam owns plain /report — the moderation
                // surface. Nettools' network probe claims /netreport instead,
                // declared in its own block below, so the route table and the
                // flat-registry fallback agree on every bot.
                new RouteDeclaration('command', '/report', payload: [
                    'processor' => BAGArt\TelegramBotAntispam\Processors\AntispamReportCommand::class,
                    'description' => 'Report a user for moderation',
                ]),
                new RouteDeclaration('command', '/appeal', payload: [
                    'processor' => BAGArt\TelegramBotAntispam\Processors\AppealCommand::class,
                    'description' => 'Appeal a punishment',
                ]),
            ],
        ),
        'mafia' => new TgModuleConfig(
            enabled: env('TG_MODULE_ENABLED_mafia', true),
            provider: BAGArt\TelegramBotMafia\MafiaModule::class,
            laravelProvider: BAGArt\TelegramBotMafia\MafiaServiceProvider::class,
            schedule: [
                new TgModuleSchedule(command: 'mafia:sweep', expression: '* * * * *'),
            ],
            routes: [
                new RouteDeclaration('command', '/play', payload: [
                    'processor' => BAGArt\TelegramBotMafia\Telegram\PlayCommandProcessor::class,
                    'description' => 'Play Mafia - join or start a game',
                ]),
                new RouteDeclaration('command', '/kick', payload: [
                    'processor' => BAGArt\TelegramBotMafia\Telegram\KickCommandProcessor::class,
                    'description' => 'Mafia: kick a player (host)',
                ]),
                new RouteDeclaration('command', '/rules', payload: [
                    'processor' => BAGArt\TelegramBotMafia\Telegram\RulesCommandProcessor::class,
                    'description' => 'Mafia game rules',
                ]),
                new RouteDeclaration('command', '/start', payload: [
                    'processor' => BAGArt\TelegramBotMafia\Telegram\StartProcessor::class,
                    'description' => 'Mafia onboarding',
                ]),
            ],
        ),
        'menu' => new TgModuleConfig(
            enabled: env('TG_MODULE_ENABLED_menu', true),
            provider: BAGArt\TelegramBotMenu\MenuModule::class,
            laravelProvider: BAGArt\TelegramBotMenu\TelegramBotMenuServiceProvider::class,
            commands: [
                BAGArt\TelegramBotMenu\Console\SessionsRevokeCommand::class,
                BAGArt\TelegramBotMenu\Console\GrantCommand::class,
                BAGArt\TelegramBotMenu\Console\RevokeCommand::class,
                BAGArt\TelegramBotMenu\Console\RolesSweepCommand::class,
                BAGArt\TelegramBotMenu\Console\T1ReconcileCommand::class,
                BAGArt\TelegramBotMenu\Console\MenuInstallCommand::class,
                BAGArt\TelegramBotMenu\Console\MenuSyncCommand::class,
                BAGArt\TelegramBotMenu\Console\MenuUninstallCommand::class,
                BAGArt\TelegramBotMenu\Console\MenuManifestCommand::class,
                BAGArt\TelegramBotMenu\Console\MenuScaffoldCommand::class,
                BAGArt\TelegramBotMenu\Console\InertiaPagesGenerateCommand::class,
            ],
            schedule: [
                new TgModuleSchedule(command: 'menu:t1:reconcile', expression: '0 3 * * *'),
                new TgModuleSchedule(command: 'menu:roles:sweep', expression: '0 4 * * *'),
            ],
            httpRoutes: [
                base_path('misc/BAGArt/telegram-platform-menu/routes/tgapp.php'),
                base_path('misc/BAGArt/telegram-platform-menu/routes/auth.php'),
                base_path('misc/BAGArt/telegram-platform-menu/routes/superadmin.php'),
            ],
            frontendPages: [
                base_path('misc/BAGArt/telegram-platform-menu/resources/js/pages'),
            ],
            sourcePath: base_path('misc/BAGArt/telegram-platform-menu'),
            routeMiddleware: [
                'tgapp.session' => BAGArt\TelegramBotMenu\Http\Laravel\TgAppSessionMiddleware::class,
                'superadmin' => BAGArt\TelegramBotMenu\Http\Laravel\SuperadminGateMiddleware::class,
            ],
            exceptionRenderables: [
                BAGArt\TelegramBotMenu\Support\TgAppThrottleRenderable::class,
            ],
            pageGenerators: ['menu:pages'],
            routes: [
                new RouteDeclaration('command', '/menu', payload: [
                    'processor' => BAGArt\TelegramBotMenu\Chats\MenuCommandProcessor::class,
                ]),
                new RouteDeclaration('command', '/login', payload: [
                    'processor' => BAGArt\TelegramBotMenu\Chats\LoginCommandProcessor::class,
                    'description' => 'Confirm a web login or telegram link code',
                ]),
                new RouteDeclaration('command', '/invite', payload: [
                    'processor' => BAGArt\TelegramBotMenu\Chats\InviteCommandProcessor::class,
                    'description' => 'Mint a workspace invite code (Owner only)',
                ]),
                new RouteDeclaration('command', '/join', payload: [
                    'processor' => BAGArt\TelegramBotMenu\Chats\JoinCommandProcessor::class,
                    'description' => 'Join a workspace with an invite code',
                ]),
                new RouteDeclaration('command', '/wsadmin', payload: [
                    'processor' => BAGArt\TelegramBotMenu\Chats\WsadminCommandProcessor::class,
                    'description' => 'Open the workspace admin panel',
                ]),
            ],
        ),
        'nettools' => new TgModuleConfig(
            enabled: env('TG_MODULE_ENABLED_nettools', true),
            provider: BAGArt\TelegramBotNettools\NettoolsModule::class,
            laravelProvider: BAGArt\TelegramBotNettools\TelegramBotNettoolsServiceProvider::class,
            routes: [
                // Mirrors Commands\CommandMap::MAP (probe commands); /netreport
                // is nettools' flat claim renamed from /report (Q5, 2026-10-07)
                // so it no longer collides with antispam's /report.
                new RouteDeclaration('command', '/ip', payload: [
                    'processor' => BAGArt\TelegramBotNettools\Commands\IpCommand::class,
                    'description' => 'IP address information',
                ]),
                new RouteDeclaration('command', '/geo', payload: [
                    'processor' => BAGArt\TelegramBotNettools\Commands\GeoCommand::class,
                    'description' => 'Geolocation for IP or host',
                ]),
                new RouteDeclaration('command', '/whois', payload: [
                    'processor' => BAGArt\TelegramBotNettools\Commands\WhoisCommand::class,
                    'description' => 'WHOIS lookup',
                ]),
                new RouteDeclaration('command', '/dns', payload: [
                    'processor' => BAGArt\TelegramBotNettools\Commands\DnsCommand::class,
                    'description' => 'DNS records lookup',
                ]),
                new RouteDeclaration('command', '/ping', payload: [
                    'processor' => BAGArt\TelegramBotNettools\Commands\PingCommand::class,
                    'description' => 'ICMP ping a host',
                ]),
                new RouteDeclaration('command', '/trace', payload: [
                    'processor' => BAGArt\TelegramBotNettools\Commands\TraceCommand::class,
                    'description' => 'Traceroute a host',
                ]),
                new RouteDeclaration('command', '/http', payload: [
                    'processor' => BAGArt\TelegramBotNettools\Commands\HttpCommand::class,
                    'description' => 'HTTP request inspection',
                ]),
                new RouteDeclaration('command', '/port', payload: [
                    'processor' => BAGArt\TelegramBotNettools\Commands\PortCommand::class,
                    'description' => 'TCP port check',
                ]),
                new RouteDeclaration('command', '/asn', payload: [
                    'processor' => BAGArt\TelegramBotNettools\Commands\AsnCommand::class,
                    'description' => 'ASN information',
                ]),
                new RouteDeclaration('command', '/subs', payload: [
                    'processor' => BAGArt\TelegramBotNettools\Commands\SubsCommand::class,
                    'description' => 'Subdomain discovery',
                ]),
                new RouteDeclaration('command', '/mail', payload: [
                    'processor' => BAGArt\TelegramBotNettools\Commands\MailCommand::class,
                    'description' => 'Email DNS/security records',
                ]),
                new RouteDeclaration('command', '/ssl', payload: [
                    'processor' => BAGArt\TelegramBotNettools\Commands\SslCommand::class,
                    'description' => 'TLS certificate inspection',
                ]),
                new RouteDeclaration('command', '/sec', payload: [
                    'processor' => BAGArt\TelegramBotNettools\Commands\SecCommand::class,
                    'description' => 'Security headers check',
                ]),
                new RouteDeclaration('command', '/os', payload: [
                    'processor' => BAGArt\TelegramBotNettools\Commands\OsCommand::class,
                    'description' => 'OS fingerprint hints',
                ]),
                new RouteDeclaration('command', '/reco', payload: [
                    'processor' => BAGArt\TelegramBotNettools\Commands\RecoCommand::class,
                    'description' => 'Reconnaissance summary',
                ]),
                new RouteDeclaration('command', '/netreport', payload: [
                    'processor' => BAGArt\TelegramBotNettools\Commands\ReportCommand::class,
                    'description' => 'Aggregated mega-report',
                ]),
                new RouteDeclaration('command', '/portscan', payload: [
                    'processor' => BAGArt\TelegramBotNettools\Commands\PortscanCommand::class,
                    'description' => 'TCP port scan (admin)',
                ]),
                new RouteDeclaration('command', '/dnsbl', payload: [
                    'processor' => BAGArt\TelegramBotNettools\Commands\DnsblCommand::class,
                    'description' => 'DNS blocklist check (admin)',
                ]),
                new RouteDeclaration('command', '/quota', payload: [
                    'processor' => BAGArt\TelegramBotNettools\Commands\QuotaCommand::class,
                    'description' => 'Nettools usage quota',
                ]),
                new RouteDeclaration('command', '/nt', payload: [
                    'processor' => BAGArt\TelegramBotNettools\Commands\NtCommand::class,
                    'description' => 'Nettools admin menu',
                ]),
                new RouteDeclaration('command', '/my', payload: [
                    'processor' => BAGArt\TelegramBotNettools\Commands\MyCommand::class,
                    'description' => 'My saved probe targets',
                ]),
                new RouteDeclaration('command', '/r', payload: [
                    'processor' => BAGArt\TelegramBotNettools\Commands\RepeatCommand::class,
                    'description' => 'Repeat the last probe',
                ]),
            ],
        ),
        'stt' => new TgModuleConfig(
            enabled: env('TG_MODULE_ENABLED_stt', true),
            provider: BAGArt\TelegramBotStt\SttModule::class,
            laravelProvider: BAGArt\TelegramBotStt\TelegramBotSttServiceProvider::class,
            commands: [
                BAGArt\TelegramBotStt\Console\SttPruneCommand::class,
                BAGArt\TelegramBotStt\Console\SttDoctorCommand::class,
            ],
            schedule: [
                new TgModuleSchedule(command: 'stt:prune', expression: '0 3 * * *'),
            ],
            routes: [
                new RouteDeclaration('command', '/text', payload: [
                    'processor' => BAGArt\TelegramBotStt\Processing\TextCommandProcessor::class,
                    'description' => 'Transcribe a voice message',
                ]),
            ],
        ),
        'summarizer' => new TgModuleConfig(
            enabled: env('TG_MODULE_ENABLED_summarizer', true),
            provider: BAGArt\TelegramBotSummarizer\SummarizerModule::class,
            laravelProvider: BAGArt\TelegramBotSummarizer\TelegramBotSummarizerServiceProvider::class,
            commands: [
                BAGArt\TelegramBotSummarizer\Console\SummarizerDigestsCommand::class,
            ],
            schedule: [
                new TgModuleSchedule(command: 'summarizer:digests', expression: '* * * * *'),
            ],
            routes: [
                new RouteDeclaration('command', '/summarizer', payload: [
                    'processor' => BAGArt\TelegramBotSummarizer\Processing\SummarizerCommandProcessor::class,
                    'description' => 'Chat summarizer admin panel',
                ]),
                new RouteDeclaration('command', '/summarizer_cancel', payload: [
                    'processor' => BAGArt\TelegramBotSummarizer\Processing\SummarizerCancelCommandProcessor::class,
                    'description' => 'Cancel pending summarizer input',
                ]),
            ],
        ),
        'tts' => new TgModuleConfig(
            enabled: env('TG_MODULE_ENABLED_tts', true),
            provider: BAGArt\TelegramBotTts\TtsModule::class,
            laravelProvider: BAGArt\TelegramBotTts\TelegramBotTtsServiceProvider::class,
            commands: [
                BAGArt\TelegramBotTts\Console\TtsPruneCommand::class,
                BAGArt\TelegramBotTts\Console\TtsDoctorCommand::class,
                BAGArt\TelegramBotTts\Console\TtsBenchCommand::class,
            ],
            schedule: [
                new TgModuleSchedule(command: 'tts:prune', expression: '0 3 * * *'),
            ],
            routes: [
                new RouteDeclaration('command', '/voice', payload: [
                    'processor' => BAGArt\TelegramBotTts\Processing\VoiceCommandProcessor::class,
                    'description' => 'Speak text with TTS',
                ]),
            ],
        ),

        // Proxy Operations (menu_integration.md M-6): platform wrapper,
        // enabled for engine-managed registration. Bootstrap provider
        // exemption remains for config/migrations/bindings (always-on).
        'proxy' => new TgModuleConfig(
            enabled: env('TG_MODULE_ENABLED_proxy', true),
            provider: BAGArt\ProxyOperations\ProxyOperationsModule::class,
            laravelProvider: BAGArt\ProxyOperations\ProxyOperationsServiceProvider::class,
            commands: [
                BAGArt\ProxyOperations\Transport\RunCapabilityProbesCommand::class,
                BAGArt\ProxyOperations\Audit\LeaseReaperCommand::class,
            ],
            schedule: [
                new TgModuleSchedule(command: 'proxy:lease:reap', expression: '* * * * *'),
            ],
            routes: [
                new RouteDeclaration('command', '/proxy', payload: [
                    'processor' => BAGArt\ProxyOperations\Bot\ProxyCommand::class,
                    'description' => 'Proxy operations (private chats only)',
                ]),
            ],
            frontendPages: [
                base_path('misc/BAGArt/tgbot-module-proxy/resources/js/pages'),
            ],
            sourcePath: base_path('misc/BAGArt/tgbot-module-proxy'),
        ),
    ],
];
