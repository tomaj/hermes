# Hermes in Nette 3.2

The files here are the Hermes integration for a fresh Nette 3.2 project, at their paths in that project. Docs: <https://tomaj.github.io/hermes/frameworks/nette>.

```bash
composer create-project nette/web-project app
cd app
composer require tomaj/hermes:dev-master contributte/console
composer require predis/predis              # only for the predis driver
composer require php-amqplib/php-amqplib    # only for the rabbitmq driver
composer require aws/aws-sdk-php            # only for the sqs driver
/path/to/hermes/examples/frameworks/nette/install.sh .

php bin/console hermes:work                            # terminal 1: the worker
php bin/console app:register-user jane@example.com     # terminal 2: emit user.registered
php bin/console hermes:restart                         # stop the worker after its current message
```

Until Hermes 6.0 is tagged, require `tomaj/hermes:dev-master` as above; switch to `^6.0` once it is released. The same files also run on Hermes 5.0.

`./e2e.sh /path/to/app redis predis rabbitmq sqs` runs the whole flow once per driver and fails if a message is not handled or the worker does not stop.
