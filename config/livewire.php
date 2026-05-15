<<<<<<< HEAD
<?php

return [    
    'temporary_file_upload' => [
        'disk' => null,        // 預設為 local
        'rules' => null,       // 範例：'required|file|max:12288' (12MB)
        'directory' => null,   // 預設存放在 livewire-tmp/
        'middleware' => null,  // 預設為 'throttle:60,1'
        
        'preview_mimes' => ['png', 'gif', 'bmp', 'jpg', 'jpeg', 'webp', 'avif', 'mp4', 'mov', 'avi', 'wmv', 'asf', 'webm',],

        'max_upload_time' => 5, // 分鐘
    ],
=======
<?php

return [    
    'temporary_file_upload' => [
        'disk' => null,        // 預設為 local
        'rules' => null,       // 範例：'required|file|max:12288' (12MB)
        'directory' => null,   // 預設存放在 livewire-tmp/
        'middleware' => null,  // 預設為 'throttle:60,1'
        
        'preview_mimes' => ['png', 'gif', 'bmp', 'jpg', 'jpeg', 'webp', 'avif', 'mp4', 'mov', 'avi', 'wmv', 'asf', 'webm',],

        'max_upload_time' => 5, // 分鐘
    ],
>>>>>>> b29039cfb5a4a2683aedba9883af961633089c73
];