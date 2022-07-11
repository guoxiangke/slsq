<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    
    public function addressIsOk(){
        return $this->address_detail;//todo
    }
    

    public function address(){
        return $this->belongsTo(Address::class);
    }
    

    public function isDeliver(){
        return Str::startsWith($this->name ,["厂～"]);
    }

    


}
