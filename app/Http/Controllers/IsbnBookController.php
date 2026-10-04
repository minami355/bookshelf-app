<?php

namespace App\Http\Controllers;

use App\Http\Requests\IsbnBookRequest;
use App\Services\GoogleBooksService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class IsbnBookController extends Controller
{
    /**
     * ISBNから書籍情報を取得してJSONを返す。
     *
     * @param  IsbnBookRequest  $request  入力と認証情報を持つリクエスト
     * @param  GoogleBooksService  $service  外部書籍検索サービス
     * @return JsonResponse 処理結果のJSONレスポンス
     */
    public function __invoke(IsbnBookRequest $request, GoogleBooksService $service): JsonResponse
    {
        try {
            $book = $service->findByIsbn($request->validated('isbn'));
        } catch (ConnectionException|RequestException|RuntimeException $e) {
            return response()->json(['error' => '書籍情報を取得できませんでした。時間をおいて再度お試しください。'], 502);
        }

        return $book === null
            ? response()->json(['error' => '書籍が見つかりませんでした。'], 404)
            : response()->json($book);
    }
}
