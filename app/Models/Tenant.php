<?php
// app/Models/Tenant.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'subdomain',
        'status',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function shops()
    {
        return $this->hasMany(Shop::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function suppliers()
    {
        return $this->hasMany(Supplier::class);
    }

    public function partners()
    {
        return $this->hasMany(Partner::class);
    }

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function accountingRules()
    {
        return $this->hasMany(AccountingRule::class);
    }

    public function channels()
    {
        return $this->hasMany(Channel::class);
    }

    public function accountingPeriods()
    {
        return $this->hasMany(AccountingPeriod::class);
    }

    public function materialDefinitions()
    {
        return $this->hasMany(MaterialDefinition::class);
    }

    public function categoryDefinitions()
    {
        return $this->hasMany(CategoryDefinition::class);
    }
}