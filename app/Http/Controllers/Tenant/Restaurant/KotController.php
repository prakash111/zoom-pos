<?php

namespace App\Http\Controllers\Tenant\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\KitchenTicket;

class KotController extends Controller
{
    public function print(KitchenTicket $kot)
    {
        $company = $kot->company ?? auth('web')->user()?->company;

        return view('documents.kot', [
            'kot' => $kot,
            'company' => $company,
        ]);
    }
}
