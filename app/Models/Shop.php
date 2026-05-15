<?php

// app/Models/Shop.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    protected $fillable = ['name'];
	
	public function warehouses() { return $this->hasMany(Warehouse::class); }
	
	public static function getOptions(): array
	{
		return self::where('is_active', true)
			->get()
			->map(fn($s) => [
				'id' => $s->id,
				'name' => $s->name])
			->toArray();
	}
}