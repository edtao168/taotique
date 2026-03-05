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
        themes: ["light", "dark"],
    },
}