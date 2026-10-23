# dem — API di quota e dati tecnici delle tracce

**Questo repo è pubblico:** nessun IP, log, evidenza o credenziale nei file di `docs/` (oc:8854).

## Conoscenza

| Argomento | Cosa copre | Pagina |
|---|---|---|
| Immagine PHP-FPM | versione PHP, pool FPM, due server, vendor diverso dal lock | [docs/knowledge/8854-messa-in-sicurezza-php2shell-dem.md](docs/knowledge/8854-messa-in-sicurezza-php2shell-dem.md) |

Procedura per aggiornare PHP: [docs/howto/aggiornare-immagine-php.md](docs/howto/aggiornare-immagine-php.md).

## Trappole

- Sui server mai `docker compose up -d` senza nome servizio, e mai copiare
  `docker-compose.yml.example` sul `docker-compose.yml` del server: i volumi di `db` sono diversi
  e il DB verrebbe ricreato su una cartella vuota (oc:8854).
- I test delle quote falliscono anche sul codice sano: gli stub sono in 4326 e il codice
  interroga in 3035 (oc:8854).
