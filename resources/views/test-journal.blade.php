{{-- resources/views/test-journal.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>測試 Journal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>日記帳測試</h1>
        
        <div class="card">
            <div class="card-body">
                <h5>{{ $journal->description }}</h5>
                
                {{-- 狀態顯示 --}}
                <div class="mb-3">
                    <strong>狀態：</strong>
                    <span class="badge {{ $journal->status_color }}">
                        {{ $journal->status_label }}
                    </span>
                </div>
                
                {{-- 操作按鈕 --}}
                <div class="btn-group">
                    @if($journal->isEditable())
                        <button class="btn btn-primary btn-sm">編輯</button>
                    @endif
                    
                    @if($journal->isPostable())
                        <button class="btn btn-success btn-sm" onclick="postJournal({{ $journal->id }})">
                            過帳
                        </button>
                    @endif
                    
                    @if($journal->canBeClosed())
                        <button class="btn btn-info btn-sm" onclick="closeJournal({{ $journal->id }})">
                            結帳
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</body>
</html>