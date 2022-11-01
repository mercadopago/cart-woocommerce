#!/usr/bin/env bash

if [[ -f $1"/.git" || -d $1"/.git" ]]; then

	SUBMODULE_STATUS=$(git submodule summary "$1")
	STATUSRETVAL=$(echo "$SUBMODULE_STATUS" | grep -A20 -i "$1")

	# shellcheck disable=SC2237
	if ! [[ -z "$STATUSRETVAL" ]]; then
		echo -e "\033[31mChecked $1 submodule, ACTION REQUIRED:\033[0m"
		echo ""
		echo -e "Different commits:"
		echo -e "$SUBMODULE_STATUS"
		echo ""

		git submodule sync --recursive -- "$1"
		git submodule update --init --remote --recursive -- "$1" || true
		git submodule update --init --remote --recursive --force -- "$1"
	fi

else

	git submodule sync --recursive --quiet -- "$1"
	git submodule update --init --remote --recursive -- "$1" || true
	git submodule update --init --remote --recursive -- "$1"

fi
