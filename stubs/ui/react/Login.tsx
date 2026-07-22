/*
 * Passwordless — login page stub (Inertia + React + TypeScript, React starter-kit stack)
 * ---------------------------------------------------------------------------------------
 * Published by:  php artisan vendor:publish --tag=passwordless-ui-react
 * Target path:   resources/js/pages/passwordless/login.tsx
 *
 * This is YOUR file now — edit freely. It talks to the package's JSON endpoints
 * (registered by the package), so the headless core is untouched. It submits with
 * `fetch` rather than Inertia's useForm, because the endpoints return JSON, not
 * Inertia responses.
 *
 * Requirements (present in a Laravel React starter-kit app): React 18+, Inertia 2,
 * Tailwind v4. The props below are supplied by the example route in
 * routes/passwordless-ui.php (also published). Style is intentionally plain Tailwind
 * (no component-library imports) so the stub drops in without extra dependencies.
 */
import { Head } from '@inertiajs/react';
import { FormEvent, useMemo, useRef, useState } from 'react';

type LoginProps = {
    appName: string;
    codeEnabled: boolean;
    linkEnabled: boolean;
    codeLength: number;
    redirect: string;
    endpoints: {
        sendCode: string | null;
        verifyCode: string | null;
        sendLink: string | null;
    };
};

type Step = 'email' | 'code' | 'sent';

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function postJson(url: string, body: Record<string, unknown>): Promise<Response> {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });
}

export default function Login({
    appName,
    codeEnabled,
    linkEnabled,
    codeLength,
    redirect,
    endpoints,
}: LoginProps) {
    const [step, setStep] = useState<Step>('email');
    const [email, setEmail] = useState('');
    const [digits, setDigits] = useState<string[]>(() => Array(codeLength).fill(''));
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const otpRef = useRef<HTMLDivElement>(null);

    const heading = useMemo(() => {
        if (step === 'code') return `Enter the ${codeLength}-digit code we emailed you.`;
        if (step === 'sent') return 'A sign-in link is on its way.';
        return 'Enter your email to receive a one-time code.';
    }, [step, codeLength]);

    function boxes(): HTMLInputElement[] {
        return otpRef.current ? Array.from(otpRef.current.querySelectorAll('input')) : [];
    }

    function messageFor(res: Response, data: any, fallback: string): string {
        if (res.status === 422) return (Object.values(data?.errors ?? {})[0] as string[])?.[0] ?? fallback;
        if (res.status === 429 || res.status === 423) {
            const secs = data?.retry_after ?? res.headers.get('Retry-After');
            return secs ? `Please wait ${secs}s and try again.` : data?.message ?? fallback;
        }
        return data?.message ?? fallback;
    }

    async function requestCode(e: FormEvent) {
        e.preventDefault();
        if (!endpoints.sendCode) return;
        setError('');
        setLoading(true);
        try {
            const res = await postJson(endpoints.sendCode, { email });
            const data = await res.json().catch(() => ({}));
            if (res.status === 202) {
                setDigits(Array(codeLength).fill(''));
                setStep('code');
                return;
            }
            setError(messageFor(res, data, 'Something went wrong. Try again.'));
        } catch {
            setError('Network error. Try again.');
        } finally {
            setLoading(false);
        }
    }

    async function requestLink() {
        if (!endpoints.sendLink) return;
        setError('');
        setLoading(true);
        try {
            const res = await postJson(endpoints.sendLink, { email });
            const data = await res.json().catch(() => ({}));
            if (res.status === 202) {
                setStep('sent');
                return;
            }
            setError(messageFor(res, data, 'Something went wrong. Try again.'));
        } catch {
            setError('Network error. Try again.');
        } finally {
            setLoading(false);
        }
    }

    async function verifyCode(code: string) {
        if (!endpoints.verifyCode) return;
        setError('');
        setLoading(true);
        try {
            const res = await postJson(endpoints.verifyCode, { email, code });
            if (res.status === 204 || res.status === 200) {
                window.location.assign(redirect);
                return;
            }
            const data = await res.json().catch(() => ({}));
            setError(messageFor(res, data, 'Invalid or expired code.'));
            setDigits(Array(codeLength).fill(''));
        } catch {
            setError('Network error. Try again.');
        } finally {
            setLoading(false);
        }
    }

    function onDigitInput(value: string, i: number) {
        const clean = value.replace(/\D/g, '').slice(-1);
        const next = [...digits];
        next[i] = clean;
        setDigits(next);
        const bs = boxes();
        if (clean && i < codeLength - 1) bs[i + 1]?.focus();
        if (next.every((d) => d !== '')) verifyCode(next.join(''));
    }

    function onDigitKeyDown(e: React.KeyboardEvent<HTMLInputElement>, i: number) {
        if (e.key === 'Backspace' && !e.currentTarget.value && i > 0) boxes()[i - 1]?.focus();
    }

    function onPaste(e: React.ClipboardEvent) {
        e.preventDefault();
        const text = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, codeLength);
        if (!text) return;
        const next = Array(codeLength).fill('');
        text.split('').forEach((ch, idx) => (next[idx] = ch));
        setDigits(next);
        const bs = boxes();
        bs[Math.min(text.length, codeLength - 1)]?.focus();
        if (text.length === codeLength) verifyCode(text);
    }

    const primaryBtn =
        'inline-flex w-full items-center justify-center rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800 focus:outline-none focus:ring-2 focus:ring-neutral-900/20 active:translate-y-px disabled:opacity-60 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200';

    return (
        <div className="flex min-h-screen items-center justify-center bg-neutral-50 px-4 py-12 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
            <Head title={`Sign in — ${appName}`} />
            <div className="w-full max-w-sm">
                <div className="mb-8 flex flex-col items-center gap-3 text-center">
                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-neutral-900 text-lg font-semibold text-white dark:bg-white dark:text-neutral-900">
                        {appName.charAt(0).toUpperCase()}
                    </div>
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">Sign in to {appName}</h1>
                        <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{heading}</p>
                    </div>
                </div>

                <div className="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                    {error && (
                        <div
                            role="alert"
                            className="mb-4 rounded-lg bg-red-50 px-3.5 py-2.5 text-sm text-red-700 dark:bg-red-950/50 dark:text-red-300"
                        >
                            {error}
                        </div>
                    )}

                    {!codeEnabled && !linkEnabled && (
                        <p className="text-sm text-neutral-500 dark:text-neutral-400">
                            No passwordless strategies are enabled. Enable <code>login_code</code> or{' '}
                            <code>magic_link</code> in <code>config/passwordless.php</code>.
                        </p>
                    )}

                    {step === 'email' && (
                        <form onSubmit={requestCode} className="flex flex-col gap-4">
                            <div className="flex flex-col gap-1.5">
                                <label htmlFor="pwl-email" className="text-sm font-medium">
                                    Email address
                                </label>
                                <input
                                    id="pwl-email"
                                    type="email"
                                    required
                                    autoComplete="email"
                                    value={email}
                                    disabled={loading}
                                    onChange={(e) => setEmail(e.target.value)}
                                    placeholder="you@example.com"
                                    className="w-full rounded-lg border border-neutral-300 bg-white px-3.5 py-2.5 text-sm shadow-sm outline-none transition placeholder:text-neutral-400 focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 disabled:opacity-60 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                                />
                            </div>

                            {codeEnabled && (
                                <button type="submit" disabled={loading} className={primaryBtn}>
                                    {loading ? 'Sending…' : 'Send me a code'}
                                </button>
                            )}

                            {linkEnabled && (
                                <button
                                    type="button"
                                    onClick={requestLink}
                                    disabled={loading}
                                    className={
                                        codeEnabled
                                            ? 'inline-flex w-full items-center justify-center rounded-lg border border-neutral-300 px-4 py-2.5 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50 disabled:opacity-60 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800'
                                            : primaryBtn
                                    }
                                >
                                    Email me a magic link
                                </button>
                            )}
                        </form>
                    )}

                    {step === 'code' && codeEnabled && (
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                verifyCode(digits.join(''));
                            }}
                            className="flex flex-col gap-4"
                        >
                            <div ref={otpRef} className="flex justify-between gap-2">
                                {digits.map((d, i) => (
                                    <input
                                        key={i}
                                        type="text"
                                        inputMode="numeric"
                                        maxLength={1}
                                        autoComplete="one-time-code"
                                        aria-label="Verification digit"
                                        value={d}
                                        onChange={(e) => onDigitInput(e.target.value, i)}
                                        onKeyDown={(e) => onDigitKeyDown(e, i)}
                                        onPaste={onPaste}
                                        className="aspect-[3/4] w-full min-w-0 rounded-lg border border-neutral-300 bg-white text-center text-lg font-semibold shadow-sm outline-none transition focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                                    />
                                ))}
                            </div>

                            <button type="submit" disabled={loading} className={primaryBtn}>
                                {loading ? 'Verifying…' : 'Verify & sign in'}
                            </button>

                            <button
                                type="button"
                                onClick={() => {
                                    setStep('email');
                                    setError('');
                                }}
                                className="text-sm font-medium text-neutral-500 transition hover:text-neutral-900 dark:hover:text-neutral-100"
                            >
                                ← Use a different email
                            </button>
                        </form>
                    )}

                    {step === 'sent' && (
                        <div className="flex flex-col gap-4 text-center">
                            <div className="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300">
                                ✓
                            </div>
                            <p className="text-sm text-neutral-600 dark:text-neutral-300">
                                Check your inbox — we sent a sign-in link to{' '}
                                <span className="font-medium text-neutral-900 dark:text-neutral-100">{email}</span>.
                            </p>
                            <button
                                type="button"
                                onClick={() => {
                                    setStep('email');
                                    setError('');
                                }}
                                className="text-sm font-medium text-neutral-500 transition hover:text-neutral-900 dark:hover:text-neutral-100"
                            >
                                ← Back
                            </button>
                        </div>
                    )}
                </div>

                {codeEnabled && linkEnabled && step === 'code' && (
                    <p className="mt-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
                        Didn't get it?{' '}
                        <button
                            type="button"
                            onClick={requestLink}
                            className="font-medium text-neutral-900 underline-offset-2 hover:underline dark:text-neutral-100"
                        >
                            Email a magic link instead
                        </button>
                    </p>
                )}
            </div>
        </div>
    );
}
