@echo off
cd /d "%~dp0"
rem Laravel/Composer doit pouvoir ecrire son manifeste de paquets avant le demarrage.
if not exist bootstrap\cache mkdir bootstrap\cache
if not exist storage\framework\cache\data mkdir storage\framework\cache\data
if not exist storage\framework\sessions mkdir storage\framework\sessions
if not exist storage\framework\views mkdir storage\framework\views
if not exist storage\framework\testing mkdir storage\framework\testing
if not exist storage\logs mkdir storage\logs
if not exist storage\app\private\documents mkdir storage\app\private\documents
if not exist storage\app\private\document-previews mkdir storage\app\private\document-previews
if not exist vendor\autoload.php (
  echo Installation des dependances Laravel...
  composer install --no-interaction
  if errorlevel 1 (
    echo Echec de l'installation Composer.
    pause
    exit /b 1
  )
)
if not exist .env copy .env.example .env >nul
findstr /c:"APP_KEY=base64:" .env >nul
if errorlevel 1 php artisan key:generate --force
php artisan config:clear
php artisan migrate --force
if errorlevel 1 (
  echo La mise a jour necessaire de la base a echoue. Verifiez MariaDB et les identifiants de .env.
  pause
  exit /b 1
)
start "IT-Docs" http://127.0.0.1:8010
php -d upload_max_filesize=16M -d post_max_size=128M -d max_file_uploads=100 artisan serve --host=127.0.0.1 --port=8010
