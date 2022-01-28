.PHONY: init-cypress test clean

all:
	$(MAKE) init-cypress
	$(MAKE) test

init-cypress:
	npm run env:start
	npm run build
	npm run cypress:setup

test:
	npx browserslist@latest --update-db
	npm run cypress:open

clean:
	npm run env:clean
