<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasFactory, SoftDeletes;

    /*
     * Account Types
     * 1 = Asset
     * 2 = Liability
     * 3 = Owner's Equity
     * 4 = Revenue
     * 5 = Expenses
     * */

    protected $fillable = [
        'number', // Number assigned to an account
        'name',
        'description',
        'starting_balance',
        'current_balance',
        'type',
        'user_id', // Created by which user
    ];
}
