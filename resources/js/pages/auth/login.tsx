import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { postJson } from '@/lib/xsrf';
import { dashboard } from '@/routes';
import {
    complete as completeChallenge,
    show as showChallenge,
    store as storeChallenge,
} from '@/routes/tg-auth/challenge';

type Challenge = {
    id: string;
    code: string;
    botUsername: string | null;
    expiresAt: string;
};

type Props = {
    challenge: Challenge;
};

type Phase = 'waiting' | 'confirmed' | 'expired';

/**
 * Device-code login (D15 / docs/questions/magic-link-login-identification.md):
 * the page shows a one-time code, the user sends it to the bot with
 * `/login <code>`, the browser polls until the bot confirms and then
 * consumes the session. No platform URL is ever delivered through Telegram.
 */
export default function Login({ challenge: initialChallenge }: Props) {
    const [challenge, setChallenge] = useState(initialChallenge);
    const [phase, setPhase] = useState<Phase>('waiting');
    const [issueError, setIssueError] = useState(false);
    const phaseRef = useRef<Phase>('waiting');

    useEffect(() => {
        phaseRef.current = phase;
    }, [phase]);

    useEffect(() => {
        const timer = window.setInterval(() => {
            if (phaseRef.current !== 'waiting') {
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

                    if (data.status === 'confirmed') {
                        setPhase('confirmed');
                        const completed = (await postJson(
                            completeChallenge.url(challenge.id),
                        )) as {
                            authenticated?: boolean;
                        };

                        if (completed.authenticated) {
                            router.visit(dashboard.url());
                        }
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
    }, [challenge.id]);

    const issueNewCode = () => {
        setIssueError(false);
        void (async () => {
            try {
                const data = (await postJson(storeChallenge.url())) as {
                    id: string;
                    code: string;
                    botUsername: string | null;
                    expiresAt: string;
                };
                setChallenge({
                    id: data.id,
                    code: data.code,
                    botUsername: data.botUsername,
                    expiresAt: data.expiresAt,
                });
                setPhase('waiting');
            } catch {
                setIssueError(true);
            }
        })();
    };

    const botUsername = challenge.botUsername;

    return (
        <AuthLayout
            title="Log in with Telegram"
            description="Send the one-time code below to the bot — it will sign you in here."
        >
            <Head title="Log in" />

            <div className="flex flex-col gap-6">
                <div className="rounded-lg border bg-muted/40 p-6 text-center">
                    <div className="font-mono text-3xl font-semibold tracking-[0.3em]">
                        {challenge.code}
                    </div>
                    <p className="mt-3 text-sm text-muted-foreground">
                        In your chat with the bot, send:
                    </p>
                    <code className="mt-1 block text-sm">
                        /login {challenge.code}
                    </code>
                </div>

                {botUsername && (
                    <a
                        href={`https://t.me/${botUsername}`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex h-10 w-full items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        data-test="open-bot-link"
                    >
                        Open @{botUsername}
                    </a>
                )}

                {phase === 'waiting' && (
                    <div
                        className="flex items-center justify-center gap-2 text-sm text-muted-foreground"
                        data-test="login-waiting"
                    >
                        <Spinner />
                        Waiting for confirmation in Telegram…
                    </div>
                )}

                {phase === 'confirmed' && (
                    <div
                        className="flex items-center justify-center gap-2 text-sm font-medium text-green-600"
                        data-test="login-confirmed"
                    >
                        <Spinner />
                        Confirmed — signing you in…
                    </div>
                )}

                {phase === 'expired' && (
                    <div
                        className="flex flex-col items-center gap-3"
                        data-test="login-expired"
                    >
                        <p className="text-sm text-muted-foreground">
                            That code has expired.
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={issueNewCode}
                        >
                            Get a new code
                        </Button>
                    </div>
                )}

                {issueError && (
                    <p className="text-center text-sm text-destructive">
                        Could not issue a new code — please refresh the page.
                    </p>
                )}

                <div className="text-center text-sm text-muted-foreground">
                    Recovering a superadmin account?{' '}
                    <a
                        href="/login/email"
                        className="underline hover:text-foreground"
                    >
                        Use email and password
                    </a>
                </div>
            </div>
        </AuthLayout>
    );
}
