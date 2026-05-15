// 檔案路徑：resources/js/components/media-gallery.js

/**
 * 媒體相簿 Lightbox 元件
 * 支援圖片與影片預覽、觸控滑動、鍵盤導航
 * 
 * @param {Object} config - 設定物件
 * @param {Array} config.initialImages - 初始媒體陣列 [{ id, url, is_video, is_primary, is_temp }]
 * @param {boolean} config.editable - 是否可編輯（顯示刪除/設首圖按鈕）
 * @returns {Object} Alpine.js 元件物件
 */
window.mediaGallery = function(config) {
    return {
        // 統一資料源：所有媒體（資料庫 + 暫存）都存在這裡
        allMedia: config.initialImages || [],
        editable: config.editable || false,
        isOpen: false,
        currentIndex: 0,
        isZoomed: false,
        touchStartX: 0,
        touchStartTime: 0,

        get currentImage() {
            return this.allMedia[this.currentIndex] || null;
        },

        get hasPrev() { 
            return this.currentIndex > 0; 
        },

        get hasNext() { 
            return this.currentIndex < this.allMedia.length - 1; 
        },

        // 依 ID 移除媒體
        removeMediaById(id) {
            const index = this.allMedia.findIndex(m => m.id == id);
            if (index !== -1) {
                this.allMedia.splice(index, 1);
                if (this.isOpen && this.currentIndex >= this.allMedia.length) {
                    this.currentIndex = Math.max(0, this.allMedia.length - 1);
                }
            }
        },

        // 替換整個媒體列表（由 Livewire re-render 後呼叫）
        syncMediaList(newList) {
            if (!Array.isArray(newList)) return;
            this.allMedia = newList;
        },

        openLightbox(index) {
            if (index < 0 || index >= this.allMedia.length) return;
            this.currentIndex = index;
            this.isOpen = true;
            document.body.style.overflow = 'hidden';
        },

        close() {
            this.isOpen = false;
            this.isZoomed = false;
            document.body.style.overflow = '';
        },

        prev() {
            if (this.hasPrev) {
                this.currentIndex--;
                this.isZoomed = false;
            }
        },

        next() {
            if (this.hasNext) {
                this.currentIndex++;
                this.isZoomed = false;
            }
        },

        toggleZoom() { 
            this.isZoomed = !this.isZoomed; 
        },

        deleteMedia(id, isTemp, index) {
            if (!confirm('確定要刪除此媒體嗎？')) return;

            // 前端即時移除，避免縮略圖殘留
            this.removeMediaById(id);

            // 再呼叫 Livewire 後端刪除
            if (isTemp) {
                const tempIndex = id.toString().replace('temp_', '');
                this.$wire.call('deleteTempMedia', tempIndex);
            } else {
                this.$wire.call('deleteImage', id);
            }
        },

        setPrimary(id, isTemp) {
            // 前端即時更新首圖標記
            this.allMedia.forEach(m => m.is_primary = false);
            const target = this.allMedia.find(m => m.id == id);
            if (target) target.is_primary = true;

            // 同步到 Livewire
            if (isTemp) {
                const tempIndex = id.toString().replace('temp_', '');
                this.$wire.call('setTempPrimary', tempIndex);
            } else {
                this.$wire.call('setPrimary', id);
            }
        },

        // 觸控手勢支援
        handleTouchStart(e) {
            this.touchStartX = e.touches[0].clientX;
            this.touchStartTime = Date.now();
        },

        handleTouchMove(e) { 
            if (e.touches.length === 2) e.preventDefault(); 
        },

        handleTouchEnd(e) {
            const diffX = this.touchStartX - e.changedTouches[0].clientX;
            const timeDiff = Date.now() - this.touchStartTime;
            if (Math.abs(diffX) > 50 && timeDiff < 300) {
                diffX > 0 ? this.next() : this.prev();
            }
        }
    };
};