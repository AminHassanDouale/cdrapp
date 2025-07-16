<?php

namespace App\Http\Controllers;

use App\Models\CustomerAccount;
use App\Http\Requests\StoreCustomerAccountRequest;
use App\Http\Requests\UpdateCustomerAccountRequest;

class CustomerAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerAccountRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(CustomerAccount $customerAccount)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CustomerAccount $customerAccount)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomerAccountRequest $request, CustomerAccount $customerAccount)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CustomerAccount $customerAccount)
    {
        //
    }
}
