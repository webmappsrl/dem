> Ticket: oc:8854

# Messa in sicurezza PHP2Shell (CVE-2026-109618/109619) su DEM (server DEM e DEM-2)

## Cosa cambia

Il container PHP-FPM di DEM passa da PHP 8.1.22 (immagine `php:8.1-fpm` del 2023, ramo fuori
supporto e senza patch) a **PHP 8.2.35** (`php:8.2.35-fpm`, prima versione corretta del ramo 8.2)
su **tutti e due i server**: DEM-2, la produzione a cui punta `dem.maphub.it`, e DEM, il server
precedente, che resta acceso. Il pool FPM viene ripulito: `session.upload_progress` spento e
limiti di upload ridotti da 1024M a 16M per file e 128M per richiesta. Sequenza del tag
`php2shell`: locale → DEM-2 → DEM, con snapshot ed evidenze prima della patch su ogni server.

## Perché

- PHP2Shell (pubblicata il 21/10/2026) consente l'esecuzione di comandi senza credenziali, con
  attacchi già in corso. Il ramo 8.1 è fuori supporto: non esiste un'immagine ufficiale corretta.
  Stesso caso di MPT (oc:8847) ed euma (oc:8845).
- Criterio comune a tutti i ticket del tag: la versione corretta più vicina che non richiede
  aggiornamenti del framework. Con Laravel 10.48 e Nova 4.33 del lock nessun pacchetto del
  `composer.lock` esclude PHP 8.2 (`composer why-not --locked php 8.2.35`: nessuno); la 8.3 è
  esclusa da `lcobucci/clock` 3.0.0. Sui server `vendor/` non corrisponde al lock (Laravel 10.39 e
  Nova 4.32.11 installati): anche i vincoli `php` di tutti i pacchetti installati ammettono 8.2.35.
- DEM resta acceso per decisione del dev (23/10): va patchato come DEM-2.
- Pool FPM: `session.upload_progress` è stato il vettore di una delle due CVE. DEM non riceve
  file: le API (`/api/v1/track`, `track3d`, `feature-collection/point-matrix`) ricevono tracce
  in JSON, e `storage/app` occupa 16K. 128M per richiesta è il default della procedura
  wm-package e lascia ampio margine alle tracce più lunghe.

## Requisiti

- [x] `docker/configs/phpfpm/Dockerfile` parte da `php:8.2.35-fpm` (pin esatto).
- [x] Il tag dell'immagine nel compose versionato (`docker-compose.yml.example`) diventa
      `wm-phpfpm-dem:8.2.35-fpm` (oggi `wm-phpfpm:8.1`, nome generico condiviso con altri progetti).
- [x] Pool FPM (`www.conf`): `php_admin_flag[session.upload_progress.enabled] = off`,
      `upload_max_filesize = 16M`, `post_max_size = 128M`.
- [x] CI (`.github/workflows/run-tests.yml`) su PHP 8.2.
- [x] In locale, prima sull'immagine 8.1 e poi sulla 8.2.35: build con `--pull`, `php -v` =
      8.2.35 nel container, profilo `php -m` confrontato (gd, intl, zip, pgsql, pdo_pgsql, exif,
      imagick, apcu, opcache), `php artisan test` con esito confrontato, `POST` di una traccia
      dei test (`tests/Feature/Stubs`) alle tre API con risposta JSON identica fra 8.1 e 8.2.35.
- [ ] Su ciascun server, prima DEM-2 e poi DEM, nell'ordine:
      1. controlli «prima»: `POST` della traccia di prova a `/api/v1/track` con risposta salvata,
         `php -m` salvato, codici di risposta delle API per client negli ultimi giorni;
      2. snapshot Hetzner nuovo;
      3. evidenze raccolte fuori dalla macchina e fuori dal repo;
      4. `git pull` su `develop` (il deploy automatico non arriva sui server: la CI fallisce
         da dicembre 2025 e il job di deploy viene saltato);
      5. immagine attuale taggata `wm-phpfpm-dem:pre-8854` (per ID, da `docker inspect`);
      6. riga `image:` del `docker-compose.yml` del server, rebuild con `--pull`, ricreazione del
         solo servizio `phpfpm`.
- [ ] Verifiche su ciascun server: `php -v` = 8.2.35 e `php-fpm -tt` con i nuovi valori dentro
      `php81_dem`; profilo `php -m` uguale a quello locale sulla 8.2.35; container ancora
      chiamato `php81_dem`; `postgres_dem` non ricreato; la traccia di prova dà la stessa
      risposta di prima; sui log, nelle ore successive, nessun client con più errori 500 di prima.
- [ ] Verifica di compromissione sulle evidenze, esito sintetico nel ticket. Dettagli sensibili
      (IP, log) solo nel ticket e in `LLM_MD`, mai in questo repo, che è pubblico.

## Rischi

- **Compose non versionato e diverso dall'esempio.** Sui due server `docker-compose.yml`
  differisce dall'esempio nei volumi di `db` (dati su `/mnt/dem-2-db` e `/mnt/volume-nbg1-1`).
  Mitigazione: sul server si cambia solo la riga `image:` del servizio `phpfpm`, mai si copia
  l'esempio; prima della ricreazione, `docker compose config` deve dare `php81_dem` e
  `postgres_dem`; sempre `up -d phpfpm`, mai `up -d` senza servizio (trappola di oc:8844).
- **Estensioni PECL.** imagick e apcu sono installate da pecl senza versione: sulla base 8.2 la
  build scarica versioni nuove, e la 8.2 non compila più `ftp`. Mitigazione: profilo `php -m`
  confrontato in locale e di nuovo sui server (locale arm64, server amd64).
- **Risposte delle API diverse sulla 8.2.** Il calcolo di quota e pendenza è numerico: un cambio
  di arrotondamento cambierebbe i dati delle tracce per i client. Mitigazione: la stessa traccia
  inviata prima e dopo, sia in locale sia in produzione, con `diff` delle risposte.
- **Errori 500 già presenti.** Un client riceve già oggi soprattutto errori 500 sulla 8.1. Senza
  una base di confronto, un errore preesistente verrebbe preso per una regressione, o il
  contrario. Mitigazione: conteggio dei codici per client prima della patch, confrontato con le
  ore successive.
- **Evidenze nel container.** La ricreazione azzera il filesystem del container. Mitigazione:
  prima del rebuild `ps aux`, `/tmp`, crontab e `docker logs` del container, più `docker commit`
  di `php81_dem`; i file PHP recenti si cercano dall'host. Tutto fuori dalla macchina e fuori dal repo.
- **Codice del vecchio DEM indietro di un commit.** DEM è su `6ebfa16`, la produzione su
  `d5cf91f`: il `git pull` porta anche `d5cf91f` (un file, `SlopeAndElevationTrait`, nessuna
  migrazione). DEM si allinea alla produzione.
- **Rollback.** Scala di ritorno, uguale per i due server:
  1. **immagine**: l'immagine attuale, taggata per ID come `wm-phpfpm-dem:pre-8854`, si rimette in
     uso cambiando la riga `image:` e ricreando il solo `phpfpm`. Il repo resta su 8.2.35: la 8.1
     è vulnerabile, il rollback è una pausa. Il tag si rimuove dopo qualche giorno di verifica;
  2. **snapshot**: serve come evidenza; mai ripristinato sull'intera macchina senza decisione di
     Giuseppe.

## Out of scope

- Sistema operativo dei server (Ubuntu 20.04, in ESM): censimento in oc:8835.
- Rotazione dei segreti: in follow-up, la fa Giuseppe per tutti i server.
- CI che fallisce da dicembre 2025 e deploy automatico che non parte.
- `APP_ENV=local` e `APP_URL=http://localhost` nel `.env` dei server.
- Pulizia dei vhost rotti su DEM e dei cron copiati da altri server.
- Errori 500 già presenti per uno dei client delle API.
- Restringere a `127.0.0.1` le porte dei container (oggi su 0.0.0.0, chiuse dall'esterno).
- Passaggio a PHP 8.3/8.4 (la 8.2 va in EOL il 31/12/2026).

## Moduli toccati

Tutti nel repo `dem` (nessun submodule):

- `docker/configs/phpfpm/Dockerfile`
- `docker/configs/phpfpm/www.conf`
- `docker-compose.yml.example`
- `.github/workflows/run-tests.yml`

Sui due server, fuori dal repo: `/var/www/html/dem/docker-compose.yml` (riga `image:`).
