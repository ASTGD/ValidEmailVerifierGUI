<?php

namespace App\Livewire\Portal;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal')]
class Support extends Component
{
    public function render()
    {
        return view('livewire.portal.support', [
            'supportEmail' => config('support.email'),
            'supportUrl' => config('support.url'),
        ]);
    }
}
