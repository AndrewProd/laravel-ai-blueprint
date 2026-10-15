<?php

namespace App\Http\Controllers;

class StripeWebhookController
{
    public function __invoke()
    {
        return response()->noContent();
    }
}
