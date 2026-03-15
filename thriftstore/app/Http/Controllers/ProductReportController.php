<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductReport;
use Illuminate\Http\Request;

class ProductReportController extends Controller
{
    public function store(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        $product = Product::query()->where('is_active', true)->findOrFail($id);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:100', 'in:' . implode(',', array_keys(ProductReport::reasonOptions()))],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        ProductReport::create([
            'product_id' => $product->id,
            'customer_id' => $request->user()->id,
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->back()->with('status', 'report-submitted');
    }
}
