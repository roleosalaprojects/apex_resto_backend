<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\Products\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return view('ecommerce.products.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Public product detail page used by /shop/products/{product}.
     * Inactive items bounce back to the listing rather than 404 so a
     * customer following a stale featured link gets a graceful redirect.
     * Parameter MUST be named $product to match the {product} URL
     * placeholder created by Route::resource — otherwise model binding
     * silently fails and every product looks inactive.
     */
    public function show(Item $product): View|RedirectResponse
    {
        if (! $product->status) {
            return redirect()->route('shops.products.index')
                ->with('error', 'That product is no longer available.');
        }

        $product->load(['category', 'wholesalePriceTiers', 'stocks']);

        return view('ecommerce.products.show', ['item' => $product]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Item $item)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Item $item)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Item $item)
    {
        //
    }
}
