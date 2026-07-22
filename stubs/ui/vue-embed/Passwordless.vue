<!--
    Passwordless — login page (INTEGRATED with the Vue starter kit)
    ---------------------------------------------------------------
    Published by:  php artisan vendor:publish --tag=passwordless-ui-vue-embed
    Target path:   resources/js/pages/auth/Passwordless.vue

    Copies the kit's auth conventions: an Inertia page under pages/auth/* (so
    app.ts auto-wraps it in AuthLayout), the kit's @/components/ui controls +
    InputError, defineOptions({ layout }) for the header, and Inertia useForm
    posting to a Fortify-style controller that redirects. Talks to the package's
    PHP API via that controller — the headless core is untouched. Edit freely.
-->
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: {
        title: 'Sign in',
        description: "Enter your email and we'll send you a one-time code",
    },
});

const props = defineProps<{
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
}>();

const emailForm = useForm({ email: props.email ?? '' });
const codeForm = useForm({ code: '' });

const submitCode = () => emailForm.post(props.routes.request);
const submitLink = () => emailForm.post(props.routes.link);
const submitVerify = () => codeForm.post(props.routes.verify);
</script>

<template>
    <Head title="Sign in" />

    <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
        {{ status }}
    </div>

    <form v-if="step === 'code' && codeEnabled" @submit.prevent="submitVerify" class="flex flex-col gap-6">
        <div class="grid gap-2">
            <Label for="code">Verification code</Label>
            <Input
                id="code"
                name="code"
                inputmode="numeric"
                autocomplete="one-time-code"
                autofocus
                required
                placeholder="123456"
                v-model="codeForm.code"
            />
            <InputError :message="codeForm.errors.code" />
        </div>

        <Button type="submit" class="w-full" :disabled="codeForm.processing">Verify &amp; sign in</Button>

        <a :href="routes.startOver" class="text-center text-sm text-muted-foreground hover:underline">
            Use a different email
        </a>
    </form>

    <form v-else @submit.prevent="submitCode" class="flex flex-col gap-6">
        <div class="grid gap-2">
            <Label for="email">Email address</Label>
            <Input
                id="email"
                type="email"
                name="email"
                autofocus
                required
                autocomplete="email"
                placeholder="email@example.com"
                v-model="emailForm.email"
            />
            <InputError :message="emailForm.errors.email" />
        </div>

        <Button v-if="codeEnabled" type="submit" class="w-full" :disabled="emailForm.processing">
            Email me a code
        </Button>

        <Button
            v-if="linkEnabled"
            type="button"
            variant="outline"
            class="w-full"
            :disabled="emailForm.processing"
            @click="submitLink"
        >
            Email me a magic link
        </Button>
    </form>
</template>
