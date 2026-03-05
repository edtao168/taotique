@props(['product' => null, 'new_photos' => []])

<x-card title="商品媒體管理" shadow border class="h-full flex flex-col overflow-hidden">
    {{-- 圖片展示區 --}}
    <div class="flex-grow overflow-y-auto custom-scrollbar p-1">
        <div class="grid grid-cols-3 sm:grid-cols-4 gap-3 scale-[0.8] origin-top-left w-[125%]">
            
            @if($product && $product->images)
                @foreach($product->images as $media)
                    <div class="relative group aspect-square">
                        @php
                            $ext = strtolower(pathinfo($media->path, PATHINFO_EXTENSION));
                            $isVideo = in_array($ext, ['mp4', 'mov', 'avi', 'webm']);
                        @endphp
                        
                        @if($isVideo)
                            <video src="{{ Storage::url($media->path) }}" class="w-full h-full object-cover rounded-lg border bg-black"></video>
                            <div class="absolute inset-0 flex items-center justify-center pointer-events-none text-white/50">
                                <x-icon name="o-play-circle" class="w-8 h-8" />
                            </div>
                        @else
                            <img src="{{ Storage::url($media->path) }}" class="w-full h-full object-cover rounded-lg border shadow-sm" />
                        @endif

                        <button type="button" wire:click="deleteImage({{ $media->id }})" wire:confirm="確定要永久刪除此媒體嗎？"
                            class="absolute -top-2 -right-2 bg-error text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity z-20">
                            <x-icon name="o-x-mark" class="w-3 h-3" />
                        </button>
                    </div>
                @endforeach
            @endif

            @foreach($new_photos as $index => $photo)
                <div class="relative group aspect-square">
                    <img src="{{ $photo->temporaryUrl() }}" class="w-full h-full object-cover rounded-lg border-2 border-dashed border-primary opacity-60" />
                    
                    <button type="button" wire:click="removeUpload('new_photos', '{{ $photo->getFilename() }}')"
                        class="absolute -top-2 -right-2 bg-warning text-white rounded-full p-1 z-20 shadow-md">
                        <x-icon name="o-x-mark" class="w-3 h-3" />
                    </button>
                    
                    <div wire:loading wire:target="new_photos" class="absolute inset-0 flex items-center justify-center bg-white/30 rounded-lg">
                        <span class="loading loading-spinner loading-xs text-primary"></span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- 上傳區域 --}}
    <div class="mt-4 pt-4 border-t">
        <input 
            type="file" 
            id="file-upload-{{ $product?->id ?? 'new' }}"
            wire:model="new_photos" 
            multiple 
            accept="image/*,video/*"
            class="hidden"
        >
        
        {{-- 拖曳上傳區域 --}}
        <div 
            x-data="dropZone()"
            x-init="init()"
            x-on:dragenter="onDragEnter($event)"
            x-on:dragover="onDragOver($event)"
            x-on:dragleave="onDragLeave($event)"
            x-on:drop="onDrop($event)"
            x-on:click="document.getElementById('file-upload-{{ $product?->id ?? 'new' }}').click()"
            :class="{ 'border-primary bg-primary/10': dragging }"
            class="p-4 border-2 border-dashed rounded-xl text-center bg-base-200 hover:border-primary transition-colors cursor-pointer group select-none"
        >
            <x-icon name="o-cloud-arrow-up" class="w-6 h-6 mx-auto mb-1 text-gray-400 group-hover:text-primary transition-colors" />
            <span class="text-xs text-gray-500 font-medium group-hover:text-gray-700">點擊或拖曳媒體至此上傳</span>
            <span class="text-[10px] text-gray-400 block mt-1">支援 JPG, PNG, MP4 等格式</span>
        </div>
        
        @if($errors->has('new_photos.*'))
            <div class="mt-2 text-xs text-error">
                @foreach($errors->get('new_photos.*') as $error)
                    {{ $error[0] }}<br>
                @endforeach
            </div>
        @endif
    </div>
</x-card>

{{-- Alpine.js 邏輯 --}}
<script>
function dropZone() {
    return {
        dragging: false,
        dragCounter: 0, // 防止子元素觸發 dragleave
        
        init() {
            // 防止整個頁面的拖曳預設行為
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                document.body.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                }, false);
            });
        },
        
        onDragEnter(e) {
            e.preventDefault();
            e.stopPropagation();
            this.dragCounter++;
            this.dragging = true;
        },
        
        onDragOver(e) {
            e.preventDefault(); // 關鍵：必須阻止才能允許 drop
            e.stopPropagation();
            e.dataTransfer.dropEffect = 'copy';
            this.dragging = true;
        },
        
        onDragLeave(e) {
            e.preventDefault();
            e.stopPropagation();
            this.dragCounter--;
            if (this.dragCounter === 0) {
                this.dragging = false;
            }
        },
        
        onDrop(e) {
            e.preventDefault(); // 關鍵：阻止開啟新頁面
            e.stopPropagation();
            this.dragCounter = 0;
            this.dragging = false;
            
            const files = e.dataTransfer.files;
            console.log('Drop 觸發，檔案數量:', files.length);
            
            if (files.length > 0) {
                const fileArray = Array.from(files);
                console.log('上傳檔案:', fileArray.map(f => f.name));
                
                // 使用 Livewire v3 上傳
                @this.uploadMultiple('new_photos', fileArray);
            }
        }
    }
}
</script>