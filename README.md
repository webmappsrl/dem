# dem.webmapp.it


# Laravel Postgis Boilerplate

Webmapp's Starting point

## Laravel 10 Project based on Nova 4

Boilerplate per Laravel 10 basato su php 8.1 e posgres + postgis. Supporto locale per web server php ed xdebug.

## INSTALL

First of all install the [GEOBOX](https://github.com/webmappsrl/geobox) repo and configure the [ALIASES command](https://github.com/webmappsrl/geobox#aliases-and-global-shell-variable).
Replace `${instance name}` with the instance name (APP_NAME in .env file)

```sh
git clone git@github.com:webmappsrl/${repository_name}.git ${instance name}
git flow init
```

Important NOTE: remember to checkout the develop branch.

```sh
chown -R 33 ${instance name}
cd ${instance name}
bash docker/init-docker.sh
```

*Important NOTE*: if you have installed XDEBUG you need to create the xdebug.log file on the docker:

```bash
docker exec -u 0 -it php81_${instance name} bash
touch /var/log/xdebug.log
chown -R 33 /var/log/
```

At the end run install command to for this instance
```bash
geobox_install ${instance name}
```
Make sure that postgis_raster extention is created
````
docker exec -i postgres_dem psql -c "CREATE EXTENSION postgis_raster CASCADE;" -U dem
````
Run this command from out side the docker to import DEM of monte pisano
````
php artisan dem:import-monte-pisano-dem 
```
*Important NOTE*: 
- Update your local repository of Geobox following its [Aliases instructions](https://github.com/webmappsrl/geobox#aliases-and-global-shell-variable). Make sure that you have set the environment variable GEOBOX_PATH correctly.
- Make sure that the version of wm-package of your instance is at leaset 1.1. Use command:
```bash
composer update wm/wp-package
```

Finally to import a fresh copy of database use Geobox restore command:

```bash
geobox_dump_restore ${instance name}
```

## Run web server from shell outside docker

In order to start a web server in local environment use the following command:
Replace `${instance name}` with the instance name (APP_NAME in .env file)

```sh
geobox_serve ${instance name}
```

### Differenze ambiente produzione locale

Questo sistema di container docker è utilizzabile sia per lo sviluppo locale sia per un sistema in produzione. In locale abbiamo queste caratteristiche:

-   la possibilità di lanciare il processo processo `php artisan serve` all'interno del container phpfpm, quindi la configurazione della porta `DOCKER_SERVE_PORT` (default: `8000`) necessaria al progetto. Se servono più istanze laravel con processo artisan serve contemporaneamente in locale, valutare di dedicare una porta tcp dedicata ad ognuno di essi. Per fare questo basta solo aggiornare `DOCKER_SERVE_PORT`.
-   la presenza di xdebug, definito in fase di build dell'immagine durante l'esecuzione del comando
-   `APP_ENV=local`, `APP_DEBUG=true` e `LOG_LEVEL=debug` che istruiscono laravel su una serie di comportamenti per il debug e l'esecuzione locale dell'applicativo
-   Una password del db con complessità minore. **In produzione usare [password complesse](https://www.avast.com/random-password-generator#pc)**

### Inizializzazione tramite boilerplate

-   Download del codice del boilerplate in una nuova cartella `nuovoprogetto` e disattivare il collegamento tra locale/remote:
    ```sh
    git clone https://github.com/webmappsrl/laravel-postgis-boilerplate.git nuovoprogetto
    cd nuovoprogetto
    git remote remove origin
    ```
-   Effettuare il link tra la repository locale e quella remota (repository vuota github)

    ```sh
    git remote add origin git@github.com:username/repo.git
    ```

-   Copy file `.env-example` to `.env`

    Questi valori nel file .env sono necessari per avviare l'ambiente docker. Hanno un valore di default e delle convenzioni associate, valutare la modifica:

    -   `APP_NAME` (it's php container name and - postgrest container name, no space)
    -   `DOCKER_PHP_PORT` (Incrementing starting from 9100 to 9199 range for MAC check with command "lsof -iTCP -sTCP:LISTEN")
    -   `DOCKER_SERVE_PORT` (always 8000, only on local environment)
    -   `DOCKER_PROJECT_DIR_NAME` (it's the folder name of the project)
    -   `DB_DATABASE`
    -   `DB_USERNAME`
    -   `DB_PASSWORD`

    Se siamo in produzione, rimuovere (o commentare) la riga:

    ```yml
    - ${DOCKER_SERVE_PORT}:8000
    ```

    dal file `docker-compose.yml`

-   Creare l'ambiente docker
    ```sh
    bash docker/init-docker.sh
    ```
-   Digitare `y` durante l'esecuzione dello script per l'installazione di xdebug

-   Verificare che i container si siano avviati

    ```sh
    docker ps
    ```

-   Avvio di una bash all'interno del container php per installare tutte le dipendenze e lanciare il comando php artisan serve (utilizzare `APP_NAME` al posto di `$nomeApp`):

    ```sh
    docker exec -it php81_$nomeApp bash
    composer install
    php artisan key:generate
    php artisan optimize
    php artisan migrate
    php artisan serve --host 0.0.0.0
    ```

-   A questo punto l'applicativo è in ascolto su <http://127.0.0.1:8000> (la porta è quella definita in `DOCKER_SERVE_PORT`)

### Configurazione xdebug vscode (solo in locale)

Assicurarsi di aver installato l'estensione [PHP Debug](https://marketplace.visualstudio.com/items?itemName=xdebug.php-debug).

Una volta avviato il container con xdebug configurare il file `.vscode/launch.json`, in particolare il `pathMappings` tenendo presente che **sulla sinistra abbiamo la path dove risiede il progetto all'interno del container**, `${workspaceRoot}` invece rappresenta la pah sul sistema host. Eg:

```json
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Listen for Xdebug",
            "type": "php",
            "request": "launch",
            "port": 9200,
            "pathMappings": {
                "/var/www/html/geomixer2": "${workspaceRoot}"
            }
        }
    ]
}
```

Aggiornare `/var/www/html/geomixer2` con la path della cartella del progetto nel container phpfpm.

Per utilizzare xdebug **su browser** utilizzare uno di questi 2 metodi:

-   Installare estensione xdebug per browser [Xdebug helper](https://chrome.google.com/webstore/detail/xdebug-helper/eadndfjplgieldjbigjakmdgkmoaaaoc)
-   Utilizzare il query param `XDEBUG_SESSION_START=1` nella url che si vuole debuggare
-   Altro, [vedi documentazione xdebug](https://xdebug.org/docs/step_debug#web-application)

Invece **su cli** digitare questo prima di invocare il comando php da debuggare:

```bash
export XDEBUG_SESSION=1
```

### Scripts

Ci sono vari scripts per il deploy nella cartella `scripts`. Per lanciarli basta lanciare una bash con la path dello script dentro il container php, eg (utilizzare `APP_NAME` al posto di `$nomeApp`):

```bash
docker exec -it php81_$nomeApp bash scripts/deploy_dev.sh
```

### Artisan commands

-   `db:dump_db`
    Create a new sql file exporting all the current database in the local disk under the `database` directory
-   `db:download`
    download a dump.sql from server
-   `db:restore`
    Restore a last-dump.sql file (must be in root dir)

### Problemi noti

Durante l'esecuzione degli script potrebbero verificarsi problemi di scrittura su certe cartelle, questo perchè di default l'utente dentro il container è `www-data (id:33)` quando invece nel sistema host l'utente ha id `1000`:

-   Chown/chmod della cartella dove si intende scrivere, eg:

    NOTA: per eseguire il comando chown potrebbe essere necessario avere i privilegi di root. In questo caso si deve effettuare l'accesso al cointainer del docker utilizzando lo specifico utente root (-u 0). Questo è valido anche sbloccare la possibilità di scrivere nella cartella /var/log per il funzionamento di Xdedug

    Utilizzare il parametro `-u` per il comando `docker exec` così da specificare l'id utente, eg come utente root (utilizzare `APP_NAME` al posto di `$nomeApp`):
    ```bash
    docker exec -u 0 -it php81_$nomeApp bash
    chown -R 33 storage
    ```

Xdebug potrebbe non trovare il file di log configurato nel .ini, quindi generare vari warnings

-   creare un file in `/var/log/xdebug.log` all'interno del container phpfpm. Eseguire un `chown www-data /var/log/xdebug.log`. Creare questo file solo se si ha esigenze di debug errori xdebug (impossibile analizzare il codice tramite breakpoint) visto che potrebbe crescere esponenzialmente nel tempo
