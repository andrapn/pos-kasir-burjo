<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Masuk ke Sistem Kasir Burjo Moro Seneng')" :description="__('Masukan Email dan Password untuk Masuk')" />

        <!-- Session Status & Errors -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        @if (session('error'))
            <div class="p-4 text-sm text-red-600 bg-red-50 dark:bg-red-900/10 dark:text-red-400 rounded-lg">
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Kata Sandi')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Kata Sandi Anda')"
                    viewable
                />

                {{-- @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif --}}
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Ingat Saya?')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Masuk') }}
                </flux:button>
            </div>
        </form>

        {{-- @if (Route::has('register'))
            <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
                <span>{{ __('Don\'t have an account?') }}</span>
                <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
            </div>
        @endif --}}
    </div>
</x-layouts.auth>
