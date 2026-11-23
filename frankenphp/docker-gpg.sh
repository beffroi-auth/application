#!/bin/sh

# gpg, the one program the provider starts (decision 0051), found before Debian's on the PATH of the image and given
# none of the environment of the process that starts it: the variables of the provider, its secrets among them, stay
# there. A run is handed its GNUPGHOME and nothing else.
exec /usr/bin/env -i PATH=/usr/bin:/bin ${GNUPGHOME:+"GNUPGHOME=${GNUPGHOME}"} /usr/bin/gpg "$@"
