# Codeception for WordPress with Docker
A CLI wrapper for [Codeception](codeception.com) with [wp-browser](https://github.com/lucatume/wp-browser) that containerizes and automates environment creation for WordPress integration testing.

## Background (Why?)
Until recently, integration testing in WordPress has basically been a pipe-dream. The barrier to entry for setting up a fully functioning environment **just for integration testing** is simply too high for most people.

Then came [Codeception](codeception.com) and [wp-browser](https://github.com/lucatume/wp-browser). Codeception is a PHP testing framework that allows developers to easily write end-to-end integration tests, while wp-browser is a module for Codeception that allows you to write tests in a WordPress context (similar to WordPress core tests). These two tools used in tandem are very powerful, but the original problem remains:

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
