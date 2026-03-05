<?php // app/Models/CategoryDefinition.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CategoryDefinition extends Model
{
    use SoftDeletes;
	
	// 如果你的主鍵是 code 而不是 id
    protected $primaryKey = 'code'; 
    
    // 如果 code 是字串 (char/varchar)
    protected $keyType = 'string'; 
    
    // 如果主鍵不是自動遞增的數字
    public $incrementing = false;
	
	protected $fillable = ['name', 'remark'];
}