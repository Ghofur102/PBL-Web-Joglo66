<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use App\Services\Admin\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class AuthController extends Controller
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $status = 200;
        $data = [];

        try {
            $result = $this->authService->login($request->validated());

            $data = [
                'success' => true,
                'message' => 'Login berhasil',
                'token'   => $result['token'],
                'user'    => $result['user']
            ];
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
            $data = [
                'success' => false,
                'message' => $e->getMessage()
            ];
        } catch (Throwable $e) {
            $status = 500;
            $data = [
                'success' => false,
                'message' => 'Terjadi kesalahan pada sistem otentikasi: ' . $e->getMessage()
            ];
        }

        return response()->json($data, $status);
    }

    public function profile(Request $request): JsonResponse
    {
        $status = 200;
        $data = [];

        try {
            $user = $request->user();
            if (!$user) {
                throw new HttpException(404, 'User tidak ditemukan atau belum login.');
            }

            $profile = $this->authService->getProfileData($user);

            $data = [
                'success' => true,
                'message' => 'Berhasil mengambil data profil.',
                'data'    => $profile
            ];
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
            $data = [
                'success' => false,
                'message' => $e->getMessage()
            ];
        } catch (Throwable $e) {
            $status = 500;
            $data = [
                'success' => false,
                'message' => 'Gagal memuat data profil: ' . $e->getMessage()
            ];
        }

        return response()->json($data, $status);
    }

    public function logout(Request $request): JsonResponse
    {
        $status = 200;
        $data = [];

        try {
            $user = $request->user();
            $this->authService->logout($user);

            $data = [
                'success' => true,
                'message' => 'Logout berhasil.'
            ];
        } catch (Throwable $e) {
            $status = 500;
            $data = [
                'success' => false,
                'message' => 'Gagal memproses logout: ' . $e->getMessage()
            ];
        }

        return response()->json($data, $status);
    }
}
