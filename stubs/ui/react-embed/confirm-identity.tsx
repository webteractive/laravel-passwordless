/*
 * Passwordless — confirm identity (INTEGRATED with the React starter kit)
 * -----------------------------------------------------------------------
 * Published by:  php artisan vendor:publish --tag=passwordless-ui-react-embed
 * Target path:   resources/js/pages/auth/confirm-identity.tsx
 *
 * Rendered in place of Fortify's confirm-password page, via the
 * ConfirmPasswordViewResponse binding in PasswordlessFortifyServiceProvider.
 *
 * Two forms, two destinations:
 *   1. passwordless.confirm.send — package route, emails a confirmation code
 *   2. password.confirm.store    — FORTIFY's own route; it runs the
 *      confirmPasswordsUsing callback and stamps auth.password_confirmed_at
 *
 * The code field must be named `password` — that is the input Fortify reads.
 */
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    status?: string;
    routes: {
        send: string;
        confirm: string;
    };
};

export default function ConfirmIdentity({ status, routes }: Props) {
    const form = useForm({ password: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(routes.confirm);
    };

    return (
        <>
            <Head title="Confirm your identity" />

            {status && <div className="mb-4 text-center text-sm font-medium text-green-600">{status}</div>}

            <div className="flex flex-col gap-6">
                <Button type="button" variant="outline" className="w-full" onClick={() => router.post(routes.send)}>
                    Email me a code
                </Button>

                <form onSubmit={submit} className="flex flex-col gap-6">
                    <div className="grid gap-2">
                        <Label htmlFor="password">Confirmation code</Label>
                        <Input
                            id="password"
                            name="password"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            autoFocus
                            required
                            placeholder="123456"
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                        />
                        <InputError message={form.errors.password} />
                    </div>

                    <Button type="submit" className="w-full" disabled={form.processing}>
                        Confirm
                    </Button>
                </form>
            </div>
        </>
    );
}

ConfirmIdentity.layout = {
    title: 'Confirm it\'s you',
    description: 'For your security, confirm your identity before changing security settings',
};
