<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialDefinition extends Model
{
    use SoftDeletes;
	
	protected $fillable = ['bb_code', 'c_code', 'name', 'market_names'];
}