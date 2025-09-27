<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class UserManager
{
    /**
     * Create a new user (admin only).
     *
     * @param array $data
     * @param User $actor
     * @return User
     * @throws AuthorizationException
     */
    public function create(array $data, User $actor): User
    {
        if (!$actor->admin) {
            throw new AuthorizationException('Only admins can create users.');
        }
        return User::create($data);
    }

    /**
     * Update an existing user. Admins can update anyone; non-admins can only update themselves.
     *
     * @param User $target
     * @param array $data
     * @param User $actor
     * @return User
     * @throws AuthorizationException
     */
    public function update(User $target, array $data, User $actor): User
    {
        $isSelf = $actor->id === $target->id;
        if (!$isSelf && !$actor->admin) {
            throw new AuthorizationException('Not authorized to update this user.');
        }

        // Normalize payload
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if (!$actor->admin) {
            unset($data['admin']);
        }

        $target->update($data);
        return $target;
    }

    /**
     * Delete a user (admin only, cannot delete self).
     *
     * @param User $target
     * @param User $actor
     * @return void
     * @throws AuthorizationException
     */
    public function delete(User $target, User $actor): void
    {
        if (!$actor->admin) {
            throw new AuthorizationException('Only admins can delete users.');
        }
        if ($actor->id === $target->id) {
            throw new AuthorizationException('You cannot delete yourself.');
        }
        $target->delete();
    }
}
