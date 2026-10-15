<?php

namespace Tests\Feature;

use App\Models\Customer;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    public function test_index(): void
    {
        $this->assertTrue(class_exists(Customer::class));
    }
}
