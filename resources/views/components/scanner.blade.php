{{-- 檔案路徑：resources/views/components/scanner.blade.php --}}
<div x-data="{
    scanner: null,
    async startScanner() {
        const config = { fps: 10, qrbox: { width: 250, height: 150 } };
        this.scanner = new Html5QrcodeScanner('reader', config, false);
        this.scanner.render((decodeText) => {
            $wire.handleScannedBarcode(decodeText);
            this.scanner.clear();
            $wire.showScanner = false;
        });
    }
}" x-init="$watch('$wire.showScanner', value => value && startScanner())">
    <div id="reader" class="w-full"></div>
</div>