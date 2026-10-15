<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Models\Customer;

class CustomerController extends Controller
{
    public function index()
    {
        return Customer::all();
    }

    public function store(StoreCustomerRequest $request)
    {
        return Customer::create($request->validated());
    }

    public function show(Customer $customer)
    {
        return $customer;
    }

    protected function helper(): void {}
}
