# TS Pay for Easy Digital Downloads 0.1.0

Prima release installabile del gateway TS Pay LinkToPay per Easy Digital Downloads.

## Download verificato

- Asset: `edd-tspay-0.1.0.zip`
- SHA-256: `A228661B63A81B6D8245B87F5893B4A42BCB6D3BE563CDE3F975540CD2DF3FEE`

## Funzioni incluse

- creazione di ordini TS Pay tramite `POST /orders/link2pay`;
- reindirizzamento alla pagina di pagamento ospitata;
- verifica server-to-server tramite `GET /charges/orders/{orderKey}`;
- webhook `tspay_charge.*` protetto da HMAC-SHA256;
- verifica di merchant, riferimento ordine, valuta e importo;
- gestione degli stati complete, pending, failed, abandoned e refunded;
- configurazioni separate per test e produzione;
- pulsante amministrativo per verificare la API key;
- laboratorio Docker e simulatore TS Pay locale.

## Compatibilità verificata

- WordPress 6.8.2
- Easy Digital Downloads 3.7.0
- PHP 8.2
- MariaDB 11.4

## Limitazioni note

- TS Pay supporta attualmente il checkout in EUR.
- La versione 0.1.0 riceve lo stato di rimborso, ma non avvia rimborsi TS Pay dal pannello EDD.
- Per i webhook reali è necessario un URL HTTPS pubblico.

Consultare la [guida di installazione](INSTALLAZIONE.md) prima del passaggio in produzione.
