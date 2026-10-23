# Immagine PHP-FPM di DEM

## Come funziona oggi

- Versione PHP e pool FPM sono in `docker/configs/phpfpm/` (Dockerfile e `www.conf`); le
  estensioni pecl (imagick, apcu) non hanno versione e cambiano a ogni build.
- DEM gira su due server, DEM-2 (produzione, `dem.maphub.it`) e DEM (server precedente, acceso):
  ogni cambio d'immagine va fatto su tutti e due.
- Il deploy automatico non arriva sui server (la CI fallisce, il job di deploy viene saltato) e
  comunque non ricostruirebbe l'immagine: `git pull`, build e ricreazione sono manuali
  ([docs/howto/aggiornare-immagine-php.md](../howto/aggiornare-immagine-php.md)).
- Sui server `vendor/` non corrisponde al `composer.lock` (Laravel 10.39 installato, 10.48 nel
  lock): la compatibilità di una versione di PHP va verificata sui pacchetti installati
  (`vendor/composer/installed.json`), non solo sul lock.
- Il `docker-compose.yml` dei server differisce dall'esempio nei volumi di `db` (dati su `/mnt`).
- `php artisan serve` in locale non passa dal pool FPM: i limiti di `www.conf` si provano solo
  dalla porta FPM.
- Gli stub raster dei test sono in EPSG:4326, mentre `SlopeAndElevationTrait` interroga in
  EPSG:3035: con gli stub così come sono ogni quota esce `null`.

## Perché così

- **PHP 8.2.35 e non 8.3/8.4** (oc:8854): il ramo 8.1 è fuori supporto e senza patch per
  PHP2Shell; né il lock né i pacchetti installati sui server escludono la 8.2, mentre
  `lcobucci/clock` 3.0.0 del lock esclude la 8.3. La 8.2 va in EOL il 31/12/2026.
- **16M per file, 128M per richiesta** (oc:8854): DEM non riceve file, le API ricevono tracce in
  JSON; 128M è il default della procedura wm-package e lascia margine alle tracce più lunghe.
- **`session.upload_progress` off** (oc:8854): vettore di una delle due CVE di PHP2Shell, non
  usato dall'app.
- **Pin esatto della base** (oc:8854): con un tag mobile un rebuild senza `--pull` riusa la base
  in cache e produce di nuovo l'immagine vulnerabile.
- **Tag `wm-phpfpm-dem`** (oc:8854): `wm-phpfpm:8.1` era un nome generico, usato anche da altri
  progetti sugli host Webmapp.

## Come ci siamo arrivati

- **`php:8.1-fpm`, tag mobile, limiti a 1024M** (fino a oc:8854, superato): immagine del 2023 mai
  ricostruita, ramo senza patch dopo il 31/12/2025.
