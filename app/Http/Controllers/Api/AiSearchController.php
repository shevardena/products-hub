<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\AiProductResource;

class AiSearchController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('q', '');
        $merchantId = $request->get('merchant_id');
        $minPrice = $request->get('min_price');
        $maxPrice = $request->get('max_price');
        $limit = min(max((int) $request->get('limit', 20), 1), 50);

        $filters = [];

        if ($merchantId) {
            $filters[] = "merchant_id = $merchantId";
        }

        if ($minPrice) {
            $filters[] = "price >= $minPrice";
        }

        if ($maxPrice) {
            $filters[] = "price <= $maxPrice";
        }

        $filterString = implode(' AND ', $filters);

        $products = Product::search($search, function ($meilisearch, $query, $options) use ($filterString, $limit) {

            if (!empty($filterString)) {
                $options['filter'] = $filterString;
            }

            $options['limit'] = $limit;

            return $meilisearch->search($query, $options);
        })->get();

        return AiProductResource::collection($products);
    }
}
