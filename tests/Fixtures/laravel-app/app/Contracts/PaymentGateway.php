<?php

namespace App\Contracts;

interface PaymentGateway
{
    public function charge(int $amount): string;
}
