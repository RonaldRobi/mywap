<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    /**
     * Render the shopping cart page.
     */
    public function index(): Response
    {
        return Inertia::render('Ecommerce/Cart');
    }
}
