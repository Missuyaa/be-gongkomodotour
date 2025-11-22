<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\Contracts\UserServiceInterface;

class UserController extends Controller
{
    /**
     * @var UserServiceInterface $userService
     */
    protected $userService;

    /**
     * Konstruktor UserController.
     */
    public function __construct(UserServiceInterface $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Ambil parameter status dari query string
        $status = $request->query('status');

        if ($status === null) {
            // Jika tidak ada query parameter, ambil semua user
            $users = $this->userService->getAllUsers();
        } elseif ($status == 1) {
            // Jika status = 1, ambil user dengan status aktif
            $users = $this->userService->getActiveUsers();
        } elseif ($status == 0) {
            // Jika status = 0 ambil user dengan status tidak aktif
            $users = $this->userService->getInactiveUsers();
        } else {
            return response()->json(['error' => 'Invalid status parameter'], 400);
        }

        if (!$users) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        return UserResource::collection($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UserStoreRequest $request)
    {
        $user = $this->userService->createUser($request->all());
        if (!$user) {
            return response()->json(['message' => 'Gagal membuat user'], 400);
        }
        return new UserResource($user);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = $this->userService->getUserById($id);
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }
        return new UserResource($user);
    }

    /**
     * Update the specified resource in storage.
     * Jika user sedang update profilnya sendiri, tidak perlu permission.
     * Jika user sedang update user lain, perlu permission "mengelola user".
     */
    public function update(UserUpdateRequest $request, string $id)
    {
        // Konversi ID ke integer untuk perbandingan
        $userId = (int) $id;
        
        // Jika user sedang update profilnya sendiri, gunakan logic updateProfile
        if ($request->user()->id == $userId) {
            // Validasi menggunakan ProfileUpdateRequest untuk update profil sendiri
            $profileRequest = ProfileUpdateRequest::createFrom($request);
            $profileRequest->setContainer(app());
            $profileRequest->validateResolved();
            
            $user = $this->userService->updateProfile($userId, $profileRequest->validated());
            if (!$user) {
                return response()->json(['message' => 'User tidak ditemukan'], 404);
            }
            return new UserResource($user);
        }
        
        // Untuk update user lain, cek permission
        if (!$request->user()->can('mengelola user')) {
            return response()->json([
                'message' => 'User does not have the right permissions.'
            ], 403);
        }
        
        // Untuk update user lain, tetap menggunakan logic biasa (perlu permission)
        $user = $this->userService->updateUser($userId, $request->all());
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }
        return new UserResource($user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $deleted = $this->userService->deleteUser($id);

        if (!$deleted) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }
        return response()->json(['message' => 'User berhasil dihapus']);
    }

    /**
     * Update Status User.
     */
    public function updateStatus(string $id, Request $request)
    {
        $request->validate([
            'status' => 'required|in:Aktif,Non Aktif',
        ]);

        $user = $this->userService->updateUserStatus($id, $request->validated());

        if (!$user) {
            return response()->json(['message' => 'Failed to update user status'], 404);
        }
        return new UserResource($user);
    }

    /**
     * Update profile user sendiri (tanpa permission mengelola user).
     * Bisa digunakan dengan atau tanpa ID di URL.
     */
    public function updateProfile(ProfileUpdateRequest $request, string $id = null)
    {
        // Jika tidak ada ID, gunakan ID user yang sedang login
        $userId = $id ?? $request->user()->id;
        
        $user = $this->userService->updateProfile($userId, $request->validated());
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }
        return new UserResource($user);
    }
}
