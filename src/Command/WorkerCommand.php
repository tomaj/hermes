<?php

namespace Tomaj\Hermes\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
// use Tomaj\Hermes\Driver\DummyDriver;
use Tomaj\Hermes\Driver\RedisPubSubDriver;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Dispatcher;

use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Handler\DumpHandler;

class MyHandler implements HandlerInterface
{
    public function handle(MessageInterface $message)
    {
        echo "Prisiel mi message";
        var_dump($message);
    }
}

class WorkerCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('hermes:worker')
            ->setDescription('Hermes Worker')
            ->addOption(
               'events',
               null,
               InputOption::VALUE_OPTIONAL,
               'If set, the worker will process only this comma separated events'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $events = null;

        if ($input->getOption('events')) {
            $events = explode(',', $input->getOption('events'));
        }

        // $driver = new RedisPubSubDriver();
        // $driver = new DummyDriver();
        $driver = new RedisSetDriver();

        $dispatcher = new Dispatcher($driver);

        $dispatcher->registerHandler('myevent', new DumpHandler());



        $dispatcher->handle();
        
        $output->writeln('Hello');
    }
}