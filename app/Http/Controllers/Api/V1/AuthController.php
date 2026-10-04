<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTokenRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    /**
     * 認証情報を確認し、デバイス用のAPIトークンを発行する。
     *
     * @param  StoreTokenRequest  $request  入力と認証情報を持つリクエスト
     * @return JsonResponse 処理結果のJSONレスポンス
     */
    public function store(StoreTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => '認証情報が正しくありません。',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $token = $user->createToken($validated['device_name'])->plainTextToken;

        return response()->json([
            'token' => $token,
        ], Response::HTTP_OK);
    }

    /**
     * リクエストの認証に使われたAPIトークンだけを失効させる。
     *
     * @param  Request  $request  入力と認証情報を持つリクエスト
     * @return Response 処理結果のHTTPレスポンス
     */
    public function destroy(Request $request): Response
    {
        /** @var PersonalAccessToken $token Bearer認証で使用中のトークン */
        $token = $request->user()->currentAccessToken();
        $token->delete();

        return response()->noContent();
    }
}
