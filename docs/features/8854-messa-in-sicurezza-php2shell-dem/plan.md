> Ticket: oc:8854

# Messa in sicurezza PHP2Shell su DEM — piano di implementazione

> **Per chi esegue:** sotto-skill `superpowers:executing-plans`. I passi usano le checkbox
> (`- [ ]`). **Nessun commit, push, merge o branch senza conferma esplicita del dev**: i comandi
> git qui sotto sono istruzioni da proporre, non da eseguire in autonomia.

**Obiettivo:** portare il container PHP-FPM di DEM da PHP 8.1.22 a `php:8.2.35-fpm`, con il pool
FPM ripulito, prima in locale e poi sui due server (DEM-2, poi DEM).

**Approccio:** quattro file di configurazione nel repo; prova in locale con i dati dei test,
prima sull'immagine 8.1 e poi sulla 8.2.35, per confrontare; sui server la sequenza del tag
`php2shell` (controlli «prima» → snapshot → evidenze → pull → patch → verifiche → verifica di
compromissione), un server alla volta.

**Stack:** Laravel 10.48, Nova 4.33, PHP-FPM in Docker (compose v2), Postgres/PostGIS 14, Apache
dell'host come proxy (`proxy:fcgi://localhost:9198`).

**Spec:** [overview.md](overview.md)

## Vincoli globali

- Target: `php:8.2.35-fpm` (pin esatto); tag immagine `wm-phpfpm-dem:8.2.35-fpm`.
- Pool FPM: `session.upload_progress` off, `upload_max_filesize = 16M`, `post_max_size = 128M`.
- Sui server: **mai** `docker compose up -d` senza nome servizio; build sempre con `--pull`;
  sul `docker-compose.yml` del server si cambia solo la riga `image:` di `phpfpm`.
- Dettagli sensibili (evidenze, IP, esiti) **mai nel repo** (pubblico): vanno
  nella cartella `evidenze/` del cantiere privato (fuori da questo repo) e nel ticket.
- Nessuna scrittura su Orchestrator in questa fase.
- Verifica della versione sempre con `php -v` dentro il container, mai l'esito del build.

## Punti da tenere d'occhio in review

1. Ricreazione di `postgres_dem` o perdita dei volumi su `/mnt` → DB fermo (Task 4, passo 6).
2. Risposte delle API diverse fra 8.1 e 8.2.35 → quote e pendenze cambiate per i client (Task 2, Task 4).
3. Estensioni diverse dopo il build (pecl imagick/apcu) → `php -m` confrontato (Task 2, Task 4).
4. Patch prima di snapshot ed evidenze → evidenze perse (Task 4, ordine).

---

### Task 1: Modifiche nel repo

**File** (repo `dem`, branch `feature/oc-8854-messa-in-sicurezza-php2shell-dem` da `develop`):
- Modifica: `docker/configs/phpfpm/Dockerfile:1`
- Modifica: `docker/configs/phpfpm/www.conf:542-543` (+ una riga)
- Modifica: `docker-compose.yml.example:11`
- Modifica: `.github/workflows/run-tests.yml:22`

- [x] **Passo 1: branch.** Proporre al dev `git checkout -b feature/oc-8854-messa-in-sicurezza-php2shell-dem`
  da `develop`; eseguire solo dopo il suo sì.
- [x] **Passo 2:** Dockerfile → `FROM php:8.2.35-fpm`.
- [x] **Passo 3:** `www.conf` → `php_admin_value[upload_max_filesize] = 16M`,
  `php_admin_value[post_max_size] = 128M`, e nuova riga
  `php_admin_flag[session.upload_progress.enabled] = off` accanto agli altri `php_admin_*`.
- [x] **Passo 4:** `docker-compose.yml.example` → `image: wm-phpfpm-dem:8.2.35-fpm`.
- [x] **Passo 5:** `run-tests.yml` → `php-version: "8.2"`.
- [x] **Passo 6: verifica.** `git diff --stat` → 4 file;
  `grep -n "8.2.35\|upload_progress\|128M\|16M" docker/configs/phpfpm/Dockerfile docker/configs/phpfpm/www.conf docker-compose.yml.example`
  → una riga per ciascun valore.

### Task 2: Prova in locale (8.1 poi 8.2.35)

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-2-prova-in-locale-81-poi-8235)

**Interfaces:** produce `evidenze/locale-8.1/` e `evidenze/locale-8.2/` (`php -m`, `php-fpm -tt`,
esito test, risposte delle API) che il Task 4 riusa come riferimento.

- [x] **Passo 1: ambiente.** `.env` locale da `.env-example` (`APP_NAME=dem`), `docker-compose.yml`
  dall'esempio con `image: wm-phpfpm-dem:base-8.1`; immagine base costruita dal Dockerfile di
  `develop` (`git show develop:docker/configs/phpfpm/Dockerfile` in una cartella di build
  temporanea, con `www.conf` e gli altri file della cartella presi anch'essi da `develop`); `docker compose up -d`; `composer install`, `php artisan key:generate`,
  `php artisan migrate`. Database di test separato (`dem_testing`, con `postgis`) se i test usano
  `RefreshDatabase`.
- [x] **Passo 2: base 8.1.** `php -v`, `php -m`, `php-fpm -tt` → `evidenze/locale-8.1/`;
  `php artisan test` → esito salvato; caricamento di uno stub raster (`tests/Feature/Stubs/montepisano_25x25_4326.sql`)
  e `POST` di `track_MontePisano_109_2D.geojson` a `/api/v1/track`, `/api/v1/track3d`,
  `/api/v1/feature-collection/point-matrix` → risposte salvate.
- [x] **Passo 3: 8.2.35.** Dockerfile su `php:8.2.35-fpm`, `image: wm-phpfpm-dem:8.2.35-fpm`;
  `docker compose build --pull phpfpm`, `docker compose up -d phpfpm`; `php -v` → `PHP 8.2.35`.
  Se un passo del build fallisce, correggere solo quella riga e annotarlo in `notes.md`.
- [x] **Passo 4:** `php -m` → `diff` con la base: differenze attese solo `ftp` (tolta dalla 8.2) e
  `random` (nuova); ogni altra va spiegata. `php-fpm -tt` → `off`, `16M`, `128M`.
- [x] **Passo 5:** test e tre `POST` ripetuti → `diff` delle risposte con la base: vuoto.
  I limiti del pool si provano passando da FPM (container nginx temporaneo davanti a `:9000`,
  come in oc:8847), non da `artisan serve`.

### Task 3: Pull request su `develop`

- [ ] **Passo 1: commit** (solo dopo «Faccio il commit?» e un sì esplicito):
  ```bash
  git add docker/configs/phpfpm/Dockerfile docker/configs/phpfpm/www.conf docker-compose.yml.example .github/workflows/run-tests.yml CLAUDE.md docs/
  git commit -m "fix(oc:8854): PHP 8.2.35 e pool FPM ripulito (PHP2Shell)"
  ```
- [ ] **Passo 2:** push e PR verso `develop`; la CI fallisce già oggi (problema preesistente,
  fuori scope): annotare l'esito, non è un blocco.
- [ ] **Passo 3:** merge su `develop` dopo la review del dev.

### Task 4: Server — prima DEM-2, poi DEM

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-4-server-prima-dem-poi-dem-2)

Stessi passi su ciascun server (`ssh dem2`, poi `ssh dem`), in `/var/www/html/dem`. Si passa a
DEM solo con DEM-2 verificato.

- [ ] **Passo 1: controlli «prima».** `POST` della traccia di prova a `https://dem.maphub.it/api/v1/track`
  (su DEM, all'IP con `Host: dem.maphub.it`) → risposta salvata; `docker exec php81_dem php -m`
  salvato; codici di risposta delle API per client dal 19/10 → `evidenze/<server>/prima/`.
- [ ] **Passo 2: snapshot Hetzner** del server, dal pannello, insieme al dev via Chrome.
  Attendere lo stato completato.
- [ ] **Passo 3: evidenze** (sola lettura, cartella temporanea fuori dal repo, poi `scp` in
  `evidenze/<server>/` locale e rimozione dal server):
  `docker exec php81_dem ps aux`; `ls -la /tmp /var/tmp` nel container;
  `crontab -l -u www-data` nel container; `docker logs --since 2026-10-19 php81_dem`;
  log Apache di `dem.maphub.it` dal 19/10 (con le rotazioni); dall'host
  `find app public routes config -name "*.php" -newermt 2026-10-19`;
  `docker commit php81_dem dem-evidenze-8854`. `sha256sum` dei file copiati.
- [ ] **Passo 4: codice.** `git pull` su `develop` → `git log -1 --oneline` = merge del Task 3;
  `git status` → solo `storage/backups` non tracciato.
- [ ] **Passo 5: punto di ritorno e disco.**
  `docker inspect php81_dem --format '{{.Image}}'` → `docker tag <ID> wm-phpfpm-dem:pre-8854`;
  `df -h /` → almeno 4GB liberi.
- [ ] **Passo 6: compose del server.** Solo la riga `image:` di `phpfpm` →
  `wm-phpfpm-dem:8.2.35-fpm`; `diff docker-compose.yml.example docker-compose.yml` → restano solo
  le righe dei volumi di `db`; `docker compose config | grep container_name` → `php81_dem` e
  `postgres_dem`. Se compare altro, fermarsi.
- [ ] **Passo 7: patch.** `docker compose build --pull phpfpm`, poi `docker compose up -d phpfpm`.
- [ ] **Passo 8: verifiche.**
  - `docker ps` → `php81_dem` su `wm-phpfpm-dem:8.2.35-fpm`, `postgres_dem` con uptime invariato;
  - `php -v` → `PHP 8.2.35`; `php -m` → uguale al locale 8.2.35;
  - `php-fpm -tt` → `upload_progress` off, `16M`, `128M`;
  - `POST` della traccia di prova → `diff` col passo 1 vuoto;
  - dopo qualche ora: codici di risposta per client confrontati col passo 1.
  Se un controllo fallisce: rollback dell'immagine (riga `image:` → `wm-phpfpm-dem:pre-8854`,
  `docker compose up -d phpfpm`). Mai ripristinare lo snapshot sull'intera macchina.

### Task 5: Verifica di compromissione e chiusura

- [ ] **Passo 1:** analisi delle evidenze di ciascun server: pattern PHP2Shell nei log
  (`auto_prepend_file`, `allow_url_include`, `php://input`) e codici di risposta; processi, file
  in `/tmp`, crontab e file PHP recenti anomali. Esito in `evidenze/esito.md` (fuori dal repo).
- [ ] **Passo 2:** se emerge qualcosa: dettagli solo nel ticket, il dev avvisa Giuseppe, ticket in
  standby. Se pulito: esito sintetico per il ticket.
- [ ] **Passo 3 (dopo qualche giorno di verifica):** `docker rmi wm-phpfpm-dem:pre-8854 dem-evidenze-8854`
  su ciascun server, dopo aver copiato fuori ciò che serve.
