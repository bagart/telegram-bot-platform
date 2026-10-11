import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import type { BreadcrumbItem } from '@/types';
import ModuleActivationController from '@/actions/BAGArt/TelegramBotManagement/Http/Controllers/ModuleActivationController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { index as modulesIndex } from '@/routes/management/modules';

interface Bot {
    bot_id: string;
}

interface ModuleState {
    id: string;
    name: string;
    version: string;
    capabilities: string[];
    defaultEnabled: boolean;
    platformEnabled: boolean;
    enabled: boolean;
    explicitlyDisabled: boolean;
}

interface ActivationLabels {
    title: string;
    description: string;
    select_bot: string;
    no_bots: string;
    no_modules: string;
    column_module: string;
    column_capabilities: string;
    column_state: string;
    state_enabled: string;
    state_disabled: string;
    enable: string;
    disable: string;
}

export default function ModuleActivationIndex({
    bots,
    selectedBotId,
    modules,
    labels,
}: {
    bots: Bot[];
    selectedBotId: string | null;
    modules: ModuleState[];
    labels: ActivationLabels;
}) {
    const [selectedBot, setSelectedBot] = useState<string>(selectedBotId ?? '');

    const handleBotChange = (value: string) => {
        setSelectedBot(value);
        router.get(
            modulesIndex().url,
            { bot_id: value },
            { preserveState: true, replace: true },
        );
    };

    const flip = (moduleId: string, enable: boolean) => {
        const action = enable
            ? ModuleActivationController.enable
            : ModuleActivationController.disable;
        router.post(action.url({ botId: selectedBot, moduleId }));
    };

    return (
        <AppLayout
            breadcrumbs={
                [{ title: 'Modules', href: modulesIndex() }] as BreadcrumbItem[]
            }
        >
            <Head title={labels.title} />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title={labels.title}
                    description={labels.description}
                />

                {bots.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {labels.no_bots}
                    </p>
                ) : (
                    <div className="grid gap-2">
                        <label className="text-sm font-medium">
                            {labels.select_bot}
                        </label>
                        <Select
                            value={selectedBot}
                            onValueChange={handleBotChange}
                        >
                            <SelectTrigger className="w-full max-w-xs">
                                <SelectValue placeholder={labels.select_bot} />
                            </SelectTrigger>
                            <SelectContent>
                                {bots.map((bot) => (
                                    <SelectItem
                                        key={bot.bot_id}
                                        value={bot.bot_id}
                                    >
                                        {bot.bot_id}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                )}

                {selectedBot && modules.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        {labels.no_modules}
                    </p>
                )}

                {modules.length > 0 && (
                    <div className="space-y-2">
                        {modules.map((module) => (
                            <div
                                key={module.id}
                                className="flex items-center justify-between rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                            >
                                <div>
                                    <h3 className="text-sm font-medium">
                                        {module.name}
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        {module.id} &middot; v{module.version}{' '}
                                        &middot;{' '}
                                        {module.capabilities.join(', ') ||
                                            '\u2014'}
                                    </p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className="text-sm text-muted-foreground">
                                        {module.enabled
                                            ? labels.state_enabled
                                            : labels.state_disabled}
                                    </span>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={!module.platformEnabled}
                                        onClick={() =>
                                            flip(module.id, !module.enabled)
                                        }
                                    >
                                        {module.enabled
                                            ? labels.disable
                                            : labels.enable}
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
