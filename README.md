# PHP Thumbnail consumer

A simple consumer which generates thumbnails within a worker.
To be used with a messagebus which is compatible with the Symfony Message component

Check the [CHANGELOG](CHANGELOG.md) for the actual changes being worked on.

## Requirements
- PHP 7.3 
- [mt](https://github.com/mutschler/mt)

## Installation
#### Docker-compose
This project is aimed to be used within a docker container, and thus is developed with this setup in mind.
1. Create a `docker-compose.override.yml` file and override the appropiate parameters to accomodate to your local environment.
    1. Make sure the `/media` directory points to a directory on your machine which contains video files
    2. Make sure `MESSENGER_TRANSPORT_DSN` is pointed to a valid AMQP server and queue
    3. If using another docker path for media, override `THUMB_DIR` environment in the app container to match the directory
2. `docker-compose build`
3. `docker-compose up`