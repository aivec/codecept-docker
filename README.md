# Codeception for WordPress with Docker
A CLI wrapper for [wp-browser](https://github.com/lucatume/wp-browser) that containerizes and automates environment setup.

## The Problem
Until recently, integration testing in WordPress has been basically unheard of. The barrier to entry for setting up a fully functioning environment **just for integration testing** is simply too high for most people, especially for professional dev teams working on a budget. That's where [Codeception](codeception.com) and [wp-browser](https://github.com/lucatume/wp-browser) come in. Codeception is a PHP testing framework that allows developers to easily write end-to-end integration tests, while wp-browser is a module for Codeception that allows you to write tests in a WordPress context (similar to WordPress core tests). These two tools used in tandem are very powerful, but the original problem remains: *setting up an environment*.

## The Solution
This library automates environment creation by putting everything in Docker containers. 