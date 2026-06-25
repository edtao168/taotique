{{-- 檔案路徑：resources/views/components/media-manager.blade.php --}}
@props([
    'product' => null,
    'temp_media' => [],
    'editable' => false,
])

@php
    $mediaList = [];

    if ($product && $product->images) {
        foreach ($product->images()->orderByDesc('is_primary')->orderBy('id')->get() as $img) {
            $ext = strtolower(pathinfo($img->path, PATHINFO_EXTENSION));
            $mediaList[] = [
                'id' => $img->id,
                'path' => $img->path,
                'url' => Storage::url($img->path) . '?v=' . $img->updated_at->timestamp,
                'is_primary' => (bool)$img->is_primary,
                'is_video' => in_array($ext, config('business.media.video_extensions')),
                'is_temp' => false,
            ];
        }
    }

    if (!empty($temp_media)) {
        foreach ($temp_media as $idx => $path) {
            $isTempFile = $path instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
            $originalName = $isTempFile ? $path->getClientOriginalName() : $path;
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            $url = '';
            try {
                $url = is_string($path) ? Storage::url($path) : $path->temporaryUrl();
            } catch (\Livewire\Features\SupportFileUploads\FileNotPreviewableException $e) {
                $url = asset('images/video-placeholder.png'); 
            }

            $mediaList[] = [
                'id' => 'temp_' . $idx,
                'path' => $path,
                'url' => $url,
                'is_primary' => false, 
                'is_video' => in_array($ext, config('business.media.video_extensions')),
                'is_temp' => true,
            ];
        }
    }
@endphp

<div class="bg-base-100 rounded-xl shadow-md border border-base-300 flex flex-col overflow-hidden {{ $editable ? 'h-[85vh] md:h-full' : 'mt-6 min-h-[300px]' }}">

    <div class="px-5 py-4 border-b border-base-300 bg-base-200/30 flex justify-between items-center">
        <div>
            <h3 class="font-bold text-lg text-base-content">{{ $editable ? '商品媒體管理' : '商品媒體相簿' }}</h3>
            @if($editable)
                <p class="text-xs text-base-content/60">點擊星號設定首圖</p>
            @endif
        </div>
        <div class="flex items-center gap-2">
            <div wire:loading wire:target="temp_media" class="flex items-center gap-1.5 text-primary">
                <span class="loading loading-spinner loading-xs"></span>
                <span class="text-xs font-medium">上傳中...</span>
            </div>

            @if($editable && !empty($mediaList))
                <span class="badge badge-primary badge-outline text-xs">共 {{ count($mediaList) }} 個檔案</span>
            @endif
        </div>
    </div>

    @if($editable)
		<div x-data="{ dragging: false }" 
			 x-on:dragenter.prevent="dragging = true" 
			 x-on:dragover.prevent="dragging = true" 
			 x-on:dragleave.prevent="dragging = false" 
			 x-on:drop.prevent="dragging = false; let files = Array.from($event.dataTransfer.files);
				if(files.length > 0) {
					@this.uploadMultiple('temp_media', files);
				}"
			 @click="$refs.fileInput.click()"
			 :class="dragging ? 'border-primary bg-primary/10 ring-2 ring-primary/20' : 'border-base-300'" 
			 class="m-4 p-6 border-2 border-dashed rounded-xl text-center bg-base-100 hover:border-primary transition-all cursor-pointer group">

			<x-icon name="o-cloud-arrow-up" class="w-8 h-8 mx-auto mb-2 text-base-content/30 group-hover:text-primary transition-colors" />
			<p class="text-sm font-medium text-base-content/70 group-hover:text-primary">點擊或拖曳媒體至此上傳</p>
			<p class="text-[10px] text-base-content/40 mt-1">支援 JPG, PNG, WebP, AVIF, HEIC, MP4, MOV (最大 20MB)</p>
			
			{{-- ✅ input 移入同一個 Alpine 組件，確保 $refs.fileInput 可訪問 --}}
			<input type="file" 
				   wire:model="temp_media" 
				   multiple 
				   accept="image/*,video/*" 
				   class="sr-only" 
				   x-ref="fileInput" />
		</div>
	@endif

    {{-- ✅ wire:key 確保 Livewire re-render 時 Alpine 重新初始化 --}}
    <div x-data="mediaGallery({
            initialImages: @js($mediaList),
            editable: @js($editable)
         })" 
         wire:key="media-gallery-{{ md5(json_encode($mediaList)) }}"
         class="p-4 {{ $editable ? 'flex-grow overflow-y-auto custom-scrollbar' : '' }}">

        <div x-show="allMedia.length === 0" 
             class="col-span-full text-center py-16 text-base-content/30 border-2 border-dashed border-base-300 rounded-xl">
            <x-icon name="o-photo" class="w-16 h-16 mx-auto mb-3 opacity-20" />
            <p class="text-sm">尚未上傳任何媒體檔案</p>
        </div>

        <div class="grid {{ $editable ? 'grid-cols-3 gap-3' : 'grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4' }}"
             x-show="allMedia.length > 0">

            <template x-for="(media, index) in allMedia" :key="media.id">
                <div class="relative group aspect-square rounded-lg overflow-hidden bg-base-200 border border-base-300 shadow-sm transition-all hover:border-primary/50">

                    <div x-show="$wire.deletingMediaId == media.id" 
                         class="absolute inset-0 z-40 bg-black/60 flex items-center justify-center">
                        <span class="loading loading-spinner loading-md text-error"></span>
                    </div>

                    <template x-if="media.is_video">
                        <div class="w-full h-full cursor-pointer" @click.stop="openLightbox(index)">
                            <video :src="media.url" class="w-full h-full object-cover" preload="metadata"></video>
                            <div class="absolute inset-0 flex items-center justify-center bg-black/20 pointer-events-none">
                                <x-icon name="o-play-circle" class="w-10 h-10 text-white/80" />
                            </div>
                        </div>
                    </template>

                    <template x-if="!media.is_video">
                        <img :src="media.url" 
                             loading="lazy" 
                             class="w-full h-full object-cover cursor-zoom-in" 
                             @click.stop="openLightbox(index)" />
                    </template>

                    <div x-show="media.is_primary" class="absolute top-1 left-1 z-20">
                        <div class="badge badge-warning gap-1 px-1.5 py-2 shadow-sm border-none">
                            <x-icon name="o-star" class="w-3 h-3 fill-current" />
                            <span class="text-[10px] font-bold">首圖</span>
                        </div>
                    </div>

                    <div x-show="editable" 
                         class="absolute top-1 right-1 flex flex-col gap-1.5 z-30 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity">
                        <button type="button" 
                                @click.stop="deleteMedia(media.id, media.is_temp, index)" 
                                class="p-2 bg-error/90 hover:bg-error text-white rounded-full shadow-lg backdrop-blur-sm transition-transform active:scale-90"
                                title="刪除媒體">
                            <x-icon name="o-trash" class="w-4 h-4" />
                        </button>

                        <button type="button" 
                                x-show="!media.is_primary"
                                @click.stop="setPrimary(media.id, media.is_temp, index)" 
                                class="p-2 bg-warning/90 hover:bg-warning text-black rounded-full shadow-lg backdrop-blur-sm transition-transform active:scale-90"
                                title="設為首圖">
                            <x-icon name="o-star" class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </template>
        </div>

        {{-- ✅ Lightbox 區塊：對齊 index.blade.php 風格 --}}
        <div x-show="isOpen" 
             x-transition.opacity.duration.300ms
             class="fixed inset-0 z-[9999] bg-black/95 backdrop-blur-sm flex items-center justify-center touch-none" 
             @click.self="close()" 
             @keydown.escape.window="close()" 
             x-trap.inert.noscroll="isOpen">

            <button @click="close()" class="absolute top-6 right-6 z-50 p-2 bg-white/10 hover:bg-white/20 rounded-full text-white transition-colors">
                <x-icon name="o-x-mark" class="w-8 h-8" />
            </button>

            <div class="absolute top-6 left-6 z-50 px-4 py-1.5 bg-white/10 rounded-full text-white text-sm font-medium">
                <span x-text="currentIndex + 1"></span> / <span x-text="allMedia.length"></span>
            </div>

            <div class="relative w-full h-full flex items-center justify-center p-4"
                 @touchstart="handleTouchStart($event)"
                 @touchmove="handleTouchMove($event)" 
                 @touchend="handleTouchEnd($event)">

                <button x-show="hasPrev" 
                        @click.stop="prev()" 
                        class="absolute left-4 md:left-8 z-50 p-3 bg-white/10 hover:bg-white/20 rounded-full text-white transition-all active:scale-95">
                    <x-icon name="o-chevron-left" class="w-8 h-8" />
                </button>

                <div class="max-w-5xl w-full flex items-center justify-center">
                    <template x-if="currentImage">
                        <div class="w-full flex justify-center">
                            <template x-if="currentImage.is_video">
                                <video :src="currentImage.url" 
                                       controls 
                                       autoplay 
                                       class="max-w-full max-h-[85vh] shadow-2xl rounded-lg"></video>
                            </template>
                            <template x-if="!currentImage.is_video">
                                <img :src="currentImage.url" 
                                     :class="isZoomed ? 'scale-150 cursor-zoom-out' : 'cursor-zoom-in'"
                                     @click="toggleZoom()"
                                     class="max-w-full max-h-[85vh] object-contain rounded-lg transition-transform duration-300" />
                            </template>
                        </div>
                    </template>
                </div>

                <button x-show="hasNext" 
                        @click.stop="next()" 
                        class="absolute right-4 md:right-8 z-50 p-3 bg-white/10 hover:bg-white/20 rounded-full text-white transition-all active:scale-95">
                    <x-icon name="o-chevron-right" class="w-8 h-8" />
                </button>
            </div>
        </div>
    </div>

    @if($editable)
        <div class="p-5 border-t border-base-300 bg-base-200/20 relative">
            <div wire:loading wire:target="temp_media" 
                 class="absolute inset-0 z-50 bg-base-200/80 backdrop-blur-sm flex items-center justify-center rounded-xl">
                <div class="text-center">
                    <span class="loading loading-spinner loading-lg text-primary"></span>
                    <p class="text-sm text-base-content/70 mt-2">正在處理媒體...</p>
                </div>
            </div>            
        </div>
    @endif
</div>