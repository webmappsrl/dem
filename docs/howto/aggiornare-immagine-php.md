# Aggiornare l'immagine PHP (locale e server)

Il perché delle scelte è in
[docs/knowledge/8854-messa-in-sicurezza-php2shell-dem.md](../knowledge/8854-messa-in-sicurezza-php2shell-dem.md).

## Nel repo

1. Cambia il pin in `docker/configs/phpfpm/Dockerfile` (riga `FROM`) **e** il tag in
   `docker-compose.yml.example` (riga `image:` del servizio `phpfpm`), nello stesso commit.
   `container_name` non si tocca.
2. Copia la stessa riga `image:` nel tuo `docker-compose.yml` locale (non è versionato).
3. Prova in locale:

   ```bash
   docker compose build --pull phpfpm
   docker compose up -d phpfpm
   docker exec php81_dem php -v        # la versione attesa
   docker exec php81_dem php -m        # confronta con l'immagine precedente
   docker exec php81_dem php artisan test
   ```

   Per confrontare le risposte delle API con quote vere, dopo `dem:import` di uno stub riproietta
   il raster locale in 3035 (`DropRasterConstraints('dem','rast')`, poi
   `update dem set rast = ST_Transform(rast, 3035)`): gli stub sono in 4326.

## Su ciascun server (DEM-2, poi DEM)

Dalla cartella del repo sul server (`/var/www/html/dem`). Mai `docker compose up -d` senza nome
servizio: ricreerebbe anche `postgres_dem`.

1. Snapshot del server e raccolta delle evidenze se l'aggiornamento chiude una falla.
2. `git pull` sul branch del server e `git log -1` = commit atteso (il deploy automatico non
   arriva sui server).
3. Punto di ritorno, taggato per ID (l'immagine in uso può non essere quella che il compose
   nomina):

   ```bash
   docker tag $(docker inspect php81_dem --format '{{.Image}}') wm-phpfpm-dem:pre-<ticket>
   ```

4. `df -h /`: il build chiede alcuni GB.
5. Nel `docker-compose.yml` del server cambia **solo** la riga `image:` di `phpfpm` (i volumi di
   `db` sono diversi dall'esempio: non copiare l'esempio) e verifica:

   ```bash
   diff docker-compose.yml.example docker-compose.yml   # restano solo i volumi di db
   docker compose config | grep container_name          # php81_dem e postgres_dem
   ```

6. Build e ricreazione del solo servizio PHP:

   ```bash
   docker compose build --pull phpfpm
   docker compose up -d phpfpm
   ```

## Verifiche finali

```bash
docker exec php81_dem php -v                      # mai fidarsi dell'esito del build
docker exec php81_dem php -m                      # stesse estensioni della prova in locale
docker exec php81_dem php-fpm -tt 2>&1 | grep -E "upload|post_max"
```

Poi la stessa traccia di prova inviata a `/api/v1/track` prima e dopo, con `diff` delle risposte.

**Rollback:** nel `docker-compose.yml` del server metti `image: wm-phpfpm-dem:pre-<ticket>` e
lancia `docker compose up -d phpfpm`. Non ritaggare `pre-<ticket>` con il nome della versione
nuova. Lo snapshot non si ripristina sull'intera macchina.
