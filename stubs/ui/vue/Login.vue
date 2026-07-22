<!--
    Passwordless — login page stub (Inertia + Vue 3 + TypeScript, Vue starter-kit stack)
    ------------------------------------------------------------------------------------
    Published by:  php artisan vendor:publish --tag=passwordless-ui-vue
    Target path:   resources/js/pages/passwordless/Login.vue

    This is YOUR file now — edit freely. It talks to the package's JSON endpoints
    (registered by the package), so the headless core is untouched. It submits with
    fetch() rather than Inertia's useForm, because the endpoints return JSON, not
    Inertia responses.

    Requirements (present in a Laravel Vue starter-kit app): Vue 3, Inertia 2,
    Tailwind v4. Props are supplied by the example route in routes/passwordless-ui.php
    (also published). Style is plain Tailwind (no component-library imports) so the
    stub drops in without extra dependencies.
-->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Props {
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
}

const props = defineProps<Props>();

type Step = 'email' | 'code' | 'sent';

const step = ref<Step>('email');
const email = ref('');
const digits = ref<string[]>(Array(props.codeLength).fill(''));
const error = ref('');
const loading = ref(false);
const otp = ref<HTMLDivElement | null>(null);

const heading = computed(() => {
    if (step.value === 'code') return `Enter the ${props.codeLength}-digit code we emailed you.`;
    if (step.value === 'sent') return 'A sign-in link is on its way.';
    return 'Enter your email to receive a one-time code.';
});

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

function boxes(): HTMLInputElement[] {
    return otp.value ? Array.from(otp.value.querySelectorAll('input')) : [];
}

function messageFor(res: Response, data: any, fallback: string): string {
    if (res.status === 422) return (Object.values(data?.errors ?? {})[0] as string[])?.[0] ?? fallback;
    if (res.status === 429 || res.status === 423) {
        const secs = data?.retry_after ?? res.headers.get('Retry-After');
        return secs ? `Please wait ${secs}s and try again.` : data?.message ?? fallback;
    }
    return data?.message ?? fallback;
}

async function requestCode() {
    if (!props.endpoints.sendCode) return;
    error.value = '';
    loading.value = true;
    try {
        const res = await postJson(props.endpoints.sendCode, { email: email.value });
        const data = await res.json().catch(() => ({}));
        if (res.status === 202) {
            digits.value = Array(props.codeLength).fill('');
            step.value = 'code';
            return;
        }
        error.value = messageFor(res, data, 'Something went wrong. Try again.');
    } catch {
        error.value = 'Network error. Try again.';
    } finally {
        loading.value = false;
    }
}

async function requestLink() {
    if (!props.endpoints.sendLink) return;
    error.value = '';
    loading.value = true;
    try {
        const res = await postJson(props.endpoints.sendLink, { email: email.value });
        const data = await res.json().catch(() => ({}));
        if (res.status === 202) {
            step.value = 'sent';
            return;
        }
        error.value = messageFor(res, data, 'Something went wrong. Try again.');
    } catch {
        error.value = 'Network error. Try again.';
    } finally {
        loading.value = false;
    }
}

async function verifyCode(code: string) {
    if (!props.endpoints.verifyCode) return;
    error.value = '';
    loading.value = true;
    try {
        const res = await postJson(props.endpoints.verifyCode, { email: email.value, code });
        if (res.status === 204 || res.status === 200) {
            window.location.assign(props.redirect);
            return;
        }
        const data = await res.json().catch(() => ({}));
        error.value = messageFor(res, data, 'Invalid or expired code.');
        digits.value = Array(props.codeLength).fill('');
    } catch {
        error.value = 'Network error. Try again.';
    } finally {
        loading.value = false;
    }
}

function onDigitInput(event: Event, i: number) {
    const target = event.target as HTMLInputElement;
    const clean = target.value.replace(/\D/g, '').slice(-1);
    target.value = clean;
    digits.value[i] = clean;
    const bs = boxes();
    if (clean && i < props.codeLength - 1) bs[i + 1]?.focus();
    if (digits.value.every((d) => d !== '')) verifyCode(digits.value.join(''));
}

function onBackspace(event: KeyboardEvent, i: number) {
    const target = event.target as HTMLInputElement;
    if (!target.value && i > 0) boxes()[i - 1]?.focus();
}

function onPaste(event: ClipboardEvent) {
    event.preventDefault();
    const text = (event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, props.codeLength);
    if (!text) return;
    const next = Array(props.codeLength).fill('');
    text.split('').forEach((ch, idx) => (next[idx] = ch));
    digits.value = next;
    const bs = boxes();
    bs.forEach((b, idx) => (b.value = next[idx] ?? ''));
    bs[Math.min(text.length, props.codeLength - 1)]?.focus();
    if (text.length === props.codeLength) verifyCode(text);
}

function toEmail() {
    step.value = 'email';
    error.value = '';
}

const primaryBtn =
    'inline-flex w-full items-center justify-center rounded-lg bg-neutral-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800 focus:outline-none focus:ring-2 focus:ring-neutral-900/20 active:translate-y-px disabled:opacity-60 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200';
</script>

<template>
    <Head :title="`Sign in — ${appName}`" />
    <div class="flex min-h-screen items-center justify-center bg-neutral-50 px-4 py-12 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
        <div class="w-full max-w-sm">
            <div class="mb-8 flex flex-col items-center gap-3 text-center">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-neutral-900 text-lg font-semibold text-white dark:bg-white dark:text-neutral-900">
                    {{ appName.charAt(0).toUpperCase() }}
                </div>
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">Sign in to {{ appName }}</h1>
                    <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{{ heading }}</p>
                </div>
            </div>

            <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <div
                    v-if="error"
                    role="alert"
                    class="mb-4 rounded-lg bg-red-50 px-3.5 py-2.5 text-sm text-red-700 dark:bg-red-950/50 dark:text-red-300"
                >
                    {{ error }}
                </div>

                <p v-if="!codeEnabled && !linkEnabled" class="text-sm text-neutral-500 dark:text-neutral-400">
                    No passwordless strategies are enabled. Enable <code>login_code</code> or
                    <code>magic_link</code> in <code>config/passwordless.php</code>.
                </p>

                <form v-if="step === 'email'" @submit.prevent="requestCode" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1.5">
                        <label for="pwl-email" class="text-sm font-medium">Email address</label>
                        <input
                            id="pwl-email"
                            v-model="email"
                            type="email"
                            required
                            autocomplete="email"
                            :disabled="loading"
                            placeholder="you@example.com"
                            class="w-full rounded-lg border border-neutral-300 bg-white px-3.5 py-2.5 text-sm shadow-sm outline-none transition placeholder:text-neutral-400 focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 disabled:opacity-60 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                        />
                    </div>

                    <button v-if="codeEnabled" type="submit" :disabled="loading" :class="primaryBtn">
                        {{ loading ? 'Sending…' : 'Send me a code' }}
                    </button>

                    <button
                        v-if="linkEnabled"
                        type="button"
                        :disabled="loading"
                        @click="requestLink"
                        :class="
                            codeEnabled
                                ? 'inline-flex w-full items-center justify-center rounded-lg border border-neutral-300 px-4 py-2.5 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50 disabled:opacity-60 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800'
                                : primaryBtn
                        "
                    >
                        Email me a magic link
                    </button>
                </form>

                <form v-if="step === 'code' && codeEnabled" @submit.prevent="verifyCode(digits.join(''))" class="flex flex-col gap-4">
                    <div ref="otp" class="flex justify-between gap-2">
                        <input
                            v-for="(d, i) in digits"
                            :key="i"
                            type="text"
                            inputmode="numeric"
                            :maxlength="1"
                            autocomplete="one-time-code"
                            aria-label="Verification digit"
                            @input="onDigitInput($event, i)"
                            @keydown.backspace="onBackspace($event, i)"
                            @paste.prevent="onPaste($event)"
                            class="aspect-[3/4] w-full min-w-0 rounded-lg border border-neutral-300 bg-white text-center text-lg font-semibold shadow-sm outline-none transition focus:border-neutral-900 focus:ring-2 focus:ring-neutral-900/10 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                        />
                    </div>

                    <button type="submit" :disabled="loading" :class="primaryBtn">
                        {{ loading ? 'Verifying…' : 'Verify & sign in' }}
                    </button>

                    <button type="button" @click="toEmail" class="text-sm font-medium text-neutral-500 transition hover:text-neutral-900 dark:hover:text-neutral-100">
                        ← Use a different email
                    </button>
                </form>

                <div v-if="step === 'sent'" class="flex flex-col gap-4 text-center">
                    <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300">
                        ✓
                    </div>
                    <p class="text-sm text-neutral-600 dark:text-neutral-300">
                        Check your inbox — we sent a sign-in link to
                        <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ email }}</span>.
                    </p>
                    <button type="button" @click="toEmail" class="text-sm font-medium text-neutral-500 transition hover:text-neutral-900 dark:hover:text-neutral-100">
                        ← Back
                    </button>
                </div>
            </div>

            <p v-if="codeEnabled && linkEnabled && step === 'code'" class="mt-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
                Didn't get it?
                <button type="button" @click="requestLink" class="font-medium text-neutral-900 underline-offset-2 hover:underline dark:text-neutral-100">
                    Email a magic link instead
                </button>
            </p>
        </div>
    </div>
</template>
