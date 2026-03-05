// 暫時留空或只寫一行註解，確保 Vite 能編譯成功
console.log('Vite JS loaded');

import Chart from 'chart.js/auto';

// 掛載 Chart 到全域，供 livewire-charts 使用
window.Chart = Chart;