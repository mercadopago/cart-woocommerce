.PHONY: build

build:
	./bin/create-release-zip.sh

release:
	./bin/setup-release.sh

sync:
	./bin/sync-sdk.sh $(tag)
