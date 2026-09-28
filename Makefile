# Release youniwemi/digital-invoice to Packagist.
#
#   make release                 # bump patch of latest tag (v0.3.2 -> v0.3.3)
#   make release VERSION=v0.4.0  # explicit version
#
# Packagist picks up new tags through the GitHub hook. If PACKAGIST_USERNAME
# and PACKAGIST_TOKEN are set, the package update is also triggered via API.

PACKAGE  := youniwemi/digital-invoice
BRANCH   := master
LATEST   := $(shell git describe --tags --abbrev=0 2>/dev/null)
VERSION  ?= $(shell echo "$(LATEST)" | awk -F. -v OFS=. '{$$NF += 1; print}')

.PHONY: help test check release packagist

help:
	@echo "make test                    Validate composer.json and run the test suite"
	@echo "make release [VERSION=vX.Y.Z] Test, tag and push (latest: $(LATEST), next: $(VERSION))"

test:
	composer validate
	composer run-script test
	@# The Malaysian UBL test rewrites its fixture with today's date.
	git checkout -- test/examples/malaysian-ubl-invoice.xml

check:
	@test "$$(git rev-parse --abbrev-ref HEAD)" = "$(BRANCH)" || { echo "Not on $(BRANCH)"; exit 1; }
	@test -z "$$(git status --porcelain)" || { echo "Working tree not clean"; git status --short; exit 1; }
	@echo "$(VERSION)" | grep -Eq '^v[0-9]+\.[0-9]+\.[0-9]+$$' || { echo "Invalid VERSION '$(VERSION)'"; exit 1; }
	@! git rev-parse -q --verify "refs/tags/$(VERSION)" >/dev/null || { echo "Tag $(VERSION) already exists"; exit 1; }
	git fetch origin $(BRANCH)
	@test -z "$$(git log HEAD..origin/$(BRANCH) --oneline)" || { echo "origin/$(BRANCH) has commits you don't have"; exit 1; }

release: check test
	@read -p "Release $(VERSION) (previous $(LATEST))? [y/N] " ok && [ "$$ok" = y ]
	git tag -a $(VERSION) -m "Release $(VERSION)"
	git push origin $(BRANCH)
	git push origin $(VERSION)
	@$(MAKE) --no-print-directory packagist

packagist:
	@if [ -n "$$PACKAGIST_USERNAME" ] && [ -n "$$PACKAGIST_TOKEN" ]; then \
		curl -sf -X POST -H 'Content-Type: application/json' \
			"https://packagist.org/api/update-package?username=$$PACKAGIST_USERNAME&apiToken=$$PACKAGIST_TOKEN" \
			-d '{"repository":{"url":"https://github.com/$(PACKAGE)"}}' && echo; \
	else \
		echo "Pushed. Packagist updates via the GitHub hook (set PACKAGIST_USERNAME/PACKAGIST_TOKEN to force)."; \
	fi
	@echo "https://packagist.org/packages/$(PACKAGE)"
