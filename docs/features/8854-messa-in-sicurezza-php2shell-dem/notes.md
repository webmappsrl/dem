> Ticket: oc:8854

# Notes — Messa in sicurezza PHP2Shell su DEM

## Divergenze dal piano, task per task

### Task 2 prova in locale 81 poi 8235

- **`vendor` di produzione al posto del lock.** `composer install` falliva: `nova.laravel.com`
  rispondeva 503. Il `vendor` dei server però non corrisponde al `composer.lock`: sul server ci
  sono Laravel 10.39 e Nova 4.32.11, il lock dice 10.48.25 e 4.33.3 (118 pacchetti su 148
  diversi). Dopo la patch i server girano con il loro `vendor`, non con il lock: la prova in
  locale usa quindi una copia del `vendor` di DEM-2. Tutti i vincoli `php` dei pacchetti
  installati ammettono 8.2.35.
- **Base locale 8.1.34, non 8.1.22.** L'immagine base è costruita dal Dockerfile di `develop`
  (`php:8.1-fpm`, tag mobile), che oggi scarica la 8.1.34. Come termine di confronto con la
  8.2.35 è equivalente.
- **Raster riproiettato in 3035, solo in locale.** Gli stub dei test sono in EPSG:4326, mentre
  `SlopeAndElevationTrait` interroga il raster in EPSG:3035: con gli stub così come sono, ogni
  quota esce `null` (8.1 e 8.2 allo stesso modo). Per confrontare davvero il calcolo, dopo
  l'import dello stub di Monte Pisano il raster locale è stato riproiettato
  (`DropRasterConstraints` + `ST_Transform(rast, 3035)`).
- **Limiti del pool provati con un nginx temporaneo** davanti a `php81_dem:9000`, poi fermato:
  `artisan serve` non passa dal pool FPM.

Esiti (dettagli in `LLM_MD`, fuori dal repo):

- `php -m`: unica differenza `ftp` (non più compilata nella 8.2) e `random` (nuova nella 8.2).
  L'app non usa `ftp`.
- `php-fpm -tt` sulla 8.2.35: `upload_max_filesize = 16M`, `post_max_size = 128M`,
  `session.upload_progress.enabled = 0`.
- `php artisan test`: 13 passati, 11 falliti, 6 risky su tutte e due le versioni, con gli stessi
  esiti test per test. I fallimenti sono preesistenti (vedi Bug trovati).
- `POST` della traccia `track_MontePisano_109_2D.geojson` a `/api/v1/track` e `/api/v1/track3d`,
  e del payload di `ApiTrackMatrixTest` a `/api/v1/feature-collection/point-matrix`: risposte
  identiche byte per byte fra 8.1 e 8.2.35, con quote vere (`ele_max` 353, `ascent` 344).
- Via FPM: traccia → 200, risposta uguale a quella di `artisan serve`; `POST` da 140MB → 413
  (`PostTooLargeException`); da 100MB passa il limite e arriva all'app.

## Bug trovati

- **`vendor` dei server diverso dal lock.** Il deploy automatico non arriva sui server (la CI
  fallisce da dicembre 2025 e il job di deploy viene saltato) e `composer install` non è stato
  rilanciato dopo l'ultimo aggiornamento del lock.
- **Test delle quote rotti.** Gli stub sono in 4326 e il codice interroga in 3035: 11 test
  falliscono su qualunque versione di PHP. È anche la causa della CI rossa.

## Decisioni

- DEM (server precedente) resta acceso e si patcha come DEM-2 (dev, 23/10).
- Stima, tag e stato del ticket saltati su richiesta del dev (23/10). Saltate anche la challenge
  (rischi presi dalle trappole già verificate su oc:8844, oc:8845, oc:8847) e la review formale
  `wm-review-ticket`.
- Il repo non aveva un `CLAUDE.md`: creato con indice della conoscenza e trappole (oc:8854).

## Follow-up

- Allineare il `vendor` dei server al lock, o il lock al `vendor`: oggi un `composer install`
  sul server cambierebbe 118 pacchetti in un colpo.
- Rigenerare gli stub dei test in 3035, o aggiornare i test: la CI tornerebbe verde e il deploy
  automatico ripartirebbe.
- PHP 8.2 in EOL il 31/12/2026: la 8.3 è esclusa da `lcobucci/clock` 3.0.0 del lock.
- Rinominare il container (`php81_dem` esegue PHP 8.2).
