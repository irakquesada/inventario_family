<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Contracts\View\View;

class SaleTicketController extends Controller
{
    public function __invoke(Sale $sale): View
    {
        abort_unless(auth()->user()?->can('sales.view'), 403);

        return view('sales.ticket', ['sale' => $sale->load(['items', 'warehouse', 'user'])]);
    }
}
