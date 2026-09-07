# Piano di implementazione: laravel-custom-fields

Aggiornato il 7 settembre 2026. Specifica di riferimento: `PLAN.md`.

Questo piano sostituisce i precedenti frammenti di implementazione, incompatibili con nome normalizzato, opzioni strutturate, patch e conversioni estensibili. Le attivita' sono ancora da eseguire. L'aggiornamento dei documenti non avvia lo scaffolding.

## Regole di lavoro

* Lavorare in `/Users/taz/Github/laravel-custom-fields`, preservando i documenti e le modifiche del prodotto.
* PHP `^8.4`, Laravel 12/13, namespace `PlinCode\CustomFields\`.
* Seguire le convenzioni PHP e Laravel del prodotto, usare esclusivamente Pint per formattazione e lint.
* Consultare `laravel-specialist` per implementazione, `laravel-testing` per test e le skill pertinenti effettivamente presenti nello skeleton.
* Cercare e leggere `php-guidelines-from-spatie` prima di dichiararne l'utilizzo. La sua assenza va segnalata senza inventarne il contenuto.
* Preparare un `AGENTS.md` breve con regole di progetto e comandi reali. Mantenere allineato `CLAUDE.md`, usando il collegamento dello skeleton quando disponibile.
* Nessun trailer `Co-authored-by`, firma AI o riferimento all'agente nei commit. Quando autorizzati, commit nel formato `type(scope): description`, scope e descrizione minuscoli, descrizione imperativa, massimo 50 caratteri, nessun numero di ticket.
* Non eseguire automaticamente push, tag remoti, pubblicazione, modifiche alla visibilita' o scritture nel progetto package-art.
* Non introdurre stime di completamento o compatibilita' come garanzie prima delle verifiche.

## Task 1: Bootstrap riproducibile

- [ ] Verificare file di lavoro e istruzioni applicabili, senza esplorare directory generate o segreti.
- [ ] Leggere la versione attuale di `laravel/package-skeleton`, compresi `AGENTS.md`, guida di configurazione e skill pertinenti.
- [ ] Recuperare lo skeleton in una directory temporanea nuova e copiarne soltanto il necessario, preservando repository e documenti esistenti. Non usare cancellazioni preventive indiscriminate.
- [ ] Configurare metadata, provider, config, traduzioni, facade e migration con la procedura non interattiva realmente supportata. Nessun controller o frontend.
- [ ] Allineare stile con i pacchetti PlinCode tramite letture mirate di Composer, Pint, analisi statica e workflow. Non copiare indiscriminatamente automazioni.
- [ ] Verificare le release dei due pacchetti PlinCode, i vincoli e i punti di riuso. Spatie ha target `^7.3.1`; il vincolo di `laravel-eloquent-sorts` deriva dalle sue release reali. Integrare il pacchetto sorts richiesto senza attribuirgli il sorter EAV che non offre. Aggiungere sql-dialect se usato effettivamente dall'operatore testuale.
- [ ] Verificare che documentazione e Composer concordino sull'obbligatorieta' di Spatie quando sorts lo richiede transitivamente.
- [ ] Configurare gli strumenti necessari e verificare installazione, discovery del provider, Pint, analisi statica e suite iniziale.
- [ ] Registrare in `AGENTS.md` solo comandi che funzionano. Mantenere i documenti di progetto versionabili; eventuale esclusione dall'archivio distribuito va gestita separatamente.

Criterio di uscita: uno laravel-custom-fields configurato, installabile e verificabile, con dipendenze motivate. Nessuna implementazione di dominio prima che il bootstrap funzioni.

## Task 2: Contratti e schema minimo

- [ ] Fissare con test i contratti di definizione, opzione, tipo, query e contesto di validazione. Evitare interfacce inutili, ma fornire quelle realmente necessarie alle estensioni.
- [ ] Implementare config per nomi tabelle/modelli, `key_type=id`, `morph_key_type=uuid`; validare i valori ammessi `id`, `uuid`, `ulid`.
- [ ] Creare migration secondo PLAN sezione 5, con unique sul nome e sullo slug per entita'. Nessun flag soft delete incompleto.
- [ ] Provare pubblicazione ed esecuzione delle migration da un consumer. Se esiste caricamento automatico, verificare che carichi migration eseguibili e non soltanto `.stub`.
- [ ] Provare rollback, FK e unicita' sui database supportati. Non rimuovere questi test dopo il bootstrap.
- [ ] Creare due modelli ospiti generici nelle fixture. Generare correttamente chiavi interne ed esterne per tutte e tre le configurazioni.

Criterio di uscita: schema utilizzabile su due modelli e contratti provati da un tipo custom minimale, senza dipendere dalla UI.

## Task 3: Prima funzionalita' completa

Implementare prima `text`, `number` e `select`. Gli altri tipi arrivano quando il percorso completo funziona.

- [ ] Registry entita' con alias stabili e controllo collisioni della morph map.
- [ ] Registry tipi aperto, risolto attraverso il container, con controllo chiavi e colonne di storage consentite.
- [ ] Modelli sostituibili, risoluzione connessione coerente e operazioni PHP per creare/modificare definizioni.
- [ ] Nome salvato con lowercase Unicode e trim; errore su nome vuoto; unique anche nel database.
- [ ] Generazione slug limitata a 100 caratteri, fallback, collisioni e immutabilita'. Un cambio del nome non altera lo slug.
- [ ] Salvataggio e lettura tramite trait con serialize/deserialize, senza un `match` chiuso sui tipi.
- [ ] Scrittura batch atomica, rifiuto dell'ospite non persistito, controllo che il campo appartenga al modello corretto.
- [ ] Prima query di uguaglianza e primo ordinamento numerico nativi Eloquent.
- [ ] Primo adattatore Spatie che delega alle stesse query; test della composizione con un sorter del pacchetto PlinCode.

Scenario di accettazione: creare un campo numerico su un modello, scrivere valori `2` e `10`, leggerli con il tipo corretto, filtrare e ordinare numericamente senza coinvolgere l'altro modello. Ripetere da una lista Spatie. Questa e' la prima dimostrazione concreta del flusso del widget.

## Task 4: Patch, opzioni e disattivazione

- [ ] Distinguere patch e validazione completa, con regole per omissione, null, zero, false e selezione vuota.
- [ ] Un required assente su un altro campo non blocca la patch. La validazione completa usa lo stato risultante di valori esistenti e modifiche.
- [ ] Validare l'intero batch prima delle scritture; errore esplicito su chiavi sconosciute e tipi incompatibili.
- [ ] Opzioni strutturate con `key`, `label`, `is_active`, chiavi uniche e non riciclabili.
- [ ] Rinominare la label senza aggiornare i valori. Disattivare un'opzione conservandola nelle definizioni.
- [ ] Consentire mantenimento di una chiave disattivata solo sulla stessa istanza che gia' la possiede. Impedirne nuove assegnazioni, anche nel multiselect.
- [ ] Provare: opzione attiva assegnata, disattivazione, salvataggio di altri dati, rimozione del vecchio valore, tentativo di riassegnazione rifiutato.
- [ ] Escludere campi disattivati da metadati ordinari, filtri e sort proposti; consentire lettura esplicita inclusiva. Rifiutare scritture ordinarie su campi disattivati.
- [ ] Metadati per il widget: entita', tipi, label, input hint, opzioni attive, required e capacita' query. Le API di gestione possono leggere anche le opzioni disattivate.
- [ ] Messaggi tradotti en/it e regole di definizione applicate anche nei normali salvataggi Eloquent supportati.

Criterio di uscita: tutti i casi concordati sul ciclo di vita dei campi e delle opzioni passano attraverso le API pubbliche.

## Task 5: Tipi e operazioni estensibili

- [ ] Completare i dodici tipi previsti dal PLAN, riusando le otto colonne.
- [ ] Provare round trip su SQLite, MySQL e PostgreSQL, compresi decimal, date, UTC e JSON.
- [ ] Convalidare limiti dello storage: lunghezze, intervallo bigint, precisione e scala decimal, formato temporale.
- [ ] Provare un tipo del consumer con regole, serializzazione, deserializzazione e operatore proprio, senza modificare il core.
- [ ] Implementare uguaglianza, appartenenza, confronti, intervalli, presenza/assenza e contains any/all dove supportati.
- [ ] Implementare contains testuale con escaping riusando sql-dialect se confermato nel bootstrap. Provare `%`, `_`, backslash e comportamento Unicode documentato.
- [ ] Il consumer abilita esplicitamente le operazioni esposte nelle request. Rifiutare operazioni incompatibili invece di costruire SQL arbitrario.
- [ ] Ordinare soltanto tipi per cui esiste una semantica dichiarata; select per chiave, multiselect non ordinabile di default.
- [ ] Cambi di tipo protetti per storage, rappresentazione e validita' della nuova definizione. Provare anche il tentativo di aggirare `changeType()` tramite normale `save()`.

## Task 6: Isolamento, concorrenza e prestazioni

- [ ] Seconda connessione reale nelle fixture: verificare tabelle coinvolte, scritture e rollback, non soltanto il valore di `getConnectionName()`.
- [ ] Consumer con modelli sostituiti e scope tenant, migration con unique per tenant, due tenant che creano lo stesso nome e slug senza vedersi a vicenda.
- [ ] Eseguire isolamento su lettura, scrittura, validazione, filtro, sort e metadati. Provare cambio contesto nella stessa applicazione per rilevare stato tenant conservato accidentalmente.
- [ ] Sort costruito da query scoped del modello configurato. Conservare select, aggregati e ordinamenti del chiamante; nessuna duplicazione delle righe.
- [ ] Scegliere fra subquery correlata e join di subquery dopo aver verificato SQL e piano di esecuzione su dati rappresentativi. Non imporre il vecchio join diretto.
- [ ] Getter che riusano eager loading, nessuna query per campo, dati aggiornati dopo scrittura nella stessa istanza.
- [ ] Collisioni concorrenti dei nomi, slug e valori con unique, gestione errori e retry limitati dove necessario.
- [ ] Coordinare scritture con disattivazione di opzioni e cambi tipo. Testare ordine dei lock, transazioni esterne e rollback del batch.
- [ ] Misurare query count e tempi su un dataset dichiarato; documentare dimensioni, database e limiti senza promettere prestazioni universali.

## Task 7: Eventi e cancellazione

- [ ] Eventi di dominio disponibili dopo commit; nessun effetto esterno dopo rollback, anche in transazioni annidate.
- [ ] Factory con modelli configurati e alias ottenuti tramite `getMorphClass()`, confrontate con una scrittura attraverso il trait.
- [ ] Operazione esplicita per rimuovere un valore e invalidare le relazioni caricate.
- [ ] Eliminazione definitiva della definizione e cascata valori con contratto eventi documentato. Non aspettarsi eventi Eloquent da cascade SQL.
- [ ] Pulizia valori su hard delete dell'ospite, conservazione su soft delete; documentare responsabilita' del prodotto per bulk delete che non emettono eventi individuali.
- [ ] Nessuna promessa di audit/versionamento delle vecchie label.

## Task 8: Verifica e documentazione del consumer

- [ ] Matrice PHP 8.4/8.5 e Laravel 12/13 compatibile con i vincoli effettivi, con controlli delle dipendenze minime dove sensati.
- [ ] Suite veloce SQLite; integrazione MySQL e PostgreSQL eseguita realmente. Copertura mirata di `id`, `uuid`, `ulid` per chiavi interne e ospiti.
- [ ] Pint, analisi statica e test comportamentali passano. Nessuna baseline usata per nascondere errori introdotti.
- [ ] Installazione in workbench come consumer: definizione, compilazione, aggiornamento, lettura, filtro, sort e metadati del widget.
- [ ] README con configurazione prima delle migration, tipi, API, opzioni disattivate, validazione completa/parziale, dipendenze, estensioni e tenancy del prodotto.
- [ ] Documentare collations, null nei sort, decimali, timezone, stessa connessione per operazione, cancellazioni massive e assenza di chiavi miste.
- [ ] Aggiornare PLAN con le scelte tecniche consolidate e chiudere i punti ancora aperti.

## Task 9: Preparazione del rilascio

- [ ] Preparare changelog e proposta `v0.1.0`, dopo la verifica del consumer.
- [ ] Art e social preview sono attivita' successive; eventuali modifiche in `/Users/taz/Development/package-art` richiedono autorizzazione filesystem quando applicabile.
- [ ] Commit solo quando autorizzati, senza coautori o attribuzioni AI.
- [ ] Push, tag remoto, release e Packagist richiedono istruzione del prodotto. Il piano non e' autorizzazione a pubblicare.

## Ordine di partenza

Prima il Task 1, poi schema/contratti del Task 2 e la funzionalita' completa del Task 3. Mostrare il risultato concreto: due modelli, un campo creato, valori salvati, filtro e sort funzionanti. Solo dopo ampliare tipi, operatori e integrazioni.

Non rimandare integrita', validazione o isolamento per rispettare una data. Se serve restringere lo scope, proporre un taglio esplicito di funzionalita' non essenziali, mantenendo filtri e sort richiesti dal prodotto.
