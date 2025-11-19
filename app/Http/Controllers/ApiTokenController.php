<?php

namespace App\Http\Controllers; // <-- (1) ตรวจสอบ Namespace

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable; // <-- (2) ✅✅✅ ต้องมีบรรทัดนี้ ✅✅✅

class ApiTokenController extends Controller
{
    /**
     * (Method: index)
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $tokens = $request->user()->tokens()->orderBy('created_at', 'desc')->get();
        return view('management.tokens.index', [
            'tokens' => $tokens,
        ]);
    }

    /**
     * (Method: show)
     * Display the specified resource's details (via AJAX).
     */
    // --- (3) ✅✅✅ ตรวจสอบ Parameter ตรงนี้ให้ถูกต้อง ✅✅✅ ---
    public function show(Request $request, $tokenId)
    {
        try {
            $token = $request->user()->tokens()->findOrFail($tokenId); // ใช้ $tokenId ที่รับมา

            return response()->json([
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at ? $token->last_used_at->format('d/m/Y H:i:s') : 'ยังไม่เคยใช้งาน',
                'created_at' => $token->created_at->format('d/m/Y H:i:s')
            ]);

        } catch (Throwable $e) {
            return response()->json(['message' => 'ไม่พบ Token หรือเกิดข้อผิดพลาด: ' . $e->getMessage()], 404);
        }
    }

    /**
     * (Method: store)
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $request->validate(['token_name' => 'required|string|max:255']);
            $newToken = $request->user()->createToken($request->token_name, ['pu:callback']);
            return response()->json(['plainTextToken' => $newToken->plainTextToken]);
        } catch (Throwable $e) {
            return response()->json(['message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
        }
    }

    /**
     * (Method: destroy)
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $tokenId)
    {
         try {
            $token = $request->user()->tokens()->findOrFail($tokenId);
            $token->delete();
            return response()->json(['message' => 'Token revoked successfully.']);
        } catch (Throwable $e) {
            return response()->json(['message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], 500);
        }
    }
}