<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactInquiry extends Model
{
    protected $fillable = ['name', 'email', 'store_type', 'phone', 'subject', 'message', 'status'];
}
