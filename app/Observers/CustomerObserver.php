<?php

namespace App\Observers;

use App\Models\Customer;
use App\Services\Xbot;

class CustomerObserver
{
    /**
     * Handle the Customer "updated" event.
     *
     * @param  \App\Models\Customer  $customer
     * @return void
     */
    public function updated(Customer $customer)
    {
        // sq地址电话更正群
        $to = "17746965832@chatroom";
        if($customer->wasChanged('address_detail')){
            $message  = "[地址更新]";
            $message .= "\n客户:" . $customer->name. ':'. $customer->id ;
            $message .= "\n地址:" . $customer->address_detail;
            $message .= "\n如地址有误，请转发至本群请求用户更正";
            return app(Xbot::class)->send($message, $to);
        }

        if($customer->wasChanged('telephone')){
            $message  = "[电话更新]";
            $message .= "\n客户:" . $customer->name. ':'. $customer->id ;
            $message .= "\n电话:" . $customer->telephone;
            $message .= "\n地址:" . $customer->address_detail;
            $message .= "\n如电话有误，请转发至本群请求用户更正";
            app(Xbot::class)->send($message, $to);
            
            //分单群
            if(!$customer->deliver_id){
                $message  = "[客户认领]";
                $message .= "\n客户:" . $customer->name. ':'. $customer->id ;
                $message .= "\n电话:" . $customer->telephone;
                $message .= "\n地址:" . $customer->address_detail;
                $message .= "\n认领此顾客，请转发至本群";
                return app(Xbot::class)->send($message, '21182221243@chatroom'); 
            }
        }

    }
}
