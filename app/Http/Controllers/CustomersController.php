<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Http\Requests\CustomersStoreRequest;
use App\Http\Requests\CustomersUpdateRequest;
use App\Services\Contracts\CustomersServiceInterface;
use Spatie\Customer\Models\Customer;

class CustomersController extends Controller
{
    /**
     * @var CustomersServiceInterface $customersService
     */
    protected $customersService;

    /**
     * Konstruktor CustomerController.
     */
    public function __construct(CustomersServiceInterface $customersService)
    {
        $this->customersService = $customersService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Ambil parameter status dari query string
        $status = $request->query('status');

        if ($status === null) {
            // Jika tidak ada query parameter, ambil semua customer
            $customers = $this->customersService->getAllCustomers();
        } elseif ($status == 1) {
            // Jika status = 1, ambil customer dengan status aktif
            $customers = $this->customersService->getActiveCustomers();
        } elseif ($status == 0) {
            // Jika status = 0 ambil customer dengan status tidak aktif
            $customers = $this->customersService->getInactiveCustomers();
        } else {
            return response()->json(['error' => 'Invalid status parameter'], 400);
        }
        return CustomerResource::collection($customers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CustomersStoreRequest $request)
    {
        $customer = $this->customersService->createCustomer($request->validated());
        return new CustomerResource($customer);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $customer = $this->customersService->getCustomerById($id);
        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }
        return new CustomerResource($customer);
    }

    /**
     * Update the specified resource in storage.
     * Jika user sedang update data customernya sendiri, tidak perlu permission.
     * Jika user sedang update customer lain, perlu permission "mengelola customers".
     */
    public function update(CustomersUpdateRequest $request, string $id)
    {
        // Ambil customer yang akan diupdate
        $customer = $this->customersService->getCustomerById($id);
        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }
        
        // Jika user sedang update data customernya sendiri, izinkan tanpa permission
        if ($request->user()->id == $customer->user_id) {
            // Hapus field yang tidak boleh diubah oleh customer sendiri
            $data = $request->validated();
            unset($data['user_id']); // Customer tidak boleh mengubah user_id
            unset($data['status']); // Customer tidak boleh mengubah status
            
            $updatedCustomer = $this->customersService->updateCustomer($id, $data);
            if (!$updatedCustomer) {
                return response()->json(['message' => 'Customer not found'], 404);
            }
            return new CustomerResource($updatedCustomer);
        }
        
        // Untuk update customer lain, cek permission
        if (!$request->user()->can('mengelola customers')) {
            return response()->json([
                'message' => 'User does not have the right permissions.'
            ], 403);
        }
        
        // Untuk update customer lain, tetap menggunakan logic biasa (perlu permission)
        $updatedCustomer = $this->customersService->updateCustomer($id, $request->validated());
        if (!$updatedCustomer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        return new CustomerResource($updatedCustomer);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $deleted = $this->customersService->deleteCustomer($id);

        if (!$deleted) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        return response()->json(['message' => 'Customer deleted successfully'], 200);
    }

    /**
     * Get Active Customers.
     */
    public function getActiveCustomers()
    {
        $customers = $this->customersService->getActiveCustomers();
        // $customers = Customer::where('status', 'Aktif')->get();
        return CustomerResource::collection($customers);
    }

    /**
     * Update Status Customer.
     */
    public function updateStatus(string $id, Request $request)
    {
        $request->validate([
            'status' => 'required|in:Aktif,Non Aktif',
        ]);

        $customer = $this->customersService->updateCustomerStatus($id, $request->validated());

        if (!$customer) {
            return response()->json(['message' => 'Failed to update customer status'], 404);
        }
        return new CustomerResource($customer);
    }
}
