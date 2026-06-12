<?php

namespace App\View\Composers;

use App\Models\Products\Category;
use Illuminate\View\View;

class EcommerceComposer
{
    public function compose(View $view): void
    {
        $view->with('navCategories', Category::where('status', true)
            ->orderBy('name')
            ->get(['id', 'name', 'icon']));
    }
}
