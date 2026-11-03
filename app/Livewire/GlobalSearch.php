<?php

namespace App\Livewire;

use App\Models\Customer;
use Livewire\Component;

/**
 * Buscador global del encabezado: clientes por nombre, DUI, teléfono o código.
 */
class GlobalSearch extends Component
{
    public string $query = '';

    public function render()
    {
        $results = collect();

        if (mb_strlen(trim($this->query)) >= 2 && auth()->user()->can('customers.view')) {
            $results = Customer::query()
                ->search($this->query)
                ->with('delinquencyStatus:id,name,color')
                ->orderBy('full_name')
                ->limit(7)
                ->get(['id', 'code', 'full_name', 'dui', 'phone_primary', 'delinquency_status_id', 'status']);
        }

        return view('livewire.global-search', ['results' => $results]);
    }
}
