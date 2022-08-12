<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use MOIREI\Vouchers\Traits\CanRedeemVouchers;

class Customer extends Model
{
    use CanRedeemVouchers;
    use HasFactory;
    use SoftDeletes;
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    public function addressIsOk(){
        return $this->deliver_id || $this->address_detail;//todo
    }

    public function deliver(){
        return $this->belongsTo(Deliver::class);
    }

    // 厂～1～xxx
    // 厂～2～xxx
    // 厂～3～xxx
    // 厂～4～xxx
    public function isDeliver(){
       return Str::startsWith($this->name ,["厂～"]);
    }

    public function getClaimParagraph(): string
    {
        $message  = "[客户认领]";
        $message .= "\n客户:" . $this->name. ':'. $this->id ;
        $message .= "\n电话:" . $this->telephone;
        $message .= "\n地址:" . $this->address_detail;
        $message .= "\n认领此顾客，请转发至本群";
        return $message;
    }

}
