if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}

WP_TESTS_DIR="${WP_TESTS_DIR-/tmp/wordpress-tests-lib}/"
WP_CORE_DIR="${WP_CORE_DIR-/tmp/wordpress}/"

download() {
    if [ `which curl` ]; then
        curl -s "$1" > "$2";
    elif [ `which wget` ]; then
        wget -nv -O "$2" "$1"
    fi
}

if [[ $WP_VERSION =~ [0-9]+\.[0-9]+(\.[0-9]+)? ]]; then
	WP_TESTS_TAG="tags/$WP_VERSION"
else
	# http serves a single offer, whereas https serves multiple. we only want one
	download http://api.wordpress.org/core/version-check/1.7/ /tmp/wp-latest.json
	grep '[0-9]+\.[0-9]+(\.[0-9]+)?' /tmp/wp-latest.json
	WP_VERSION=$(grep -o '"version":"[^"]*' /tmp/wp-latest.json | sed 's/"version":"//')
	if [[ -z "$WP_VERSION" ]]; then
		echo "Latest WordPress version could not be found"
		exit 1
	fi
	WP_TESTS_TAG="tags/$WP_VERSION"
fi

echo $WP_VERSION

set -ex

# Footle around to remove any trailing slashes, and then add
# them back in again.
# We're going to install WordPress and the WordPress test lib
# to version specific directories, for greater control locally
shopt -s extglob;
WP_TESTS_DIR_ACTUAL="${WP_TESTS_DIR%%+(/)}-${WP_VERSION}/"
WP_CORE_DIR_ACTUAL="${WP_CORE_DIR%%+(/)}-${WP_VERSION}/"

install_wp() {

	if [ -d $WP_CORE_DIR_ACTUAL ]; then
		return;
	fi

	mkdir -p $WP_CORE_DIR_ACTUAL

	if [ $WP_VERSION == 'latest' ]; then
		local ARCHIVE_NAME='latest'
	else
		local ARCHIVE_NAME="wordpress-$WP_VERSION"
	fi

	download https://wordpress.org/${ARCHIVE_NAME}.tar.gz  /tmp/wordpress.tar.gz
	tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C $WP_CORE_DIR_ACTUAL

	download https://raw.github.com/markoheijnen/wp-mysqli/master/db.php $WP_CORE_DIR_ACTUAL/wp-content/db.php
}

install_test_suite() {
	# portable in-place argument for both GNU sed and Mac OSX sed
	if [[ $(uname -s) == 'Darwin' ]]; then
		local ioption='-i .bak'
	else
		local ioption='-i'
	fi

	# set up testing suite if it doesn't yet exist
	if [ ! -d $WP_TESTS_DIR_ACTUAL ]; then
		# set up testing suite
		mkdir -p $WP_TESTS_DIR_ACTUAL
		svn co --quiet --ignore-externals https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/ $WP_TESTS_DIR_ACTUAL/includes
		svn co --quiet --ignore-externals https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/ $WP_TESTS_DIR_ACTUAL/data
	fi

	cd $WP_TESTS_DIR_ACTUAL

	if [ ! -f wp-tests-config.php ]; then
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php "$WP_TESTS_DIR_ACTUAL"/wp-tests-config.php
		sed $ioption "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR':" "$WP_TESTS_DIR_ACTUAL"/wp-tests-config.php
		sed $ioption "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR_ACTUAL"/wp-tests-config.php
		sed $ioption "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR_ACTUAL"/wp-tests-config.php
		sed $ioption "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR_ACTUAL"/wp-tests-config.php
		sed $ioption "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR_ACTUAL"/wp-tests-config.php
	fi

}

install_wp
install_test_suite

# Create a symbolic link to the version specific directories
# from a generic location
rm -rf "${WP_CORE_DIR%%+(/)}"
rm -rf "${WP_TESTS_DIR%%+(/)}"
ln -s "${WP_CORE_DIR_ACTUAL%%+(/)}" "${WP_CORE_DIR%%+(/)}"
ln -s "${WP_TESTS_DIR_ACTUAL%%+(/)}" "${WP_TESTS_DIR%%+(/)}"

