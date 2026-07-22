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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    step: 'email' | 'code';
    email?: string;
    status?: string;
    codeEnabled: boolean;
    linkEnabled: boolean;
    routes: {
        request: string;
        verify: string;
        link: string;
        startOver: string;
    };
};

export default function Passwordless({ step, email, status, codeEnabled, linkEnabled, routes }: Props) {
    const emailForm = useForm({ email: email ?? '' });
    const codeForm = useForm({ code: '' });

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
                </form>
            )}
        </>
    );
}

Passwordless.layout = {
    title: 'Sign in',
    description: 'Enter your email and we\'ll send you a one-time code',
};
