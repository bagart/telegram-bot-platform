import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import type { BreadcrumbItem } from '@/types';
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
import SettingsLayout from '@/layouts/settings/layout';

interface Bot {
    bot_id: string;
}

interface Screen {
    screenId: string;
    moduleId: string;
    label: string;
    fieldsCount: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Module Settings', href: '/modules/settings' },
];

export default function ModuleSettingsIndex({
    bots,
    selectedBotId,
    screens,
}: {
    bots: Bot[];
    selectedBotId: string | null;
    screens: Screen[];
}) {
    const [selectedBot, setSelectedBot] = useState<string>(
        selectedBotId ?? '',
    );

    const handleBotChange = (value: string) => {
        setSelectedBot(value);
        router.get(
            '/modules/settings',
            { bot_id: value },
            { preserveState: true, replace: true },
        );
    };

    const openScreen = (screenId: string) => {
        router.get(`/modules/settings/${selectedBot}/${screenId}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Module Settings" />

            <h1 className="sr-only">Module Settings</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Module Settings"
                        description="Configure settings for enabled modules on a per-bot basis. Settings are stored as JSON config files."
                    />

                    <div className="grid gap-2">
                        <label className="text-sm font-medium">
                            Select Bot
                        </label>
                        <Select
                            value={selectedBot}
                            onValueChange={handleBotChange}
                        >
                            <SelectTrigger className="w-full max-w-xs">
                                <SelectValue placeholder="Choose a bot..." />
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

                    {selectedBot && screens.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No settings screens available for this bot.
                            Modules may not have any settings configured, or
                            no modules are enabled.
                        </p>
                    )}

                    {screens.length > 0 && (
                        <div className="space-y-2">
                            {screens.map((screen) => (
                                <div
                                    key={screen.screenId}
                                    className="flex items-center justify-between rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                                >
                                    <div>
                                        <h3 className="text-sm font-medium">
                                            {screen.label}
                                        </h3>
                                        <p className="text-xs text-muted-foreground">
                                            Module: {screen.moduleId} &middot;{' '}
                                            {screen.fieldsCount} field
                                            {screen.fieldsCount !== 1
                                                ? 's'
                                                : ''}
                                        </p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            openScreen(screen.screenId)
                                        }
                                    >
                                        Configure
                                    </Button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
