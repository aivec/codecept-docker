# Codeception for WordPress with Docker
A CLI wrapper for [Codeception](https://codeception.com) with [wp-browser](https://github.com/lucatume/wp-browser) that containerizes and automates environment creation for WordPress integration testing. [Jump to Quickstart Guide](#quickstart-guide)

## Background (Why?)
Until recently, integration testing in WordPress has basically been a pipe-dream. The barrier to entry for setting up a fully functioning environment **just for integration testing** is simply too high for most people.

Then came [Codeception](https://codeception.com) and [wp-browser](https://github.com/lucatume/wp-browser). Codeception is a PHP testing framework that allows developers to easily write end-to-end integration tests, while wp-browser is a module for Codeception that allows you to write tests in a WordPress context (similar to WordPress core tests). These two tools used in tandem are very powerful, but the original problem remains:

*setting up an environment...*

## Docker to the Rescue
This library automates environment creation and puts everything in Docker containers. **THERE ARE NO INTERACTIVE PROMPTS**. The whole purpose of integration testing is automation. *Integration environment creation should be no different*.

The only thing required is a `codecept-docker.json` config file in the root folder of your project, which at a minimum must specify whether the root folder is a plugin or theme. The CLI then mounts the root folder in the WordPress container as a plugin/theme. This config can also be used to specify which WordPress and PHP versions to install, which plugins/themes to install, etc.

By default, the containers created are prefixed with the name of the root folder where this package is installed. **This allows having multiple non-conflicting environments on a per-project basis.**

After the environment is created, the WordPress container acts like a proxy to the `codecept` command. All tests are then invoked from within the WordPress container.

The end result is that the developer doesn't need to know any details about the environment their tests run in. Just start it and **GO!**

## Main Features
- Automatic setup and configuration of `WordPress`, `MySQL`, and `phpMyAdmin` containers
- Automatic setup and configuration of [Selenoid](https://aerokube.com/selenoid/), a Docker solution for `WPWebDriver` tests
- Automatic **per-test** video recording for `WPWebDriver` tests. (ie. `myFirstTest.success.mp4`)
- Snapshot feature that greatly speeds up `WordPress` container restarts
- WP-CLI for easy customization of the `WordPress` install

## Requirements
- PHP 7.2^
- composer
- docker

## Installation
```sh
composer require --dev aivec/codecept-docker
```

## Quickstart Guide
### Create the Scaffolding
The following commands only need to be run the **very first time you install this package**. There are 2 steps:

First, create a `codecept-docker.json` config file:
```sh
# --type may be 'plugin', 'theme', or 'other'
./vendor/bin/aivec-codecept create-config --type=plugin
```
Second, generate the Codeception folders/files:
```sh
./vendor/bin/aivec-codecept bootstrap --with-sample-tests
```

### Start the Containers
Finally, spin up the containers. If it's your very first time it may take a while:
```sh
./vendor/bin/aivec-codecept up
```

### Run the Tests
All suites, excluding `unit` tests, must be run from within the container with the `aivec-codecept codecept` command. Lets run the sample test created for the `wpunit` suite with the folliwing:
```sh
./vendor/bin/aivec-codecept codecept run wpunit
```
Thats it!

The `aivec-codecept codecept` command behaves exactly like the normal `codecept` command, except that it is invoked from within the WordPress container. Use this command in place of the normal `codecept` command for running tests.
