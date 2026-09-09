<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        return view('customers.index', [
            'customers' => Customer::withCount('quotations')
                ->when($request->q, fn ($query, $term) => $query->where(fn ($x) => $x
                    ->where('contact_name', 'like', "%{$term}%")
                    ->orWhere('company_name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")))
                ->orderBy('contact_name')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('customers.form', ['customer' => new Customer]);
    }

    public function edit(Customer $customer)
    {
        return view('customers.form', [
            'customer' => $customer->load(['quotations' => fn ($query) => $query->latest('id')->limit(10)]),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $this->validated($request);
        $data['code'] = Customer::nextCode();
        $data['created_by'] = auth()->id();

        $customer = Customer::create($data);
        $audit->log('created', $customer, [], $customer->toArray());

        return redirect()->route('customers.edit', $customer)->with('success', 'Customer created.');
    }

    public function update(Request $request, Customer $customer, AuditService $audit)
    {
        $before = $customer->toArray();
        $customer->update($this->validated($request, $customer));
        $audit->log('customer_changed', $customer, $before, $customer->fresh()->toArray());

        return back()->with('success', 'Customer updated.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'contact_name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => ['nullable', 'email', 'max:255', Rule::unique('customers')->ignore($customer)],
            'address' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);
    }
}
