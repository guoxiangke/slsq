<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\MorphOne;
use Laravel\Nova\Fields\MorphTo;
use Laravel\Nova\Http\Requests\NovaRequest;
use MOIREI\Vouchers\Models\Voucher;


class Coupon extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = Voucher::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'left';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function fields(Request $request)
    {
        $users = [];
        foreach(\App\Models\User::all() as $user){
            $users[json_encode([
                'voucherable_type' => $user->getMorphClass(),
                'voucherable_id' => $user->getKey(),
            ])] = "$user->name (User)";
            // $users[$user->id] = $user->name;
        }
        return [
            ID::make()->sortable(),
            Text::make('code'),
            Text::make('quantity'),
            Text::make('quantity_used'),
//            Text::make('value'),
//            DateTime::make('active_date'),
//            DateTime::make('expires_at'),

//            MorphTo::make('Product', 'product')->readOnly(function(){
//                return !is_null($this->model);
//            })->types([
//                Product::class,
//            ]),


//            MorphOne::make('Data', 'modeldata', Customer::class), // you'll have to create a ModelData resource

//            Multiselect::make('Only users', 'can_redeem')->options($users),

//            Multiselect::make('Except users', 'cannot_redeem')->options($users),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function cards(Request $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function filters(Request $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function lenses(Request $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function actions(Request $request)
    {
        return [];
    }
}
