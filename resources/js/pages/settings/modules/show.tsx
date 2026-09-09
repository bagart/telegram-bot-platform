import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import type { BreadcrumbItem } from '@/types';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

interface FieldOption {
    value: string;
    labelKey: string;
}

interface Field {
    fieldId: string;
    type: 'int' | 'float' | 'bool' | 'string' | 'enum' | 'text';
    default: unknown;
    label: string | null;
    description: string | null;
    min: number | null;
    max: number | null;
    options: FieldOption[] | null;
    required: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Module Settings', href: '/modules/settings' },
];

export default function ModuleSettingsShow({
    botId,
    moduleId,
    screenId,
    label,
    fields,
    values,
    mtime,
}: {
    botId: string;
    moduleId: string;
    screenId: string;
    label: string;
    fields: Field[];
    values: Record<string, unknown>;
    mtime: number;
}) {
    const [formValues, setFormValues] = useState<Record<string, unknown>>(
        () => {
            const initial: Record<string, unknown> = {};
            for (const field of fields) {
                initial[field.fieldId] =
                    values[field.fieldId] ?? field.default ?? '';
            }
            return initial;
        },
    );
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [conflict, setConflict] = useState<{
        serverValues: Record<string, unknown>;
        serverMtime: number;
    } | null>(null);

    const setField = (fieldId: string, value: unknown) => {
        setFormValues((prev) => ({ ...prev, [fieldId]: value }));
        setErrors((prev) => {
            const next = { ...prev };
            delete next[fieldId];
            return next;
        });
    };

    const submit = () => {
        setSaving(true);
        setErrors({});
        setConflict(null);

        router.put(
            `/modules/settings/${botId}/${screenId}`,
            { ...formValues, _mtime: mtime },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSaving(false);
                },
                onError: (serverErrors: Record<string, string>) => {
                    setSaving(false);
                    if (serverErrors && typeof serverErrors === 'object') {
                        setErrors(serverErrors);
                    }
                },
                onFinish: () => {
                    setSaving(false);
                },
            },
        );
    };

    const handleConflictRebase = () => {
        if (conflict === null) {
            return;
        }
        setFormValues((prev) => {
            const rebased = { ...prev };
            for (const field of fields) {
                if (field.fieldId in conflict.serverValues) {
                    rebased[field.fieldId] = conflict.serverValues[field.fieldId];
                }
            }
            return rebased;
        });
        setConflict(null);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${label} Settings`} />

            <h1 className="sr-only">{label} Settings</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <div>
                        <Heading
                            variant="small"
                            title={label}
                            description={`Settings for module "${moduleId}" on bot "${botId}".`}
                        />
                        <p className="text-xs text-muted-foreground">
                            Config file:{' '}
                            <code>
                                storage/app/modules/{moduleId}/settings.json
                            </code>
                        </p>
                    </div>

                    <Separator />

                    <div className="space-y-4">
                        {fields.map((field) => (
                            <FieldRenderer
                                key={field.fieldId}
                                field={field}
                                value={formValues[field.fieldId]}
                                onChange={(v) => setField(field.fieldId, v)}
                                error={errors[field.fieldId]}
                            />
                        ))}
                    </div>

                    {conflict !== null && (
                        <div className="rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950">
                            <p className="text-sm font-medium text-amber-800 dark:text-amber-200">
                                Conflict: settings were modified by someone
                                else.
                            </p>
                            <div className="mt-2 flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleConflictRebase}
                                >
                                    Rebase on their changes
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setConflict(null)}
                                >
                                    Cancel
                                </Button>
                            </div>
                        </div>
                    )}

                    <div className="flex items-center gap-4">
                        <Button disabled={saving} onClick={submit}>
                            {saving ? 'Saving...' : 'Save'}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.get('/modules/settings', {
                                    bot_id: botId,
                                })
                            }
                        >
                            Back to list
                        </Button>
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

function FieldRenderer({
    field,
    value,
    onChange,
    error,
}: {
    field: Field;
    value: unknown;
    onChange: (v: unknown) => void;
    error?: string;
}) {
    const label = field.label ?? field.fieldId;
    const description = field.description;

    return (
        <div className="space-y-2">
            <div className="grid gap-1.5">
                <Label htmlFor={field.fieldId}>
                    {label}
                    {field.required && (
                        <span className="ml-1 text-destructive">*</span>
                    )}
                </Label>
                {description && (
                    <p className="text-xs text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>

            {field.type === 'bool' && (
                <div className="flex items-center gap-2">
                    <Checkbox
                        id={field.fieldId}
                        checked={Boolean(value)}
                        onCheckedChange={(checked) => onChange(Boolean(checked))}
                    />
                    <Label
                        htmlFor={field.fieldId}
                        className="font-normal text-muted-foreground"
                    >
                        {value ? 'Enabled' : 'Disabled'}
                    </Label>
                </div>
            )}

            {field.type === 'int' && (
                <Input
                    id={field.fieldId}
                    type="number"
                    step="1"
                    min={field.min ?? undefined}
                    max={field.max ?? undefined}
                    value={String(value ?? '')}
                    onChange={(e) => onChange(parseInt(e.target.value, 10) || 0)}
                    required={field.required}
                />
            )}

            {field.type === 'float' && (
                <Input
                    id={field.fieldId}
                    type="number"
                    step="any"
                    min={field.min ?? undefined}
                    max={field.max ?? undefined}
                    value={String(value ?? '')}
                    onChange={(e) =>
                        onChange(parseFloat(e.target.value) || 0)
                    }
                    required={field.required}
                />
            )}

            {field.type === 'string' && (
                <Input
                    id={field.fieldId}
                    type="text"
                    value={String(value ?? '')}
                    onChange={(e) => onChange(e.target.value)}
                    required={field.required}
                />
            )}

            {field.type === 'text' && (
                <textarea
                    id={field.fieldId}
                    className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    value={String(value ?? '')}
                    onChange={(e) => onChange(e.target.value)}
                    required={field.required}
                />
            )}

            {field.type === 'enum' && field.options && (
                <Select
                    value={String(value ?? '')}
                    onValueChange={(v) => onChange(v)}
                >
                    <SelectTrigger className="w-full">
                        <SelectValue placeholder="Select..." />
                    </SelectTrigger>
                    <SelectContent>
                        {field.options.map((opt) => (
                            <SelectItem key={opt.value} value={opt.value}>
                                {opt.labelKey}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            )}

            {error && <p className="text-xs text-destructive">{error}</p>}
        </div>
    );
}
