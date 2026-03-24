import defaultTheme from 'tailwindcss/defaultTheme'; // 必須引用

/** @type {import('tailwindcss').Config} */
export default { // Laravel 12 建議使用 export default
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.vue",
        "./vendor/robsontenorio/mary/src/View/Components/**/*.php", // Mary UI 路徑
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },
    
    // 順序建議：先引入 forms 再引入 daisyui
    plugins: [
        require("@tailwindcss/forms"),
        require("@tailwindcss/typography"),
        require("daisyui"),
    ],

    // 可選：指定 DaisyUI 主題，確保 dark mode 不會導致文字顏色錯亂
    daisyui: {
        themes: [
			{
				song_dynasty: {
					"primary": "#7BA2A8",    // 汝窯天青
					"secondary": "#C7D2D4",  // 月白
					"accent": "#B45341",     // 硃砂 (用於重要警告或印章感)
					"neutral": "#4A4A4A",    // 水墨灰
					"base-100": "#F7F3E8",   // 宣紙色 (主背景)
					"base-200": "#EDE8DB",   // 稍深的紙色 (側邊欄/卡片)
					"base-300": "#DED9CD",   // 邊框色
					"success": "#6B8E23",    // 竹青
					"error": "#991B1B",      // 絳紅

					// UI 細節調整
					"--rounded-box": "0.1rem", 
					"--rounded-btn": "0rem",   // 宋代美學較為方正俐落
				},
			},
			"light", // 保留一個預設主題作為備援
		],
    },
}