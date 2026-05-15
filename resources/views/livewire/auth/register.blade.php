<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header 
            :title="__('建立帳號')" 
            :description="__('填寫以下資訊以建立管理員帳號')" 
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            
            <flux:input
                name="name"
                :label="__('姓名')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('您的全名')"
            />

            <flux:input
                name="email"
                :label="__('電子郵件')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <flux:input
                name="password"
                :label="__('密碼')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('請輸入密碼')"
                viewable
            />

            <flux:input
                name="password_confirmation"
                :label="__('確認密碼')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('請再次輸入密碼')"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full">
                    {{ __('建立帳號') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('已經有帳號了？') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('回登入頁面') }}</flux:link>
        </div>
    </div>
</x-layouts.auth>