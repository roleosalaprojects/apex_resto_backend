<?php

namespace App\View\Components\Modals;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class CreateEdit extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $identifier,
        public string $title
    )
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.modals.create-edit');
    }
}
