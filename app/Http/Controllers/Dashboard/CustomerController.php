<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        return view('dashboard.customers.index', [
            'customers' => Customer::search($request->query('q'))
                ->orderByDesc('total_spent')
                ->paginate(20)
                ->withQueryString(),
            'filters' => $request->only('q'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = Customer::create($this->validated($request));

        ActivityLog::record('customer.create', "Menambah pelanggan {$customer->name}", $customer);

        return back()->with('status', "Pelanggan {$customer->name} ditambahkan.");
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validated($request));

        ActivityLog::record('customer.update', "Mengubah pelanggan {$customer->name}", $customer);

        return back()->with('status', 'Data pelanggan diperbarui.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $name = $customer->name;
        $customer->delete();

        ActivityLog::record('customer.delete', "Menghapus pelanggan {$name}", $customer);

        return back()->with('status', "Pelanggan {$name} dihapus.");
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'code' => ['nullable', 'string', 'max:32'],
        ], [], ['name' => 'nama pelanggan'])
            + ['is_member' => $request->boolean('is_member')];
    }
}
