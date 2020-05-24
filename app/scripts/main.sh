set -o allexport;
source .env;
set +o allexport;

php app/scripts/backupDBForDuelLinks.php;
php app/scripts/backupDBForMovies.php;