# Change Log
All notable changes to this project will be documented in this file.
Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## [Unreleased][unreleased]

#### Added

* Added missing handle() method to DispatcherInterface

## 2.0.0 - 2018-08-14

### Added

* Message now support scheduled parameter - Driver needs to support this behaviour.
* Type hints

### Changed

* Dropped support for php 5.4
* Deprecated emit() in Disapatcher - introduced Emitter

## 1.2.0 - 2016-09-26

### Updated

* Amazon aws library updated to version 3 in composer - still works with v2 but you have to initialize Sqs client in v2 style

## 1.1.0 - 2016-09-05

### Added

* Amazon SQS driver

## 1.0.0 - 2016-09-02

### Added

* First stable version
* Added ACK to rabbitmq driver

## 0.4.0 - 2016-04-26

### Added

* Added RabbitMQ Lazy driver

## 0.3.0 - 2016-03-23

### Added

* Added possibility to gracefull restart worker with RestartInterface
* Added Tracy debugger log when error occured

## 0.2.0 - 2015-10-30

### Changed

* Handling responses from handlers.
* Tests structure refactored

## 0.1.0 - 2015-10-28

### Added

* initial version with 2 drivers
