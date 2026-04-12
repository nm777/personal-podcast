<?php

namespace App\Policies;

use App\Models\LibraryItem;
use App\Models\User;

class LibraryItemPolicy
{
    public function update(User $user, LibraryItem $libraryItem): bool
    {
        return $user->id === $libraryItem->user_id;
    }

    public function delete(User $user, LibraryItem $libraryItem): bool
    {
        return $user->id === $libraryItem->user_id;
    }

    public function retry(User $user, LibraryItem $libraryItem): bool
    {
        return $user->id === $libraryItem->user_id;
    }
}
