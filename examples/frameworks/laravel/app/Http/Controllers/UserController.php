<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tomaj\Hermes\EmitterInterface;
use Tomaj\Hermes\Message;

final class UserController extends Controller
{
    public function __construct(private readonly EmitterInterface $emitter) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        // Save the user here, then hand the slow work to the worker.
        $this->emitter->emit(new Message('user.registered', ['email' => $data['email']]));

        return new JsonResponse(['status' => 'registered'], 201);
    }
}
