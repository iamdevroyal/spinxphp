<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Services;

use App\Modules\Auth\Domain\Entities\User as UserEntity;
use App\Modules\Auth\Domain\Repositories\UserRepositoryInterface;
use Spinx\Auth\Auth;
use Spinx\Auth\Hash;

/**
 * Application Service for Auth business logic.
 * Encapsulates password hashing, validation, session establishment, and domain orchestration.
 */
final class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * Registers a new user with hashed password and establishes an active auth session.
     *
     * @throws \InvalidArgumentException If input fails business rules or email is already taken
     */
    public function register(string $name, string $email, string $password): UserEntity
    {
        $email = strtolower(trim($email));

        if ($this->userRepository->existsByEmail($email)) {
            throw new \InvalidArgumentException('An account with this email address already exists.');
        }

        $passwordHash = Hash::make($password);
        $userEntity = UserEntity::create($name, $email, $passwordHash);

        $savedUser = $this->userRepository->save($userEntity);

        // Auto-login into session
        $userModel = \App\Modules\Auth\Infrastructure\Persistence\Models\User::find($savedUser->id);
        if ($userModel !== null) {
            Auth::login($userModel);
        }

        return $savedUser;
    }

    /**
     * Attempts to verify credentials and log in the user.
     */
    public function login(string $email, string $password): bool
    {
        return Auth::attempt([
            'email'    => strtolower(trim($email)),
            'password' => $password,
        ]);
    }

    /**
     * Logs out the currently authenticated user and clears session state.
     */
    public function logout(): void
    {
        Auth::logout();
    }

    /**
     * Retrieves the currently authenticated Domain User entity, or null.
     */
    public function currentUser(): ?UserEntity
    {
        $id = Auth::id();
        if ($id === null) {
            return null;
        }

        return $this->userRepository->findById($id);
    }

    /**
     * Checks if a user is currently authenticated.
     */
    public function check(): bool
    {
        return Auth::check();
    }
}
