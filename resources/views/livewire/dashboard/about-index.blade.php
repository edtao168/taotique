<div class="max-w-4xl mx-auto space-y-8 p-4">
    
    {{-- 系統簡介卡 --}}
    <x-card class="bg-base-100 shadow-sm border border-base-300">
        <div class="flex items-center gap-4 mb-4">
            <div class="w-16 h-16 rounded-full bg-[#8B4513] flex items-center justify-center">
                <img src="{{ asset('logo.png') }}" class="w-12" />
            </div>
            <div>
                <h1 class="text-2xl font-bold text-[#5C3A1E]">陶老闆進銷存系統</h1>
                <p class="text-sm text-[#8B7355]">版本 {{ $this->getVersion() }} · 第一版</p>
            </div>
        </div>
        
        <div class="prose max-w-none">
            <p class="text-base-content/80 leading-relaxed">
                這是一套專為中小型零售業打造的輕量級進銷存管理系統。從採購入庫、銷售出貨到庫存盤點，從日記帳到自動過帳，讓每一位店長都能輕鬆掌握店鋪的經營脈絡。
            </p>
        </div>
    </x-card>

    {{-- 願景與理念 --}}
    <x-card title="願景與理念" class="bg-base-100 shadow-sm border border-base-300" separator>
        <div class="space-y-4">
            <div class="flex gap-3">
                <x-icon name="o-light-bulb" class="w-5 h-5 text-amber-600 flex-shrink-0 mt-1" />
                <div>
                    <h3 class="font-semibold text-[#5C3A1E]">讓專業管理觸手可及</h3>
                    <p class="text-sm text-base-content/70">我們相信，精準的庫存管理與清晰的帳務紀錄，不應該是大企業的專利。這套系統的目標是讓每一位用心經營的店主，都能擁有專業級的工具。</p>
                </div>
            </div>
            
            <div class="flex gap-3">
                <x-icon name="o-scale" class="w-5 h-5 text-amber-600 flex-shrink-0 mt-1" />
                <div>
                    <h3 class="font-semibold text-[#5C3A1E]">簡潔而不簡單</h3>
                    <p class="text-sm text-base-content/70">界面設計參考宋朝美學——素雅、留白、重視實用。沒有多餘的裝飾，每一個功能都為實際業務場景而生。</p>
                </div>
            </div>
            
            <div class="flex gap-3">
                <x-icon name="o-shield-check" class="w-5 h-5 text-amber-600 flex-shrink-0 mt-1" />
                <div>
                    <h3 class="font-semibold text-[#5C3A1E]">資料自主可控</h3>
                    <p class="text-sm text-base-content/70">系統可部署於本地伺服器，多點使用（包含各類移動設備）可部署到雲端，所有業務資料與財務紀錄完全由店主自行掌握，無需擔心第三方平台的資料安全問題。</p>
                </div>
            </div>
        </div>
    </x-card>

    {{-- 作者簡介 --}}
    <x-card title="關於作者" class="bg-base-100 shadow-sm border border-base-300" separator>
        <div class="flex items-start gap-4">
            <div class="avatar placeholder">
    <div class="bg-[#8B4513] text-[#F5E6C8] rounded-full w-14 h-14 overflow-hidden">
        <img src="{{ asset('me.jpg') }}" alt="這是我" class="w-full h-full object-cover">
    </div>
</div>
            <div class="space-y-2">
                <div>
                    <h3 class="font-bold text-[#5C3A1E]">虛靈根散修</h3>
                    <p class="text-xs text-[#8B7355]">業餘獨立開發者 · 經驗淺薄之系統架構師</p>
                </div>
                <p class="text-sm text-base-content/70 leading-relaxed">
                    因為親身經歷過傳統零售業在數位轉型上的困境，決定親手打造一套真正貼近店主需求的進銷存系統。從第一行代碼到現在，持續迭代、持續傾聽用戶回饋。
                </p>
            </div>
        </div>
    </x-card>

    {{-- 社群與支援 --}}
    <x-card title="社群與支援" class="bg-base-100 shadow-sm border border-base-300" separator>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <a href="https://github.com/edtao168/taotique" target="_blank" 
               class="btn btn-outline border-[#8B7355] text-[#5C3A1E] hover:bg-[#8B4513] hover:text-[#F5E6C8] hover:border-[#8B4513] normal-case justify-start gap-3">
                <x-icon name="o-code-bracket" class="w-5 h-5" />
                <div class="text-left">
                    <div class="text-sm font-medium">GitHub 原始碼</div>
                    <div class="text-xs opacity-70">查看原始碼、回報 Bug</div>
                </div>
            </a>
            
            <a href="#" target="_blank" 
               class="btn btn-outline border-[#8B7355] text-[#5C3A1E] hover:bg-[#8B4513] hover:text-[#F5E6C8] hover:border-[#8B4513] normal-case justify-start gap-3">
                <x-icon name="o-document-text" class="w-5 h-5" />
                <div class="text-left">
                    <div class="text-sm font-medium">使用文件</div>
                    <div class="text-xs opacity-70">操作教學與常見問題</div>
                </div>
            </a>
            
            <a href="https://www.facebook.com/share/g/1M39qsMKvj/" target="_blank" 
               class="btn btn-outline border-[#8B7355] text-[#5C3A1E] hover:bg-[#8B4513] hover:text-[#F5E6C8] hover:border-[#8B4513] normal-case justify-start gap-3">
                <x-icon name="o-chat-bubble-left-right" class="w-5 h-5" />
                <div class="text-left">
                    <div class="text-sm font-medium">Facebook討論區</div>
                    <div class="text-xs opacity-70">與其他用戶交流心得。如果你是第一次來，請回答：「非我」。</div>
                </div>
            </a>
            
            <a href="mailto:edtaoisgod@gmail.com" 
               class="btn btn-outline border-[#8B7355] text-[#5C3A1E] hover:bg-[#8B4513] hover:text-[#F5E6C8] hover:border-[#8B4513] normal-case justify-start gap-3">
                <x-icon name="o-envelope" class="w-5 h-5" />
                <div class="text-left">
                    <div class="text-sm font-medium">聯絡作者</div>
                    <div class="text-xs opacity-70">功能建議與商務合作</div>
                </div>
            </a>
        </div>
    </x-card>
	
	<x-card class="text-center">
		<p class="mt-2 text-muted">覺得不賴，可以贊助一下。</p>
		<img src="{{ asset('cathay_qr_code.png') }}" 
			 alt="國泰QR Code" 
			 style="width: 180px; height: auto; display: block; margin: 0 auto;">    
	</x-card>
	
    {{-- 技術棧與授權 --}}
    <x-card title="技術棧與授權" class="bg-base-100 shadow-sm border border-base-300" separator>
        <div class="space-y-3">
            <div class="flex flex-wrap gap-2">
                <span class="badge badge-outline border-[#8B7355] text-[#5C3A1E]">Laravel 12</span>
                <span class="badge badge-outline border-[#8B7355] text-[#5C3A1E]">Livewire 3</span>
                <span class="badge badge-outline border-[#8B7355] text-[#5C3A1E]">Mary UI</span>
                <span class="badge badge-outline border-[#8B7355] text-[#5C3A1E]">DaisyUI</span>
                <span class="badge badge-outline border-[#8B7355] text-[#5C3A1E]">Tailwind CSS</span>
                <span class="badge badge-outline border-[#8B7355] text-[#5C3A1E]">Alpine.js</span>
                <span class="badge badge-outline border-[#8B7355] text-[#5C3A1E]">Chart.js</span>
            </div>
            
            <div class="divider before:bg-[#D4C5B0] after:bg-[#D4C5B0]"></div>
            
            <p class="text-xs text-base-content/60 text-center">
                本系統採用 MIT 授權條款開源釋出。<br>
                陶老闆進銷存系統 © {{ date('Y') }} 陶老闆. 保留所有權利。
            </p>
        </div>
    </x-card>

</div>