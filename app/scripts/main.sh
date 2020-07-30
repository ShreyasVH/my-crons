if [ -f ".env" ]; then
  dos2unix .env;
  export $(xargs < .env)
fi

php app/scripts/backupDBForDuelLinks.php;
php app/scripts/backupDBForMovies.php;