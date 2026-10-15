<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;

class OrderController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function index()
    {
        return Order::query()->paginate();
    }

    public function store()
    {
        return response()->noContent();
    }
}
