<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeliversRequest;
use App\Http\Requests\UpdateDeliversRequest;
use App\Models\Delivers;

class DeliversController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreDeliversRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreDeliversRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Delivers  $delivers
     * @return \Illuminate\Http\Response
     */
    public function show(Delivers $delivers)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Delivers  $delivers
     * @return \Illuminate\Http\Response
     */
    public function edit(Delivers $delivers)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateDeliversRequest  $request
     * @param  \App\Models\Delivers  $delivers
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateDeliversRequest $request, Delivers $delivers)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Delivers  $delivers
     * @return \Illuminate\Http\Response
     */
    public function destroy(Delivers $delivers)
    {
        //
    }
}
