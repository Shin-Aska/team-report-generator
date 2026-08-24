<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Apply user mutations and application authorization rules.
 *
 * Administrators manage all users; regular users may update only themselves and cannot grant administrator access.
 */
class UserManager
{
    /**
     * Create a new user (admin only).
     *
     * @throws AuthorizationException
     */
    public function create(array $data, User $actor): User
    {
        if (! $actor->admin) {
            throw new AuthorizationException('Only admins can create users.');
        }

        return User::create($data);
    }

    /**
     * Update an existing user. Admins can update anyone; non-admins can only update themselves.
     *
     * @throws AuthorizationException
     */
    public function update(User $target, array $data, User $actor): User
    {
        $isSelf = $actor->id === $target->id;
        if (! $isSelf && ! $actor->admin) {
            throw new AuthorizationException('Not authorized to update this user.');
        }

        // Normalize payload
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if (! $actor->admin) {
            unset($data['admin']);
        }

        $target->update($data);

        return $target;
    }

    /**
     * Delete a user (admin only, cannot delete self).
     *
     * @throws AuthorizationException
     */
    public function delete(User $target, User $actor): void
    {
        if (! $actor->admin) {
            throw new AuthorizationException('Only admins can delete users.');
        }
        if ($actor->id === $target->id) {
            throw new AuthorizationException('You cannot delete yourself.');
        }
        $target->delete();
    }
}
