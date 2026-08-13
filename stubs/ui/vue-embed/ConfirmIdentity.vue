<!--
    Passwordless — confirm identity (INTEGRATED with the Vue starter kit)
    --------------------------------------------------------------------
    Published by:  php artisan vendor:publish --tag=passwordless-ui-vue-embed
    Target path:   resources/js/pages/auth/ConfirmIdentity.vue

    Rendered in place of Fortify's confirm-password page, via the
    ConfirmPasswordViewResponse binding in PasswordlessFortifyServiceProvider.

    Two forms, two destinations:
      1. passwordless.confirm.send — package route, emails a confirmation code
      2. password.confirm.store    — FORTIFY's own route; it runs the
         confirmPasswordsUsing callback and stamps auth.password_confirmed_at

    The code field must be named `password` — that is the input Fortify reads.
-->
<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        title: "Confirm it's you",
        description: 'For your security, confirm your identity before changing security settings',
    },
});

const props = defineProps<{
    status?: string;
    routes: {
        send: string;
        confirm: string;
    };
}>();

const form = useForm({ password: '' });

const sendCode = () => router.post(props.routes.send);
const submit = () => form.post(props.routes.confirm);
</script>

<template>
    <Head title="Confirm your identity" />

    <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
        {{ status }}
    </div>

    <div class="flex flex-col gap-6">
        <Button type="button" variant="outline" class="w-full" @click="sendCode">Email me a code</Button>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-2">
                <Label for="password">Confirmation code</Label>
                <Input
                    id="password"
                    name="password"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    autofocus
                    required
                    placeholder="123456"
                    v-model="form.password"
                />
                <InputError :message="form.errors.password" />
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">Confirm</Button>
        </form>
    </div>
</template>
