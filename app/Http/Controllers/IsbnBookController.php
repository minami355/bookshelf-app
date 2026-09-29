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
