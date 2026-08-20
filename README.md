# TS Pay for Easy Digital Downloads

Plugin WordPress indipendente che aggiunge **TS Pay** a Easy Digital Downloads 3.7.0 usando il flusso ufficiale **Incasso e-commerce immediato / LinkToPay**.

Documentazione di riferimento: [Onboarding e autenticazione](https://tspay.stoplight.io/docs/ts-pay/ea584d632ef57-onboarding-e-autenticazione), [Incasso e-commerce immediato](https://tspay.stoplight.io/docs/ts-pay/q1me0pm73775g-incasso-e-commerce-con-addebito-immediato), [POS digitale](https://tspay.stoplight.io/docs/ts-pay/dlb8vc8cyopq5-pos-digitale) e [Notifiche](https://tspay.stoplight.io/docs/ts-pay/g3ly0lilrng40-notifiche).

- [Guida completa di installazione e configurazione](docs/INSTALLAZIONE.md)
- [Release e download del plugin](https://github.com/domtric80/edd-tspay-gateway/releases)

## Perché non usa il “POS digitale” statico

Il POS digitale TS Pay richiede al pagatore di digitare importo e causale e, secondo la documentazione TS Pay, non prevede riconciliazione automatica. Un checkout EDD deve invece trasmettere un importo non modificabile e collegare in modo certo il pagamento all'ordine. Per questo il gateway usa `POST /orders/link2pay`, quindi verifica `GET /charges/orders/{orderKey}` e riceve gli eventi `tspay_charge.*` via webhook.

## Avvio locale

Prerequisiti: Docker Desktop e PowerShell.

```powershell
cd D:\Docker\WordPress\edd-tspay-gateway
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\setup.ps1
```

Il laboratorio espone:

- WordPress: <http://localhost:8080>
- amministrazione: <http://localhost:8080/wp-admin/> (`admin` / `admin`, solo ambiente locale)
- simulatore TS Pay: <http://localhost:8090>

Aprire il “Prodotto demo TS Pay”, aggiungerlo al carrello e completare il checkout. Il simulatore consente di provare pagamento riuscito, pending, fallito e annullato.

Per ricreare tutto da zero:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\setup.ps1 -Reset
```

## Configurazione reale TS Pay

In **Download > Impostazioni > Pagamenti > TS Pay** configurare:

1. API key attiva per test e/o produzione, ottenuta tramite il flusso `/auth/api-key` previsto da TS Pay.
2. `merchantRef` (codice titolare) del merchant.
3. URL API dell'ambiente. I valori predefiniti sono `https://api-staging.tspay.app` e `https://api.tspay.app`; vanno confermati con TS Pay nel contratto tecnico.
4. Metodi ammessi (`card`, `paypal`, `sepa_debit`, `pis_charge`).
5. Webhook HTTPS pubblico mostrato nella pagina impostazioni. Registrare l'evento `tspay_charge.*` tramite `POST /notification/webhook` e copiare nel plugin il `secret` restituito.

Il webhook rifiuta richieste senza firma o con HMAC-SHA256 non valido (`X-Message-Hash`) e verifica `merchantRef`, `orderKey`, valuta e importo prima di completare un ordine.

> Un'installazione su `localhost` non può ricevere webhook TS Pay. Per il collaudo reale serve un URL HTTPS pubblico; il ritorno browser continua comunque a verificare il pagamento interrogando TS Pay server-to-server.

## Creare lo ZIP installabile

```powershell
.\scripts\package.ps1
```

Il risultato viene scritto in `dist/edd-tspay-0.1.1.zip`. Lo script verifica che i percorsi interni usino separatori compatibili con WordPress su Linux.

## Struttura

- `plugin/edd-tspay/`: plugin distribuibile.
- `mock-tspay/`: simulatore locale dell'API LinkToPay.
- `wordpress/`: volume sorgente dei plugin per Docker; EDD è escluso da Git.
- `scripts/`: setup riproducibile e packaging.

## Sicurezza e limiti

- Le API key non sono mai inserite nel codice o nei log del plugin.
- Il plugin non gestisce numeri di carta: il cliente paga sulla pagina ospitata TS Pay.
- I rimborsi dal pannello EDD non sono ancora inviati automaticamente a TS Pay nella versione 0.1.0.
- Prima della produzione sono necessari AppSecret/API key, merchant test, carte di prova e conferma degli URL ambientali forniti da TeamSystem Payments.
