#!/bin/bash
# Purgne z Cloudflare cache URL souborů změněných tímto nasazením.
#
#   build/purge.sh <web> <základní-url>
#
# Manifest hashů z minulého nasazení drží actions/cache v temp/manifest-<web>.txt.
# Na rozdíl od ../fo se BEZ manifestu nepurguje celá zóna: oba weby jsou
# v zóně fyzikalniolympiada.cz spolu s hlavním webem, takže purge_everything
# odsud by shodil cache i jemu. Místo toho se purgne vše z nového manifestu.
set -eu

WEB_SLUG="${1:?chybí slug webu}"
WEB="${2:?chybí základní URL}"
DIST="dist/$WEB_SLUG"
MANIFEST="temp/manifest-$WEB_SLUG.txt"

if [ -z "${CLOUDFLARE_ZONE_ID:-}" ]; then
	echo "CLOUDFLARE_ZONE_ID není nastaveno, purge se přeskakuje"
	exit 0
fi

NOVY=$(mktemp)
(cd "$DIST" && find . -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum) > "$NOVY"

api() {
	curl -sS -X POST "https://api.cloudflare.com/client/v4/zones/$CLOUDFLARE_ZONE_ID/purge_cache" \
		-H "Authorization: Bearer $CLOUDFLARE_API_TOKEN" \
		-H "Content-Type: application/json" \
		--data "$1" | jq -e '.success' > /dev/null
}

# Cesty ke zneplatnění: buď rozdíl proti minulému manifestu, nebo (chybí-li)
# všechno z toho nového.
CESTY=$(mktemp)
if [ -f "$MANIFEST" ]; then
	diff "$MANIFEST" "$NOVY" | sed -n 's/^[<>] [0-9a-f]*  \.//p' | sort -u > "$CESTY"
else
	echo "manifest z minulého nasazení chybí: purgne se celý $WEB_SLUG"
	sed -n 's/^[0-9a-f]*  \.//p' "$NOVY" | sort -u > "$CESTY"
fi

# U .html i URL bez přípony — Pages je servírují na obou adresách.
URLS=$(mktemp)
while read -r CESTA; do
	[ -n "$CESTA" ] || continue
	echo "$WEB$CESTA"
	case "$CESTA" in
		/index.html) echo "$WEB/" ;;
		*.html) echo "$WEB${CESTA%.html}" ;;
	esac
done < "$CESTY" | sort -u > "$URLS"

echo "$WEB_SLUG: URL k purgnutí: $(wc -l < "$URLS")"
if [ -s "$URLS" ]; then
	split -l 30 "$URLS" "$URLS-batch-"
	for DAVKA in "$URLS-batch-"*; do
		api "$(jq -R . "$DAVKA" | jq -cs '{files: .}')"
	done
fi

mkdir -p temp
mv "$NOVY" "$MANIFEST"
echo "$WEB_SLUG: hotovo"
