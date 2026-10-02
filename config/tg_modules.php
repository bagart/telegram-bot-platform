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
use BAGArt\TelegramModuleEngine\Settings\SettingsDescriptor;
use BAGArt\TelegramModuleEngine\Settings\SettingsField;
use BAGArt\TelegramModuleEngine\Settings\SettingsFieldType;
use BAGArt\TelegramModuleEngine\Settings\SettingsScreenContribution;
use BAGArt\TelegramModuleEngine\Settings\WebAccessLevel;
use BAGArt\TelegramModuleEngine\Settings\WebScreenBinding;

return [
    'strict' => false,

    // Dispatch driver for the lib ModuleEnablementContract (doc 05):
    // 'legacy' = management service over tg_module_enablements (default),
    // 'engine' = engine adapter over bot_module_activations.
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
                // /report is owned by antispam; nettools' report probe stays
                // flat-registry-only until the collision is a product decision.
                new RouteDeclaration('command', '/report', payload: [
                    'processor' => BAGArt\TelegramBotAntispam\Processors\AntispamReportCommand::class,
                    'description' => 'Report a user for moderation',
                ]),
                new RouteDeclaration('command', '/appeal', payload: [
                    'processor' => BAGArt\TelegramBotAntispam\Processors\AppealCommand::class,
                    'description' => 'Appeal a punishment',
                ]),
            ],
            settingsScreens: [
                new SettingsScreenContribution(
                    screenId: 'antispam.settings',
                    descriptor: new SettingsDescriptor(fields: [
                        new SettingsField(
                            fieldId: 'antispam.counter_driver',
                            type: SettingsFieldType::Enum,
                            default: 'redis',
                            options: [
                                ['value' => 'redis', 'labelKey' => 'antispam::settings.counter_driver_redis'],
                                ['value' => 'memory', 'labelKey' => 'antispam::settings.counter_driver_memory'],
                            ],
                        ),
                        new SettingsField(
                            fieldId: 'antispam.ai.enabled',
                            type: SettingsFieldType::Bool,
                            default: false,
                        ),
                        new SettingsField(
                            fieldId: 'antispam.blocklist.retention_days',
                            type: SettingsFieldType::Int,
                            default: 30,
                            min: 1,
                            max: 365,
                        ),
                        new SettingsField(
                            fieldId: 'antispam.cache_ttl_seconds',
                            type: SettingsFieldType::Int,
                            default: 300,
                            min: 30,
                            max: 600,
                        ),
                        new SettingsField(
                            fieldId: 'antispam.instrumentation',
                            type: SettingsFieldType::Bool,
                            default: false,
                        ),
                    ]),
                    web: new WebScreenBinding(
                        component: 'Settings/Antispam',
                        accessLevel: WebAccessLevel::PlatformAdmin,
                    ),
                ),
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
            settingsScreens: [
                new SettingsScreenContribution(
                    screenId: 'nettools.settings',
                    descriptor: new SettingsDescriptor(fields: [
                        new SettingsField(
                            fieldId: 'nettools.features.recon',
                            type: SettingsFieldType::Bool,
                            default: true,
                        ),
                        new SettingsField(
                            fieldId: 'nettools.features.active',
                            type: SettingsFieldType::Bool,
                            default: true,
                        ),
                        new SettingsField(
                            fieldId: 'nettools.features.audit',
                            type: SettingsFieldType::Bool,
                            default: true,
                        ),
                        new SettingsField(
                            fieldId: 'nettools.features.portscan',
                            type: SettingsFieldType::Bool,
                            default: false,
                        ),
                        new SettingsField(
                            fieldId: 'nettools.features.dnsbl',
                            type: SettingsFieldType::Bool,
                            default: false,
                        ),
                        new SettingsField(
                            fieldId: 'nettools.quotas.daily_units',
                            type: SettingsFieldType::Int,
                            default: 40,
                            min: 1,
                            max: 500,
                        ),
                        new SettingsField(
                            fieldId: 'nettools.quotas.chat_ceiling',
                            type: SettingsFieldType::Int,
                            default: 150,
                            min: 10,
                            max: 2000,
                        ),
                        new SettingsField(
                            fieldId: 'nettools.ui.heavy_confirm',
                            type: SettingsFieldType::Bool,
                            default: true,
                        ),
                        new SettingsField(
                            fieldId: 'nettools.memory.enabled',
                            type: SettingsFieldType::Bool,
                            default: true,
                        ),
                    ]),
                    web: new WebScreenBinding(
                        component: 'Settings/Nettools',
                        accessLevel: WebAccessLevel::PlatformAdmin,
                    ),
                ),
            ],
            routes: [
                // Mirrors Commands\CommandMap::MAP (probe commands); /report
                // deliberately omitted — antispam owns it in the route table.
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
            settingsScreens: [
                new SettingsScreenContribution(
                    screenId: 'summarizer.settings',
                    descriptor: new SettingsDescriptor(fields: [
                        new SettingsField(
                            fieldId: 'summarizer.retention_days',
                            type: SettingsFieldType::Int,
                            default: 14,
                            min: 1,
                            max: 90,
                        ),
                        new SettingsField(
                            fieldId: 'summarizer.transcript_budget_chars',
                            type: SettingsFieldType::Int,
                            default: 120000,
                            min: 1000,
                            max: 500000,
                        ),
                        new SettingsField(
                            fieldId: 'summarizer.max_transcript_messages',
                            type: SettingsFieldType::Int,
                            default: 2000,
                            min: 100,
                            max: 10000,
                        ),
                        new SettingsField(
                            fieldId: 'summarizer.llm_timeout_seconds',
                            type: SettingsFieldType::Int,
                            default: 90,
                            min: 5,
                            max: 300,
                        ),
                        new SettingsField(
                            fieldId: 'summarizer.pending_input_ttl_minutes',
                            type: SettingsFieldType::Int,
                            default: 15,
                            min: 1,
                            max: 120,
                        ),
                    ]),
                    web: new WebScreenBinding(
                        component: 'Settings/Summarizer',
                        accessLevel: WebAccessLevel::PlatformAdmin,
                    ),
                ),
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
            settingsScreens: [
                new SettingsScreenContribution(
                    screenId: 'tts.settings',
                    descriptor: new SettingsDescriptor(fields: [
                        new SettingsField(
                            fieldId: 'tts.budget_seconds',
                            type: SettingsFieldType::Int,
                            default: 30,
                            min: 5,
                            max: 120,
                        ),
                        new SettingsField(
                            fieldId: 'tts.global_concurrency',
                            type: SettingsFieldType::Int,
                            default: 4,
                            min: 1,
                            max: 20,
                        ),
                        new SettingsField(
                            fieldId: 'tts.timeout_seconds',
                            type: SettingsFieldType::Int,
                            default: 25,
                            min: 5,
                            max: 60,
                        ),
                        new SettingsField(
                            fieldId: 'tts.retention_days',
                            type: SettingsFieldType::Int,
                            default: 30,
                            min: 1,
                            max: 90,
                        ),
                    ]),
                    web: new WebScreenBinding(
                        component: 'Settings/Tts',
                        accessLevel: WebAccessLevel::PlatformAdmin,
                    ),
                ),
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
            settingsScreens: [
                new SettingsScreenContribution(
                    screenId: 'proxy.settings',
                    descriptor: new SettingsDescriptor(fields: [
                        new SettingsField(
                            fieldId: 'proxy.selection_strategy',
                            type: SettingsFieldType::Enum,
                            default: 'round_robin',
                            labelKey: 'proxy::settings.selection_strategy',
                            descriptionKey: 'proxy::settings.selection_strategy_desc',
                            options: [
                                ['value' => 'round_robin', 'labelKey' => 'proxy::settings.strategy_round_robin'],
                                ['value' => 'random', 'labelKey' => 'proxy::settings.strategy_random'],
                                ['value' => 'least_used', 'labelKey' => 'proxy::settings.strategy_least_used'],
                                ['value' => 'weighted', 'labelKey' => 'proxy::settings.strategy_weighted'],
                            ],
                        ),
                        new SettingsField(
                            fieldId: 'proxy.lease_ttl_seconds',
                            type: SettingsFieldType::Int,
                            default: 300,
                            labelKey: 'proxy::settings.lease_ttl',
                            descriptionKey: 'proxy::settings.lease_ttl_desc',
                            min: 60,
                            max: 3600,
                        ),
                        new SettingsField(
                            fieldId: 'proxy.reaper_batch_size',
                            type: SettingsFieldType::Int,
                            default: 200,
                            labelKey: 'proxy::settings.reaper_batch_size',
                            descriptionKey: 'proxy::settings.reaper_batch_size_desc',
                            min: 10,
                            max: 1000,
                        ),
                        new SettingsField(
                            fieldId: 'proxy.max_concurrent_probes',
                            type: SettingsFieldType::Int,
                            default: 50,
                            labelKey: 'proxy::settings.max_concurrent_probes',
                            descriptionKey: 'proxy::settings.max_concurrent_probes_desc',
                            min: 1,
                            max: 500,
                        ),
                        new SettingsField(
                            fieldId: 'proxy.hysteresis_degrade',
                            type: SettingsFieldType::Int,
                            default: 2,
                            labelKey: 'proxy::settings.hysteresis_degrade',
                            descriptionKey: 'proxy::settings.hysteresis_degrade_desc',
                            min: 1,
                            max: 10,
                        ),
                        new SettingsField(
                            fieldId: 'proxy.hysteresis_failing',
                            type: SettingsFieldType::Int,
                            default: 5,
                            labelKey: 'proxy::settings.hysteresis_failing',
                            descriptionKey: 'proxy::settings.hysteresis_failing_desc',
                            min: 1,
                            max: 20,
                        ),
                        new SettingsField(
                            fieldId: 'proxy.hysteresis_dead',
                            type: SettingsFieldType::Int,
                            default: 10,
                            labelKey: 'proxy::settings.hysteresis_dead',
                            descriptionKey: 'proxy::settings.hysteresis_dead_desc',
                            min: 1,
                            max: 50,
                        ),
                    ]),
                    web: new WebScreenBinding(
                        component: 'Settings/Proxy',
                        accessLevel: WebAccessLevel::PlatformAdmin,
                    ),
                ),
            ],
        ),
    ],
];
