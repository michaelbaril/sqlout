#!/bin/bash

LARAVEL_8_MIN_VERSION='^8.79'
LARAVEL_9_MIN_VERSION='^9.46'
LARAVEL_10_MIN_VERSION='^10.0'

run_test() {
    local php_version=${1}
    local laravel_version=${2}
    local testbench_version=${3}
    local dependency_version=${4}

    echo "Testing: PHP ${php_version} - Laravel ${laravel_version} - ${dependency_version}"
    docker-compose exec mysql mysql sqlout -e 'DROP TABLE migrations; DROP TABLE posts; DROP TABLE comments; DROP TABLE searchindex' > /dev/null 2>&1 || true
    docker-compose exec php-${php_version} composer require --no-interaction --no-update "illuminate/database:${laravel_version}" "illuminate/support:${laravel_version}" > /dev/null 2>&1
    docker-compose exec php-${php_version} composer require --no-interaction --no-update --dev "orchestra/testbench:${testbench_version}" > /dev/null 2>&1
    docker-compose exec php-${php_version} composer update  --no-interaction --prefer-dist "--${dependency_version}" > /dev/null 2>&1
    docker-compose exec php-${php_version} ./vendor/bin/phpunit
    echo
}

docker-compose up -d --force-recreate --build > /dev/null 2>&1

# Laravel 8
run_test '7.3' ${LARAVEL_8_MIN_VERSION} '^6.23' 'prefer-lowest'
run_test '7.3' ${LARAVEL_8_MIN_VERSION} '^6.23' 'prefer-stable'

run_test '7.4' ${LARAVEL_8_MIN_VERSION} '^6.23' 'prefer-lowest'
run_test '7.4' ${LARAVEL_8_MIN_VERSION} '^6.23' 'prefer-stable'

run_test '8.0' ${LARAVEL_8_MIN_VERSION} '^6.23' 'prefer-lowest'
run_test '8.0' ${LARAVEL_8_MIN_VERSION} '^6.23' 'prefer-stable'

run_test '8.1' ${LARAVEL_8_MIN_VERSION} '^6.23' 'prefer-lowest'
run_test '8.1' ${LARAVEL_8_MIN_VERSION} '^6.23' 'prefer-stable'

# Laravel 9
run_test '8.0' ${LARAVEL_9_MIN_VERSION} '^7.0' 'prefer-lowest'
run_test '8.0' ${LARAVEL_9_MIN_VERSION} '^7.0' 'prefer-stable'

run_test '8.1' ${LARAVEL_9_MIN_VERSION} '^7.0' 'prefer-lowest'
run_test '8.1' ${LARAVEL_9_MIN_VERSION} '^7.0' 'prefer-stable'

run_test '8.2' ${LARAVEL_9_MIN_VERSION} '^7.0' 'prefer-lowest'
run_test '8.2' ${LARAVEL_9_MIN_VERSION} '^7.0' 'prefer-stable'

run_test '8.3' ${LARAVEL_9_MIN_VERSION} '^7.0' 'prefer-lowest'
run_test '8.3' ${LARAVEL_9_MIN_VERSION} '^7.0' 'prefer-stable'

# Laravel 10
run_test '8.1' ${LARAVEL_10_MIN_VERSION} '^8.0' 'prefer-lowest'
run_test '8.1' ${LARAVEL_10_MIN_VERSION} '^8.0' 'prefer-stable'

run_test '8.2' ${LARAVEL_10_MIN_VERSION} '^8.0' 'prefer-lowest'
run_test '8.2' ${LARAVEL_10_MIN_VERSION} '^8.0' 'prefer-stable'

run_test '8.3' ${LARAVEL_10_MIN_VERSION} '^8.0' 'prefer-lowest'
run_test '8.3' ${LARAVEL_10_MIN_VERSION} '^8.0' 'prefer-stable'
