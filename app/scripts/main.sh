if [ -f ".env" ]; then
  dos2unix .env;
  export $(xargs < .env)
fi

php app/scripts/backupDBForDuelLinks.php;
php app/scripts/backupDBForMovies.php;
php app/scripts/backupDBForCric.php;
php app/scripts/backupLogs.php;