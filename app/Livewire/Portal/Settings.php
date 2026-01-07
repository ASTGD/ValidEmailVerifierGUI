<?php

namespace App\Livewire\Portal;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal')]
class Settings extends Component
{
    public function render()
    {
        return view('livewire.portal.settings');
    }
}
