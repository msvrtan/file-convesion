.PHONY: lint fix analyse test jwt-keypair

lint:
	vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff

fix:
	vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php

analyse:
	vendor/bin/phpstan analyse --no-progress -c phpstan.dist.neon

test:
	vendor/bin/phpunit -c phpunit.dist.xml

jwt-keypair:
	php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction
