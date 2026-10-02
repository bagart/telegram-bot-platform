import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { delJson, postJson } from '@/lib/xsrf';
import {
    show as showChallenge,
    store as storeChallenge,
} from '@/routes/tg-auth/challenge';
import { unlink as unlinkTelegram } from '@/routes/tg-auth/telegram';

type Challenge = {
    id: string;
    code: string;
    botUsername: string | null;
    expiresAt: string;
};

type Phase = 'idle' | 'code' | 'confirmed' | 'expired';

/**
 * Telegram link surface on the profile (D9): connect = mint a device-code
 * challenge and confirm it with `/login <code>` in the bot; disconnect =
 * DELETE, refused with a clear message when it is the last recovery channel.
 */
export default function TelegramAccount({
    telegramId,
}: {
    telegramId: number | null;
}) {
    const [phase, setPhase] = useState<Phase>('idle');
    const [challenge, setChallenge] = useState<Challenge | null>(null);
    const [error, setError] = useState<string | null>(null);
    const phaseRef = useRef<Phase>('idle');

    useEffect(() => {
        phaseRef.current = phase;
    }, [phase]);

    useEffect(() => {
        if (phase !== 'code' || !challenge) {
            return;
        }

        const timer = window.setInterval(() => {
            if (phaseRef.current !== 'code') {
                return;
            }

            void (async () => {
                try {
                    const response = await fetch(
                        showChallenge.url(challenge.id),
                        {
                            credentials: 'same-origin',
                            headers: { Accept: 'application/json' },
                        },
                    );

                    if (!response.ok) {
                        return;
                    }

                    const data = (await response.json()) as { status: string };

                    if (
                        data.status === 'confirmed' ||
                        data.status === 'consumed'
                    ) {
                        setPhase('confirmed');
                        router.reload({ only: ['telegramId'] });
                    } else if (
                        data.status === 'expired' ||
                        data.status === 'failed'
                    ) {
                        setPhase('expired');
                    }
                } catch {
                    // transient network failure — keep polling
                }
            })();
        }, 2000);

        return () => window.clearInterval(timer);
    }, [phase, challenge]);

    const connect = () => {
        setError(null);
        void (async () => {
            try {
                const data = await postJson<Challenge>(storeChallenge.url());
                setChallenge(data);
                setPhase('code');
            } catch {
                setError('Could not start the connection flow — please retry.');
            }
        })();
    };

    const disconnect = () => {
        setError(null);
        void (async () => {
            try {
                await delJson(unlinkTelegram.url());
                router.reload({ only: ['telegramId'] });
            } catch (err) {
                const status = (err as { status?: number }).status;
                setError(
                    status === 422
                        ? 'Add a recovery email to this account before disconnecting Telegram.'
                        : 'Could not disconnect Telegram — please retry.',
                );
            }
        })();
    };

    if (telegramId !== null) {
        return (
            <div className="space-y-4">
                <Heading
                    variant="small"
                    title="Telegram account"
                    description="Your platform identity is linked to a Telegram account"
                />
                <div
                    className="flex flex-wrap items-center gap-4"
                    data-test="tg-linked"
                >
                    <span className="text-sm text-muted-foreground">
                        Linked as{' '}
                        <span className="font-mono">{telegramId}</span>
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={disconnect}
                        data-test="tg-disconnect"
                    >
                        Disconnect
                    </Button>
                </div>
                {error && <p className="text-sm text-destructive">{error}</p>}
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <Heading
                variant="small"
                title="Telegram account"
                description="Link your Telegram account to sign in with it"
            />

            {phase === 'idle' && (
                <Button type="button" onClick={connect} data-test="tg-connect">
                    Connect Telegram
                </Button>
            )}

            {phase === 'code' && challenge && (
                <div className="space-y-3" data-test="tg-connect-code">
                    <div className="rounded-lg border bg-muted/40 p-4 text-center">
                        <div className="font-mono text-2xl font-semibold tracking-[0.3em]">
                            {challenge.code}
                        </div>
                        <p className="mt-2 text-sm text-muted-foreground">
                            In your chat with the bot, send:{' '}
                            <code>/login {challenge.code}</code>
                        </p>
                    </div>
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Spinner />
                        Waiting for confirmation in Telegram…
                    </div>
                    {challenge.botUsername && (
                        <a
                            href={`https://t.me/${challenge.botUsername}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-sm underline hover:text-foreground"
                        >
                            Open @{challenge.botUsername}
                        </a>
                    )}
                </div>
            )}

            {phase === 'confirmed' && (
                <div
                    className="text-sm font-medium text-green-600"
                    data-test="tg-connected"
                >
                    Telegram connected.
                </div>
            )}

            {phase === 'expired' && (
                <div className="flex items-center gap-3">
                    <span className="text-sm text-muted-foreground">
                        That code has expired.
                    </span>
                    <Button type="button" variant="outline" onClick={connect}>
                        Get a new code
                    </Button>
                </div>
            )}

            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}
