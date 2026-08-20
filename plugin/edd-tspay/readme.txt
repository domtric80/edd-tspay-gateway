=== TS Pay for Easy Digital Downloads ===
Contributors: tspay-edd-integration
Tags: easy digital downloads, edd, payment gateway, tspay, teamsystem
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gateway TS Pay LinkToPay per Easy Digital Downloads, con riconciliazione server-to-server e webhook HMAC-SHA256.

== Description ==

Il plugin aggiunge TS Pay ai gateway di Easy Digital Downloads. Usa il flusso Incasso e-commerce immediato (`/orders/link2pay`) perché associa importo e ordine EDD in modo non ambiguo.

Funzioni principali:

* ordine EDD pending prima del reindirizzamento;
* pagina di pagamento ospitata da TS Pay;
* verifica del ritorno tramite `/charges/orders/{orderKey}`;
* webhook `tspay_charge.*` con verifica `X-Message-Hash` HMAC-SHA256;
* controllo di merchant, orderKey, valuta e importo;
* gestione idempotente degli stati active, pending, failed, error e refunded;
* credenziali separate per test e produzione.

Il plugin non raccoglie né memorizza dati carta.

== Installation ==

1. Installare e attivare Easy Digital Downloads 3.3 o successivo.
2. Caricare la cartella `edd-tspay` in `/wp-content/plugins/` oppure installare lo ZIP.
3. Attivare il plugin.
4. Aprire Download > Impostazioni > Pagamenti > TS Pay.
5. Inserire API key attiva, merchantRef e metodi abilitati.
6. Abilitare TS Pay nella lista dei gateway EDD.
7. Registrare presso TS Pay il webhook HTTPS mostrato nelle impostazioni per `tspay_charge.*`, quindi salvare il secret restituito.

== Frequently Asked Questions ==

= Perché non viene usato il POS digitale statico? =

Il POS digitale permette al cliente di inserire manualmente l'importo e non prevede riconciliazione automatica. LinkToPay è il flusso TS Pay progettato per un carrello e-commerce.

= Il webhook funziona su localhost? =

No. TS Pay deve raggiungere un endpoint HTTPS pubblico. In locale il ritorno browser esegue comunque una verifica API server-to-server.

= Sono supportiti i rimborsi dal pannello EDD? =

La versione 0.1.0 riceve lo stato refunded da TS Pay, ma non avvia ancora un rimborso TS Pay dal pannello EDD.

== Changelog ==

= 0.1.0 =

* Prima implementazione del gateway LinkToPay.
* Ritorno verificato server-to-server.
* Webhook firmato e validazione ordine/importo/valuta.

