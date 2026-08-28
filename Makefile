# Statické weby krajských komisí FO

.PHONY: dev dev-kvary build check

# lokální náhled; adresy jsou shodné s produkcí
dev:
	FO_SITE=praha php -S localhost:8000 build/router.php

dev-kvary:
	FO_SITE=kvary php -S localhost:8001 build/router.php

# sestavení obou webů do dist/praha a dist/kvary
build:
	php build/build.php

# kontrola obsahu (běží i v CI)
check:
	php tools/kontrola.php
