<?php

declare(strict_types=1);

namespace App\Presentation\User;

use Nette\Application\Attributes\Requires;
use Nette\Application\UI\Presenter;
use Tomaj\Hermes\EmitterInterface;
use Tomaj\Hermes\Message;

final class UserPresenter extends Presenter
{
    public function __construct(private readonly EmitterInterface $emitter)
    {
        parent::__construct();
    }

    /** POST /user/create with an email field */
    #[Requires(methods: 'POST')]
    public function actionCreate(): void
    {
        $email = $this->getHttpRequest()->getPost('email');
        if (!is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->getHttpResponse()->setCode(422);
            $this->sendJson(['error' => 'A valid email is required.']);
        }

        // Save the user here, then hand the slow work to the worker.
        $this->emitter->emit(new Message('user.registered', ['email' => $email]));

        $this->getHttpResponse()->setCode(201);
        $this->sendJson(['status' => 'registered']);
    }
}
