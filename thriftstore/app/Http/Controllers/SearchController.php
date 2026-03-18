<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Seller;

class SearchController extends Controller
{
    public function suggest(Request $request)
    {
        $q = trim($request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        // Products: name, tags, price
        $products = Product::query()
            ->where('is_active', true)
            ->where(function($query) use ($q) {
                $query->where('name', 'like', "%$q%")
                      ->orWhere('tags', 'like', "%$q%")
                      ->orWhereRaw('CAST(price AS CHAR) LIKE ?', ["%$q%"]);
            })
            ->with('seller')
            ->limit(5)
            ->get();

        // Sellers: store name
        $sellers = Seller::query()
            ->where('status', 'approved')
            ->where('is_open', true)
            ->where('store_name', 'like', "%$q%")
            ->limit(3)
            ->get();

        $results = [];
        // Product suggestions
        foreach ($products as $product) {
            $name = $product->name;
            $pos = stripos($name, $q);
            $prefix = $pos !== false ? mb_substr($name, 0, $pos + mb_strlen($q)) : $name;
            $suffix = $pos !== false ? mb_substr($name, $pos + mb_strlen($q)) : '';
            $results[] = [
                'type' => 'product',
                'name' => $name,
                'prefix' => $prefix,
                'suffix' => $suffix,
                'url' => route('product.show', $product->id),
                'image_path' => $product->image_path ? asset('storage/' . $product->image_path) : null,
            ];
        }
        // Seller suggestions
        foreach ($sellers as $seller) {
            $name = $seller->store_name;
            $pos = stripos($name, $q);
            $prefix = $pos !== false ? mb_substr($name, 0, $pos + mb_strlen($q)) : $name;
            $suffix = $pos !== false ? mb_substr($name, $pos + mb_strlen($q)) : '';
            $results[] = [
                'type' => 'seller',
                'name' => $name,
                'prefix' => $prefix,
                'suffix' => $suffix,
                'url' => route('store.show', $seller->store_name),
                'logo_path' => $seller->logo_path ? asset('storage/' . $seller->logo_path) : null,
            ];
        }
        return response()->json($results);
    }
}
