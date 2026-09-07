COMPOSE=docker compose
APP=$(COMPOSE) exec app
VITE=$(COMPOSE) exec vite

.PHONY: setup build start build-start build-and-start restart stop logs shell key migrate fresh seed test lint format npm-install npm-build npm-dev redisinsight artisan

setup:
	test -f .env || cp .env.example .env
	$(COMPOSE) build
	$(COMPOSE) up -d
	$(APP) php artisan key:generate
	$(APP) php artisan migrate

build:
	$(COMPOSE) build

start:
	$(COMPOSE) up -d

build-start: build start

build-and-start: build-start

restart:
	$(COMPOSE) restart

stop:
	$(COMPOSE) down

logs:
	$(COMPOSE) logs -f

shell:
	$(APP) sh

key:
	$(APP) php artisan key:generate

migrate:
	$(APP) php artisan migrate

fresh:
	$(APP) php artisan migrate:fresh --seed

seed:
	$(APP) php artisan db:seed

test:
	$(APP) composer test

lint:
	$(APP) ./vendor/bin/pint --test

format:
	$(APP) ./vendor/bin/pint

npm-install:
	$(VITE) npm install

npm-build:
	$(VITE) npm run build

npm-dev:
	$(COMPOSE) up vite

redisinsight:
	$(COMPOSE) --profile dev up -d redisinsight

artisan:
	$(APP) php artisan $(cmd)
