<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class BookDetailResource extends BookResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'reviews' => $this->whenLoaded('reviews', fn () => $this->reviews->map(
                fn ($review) => [
                    'id' => $review->id,
                    'user_name' => $review->user->name,
                    'rating' => (int) $review->rating,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at?->format('Y-m-d H:i:s'),
                ]
            )),
        ];
    }
}
