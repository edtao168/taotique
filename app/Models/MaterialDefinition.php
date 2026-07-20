<?php

namespace App\Models;

use App\Models\Traits\TenantScoped;
Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialDefinition extends Model
{
    use SoftDeletes, TenantScoped;
	
	protected $fillable = ['bb_code', 'c_code', 'name', 'market_names'];
}