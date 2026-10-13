# Logging

`Emitter` and `Dispatcher` accept any `Psr\Log\LoggerInterface`. With no logger, both classes skip every log call. Hermes does not configure Monolog for you. `monolog/monolog` is a suggested package.

<Diagram name="logging-1" alt="Logging diagram" />

```php
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Tomaj\Hermes\Dispatcher;

$log = new Logger('hermes');
$log->pushHandler(new StreamHandler('hermes.log'));

$dispatcher = new Dispatcher($driver, $log, $shutdown);
$emitter = new Emitter($driver, $log);
```

Give the emitter and the dispatcher their own logger instances if they run in different processes. A logger object is not shared through the queue.

## Lines the library writes

Levels are PSR-3 strings (`Psr\Log\LogLevel`).

| Where | Level | Message |
| --- | --- | --- |
| `Emitter::emit` | `info` | `Dispatcher send message #<id> to driver <class>` |
| `Dispatcher::emit` (deprecated) | `info` | `Dispatcher send message #<id> with priority <n> to driver <class>` |
| `Dispatcher::handle` | `info` | `Start handle message #<id> (<type>) priority:<n>` |
| `Dispatcher` after a handler returns | `info` | `End handle message #<id> (<type>)` |
| `Dispatcher` when a handler throws | `error` | `Handler <class> throws exception - <exception message>` |
| `Dispatcher` on `ShutdownException` | `notice` | `Exiting hermes dispatcher - shutdown` |

Emitter context is `id`, `created`, `type`, `payload`. Dispatcher context adds `retries` and `execute_at`. The error context also includes `error`, `message` (the array above), and `exception`.

A type with no registered handler still logs the start line. It does not log the end line, because `handleMessage()` is never called.

## Handlers

Before `handle()`, the dispatcher calls `setLogger()` when the logger is non-null and the method exists. Implementing `LoggerAwareInterface` is the usual way to get that method. The check itself is `method_exists($handler, 'setLogger')`.

```php
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class SendEmailHandler implements HandlerInterface
{
    use LoggerAwareTrait;

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();
        if ($payload === null) {
            return false;
        }

        if ($this->logger instanceof LoggerInterface) {
            $this->logger->info('Trying to send email to ' . $payload['to']);
        }

        mail($payload['to'], $payload['subject'], $payload['message']);

        return true;
    }
}
```

`LoggerAwareTrait` leaves `$this->logger` unset until the dispatcher injects one. The `instanceof` check keeps a handler usable in a test that never passes a logger.

## Tracy

`tracy/tracy` is a required dependency. Hermes writes to it only when `Debugger::isEnabled()` is already true:

- the exception caught inside `handleMessage()`
- any other `Exception` that escapes `Driver::wait()`, except `ShutdownException`

Both use `Debugger::log($exception, Debugger::EXCEPTION)`. Call `Debugger::enable()` in the worker bootstrap if you want those files. Hermes will not enable Tracy on its own.
