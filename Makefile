.PHONY: up down build restart migrate seed test test-unit test-integration test-feature swagger logs shell rabbitmq-ui fresh

up:
	docker-compose up -d --build

down:
	docker-compose down

build:
	docker-compose build --no-cache

restart:
	docker-compose restart

migrate:
	docker-compose exec app php artisan migrate

migrate-fresh:
	docker-compose exec app php artisan migrate:fresh --seed

seed:
	docker-compose exec app php artisan db:seed

test:
	docker-compose exec app php artisan test

test-unit:
	docker-compose exec app php artisan test --testsuite=Unit

test-feature:
	docker-compose exec app php artisan test --testsuite=Feature

test-integration:
	docker-compose exec app php artisan test --testsuite=Integration

swagger:
	docker-compose exec app php artisan l5-swagger:generate

logs:
	docker-compose logs -f worker_transactional worker_marketing

shell:
	docker-compose exec app sh

rabbitmq-ui:
	xdg-open http://localhost:15672 2>/dev/null || open http://localhost:15672

fresh: down
	docker-compose up -d --build
	sleep 10
	docker-compose exec app php artisan migrate --seed
