<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    /**
     * 対象操作（viewAny）をユーザーに許可するか判定する。
     *
     * @param  User  $user  処理対象のユーザー
     * @return bool 許可または条件成立ならtrue
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * 対象操作（view）をユーザーに許可するか判定する。
     *
     * @param  User  $user  処理対象のユーザー
     * @param  Book  $book  対象書籍
     * @return bool 許可または条件成立ならtrue
     */
    public function view(User $user, Book $book): bool
    {
        return true;
    }

    /**
     * 対象操作（create）をユーザーに許可するか判定する。
     *
     * @param  User  $user  処理対象のユーザー
     * @return bool 許可または条件成立ならtrue
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * 対象操作（update）をユーザーに許可するか判定する。
     *
     * @param  User  $user  処理対象のユーザー
     * @param  Book  $book  対象書籍
     * @return bool 許可または条件成立ならtrue
     */
    public function update(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    /**
     * 対象操作（delete）をユーザーに許可するか判定する。
     *
     * @param  User  $user  処理対象のユーザー
     * @param  Book  $book  対象書籍
     * @return bool 許可または条件成立ならtrue
     */
    public function delete(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    /**
     * 対象操作（restore）をユーザーに許可するか判定する。
     *
     * @param  User  $user  処理対象のユーザー
     * @param  Book  $book  対象書籍
     * @return bool 許可または条件成立ならtrue
     */
    public function restore(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }

    /**
     * 対象操作（forceDelete）をユーザーに許可するか判定する。
     *
     * @param  User  $user  処理対象のユーザー
     * @param  Book  $book  対象書籍
     * @return bool 許可または条件成立ならtrue
     */
    public function forceDelete(User $user, Book $book): bool
    {
        return $user->id === $book->user_id;
    }
}
