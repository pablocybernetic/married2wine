<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MpesaTransaction extends Model
{
  
        protected $fillable = ['transaction_id', 'amount', 'phone', 'status', 'payment_date'];
    
        // Add any other model properties, relationships, or methods here
    
    
}
