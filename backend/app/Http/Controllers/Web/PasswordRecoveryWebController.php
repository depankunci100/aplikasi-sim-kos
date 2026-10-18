<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\PasswordRecoveryService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PasswordRecoveryWebController extends Controller
{
    public function __construct(
        protected PasswordRecoveryService $recoveryService
    ) {}

    /**
     * Inisiasi reset password penyewa dari dashboard web
     */
    public function initiate(Request $request): JsonResponse|RedirectResponse
    {
        $actor = $request->user();

        $request->validate([
            'tenant_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $result = $this->recoveryService->initiateTenantPasswordReset(
                (int) $request->tenant_id,
                $actor,
                $request
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Inisiasi reset password penyewa berhasil diproses.',
                    'data' => $result,
                ]);
            }

            return redirect()->back()->with('success', 'Inisiasi reset password berhasil. Token pemulihan berlaku selama 60 menit.');
        } catch (DomainException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
