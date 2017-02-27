ARGS = $(filter-out $@,$(MAKECMDGOALS))
MAKEFLAGS += --silent
CONTAINERS = $(shell docker ps -a -q)
VOLUMES = $(shell docker volume ls |awk 'NR>1 {print $2}')
COMPOSER_DIR = $(realpath $(PWD))

list:
	sh -c "echo; $(MAKE) -p no_targets__ | awk -F':' '/^[a-zA-Z0-9][^\$$#\/\\t=]*:([^=]|$$)/ {split(\$$1,A,/ /);for(i in A)print A[i]}' | grep -v '__\$$' | grep -v 'Makefile'| sort"

#############################
# Docker machine states
#############################

start:
	docker-compose start

stop:
	docker-compose stop

state:
	docker-compose ps

build:
	@if [ ! -f ./.env ]; then\
  	read -p "Enter master public key:" master_key; echo "MASTER_KEY=$$master_key" >> ./.env; \
  	read -p "Enter horizon host [with protocol and port (optional)]:" horizon_host; echo "HORIZON_HOST=$$horizon_host" >> ./.env; \
  	read -p "Enter welcome host [with protocol and port (optional)]:" welcome_host; echo "WELCOME_HOST=$$welcome_host" >> ./.env; \
  	read -p "Enter merchant host [with protocol and port (optional)]:" merchant_host; echo "MERCHANT_HOST=$$merchant_host" >> ./.env; \
  	read -p "Enter riak host [only domain]:" riak_host; echo "RIAK_HOST=$$riak_host" >> ./.env; \
  	read -p "Enter riak port:" riak_port; echo "RIAK_PORT=$$riak_port" >> ./.env; \
  	read -p "Enter SMTP host:" smtp_host; echo "SMTP_HOST=$$smtp_host" >> ./.env; \
  	read -p "Enter SMTP port:" smtp_port; echo "SMTP_PORT=$$smtp_port" >> ./.env; \
  	read -p "Enter SMTP security:" smtp_security; echo "SMTP_SECURITY=$$smtp_security" >> ./.env; \
  	read -p "Enter SMTP username:" smtp_user; echo "SMTP_USER=$$smtp_user" >> ./.env; \
  	read -p "Enter SMTP password:" smtp_pass; echo "SMTP_PASS=$$smtp_pass" >> ./.env; \
  	read -p "Enter SMS login:" sms_login; echo "SMS_AUTH_LOGIN=$$sms_login" >> ./.env; \
  	read -p "Enter SMS password:" sms_pass; echo "SMS_AUTH_PASS=$$sms_pass" >> ./.env; \
	fi
	docker-compose build
	docker-compose up -d

indexes:
	docker exec crypto-api-php bash -c '/usr/local/bin/php /src/app/cli/cli.php index init'

test:
	docker exec crypto-api-php bash -c 'cd /src/tests; phpunit'

attach:
	docker exec -i -t ${c} /bin/bash

purge:
	docker stop $(CONTAINERS)
	docker rm $(CONTAINERS)
	docker volume rm $(VOLUMES)
