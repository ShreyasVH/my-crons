set -o allexport;
source .env;
set +o allexport;

php app/scripts/backupDBForDuelLinks.php;