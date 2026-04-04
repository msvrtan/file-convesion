.PHONY: lint fix analyse reviewer test dev-db-setup test-db-setup db-migrate-dev db-fixtures-dev db-drop-test db-migrate-test db-fixtures-test jwt-keypair messenger-setup-transports

lint:
	vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff

fix:
	vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php

analyse:
	vendor/bin/phpstan analyse --no-progress -c phpstan.dist.neon

reviewer: jwt-keypair dev-db-setup test-db-setup

test: test-db-setup
	vendor/bin/phpunit -c phpunit.dist.xml

dev-db-setup: db-migrate-dev db-fixtures-dev

test-db-setup: db-drop-test db-migrate-test db-fixtures-test

db-migrate-dev:
	php bin/console doctrine:migrations:migrate --no-interaction

db-fixtures-dev:
	php bin/console doctrine:fixtures:load --no-interaction

db-drop-test:
	rm -f var/data_test.db

db-migrate-test:
	APP_ENV=test php bin/console doctrine:migrations:migrate --no-interaction

db-fixtures-test:
	APP_ENV=test php bin/console doctrine:fixtures:load --no-interaction

jwt-keypair:
	php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction

messenger-setup-transports:
	php bin/console messenger:setup-transports --no-interaction
