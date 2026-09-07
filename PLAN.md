# Piano: plin-code/laravel-custom-fields

Specifica aggiornata il 7 settembre 2026 con le decisioni del prodotto. Questo documento descrive il comportamento richiesto; `IMPLEMENTATION.md` descrive ordine di lavoro e verifiche. Gli esempi API sono proposte da consolidare con i primi test, non codice gia' disponibile.

## 1. Identita' e obiettivo

| Voce | Scelta |
| --- | --- |
| Pacchetto | `plin-code/laravel-custom-fields` |
| Namespace | `PlinCode\CustomFields\` |
| PHP | `^8.4` |
| Laravel | 12 e 13 |
| Licenza | MIT |
| Autore | Daniele Barbaro, `barbaro.daniele@gmail.com` |
| Directory | `/Users/taz/Github/laravel-custom-fields` |
| Base | https://github.com/laravel/package-skeleton |

Il prodotto crea una definizione tramite un widget, mostra il campo sulle schede delle entita' e permette filtri e ordinamenti nelle liste. Il pacchetto fornisce metadati e operazioni PHP. Controller, autorizzazioni, endpoint e rendering restano nel prodotto.

Il primo consumer e' Mizuno, previsto per meta' settembre 2026. I modelli di dominio restano volutamente generici. La data orienta le priorita', ma non giustifica eliminare verifiche di integrita' o promettere compatibilita' non provate.

## 2. Decisioni confermate

1. Una definizione appartiene a un solo tipo di entita'. `note` su Paziente e `note` su Azienda sono definizioni indipendenti.
2. Il tipo di chiave dei modelli ospiti e' unico per installazione, configurabile fra `uuid`, `id`, `ulid`, con default `uuid`. Chiavi miste non supportate.
3. Il nome viene salvato con trim e lowercase Unicode, ed e' univoco per entita'. Anche la UI riceve il nome in minuscolo.
4. Lo slug e' la chiave tecnica stabile, generata alla creazione e immutabile. Rinominare il campo non modifica API, filtri o valori.
5. Le opzioni hanno chiave stabile, etichetta modificabile e stato attivo. Nei valori si persistono le chiavi.
6. Le opzioni disattivate restano leggibili sui record esistenti. Non possono essere assegnate a nuovi record o aggiunte a selezioni esistenti.
7. Gli aggiornamenti sono parziali. Il prodotto richiede esplicitamente la validazione completa; rendere un campo obbligatorio non modifica dati gia' presenti.
8. Un campo disattivato non viene proposto nei form, filtri e sort. I dati restano disponibili attraverso lettura esplicita.
9. Ogni tipo dichiara operazioni di query compatibili. Il prodotto sceglie quali esporre.
10. I tipi aggiuntivi supportano validazione e conversioni proprie. Allegati, riferimenti e oggetti complessi sono demandati alle estensioni.
11. Il prodotto identifica il tenant. Il pacchetto predispone modelli sostituibili, migration personalizzabili, rispetto degli scope e gestione coerente delle connessioni.
12. Nessun commit include `Co-authored-by` o attribuzioni AI.

## 3. Scope della prima versione

Inclusi: definizioni e valori, due registry, trait Eloquent, operazioni di gestione delle definizioni, validazione, opzioni disattivabili, eventi, factory, traduzioni inglese e italiano, query native, adattatori Spatie, test, documentazione e workbench.

Esclusi: UI, controller, autorizzazioni, risoluzione del tenant, cache interna, audit delle modifiche, import/export, condizioni fra campi e tipi complessi predefiniti. Lettura dei dati di campi/opzioni disattivati non significa versionamento storico: rinominare una label cambia la label visualizzata anche sui valori esistenti.

L'appiattimento per Scout o altri motori full text viene dopo la prima versione, senza dipendenza obbligatoria da Scout. Filament potra' avere un pacchetto separato.

## 4. Dipendenze e riuso

Requisiti di base: `php ^8.4`, `illuminate/database` e `illuminate/support` compatibili con Laravel 12/13, `spatie/laravel-package-tools`. Dichiarare anche i componenti Illuminate direttamente utilizzati, ad esempio validation, dopo aver verificato il bootstrap.

Target Spatie: `spatie/laravel-query-builder ^7.3.1`. Questa versione appartiene a Spatie, non a `plin-code/laravel-eloquent-sorts`.

Il prodotto ha richiesto `plin-code/laravel-eloquent-sorts` come dipendenza: prevederlo in `require`. Prima di fissarne il vincolo Composer si verificano release, API e compatibilita' reali. Nel checkout esaminato richiede PHP `^8.4` e Spatie `^7.3.1`: Spatie e' quindi obbligatorio anche transitivamente. Dichiarare direttamente anche Spatie, dato che gli adattatori ne usano le interfacce. Non documentare un'installazione senza Spatie.

La logica dei custom fields resta utilizzabile attraverso Eloquent senza passare da richieste HTTP. Questo disaccoppiamento delle API non equivale a dipendenze Composer opzionali.

`laravel-eloquent-sorts` gestisce ordinamenti generici per relazione, conteggio ed enum. Il suo `RelationOrder` attuale non esprime tutti i vincoli EAV. Non forzarlo a gestire una relazione per cui non e' progettato: verificare un riuso reale e testare la composizione dei sorter nelle liste. Il sorter specifico dei custom fields appartiene a questo pacchetto.

`plin-code/laravel-sql-dialect` e' candidato al riuso per un operatore testuale `contains` con escaping. Non aggiungerlo solo per confronti, intervalli o ordinamenti gia' espressi da Eloquent. Nel bootstrap verificare il punto di riuso e il vincolo di release, prima di aggiungerlo a `require`. La semantica di case sensitivity dipende comunque da driver e collation.

Riferimenti locali: `/Users/taz/Github/laravel-eloquent-sorts`, `/Users/taz/Github/laravel-sql-dialect`, `/Users/taz/Github/laravel-istat-geography`, `/Users/taz/Github/laravel-full-name`. Leggere file mirati per stile e API. Non e' necessario accedere al repository dell'applicazione progetto-32.

## 5. Schema

### Definizioni: `custom_fields`

| Colonna | Tipo e significato |
| --- | --- |
| `id` | secondo `key_type` |
| `entity_type` | alias stabile registrato, stringa |
| `name` | stringa normalizzata, massimo 255 caratteri |
| `slug` | stringa stabile, massimo 100 caratteri |
| `type` | chiave del registry tipi, massimo 50 caratteri |
| `options` | JSON nullable con chiave, label e stato di ogni opzione |
| `is_required` | booleano, default false |
| `is_active` | booleano, default true |
| `sort_order` | intero non negativo, default 0 |
| timestamps | creazione e aggiornamento |

Indici: unique `(entity_type, name)`, unique `(entity_type, slug)`, index `(entity_type, is_active)`.

Normalizzare il nome prima di validarlo e salvarlo; rifiutare un nome vuoto dopo trim. Non comprimere automaticamente gli spazi interni o rimuovere accenti. Verificare le collations scelte nei tre database: lowercase e trim non rendono identiche tutte le regole Unicode dei database. Non promettere equivalenza fra nomi accentati e non accentati.

Lo slug viene generato da un nome non vuoto, con fallback se la traslitterazione produce una stringa vuota. Troncare riservando spazio agli eventuali suffissi. Gestire collisioni concorrenti tramite unique e retry limitato, senza affidarsi soltanto a `exists()`. Rifiutare cambi dello slug e dell'entita' dopo la creazione attraverso le API supportate.

Esempio di opzioni:

```json
[
  { "key": "retail", "label": "Vendita al dettaglio", "is_active": true },
  { "key": "legacy", "label": "Categoria precedente", "is_active": false }
]
```

Le chiavi sono stringhe non vuote, univoche nella definizione e compatibili con lo storage. La modifica ordinaria delle opzioni aggiorna label/stato e aggiunge nuove chiavi; non elimina o ricicla chiavi esistenti. La disattivazione conserva la possibilita' di risolverne la label. L'elenco puo' non avere piu' opzioni attive, ma deve conservare le opzioni storiche.

### Valori: `custom_field_values`

| Colonna | Tipo |
| --- | --- |
| `id` | secondo `key_type` |
| `custom_field_id` | FK alla definizione, cascade per eliminazione definitiva |
| `valuable_type` | alias morph stabile |
| `valuable_id` | secondo `morph_key_type` |
| `value_string` | varchar(255), nullable |
| `value_text` | text, nullable |
| `value_integer` | bigint, nullable |
| `value_decimal` | decimal(20,6), nullable |
| `value_boolean` | boolean, nullable |
| `value_date` | date, nullable |
| `value_datetime` | datetime, nullable; convenzione UTC |
| `value_json` | JSON, nullable |
| timestamps | creazione e aggiornamento |

Unique `(custom_field_id, valuable_type, valuable_id)`, index `(valuable_type, valuable_id)`. Indici aggiuntivi per `(custom_field_id, value_string)` e `(custom_field_id, value_boolean)`; indici numerici e temporali da verificare con query rappresentative. Non indicizzare indiscriminatamente tutte le colonne.

Una scrittura usa soltanto la colonna del tipo e pulisce le altre colonne di storage gestite. Definizione, alias e modello ospite devono essere coerenti; la FK da sola non garantisce questa corrispondenza.

### Configurazione e migration

```php
'key_type' => 'id',         // chiavi delle due tabelle interne
'morph_key_type' => 'uuid', // chiavi dei modelli ospiti
```

`key_type` resta una scelta tecnica distinta, default `id`; entrambe le impostazioni accettano `id`, `uuid`, `ulid`. Generare UUID e ULID anche nei modelli interni, non soltanto nelle fixture. La configurazione dello schema viene scelta prima delle migration e non cambia a database popolato senza migrazione esplicita.

Nomi tabelle e modelli configurabili. Migration pubblicabili per personalizzazioni e connessioni. Un caricamento automatico opzionale deve funzionare con file realmente eseguibili: `loadMigrationsFrom()` non basta per file terminanti in `.php.stub`. Verificare separatamente pubblicazione, esecuzione e rollback.

Nessun flag `soft_deletes` incompleto nella prima versione. Disattivazione delle definizioni e delle opzioni e' il percorso ordinario. Una personalizzazione con SoftDeletes richiede migration, modelli, unicita' e restore coerenti ed e' responsabilita' del consumer.

## 6. Entita', tenant e connessioni

Registrazione proposta: `CustomFields::registerEntity(Patient::class, key: 'patient', label: 'Paziente')`.

Registrare l'alias nella morph map senza imporre una morph map globale alle relazioni estranee al pacchetto. Rifiutare collisioni di alias o registrazioni incompatibili, inclusi alias preesistenti per lo stesso modello. `getMorphClass()` e' la fonte comune per valori e factory.

Tutte le query passano dai modelli configurati. Usare la connessione effettiva dell'istanza ospite anche per definizioni, scritture, validazione e transazione. Non aprire una transazione su una connessione e scrivere attraverso una query statica su un'altra. I tre insiemi di dati devono essere sulla stessa connessione per l'operazione; accesso trasversale fra database fuori scope.

Per operazioni che ricevono solo una classe o un alias, il prodotto deve fornire il contesto della connessione tramite i modelli configurati o un contesto esplicito. Non perderlo convertendo immediatamente un'istanza in nome classe.

Database condiviso: il prodotto aggiunge tenant e scope ai modelli e adatta gli indici a `(tenant_id, entity_type, name)` e `(tenant_id, entity_type, slug)`. Garantisce che definizioni, valori e ospiti appartengano allo stesso tenant. Lo scope non sostituisce il vincolo unique o la validazione in scrittura.

Database separato: il prodotto risolve la connessione prima delle operazioni. Il pacchetto non conserva dati dipendenti dal tenant in singleton, cache statiche o registry globali. Le definizioni sono dati contestuali; i registry contengono tipi e classi.

## 7. Contratto dei tipi

Sostituire il vecchio `cast(): string` con conversioni reali. Il contratto deve esprimere:

| Responsabilita' | API proposta |
| --- | --- |
| Identita' e storage | `key()`, `storageColumn()` |
| Conversione in scrittura | `serialize(value, definition)` |
| Conversione in lettura | `deserialize(storedValue, definition)` |
| Validazione del valore | `rules(definition)` e contesto di aggiornamento dove necessario |
| Metadati | `label()`, `inputHint()`, `requiresOptions()`, `default()` |
| Query | operazioni supportate e relativi handler |

I nomi e le firme PHP definitive si consolidano nel primo test di un tipo personalizzato. Nessun `match` chiuso sui dodici tipi nel manager. Gli handler custom devono poter implementare query diverse da quelle dello storage scalare.

La serializzazione del tipo e gli eventuali cast Eloquent devono avere responsabilita' distinte, evitando doppia codifica JSON o cast impliciti che cambiano precisione. Decimali restituiti come stringhe a precisione definita; date e datetime con convenzioni esplicite. Non usare float come formato canonico del decimal.

| Tipo | Storage | Query candidate |
| --- | --- | --- |
| text, email, url, phone | value_string | uguaglianza, appartenenza, contains, sort |
| textarea | value_text | uguaglianza, contains; sort non proposto di default |
| number | value_integer | uguaglianza, confronti, intervalli, sort |
| decimal | value_decimal | uguaglianza, confronti, intervalli, sort |
| boolean | value_boolean | uguaglianza, sort |
| date | value_date | uguaglianza, confronti, intervalli, sort |
| datetime | value_datetime | uguaglianza, confronti, intervalli, sort |
| select | value_string | uguaglianza e appartenenza alle chiavi, sort per chiave |
| multiselect | value_json | contains any, contains all; nessun sort naturale |

Presenza/assenza disponibile dove semanticamente definita. Non promettere sort per label tradotta delle opzioni: il sort scalare iniziale usa la chiave. I metadati descrivono capacita', non renderizzano componenti complessi automaticamente.

## 8. Scrittura e validazione

API proposta per lettura/scrittura: `getCustomField`, `getCustomFields`, `setCustomField`, `setCustomFields`, operazione esplicita di cancellazione del valore. Lettura ordinaria limitata ai campi attivi; parametro esplicito per includere quelli disattivati. La scrittura ordinaria su definizioni disattivate viene rifiutata.

Proposta semantica da fissare nei test iniziali:

* Chiave omessa in una scrittura parziale: valore invariato.
* Valore nullo: svuotamento esplicito, rifiutato se il campo e' obbligatorio.
* `false`, `0` e stringa `"0"`: valori, non assenza.
* `[]` su multiselect: selezione vuota; verificare l'obbligatorieta'.
* Riga assente: default del tipo. Non confondere un default esposto in lettura con un valore realmente persistito.

Validazione completa invocabile dal prodotto sullo stato risultante, unendo valori esistenti e modifiche proposte. Un required non compilato altrove non blocca una patch valida. `rulesFor()` deve distinguere i due contesti; le sole regole di una FormRequest non bastano per il mantenimento delle opzioni disattivate.

Opzioni disattivate: in select consentire il valore disattivato solo se gia' assegnato alla stessa istanza. In multiselect consentire soltanto le chiavi disattivate gia' presenti nella selezione corrente; nuove chiavi devono essere attive. Dopo aver rimosso una chiave disattivata non la si puo' riassegnare. Validare per istanza, non contro tutte le opzioni storiche della definizione.

Validare struttura delle definizioni, nome, tipo, limiti, opzioni e chiavi sconosciute. Non ignorare input errati. Validare tutti i valori prima di persistere il batch, all'interno del contesto transazionale corretto.

Gestire creazione concorrente dello stesso valore senza duplicati, con unique e strategia atomica o retry limitato. Coordinare scritture e modifiche delle definizioni/opzioni in modo che una validazione non venga invalidata prima del salvataggio; definire un ordine dei lock coerente.

Cambio tipo: bloccare cambio di storage se esistono valori. Stesso storage non significa automaticamente stessa rappresentazione o regole compatibili: il passaggio deve validare la nuova definizione e rispettare la compatibilita' delle conversioni. Conversioni distruttive automatiche fuori scope. Proteggere anche il normale salvataggio Eloquent, non soltanto `changeType()`; scritture SQL dirette restano fuori dalle garanzie.

## 9. Query, caricamento ed eventi

Operazioni Eloquent native nel core; filtri e sorter Spatie delegano alle medesime implementazioni. Proporre soltanto campi attivi e operazioni abilitate esplicitamente dal consumer. Colonne e operatori provengono dal registry validato, mai direttamente dalla request.

Filtro e sort devono rispettare gli scope del modello valori configurato. Per il sort partire da una subquery Eloquent correlata oppure da una subquery scoped in join, confrontando il piano SQL. Un join diretto sul nome tabella perde gli scope. Non sovrascrivere `select`, aggregati o join del chiamante; non moltiplicare righe. Alias sicuri e univoci se si usa un join.

Proposta: `orderByCustomField()` ordina sulla colonna tipizzata; `whereCustomField()` mantiene una forma breve per uguaglianza e forme esplicite per gli altri operatori. Distinguere contains any/all nei multiselect. Filtri sulla presenza devono definire il comportamento per riga assente, null e selezione vuota.

Posizione dei null inizialmente documentata secondo driver, senza fingere uniformita'. Il consumer aggiunge un ordinamento stabile sulla chiave per la paginazione. Non ordinare automaticamente il JSON.

Accessor compatibili con eager loading: riusare relazioni caricate, precaricare definizioni dei valori ed evitare una query per campo. Dopo una scrittura invalidare o aggiornare le relazioni caricate. Verificare conteggio query e letture dopo aggiornamento nella stessa istanza.

Eventi previsti: creazione/modifica/eliminazione della definizione, salvataggio/eliminazione del valore. Notificare effetti esterni dopo commit, senza eventi relativi a transazioni annullate. Una cascade SQL non emette eventi Eloquent per ogni valore: documentare e testare il contratto delle eliminazioni.

La disattivazione conserva dati. La cancellazione definitiva delle definizioni e' un'operazione esplicita distruttiva. Per eliminazione dell'ospite proporre pulizia dei valori su hard delete, conservazione su soft delete; bulk delete SQL richiede una strategia del prodotto perche' non attraversa gli eventi dei singoli modelli.

## 10. Qualita' e rilascio

Pint per formattazione, Pest e Orchestra Testbench per comportamento, Larastan per analisi statica. PHP 8.4 e 8.5, Laravel 12/13 nelle combinazioni supportate dalle dipendenze effettive. SQLite per feedback locale, MySQL e PostgreSQL per comportamento SQL reale. Esercitare `id`, `uuid`, `ulid` per chiavi ospiti e interne, con una matrice mirata, senza moltiplicare ogni asse inutilmente.

Test essenziali: isolamento fra modelli, nomi normalizzati/univoci, slug immutabile, ciclo opzioni, patch e validazione completa, tipi custom, round trip dei dodici tipi, rollback su seconda connessione, scope in query e sort, query count, concorrenza, migration pubblicate e valori disattivati.

Release iniziale proposta `v0.1.0` via VCS per il consumer. Packagist e `v1.0.0` dopo uso reale. Art e automazioni editoriali seguono la funzionalita'. Non eseguire pubblicazioni, modifiche alla visibilita' del repository o push come effetto implicito di questo documento.

Le precedenti statistiche sui concorrenti non costituiscono una giustificazione tecnica aggiornata e non vengono usate per decidere lo scope.

## 11. Decisioni tecniche da chiudere durante il bootstrap

1. Versioni pubblicate dei pacchetti PlinCode, riuso concreto e vincoli Composer. Non assegnare `^7.3.1` a `laravel-eloquent-sorts` per confusione con Spatie.
2. Firme dei contratti tipi/query e contesto di validazione, provate con un tipo aggiunto dal consumer.
3. Rappresentazione dello svuotamento, convenzioni date e precisione decimali.
4. Strategia query e concorrenza verificata sui tre database.
5. Recupero della skill `php-guidelines-from-spatie` richiesta dal CLAUDE.md globale e non trovata nelle cartelle controllate. Non dichiararla applicata prima di averla letta.

Queste sono scelte implementative circoscritte. Non riaprono le decisioni di prodotto della sezione 2.
