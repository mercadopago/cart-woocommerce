.PHONY: build

build:
	./bin/create-release-zip.sh

watch:
	npm run watch:release

release:
	./bin/setup-release.sh
