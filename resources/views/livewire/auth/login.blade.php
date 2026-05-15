<x-layouts.auth>
    <div class="flex flex-col gap-6">
        {{-- 標題與描述 --}}
        <div class="text-center mb-4">
            <div class="text-2xl font-bold text-zinc-900 dark:text-white">{{ __('進銷存系統 - 登入') }}</div>
        </div>

        {{-- 錯誤訊息顯示區 (針對 Fortify 驗證失敗) --}}
        @if ($errors->any())
            <div class="alert alert-error mb-4 shadow-sm text-sm py-2">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- 
            重點 1：使用傳統 POST 指向 Fortify 路由
            移除 wire:submit.prevent，確保請求直接送往後端
        --}}
        <form action="{{ route('login') }}" method="POST" class="flex flex-col gap-6">
            @csrf

            {{-- 
                重點 2：Mary UI 的 x-input
                必須明確寫出 name="email"，這是 Fortify 抓取資料的唯一依據。
                加上 wire:model.defer (或 v3 的預設) 是為了讓 Livewire 不干擾 POST 流程。
            --}}
            <x-input
                label="{{ __('電子郵件') }}"
                name="email"
                type="email"
                icon="o-envelope"
                value="{{ old('email') }}"
                placeholder="email@example.com"
                required
                autofocus
            />

            <div class="space-y-1">
                <x-input
                    label="{{ __('密碼') }}"
                    name="password"					
                    type="password"
                    icon="o-key"
                    placeholder="{{ __('請輸入密碼') }}"
                    required
                />
                
                <div class="flex justify-end">
                    <a href="{{ route('password.request') }}" class="text-xs text-zinc-500 hover:text-primary transition-colors">
                        {{ __('忘記密碼？') }}
                    </a>
                </div>
            </div>

            <x-checkbox 
                label="{{ __('記住我') }}" 
                name="remember"
                class="checkbox-primary"
            />

            <div class="mt-2">
                {{-- 
                    重點 3：不要在按鈕使用 spinner="login"
                    因為我們現在是走傳統 POST 頁面跳轉，不是執行 Livewire 方法。
                --}}
                <x-button 
                    label="{{ __('登入系統') }}" 
                    type="submit" 
                    icon="o-arrow-right-on-rectangle" 
                    class="btn-primary w-full" 
                />
            </div>
        </form>

        <div class="text-center text-sm text-zinc-600 dark:text-zinc-400 mt-4">
            <span>{{ __('還沒有帳號嗎？') }}</span>
            <a href="{{ route('register') }}" class="font-semibold text-primary hover:underline">
                {{ __('立即註冊') }}
            </a>
        </div>
    </div>
</x-layouts.auth>