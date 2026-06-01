#!/bin/bash
cd / && composer update -o --no-dev
/bin/bash "$@"
