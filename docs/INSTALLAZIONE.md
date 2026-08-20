# Guida di installazione — TS Pay for Easy Digital Downloads

Questa guida descrive l'installazione del plugin, la configurazione dell'ambiente di test TS Pay e il passaggio in produzione.

## 1. Requisiti

- WordPress 6.2 o successivo.
- PHP 7.4 o successivo; PHP 8.1/8.2 consigliato.
- Easy Digital Downloads 3.3 o successivo; il progetto è collaudato con EDD 3.7.0.
- Negozio configurato in euro (`EUR`).
- Certificato HTTPS valido sul sito pubblico.
- Merchant TS Pay con onboarding completato e relativo `merchantRef`.
- API key TS Pay attiva per l'ambiente che si vuole utilizzare.

TS Pay accetta e gestisce i dati di pagamento sulla propria pagina ospitata. Il plugin non riceve né memorizza numeri di carta.

## 2. Download e installazione

1. Aprire la pagina [Releases](https://github.com/domtric80/edd-tspay-gateway/releases).
2. Scaricare `edd-tspay-0.1.0.zip` dalla release più recente.
3. In WordPress aprire **Plugin > Aggiungi plugin > Carica plugin**.
4. Selezionare lo ZIP senza estrarlo, scegliere **Installa ora** e poi **Attiva plugin**.
5. Verificare che Easy Digital Downloads sia già installato e attivo.

In alternativa, estrarre la cartella `edd-tspay` in `wp-content/plugins/` e attivarla dalla pagina Plugin.

## 3. Credenziali TS Pay

Il plugin richiede una **API key già attivata**. Non bisogna inserire l'AppSecret nel plugin.

Il flusso previsto da TS Pay è:

1. completare l'onboarding e ottenere il codice titolare `merchantRef`;
2. il software integrato usa l'AppSecret per richiedere una API key tramite `POST /auth/api-key`;
3. TS Pay restituisce `loginUrl`, `authKey` e `apiKey`;
4. il merchant apre `loginUrl` e autorizza l'API key;
5. lo stato passa da `init` ad `active`;
6. la chiave attiva viene inserita nelle impostazioni del plugin.

Riferimento ufficiale: [Onboarding e autenticazione TS Pay](https://tspay.stoplight.io/docs/ts-pay/ea584d632ef57-onboarding-e-autenticazione).

Le tre credenziali hanno scopi differenti:

| Valore | Utilizzo | Dove conservarlo |
|---|---|---|
| AppSecret | Richiesta iniziale dell'API key | Sistema dell'integratore; non nel plugin |
| API key | Chiamate LinkToPay e verifica pagamenti | Impostazioni TS Pay del plugin |
| Webhook secret | Verifica HMAC delle notifiche | Impostazioni TS Pay del plugin |

## 4. Configurazione del gateway in EDD

Aprire **Download > Impostazioni > Pagamenti > TS Pay**.

### Ambiente di test

1. Attivare **Modalità test** nelle impostazioni generali dei pagamenti EDD.
2. Impostare **URL API test**. Il valore predefinito è `https://api-staging.tspay.app`.
3. Inserire **API key test**.
4. Inserire **Codice titolare test (merchantRef)**.
5. Scegliere i metodi TS Pay, separati da virgola:
   - `card` — carta di credito/debito;
   - `paypal` — PayPal;
   - `sepa_debit` — addebito SEPA;
   - `pis_charge` — bonifico istantaneo/Open Banking.
6. Salvare le modifiche.
7. Usare **Verifica la connessione attiva**: TS Pay deve confermare che l'API key è valida e attiva.

### Ambiente di produzione

1. Impostare **URL API produzione**. Il valore predefinito è `https://api.tspay.app`.
2. Inserire **API key produzione** e **merchantRef produzione**.
3. Non disattivare la modalità test finché checkout, callback e webhook non sono stati collaudati.

Le credenziali di test e produzione sono separate. Verificare gli URL assegnati nel contratto tecnico TS Pay prima della messa online.

## 5. Attivazione in Easy Digital Downloads

Aprire **Download > Impostazioni > Pagamenti > Generale** e:

1. attivare **TS Pay** nell'elenco dei gateway;
2. selezionarlo come gateway predefinito, se desiderato;
3. verificare che la valuta del negozio sia `EUR`;
4. salvare le modifiche.

Nel checkout apparirà il metodo **TS Pay**. Il cliente viene reindirizzato alla pagina LinkToPay e ritorna poi alla ricevuta EDD.

## 6. Configurazione del webhook

Il ritorno del browser viene verificato interrogando TS Pay, ma il webhook è necessario per ricevere aggiornamenti affidabili anche se il cliente chiude la pagina.

1. Copiare l'URL mostrato nelle impostazioni TS Pay del plugin. Ha forma:

   ```text
   https://negozio.example/wp-json/edd-tspay/v1/webhook
   ```

   Su configurazioni WordPress senza permalink può essere mostrata la variante `?rest_route=/edd-tspay/v1/webhook`.

2. L'endpoint deve essere pubblico, HTTPS e raggiungibile dai server TS Pay. `localhost` non è utilizzabile.
3. Registrare l'evento `tspay_charge.*` tramite `POST /notification/webhook`, autenticandosi con la API key attiva. Esempio indicativo:

   ```bash
   curl -X POST 'https://api-staging.tspay.app/notification/webhook' \
     -H 'Authorization: Bearer API_KEY_TEST' \
     -H 'Content-Type: application/json' \
     -d '{
       "event": "tspay_charge.*",
       "endpoint": "https://negozio.example/wp-json/edd-tspay/v1/webhook"
     }'
   ```

4. TS Pay restituisce un `secret`: copiarlo nel campo **Webhook secret** del plugin.
5. Salvare le impostazioni.

Il plugin calcola l'HMAC-SHA256 sul corpo originale e lo confronta con `X-Message-Hash`. Prima di aggiornare l'ordine verifica anche `merchantRef`, `orderKey`, valuta e importo.

Riferimento ufficiale: [Notifiche e webhook TS Pay](https://tspay.stoplight.io/docs/ts-pay/g3ly0lilrng40-notifiche).

## 7. Collaudo

Eseguire almeno questi casi in ambiente test:

1. pagamento carta riuscito: ordine EDD `Complete`;
2. pagamento pending: ordine EDD `Pending`, poi aggiornamento via webhook;
3. pagamento rifiutato: ordine EDD `Failed`;
4. annullamento dalla pagina TS Pay: ordine EDD `Abandoned`;
5. ritorno al sito dopo il pagamento;
6. consegna del webhook con risposta HTTP 200;
7. ricezione dell'e-mail e accesso ai file acquistati solo dopo lo stato `Complete`.

Il gateway utilizza il flusso [Incasso e-commerce immediato / LinkToPay](https://tspay.stoplight.io/docs/ts-pay/q1me0pm73775g-incasso-e-commerce-con-addebito-immediato), non il POS digitale statico.

## 8. Laboratorio Docker incluso nel repository

Per provare l'integrazione senza credenziali TS Pay reali:

```powershell
git clone https://github.com/domtric80/edd-tspay-gateway.git
cd edd-tspay-gateway
# Copiare easy-digital-downloads.3.7.0.zip nella cartella padre del progetto.
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\setup.ps1
```

Servizi locali:

- WordPress: <http://localhost:8080>
- amministrazione: <http://localhost:8080/wp-admin/> (`admin` / `admin`, esclusivamente locale)
- simulatore TS Pay: <http://localhost:8090>

Il simulatore permette di scegliere esito riuscito, pending, fallito o annullato.

## 9. Passaggio in produzione

Prima di disattivare la modalità test:

- creare o attivare una API key di produzione;
- configurare `merchantRef` e webhook secret di produzione;
- verificare il dominio API comunicato da TS Pay;
- verificare HTTPS, permalink e raggiungibilità del webhook;
- eseguire un pagamento reale di importo minimo;
- verificare ordine EDD, `orderKey`, `chargeKey`, e-mail e download;
- predisporre backup del database e log applicativi;
- non copiare mai credenziali in ticket, repository o screenshot pubblici.

## 10. Stati gestiti

| Stato TS Pay | Stato EDD |
|---|---|
| `active` | `complete` |
| `pending` | `pending` |
| `failed`, `error` | `failed` |
| `refunded` | `refunded` |
| annullamento browser | `abandoned` |

Gli eventi di contestazione vengono memorizzati da TS Pay ma la versione 0.1.0 non automatizza la relativa gestione commerciale in EDD.

## 11. Risoluzione problemi

### “La API key TS Pay non è configurata”

Controllare che la modalità test di EDD corrisponda al gruppo di credenziali compilato.

### “Unauthorized” durante la verifica

La API key può essere ancora in stato `init`, disabilitata o appartenere a un altro ambiente. Completare l'attivazione tramite `loginUrl`.

### Il pagamento resta pending

Verificare:

- raggiungibilità HTTPS del webhook;
- correttezza del webhook secret;
- sottoscrizione a `tspay_charge.*`;
- eventuali errori nei log EDD e in `wp-content/debug.log`.

### Il webhook restituisce 401

La firma `X-Message-Hash` non coincide. Copiare nuovamente il secret restituito dalla registrazione webhook senza spazi aggiuntivi.

### Il webhook restituisce 422

Il messaggio è firmato, ma importo, valuta o `orderKey` non coincidono con l'ordine EDD. Non completare manualmente l'ordine prima di avere verificato la transazione in TS Pay.

## 12. Aggiornamento e disinstallazione

Per aggiornare il plugin, installare lo ZIP della nuova release sostituendo la versione esistente. Eseguire prima un backup.

La disinstallazione non elimina impostazioni e metadati degli ordini: vengono conservati intenzionalmente per non perdere la traccia contabile e di audit.
