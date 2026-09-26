<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\PublicAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicAnalyticsController extends Controller
{
    public function store(Request $request, PublicAnalytics $analytics): JsonResponse
    {
        $data = $request->validate([
            'event_type' => ['required', Rule::in(['click', 'product_view'])],
            'path' => ['nullable', 'string', 'max:500'],
            'label' => ['nullable', 'string', 'max:255'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);
        $analytics->record($request, $data['event_type'], $data['path'] ?? null, $data['label'] ?? null, $data['product_id'] ?? null);

        return response()->json(status: 202);
    }
}
