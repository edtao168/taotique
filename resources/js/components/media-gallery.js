// 檔案路徑：resources/js/components/media-gallery.js

/**
 * 媒體相簿 Lightbox 元件
 * 支援圖片與影片預覽、觸控滑動、鍵盤導航
 * 
 * @param {Object} config - 設定物件
 * @param {Array} config.images - 媒體陣列 [{ id, url, is_video, is_primary, is_temp }]
 * @param {boolean} config.editable - 是否可編輯（顯示刪除/設首圖按鈕）
 * @returns {Object} Alpine.js 元件物件
 */
window.mediaGallery = function(config) {
    return {
        images: config.images || [],
        editable: config.editable || false,
        isOpen: false,
        currentIndex: 0,
        currentImage: null,
        isZoomed: false,
        touchStartX: 0,
        touchStartTime: 0,

        get hasPrev() { 
            return this.currentIndex > 0; 
        },

        get hasNext() { 
            return this.currentIndex < this.images.length - 1; 
        },

        openLightbox(index) {
            this.currentIndex = index;
            this.currentImage = this.images[index];
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
                this.currentImage = this.images[this.currentIndex];
                this.isZoomed = false;
            }
        },

        next() {
            if (this.hasNext) {
                this.currentIndex++;
                this.currentImage = this.images[this.currentIndex];
                this.isZoomed = false;
            }
        },

        toggleZoom() { 
            this.isZoomed = !this.isZoomed; 
        },

        deleteMedia(id, isTemp) {
            if (!confirm('確定要刪除此媒體嗎？')) return;
            if (isTemp) {
                const index = id.replace('temp_', '');
                this.$wire.call('deleteTempMedia', index);
            } else {
                this.$wire.call('deleteImage', id);
            }
        },

        setPrimary(id, isTemp) {
            if (isTemp) {
                const index = id.replace('temp_', '');
                this.$wire.call('setTempPrimary', index);
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