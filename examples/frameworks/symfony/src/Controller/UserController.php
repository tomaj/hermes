<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Tomaj\Hermes\EmitterInterface;
use Tomaj\Hermes\Message;

final class UserController
{
    public function __construct(private readonly EmitterInterface $emitter) {}

    #[Route('/users', name: 'user_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $email = $request->getPayload()->getString('email');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return new JsonResponse(['error' => 'A valid email is required.'], 422);
        }

        // Save the user here, then hand the slow work to the worker.
        $this->emitter->emit(new Message('user.registered', ['email' => $email]));

        return new JsonResponse(['status' => 'registered'], 201);
    }
}
