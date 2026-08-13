/*
 * Passwordless — login page (INTEGRATED with the React starter kit)
 * -----------------------------------------------------------------
 * Published by:  php artisan vendor:publish --tag=passwordless-ui-react-embed
 * Target path:   resources/js/pages/auth/passwordless.tsx
 *
 * Copies the kit's auth conventions: an Inertia page under pages/auth/* (so
 * app.tsx auto-wraps it in AuthLayout), the kit's @/components/ui controls +
 * InputError, and Inertia `useForm` submitting to a Fortify-style controller
 * that redirects. It talks to the package's PHP API via that controller — the
 * headless core is untouched. This is YOUR file now; edit freely.
 */
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type DevUser = { id: number | string; email: string };

type Props = {
    step: 'email' | 'code';
    email?: string;
    status?: string;
    codeEnabled: boolean;
    linkEnabled: boolean;
    // Empty unless the package's dev_login guard passed — production sees [].
    devUsers: DevUser[];
    devLoginRoute: string | null;
    routes: {
        request: string;
        verify: string;
        link: string;
        startOver: string;
    };
};

export default function Passwordless({ step, email, status, codeEnabled, linkEnabled, devUsers, devLoginRoute, routes }: Props) {
    // `remember` is chosen at send time. The package stores it on the challenge,
    // so it still applies when the emailed code or link is used later.
    const emailForm = useForm({ email: email ?? '', remember: false });
    const codeForm = useForm({ code: '' });
    const devForm = useForm({ user: devUsers[0]?.id ?? '' });

    const submitCode = (e: FormEvent) => {
        e.preventDefault();
        emailForm.post(routes.request);
    };

    const submitLink = () => emailForm.post(routes.link);

    const submitVerify = (e: FormEvent) => {
        e.preventDefault();
        codeForm.post(routes.verify);
    };

    return (
        <>
            <Head title="Sign in" />

            {status && <div className="mb-4 text-center text-sm font-medium text-green-600">{status}</div>}

            {step === 'code' && codeEnabled ? (
                <form onSubmit={submitVerify} className="flex flex-col gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="code">Verification code</Label>
                        <Input
                            id="code"
                            name="code"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            autoFocus
                            required
                            placeholder="123456"
                            value={codeForm.data.code}
                            onChange={(e) => codeForm.setData('code', e.target.value)}
                        />
                        <InputError message={codeForm.errors.code} />
                    </div>

                    <Button type="submit" className="w-full" disabled={codeForm.processing}>
                        Verify & sign in
                    </Button>

                    <a href={routes.startOver} className="text-center text-sm text-muted-foreground hover:underline">
                        Use a different email
                    </a>
                </form>
            ) : (
                <form onSubmit={submitCode} className="flex flex-col gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            autoFocus
                            required
                            autoComplete="email"
                            placeholder="email@example.com"
                            value={emailForm.data.email}
                            onChange={(e) => emailForm.setData('email', e.target.value)}
                        />
                        <InputError message={emailForm.errors.email} />
                    </div>

                    <div className="flex items-center space-x-3">
                        <Checkbox
                            id="remember"
                            name="remember"
                            checked={emailForm.data.remember}
                            onClick={() => emailForm.setData('remember', !emailForm.data.remember)}
                        />
                        <Label htmlFor="remember">Remember me</Label>
                    </div>

                    {codeEnabled && (
                        <Button type="submit" className="w-full" disabled={emailForm.processing}>
                            Email me a code
                        </Button>
                    )}

                    {linkEnabled && (
                        <Button
                            type="button"
                            variant="outline"
                            className="w-full"
                            onClick={submitLink}
                            disabled={emailForm.processing}
                        >
                            Email me a magic link
                        </Button>
                    )}

                    {devLoginRoute && devUsers.length > 0 && (
                        <div className="flex flex-col gap-2 border-t pt-4">
                            <Label htmlFor="devUser">Dev sign-in</Label>
                            <select
                                id="devUser"
                                value={String(devForm.data.user)}
                                onChange={(e) => devForm.setData('user', e.target.value)}
                                className="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                            >
                                {devUsers.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.email}
                                    </option>
                                ))}
                            </select>
                            <Button
                                type="button"
                                variant="outline"
                                className="w-full"
                                onClick={() => devForm.post(devLoginRoute)}
                            >
                                Sign in as selected user
                            </Button>
                        </div>
                    )}
                </form>
            )}
        </>
    );
}

Passwordless.layout = {
    title: 'Sign in',
    description: 'Enter your email and we\'ll send you a one-time code',
};
