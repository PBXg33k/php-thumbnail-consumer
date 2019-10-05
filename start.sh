#!/usr/bin/env sh
composer install && bin/console messenger:consume -vvv
