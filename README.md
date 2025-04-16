# Documentazione Completa: Sistema di Generazione PDF

## Introduzione

Questo sistema offre API e strumenti per generare documenti PDF dinamici con tabelle, testi e formattazione avanzata. È basato sulla libreria TCPDF e consente di creare report complessi e ben strutturati tramite richieste API in formato JSON.

## Componenti Principali

Il sistema è composto da due script PHP principali:

1. **generate-pdf.php**: API per generare PDF da dati JSON con funzionalità avanzate di formattazione
2. **base64-to-pdf.php**: Strumento per convertire dati PDF in formato Base64 a file PDF reali

## 1. API di Generazione PDF (generate-pdf.php)

### Panoramica

Lo script `generate-pdf.php` espone un'API REST che accetta richieste POST con dati JSON per generare documenti PDF personalizzati.

### Formato della Richiesta

L'API accetta richieste POST con un payload JSON che deve contenere almeno questi campi:

```json
{
  "columns": ["Nome Colonna 1", "Nome Colonna 2", ...],
  "rows": [
    ["Valore 1", "Valore 2", ...],
    ["Valore 1", "Valore 2", ...],
    ...
  ]
}
```

### Parametri Supportati

| Parametro | Tipo | Default | Descrizione |
|-----------|------|---------|-------------|
| `orientation` | string | `"P"` | Orientamento del documento: `"P"` (Portrait/Verticale) o `"L"` (Landscape/Orizzontale) |
| `title` | string | `"Generated Document"` | Titolo del documento |
| `author` | string | `"PDF Generator API"` | Autore del documento |
| `margins` | array | `[15, 15, 15]` | Margini in mm: [sinistro, superiore, destro] |
| `title_font_size` | number | `16` | Dimensione font del titolo |
| `content_font_size` | number | `10` | Dimensione font del contenuto |
| `columns` | array | - | **Obbligatorio**: Nomi delle colonne della tabella |
| `rows` | array | - | **Obbligatorio**: Dati delle righe della tabella |
| `column_config` | object | - | Configurazione avanzata delle colonne per formattazione e stile |
| `table_border` | number | `1` | Spessore del bordo tabella |
| `table_padding` | number | `5` | Padding delle celle in tabella |
| `header_bg` | string | `"#f2f2f2"` | Colore di sfondo intestazione tabella (formato hex) |
| `content_before` | string | - | Testo da inserire prima della tabella |
| `content_after` | string | - | Testo da inserire dopo la tabella |
| `group_by` | string/array | - | Colonna(e) da utilizzare per raggruppamento (singola colonna o array per multi-livello) |
| `group_config` | object | - | Configurazione per ciascun livello di raggruppamento |
| `sort_by` | string/number | - | Colonna da utilizzare per ordinamento |
| `sort_ascending` | boolean | `true` | Ordine crescente (`true`) o decrescente (`false`) |
| `output_mode` | string | `"B64"` | Modalità output: `"B64"` (Base64) o `"F"` (File) |
| `filename` | string | - | Nome del file PDF generato |
| `format` | string | `"A4"` | Formato del documento (A4, A3, A0, ecc.) |

### Configurazione Avanzata delle Colonne

Utilizzando il parametro `column_config`, è possibile specificare diverse proprietà per ogni colonna:

| Proprietà | Tipo | Default | Descrizione |
|-----------|------|---------|-------------|
| `type` | string | `"string"` | Tipo di dati (string, price, percentage, number, date) |
| `align` | string | `"L"` | Allineamento del testo: "L" (sinistra), "C" (centro), "R" (destra) |
| `width` | string | `null` | Larghezza fissa della colonna (es. "20mm", "2cm") |
| `fontWeight` | string | `"N"` | Stile del font: "N" (normale), "B" (grassetto), "I" (corsivo), "BI" (grassetto corsivo) |
| `backgroundColor` | string | `null` | Colore di sfondo della colonna in formato HTML (es. "#f0f0f0") |
| `textColor` | string | `null` | Colore del testo della colonna in formato HTML (es. "#006400") |
| `padding` | number | `1` | Padding della cella in punti |

### Raggruppamento Multi-livello e Configurazione Gruppi

Quando si utilizza `group_by` con un array di colonne per il raggruppamento multi-livello, è possibile configurare dettagliatamente ogni livello con `group_config`:

| Proprietà | Tipo | Default | Descrizione |
|-----------|------|---------|-------------|
| `pageBreak` | boolean | `false` | Indica se ogni categoria di questo livello deve iniziare su una nuova pagina |
| `showSummary` | boolean | `false` | Indica se mostrare un riepilogo per questo gruppo |
| `tableSummary` | boolean | `false` | Indica se mostrare una riga di riepilogo nella tabella |
| `summaryColumns` | object | `{}` | Definisce quali colonne includere nel riepilogo e quali operazioni eseguire |
| `summaryTitle` | string | `"Summary"` | Titolo per la sezione di riepilogo |
| `summaryTitleFontSize` | number | `14` | Dimensione del font per il titolo del riepilogo |
| `summaryTextFormat` | string | `"{label}: {value}" ({column} or {operation})` | Formato per il testo del riepilogo |
| `summaryTextStyle` | object | - | Stili CSS per il testo del riepilogo |
| `titleFormat` | function | - | Funzione per formattare il titolo del gruppo |
| `contentAfter` | string/function | - | Testo HTML o funzione per aggiungere contenuto dopo il gruppo |

### Operazioni di Riepilogo Supportate

Utilizzabili in `summaryColumns` per calcolare valori di riepilogo:

| Operazione | Descrizione |
|------------|-------------|
| `sum` | Somma di tutti i valori |
| `avg` | Media aritmetica dei valori |
| `count` | Conteggio degli elementi |
| `min` | Valore minimo |
| `max` | Valore massimo |

### Tipi di Dati Supportati per Colonne

| Tipo | Descrizione | Esempio |
|------|-------------|---------|
| `string` | Testo standard (default) | `"Testo esempio"` |
| `price` | Valore monetario | `"€ 1.234,56"` |
| `percentage` | Percentuale | `"12,34%"` |
| `number` | Numero intero formattato | `"1.234"` |
| `date` | Data formattata (gg/mm/aaaa) | `"01/01/2023"` |

### Definizione della Configurazione Colonne

È possibile specificare le configurazioni di colonna in diversi modi:

1. **Per indice**: `{"column_config": {"0": {"type": "price", "align": "right"}}}`
2. **Per nome**: `{"column_config": {"Prezzo": {"type": "price", "align": "right"}}}`
3. **Con stile e formattazione**: `{"column_config": {"Totale": {"type": "price", "align": "right", "fontWeight": "B", "textColor": "#006400"}}}`

### Esempi di Utilizzo

#### Esempio 1: Tabella Semplice

```json
{
  "title": "Report Vendite",
  "columns": ["Prodotto", "Prezzo", "Quantità", "Totale"],
  "rows": [
    ["Prodotto A", "10.50", "5", "52.50"],
    ["Prodotto B", "25.00", "2", "50.00"],
    ["Prodotto C", "15.75", "3", "47.25"]
  ],
  "column_config": {
    "Prezzo": {"type": "price", "align": "right"},
    "Quantità": {"type": "number", "align": "center"},
    "Totale": {"type": "price", "align": "right", "fontWeight": "B"}
  },
  "content_before": "<h1>Report Mensile</h1><p>Questo è il report delle vendite di aprile 2025.</p>"
}
```

#### Esempio 2: Tabella Raggruppata per Categoria

```json
{
  "title": "Report per Categorie",
  "columns": ["Categoria", "Prodotto", "Prezzo", "Quantità"],
  "rows": [
    ["Elettronica", "Smartphone", "399.99", "10"],
    ["Elettronica", "Laptop", "899.50", "5"],
    ["Abbigliamento", "Camicia", "29.99", "20"],
    ["Abbigliamento", "Pantaloni", "49.99", "15"],
    ["Elettronica", "Tablet", "299.00", "8"]
  ],
  "column_config": {
    "Categoria": {"type": "string", "fontWeight": "B", "backgroundColor": "#f0f0f0"},
    "Prezzo": {"type": "price", "align": "right"},
    "Quantità": {"type": "number", "align": "center"}
  },
  "group_by": "Categoria",
  "sort_by": "Prezzo",
  "sort_ascending": false
}
```

#### Esempio 3: Raggruppamento Multi-livello con Configurazione Avanzata

```json
{
  "title": "Report Vendite Multi-livello",
  "orientation": "L",
  "format": "A4",
  "columns": ["Reparto", "Categoria", "Prodotto", "Prezzo", "Vendite", "Totale"],
  "rows": [
    ["Elettronica", "Smartphone", "iPhone 14", 999.99, 5, 4999.95],
    ["Elettronica", "Smartphone", "Samsung S22", 899.99, 8, 7199.92],
    ["Elettronica", "Laptop", "MacBook Pro", 1499.99, 3, 4499.97],
    ["Abbigliamento", "Magliette", "T-shirt Bianca", 19.99, 15, 299.85],
    ["Abbigliamento", "Pantaloni", "Jeans Classic", 59.99, 10, 599.90]
  ],
  "column_config": {
    "Prezzo": {"type": "price", "align": "right"},
    "Vendite": {"type": "number", "align": "center"},
    "Totale": {"type": "price", "align": "right", "fontWeight": "B"}
  },
  "group_by": ["Reparto", "Categoria"],
  "sort_by": "Totale",
  "sort_ascending": false,
  "group_config": {
    "Reparto": {
      "pageBreak": true,
      "showSummary": true,
      "summaryTitle": "Riepilogo Reparto",
      "summaryColumns": {
        "Vendite": {"operation": "sum", "label": "Articoli Venduti"},
        "Totale": {"operation": "sum", "label": "Fatturato Reparto"}
      },
      "contentAfter": "<p><em>Fine sezione reparto</em></p>"
    },
    "Categoria": {
      "pageBreak": true,
      "showSummary": true,
      "tableSummary": true,
      "tableSummaryTitle": "Totale Categoria",
      "tableSummaryBgColor": "#e8f5e9",
      "summaryColumns": {
        "Vendite": "sum",
        "Totale": {"operation": "sum", "label": "Totale Categoria"}
      },
      "summaryTextFormat": "<strong>{label}</strong>: {value}"
    }
  }
}
```

#### Esempio 4: Report Complesso con Formattazione Avanzata

```json
{
  "title": "Report Analitico Vendite",
  "author": "Sistema di Reportistica",
  "orientation": "L",
  "format": "A0",
  "margins": [15, 15, 15],
  "columns": ["Data", "Negozio", "Reparto", "Categoria", "Prodotto", "Prezzo", "Sconto", "Quantità", "Totale"],
  "rows": [
    ["2025-04-10", "Milano", "Elettronica", "Audio", "Cuffie Wireless", 129.99, "10%", 3, 350.97],
    ["2025-04-11", "Roma", "Elettronica", "TV", "Smart TV 55\"", 699.99, "5%", 2, 1329.98],
    ["2025-04-10", "Milano", "Casa", "Arredamento", "Poltrona Design", 349.99, "15%", 1, 297.49]
  ],
  "column_config": {
    "Data": {"type": "date", "align": "center", "width": "25mm"},
    "Negozio": {"type": "string", "align": "left", "width": "30mm"},
    "Reparto": {"type": "string", "align": "left", "width": "30mm"},
    "Categoria": {"type": "string", "align": "left", "width": "30mm"},
    "Prodotto": {"type": "string", "align": "left", "width": "40mm", "fontWeight": "B"},
    "Prezzo": {"type": "price", "align": "right", "width": "25mm"},
    "Sconto": {"type": "percentage", "align": "center", "width": "20mm", "textColor": "#e53935"},
    "Quantità": {"type": "number", "align": "center", "width": "20mm"},
    "Totale": {"type": "price", "align": "right", "width": "25mm", "fontWeight": "B", "textColor": "#006400"}
  },
  "group_by": ["Negozio", "Reparto", "Categoria"],
  "sort_by": "Totale",
  "sort_ascending": false,
  "group_config": {
    "Negozio": {
      "pageBreak": true,
      "showSummary": true,
      "summaryTitle": "Performance Negozio",
      "summaryTitleFontSize": 14,
      "summaryColumns": {
        "Quantità": {"operation": "sum", "label": "Articoli Venduti"},
        "Totale": {"operation": "sum", "label": "Fatturato Totale"}
      },
      "summaryTextStyle": {
        "bgColor": "#e3f2fd",
        "textColor": "#0d47a1",
        "fontWeight": "bold",
        "fontSize": "12pt"
      },
      "contentAfter": "<p style='text-align: center;'><strong>* I dati di vendita sono indicativi e potrebbero essere soggetti a variazioni</strong></p>"
    },
    "Reparto": {
      "pageBreak": true,
      "tableSummary": true,
      "tableSummaryTitle": "Totale Reparto",
      "tableSummaryBgColor": "#e8f5e9",
      "tableSummaryTextColor": "#2e7d32",
      "summaryColumns": {
        "Quantità": "sum",
        "Totale": {"operation": "sum", "label": "Totale Reparto"}
      }
    },
    "Categoria": {
      "pageBreak": false,
      "showSummary": true,
      "summaryTitle": "Analisi Categoria",
      "summaryColumns": {
        "Sconto": {"operation": "avg", "label": "Sconto Medio"},
        "Quantità": {"operation": "sum", "label": "Quantità Venduta"},
        "Totale": "sum"
      },
      "summaryTextFormat": "{label}: <strong>{value}</strong>"
    }
  },
  "content_before": "<h1 style='color: #1976d2;'>Report di Vendita Dettagliato</h1><p>Analisi delle performance di vendita per negozio, reparto e categoria.</p>",
  "content_after": "<div style='margin-top: 10mm; text-align: center;'><p>Report generato automaticamente - Aprile 2025</p><p>Documento ad uso interno</p></div>",
  "table_border": 1,
  "table_padding": 6,
  "header_bg": "#1976d2",
  "output_mode": "F"
}
```

### Modalità di Output

L'API supporta due modalità di output:

1. **B64** (default): Restituisce il PDF codificato in Base64 nella risposta JSON
2. **F**: Salva il PDF come file nella directory `pdf_output` e restituisce il percorso del file

### Risposta dell'API

#### Successo (formato Base64)

```json
{
  "success": true,
  "message": "PDF generated successfully",
  "filename": "nome_file.pdf",
  "base64": "JVBERi0xLjcKJeLjz9MK....[dati base64]"
}
```

#### Successo (formato File)

```json
{
  "success": true,
  "message": "PDF saved to file successfully",
  "file_path": "pdf_output/nome_file.pdf"
}
```

#### Errore

```json
{
  "error": "Messaggio di errore specifico"
}
```

## 2. Strumento di Conversione Base64-a-PDF (base64-to-pdf.php)

### Panoramica

Lo script `base64-to-pdf.php` fornisce un'interfaccia web e un'API per convertire stringhe PDF in formato Base64 a file PDF reali.

### Utilizzo

#### Tramite Interfaccia Web

1. Accedere a `http://tuo-server/base64-to-pdf.php` nel browser
2. Inserire il nome del file desiderato
3. Incollare la stringa Base64 nell'area di testo
4. Cliccare su "Convert to PDF"

#### Tramite API REST

Inviare una richiesta POST con payload JSON:

```json
{
  "base64": "JVBERi0xLjcKJe...[stringa base64]",
  "filename": "nome_file.pdf"
}
```

#### Parametri URL (GET)

È possibile anche utilizzare parametri URL:
`http://tuo-server/base64-to-pdf.php?base64=JVBERi0xLjcK...&filename=test.pdf`

### Risposta

La risposta è in formato JSON:

```json
{
  "success": true,
  "message": "PDF file created successfully",
  "file_path": "pdf_output/nome_file.pdf",
  "file_url": "http://tuo-server/pdf_output/nome_file.pdf"
}
```

## Funzionalità Avanzate

### Raggruppamento Multi-livello

La funzione `addTablesByMultipleCategories` consente di:
- Raggruppare i dati per multiple colonne creando una struttura gerarchica
- Configurare indipendentemente ogni livello di raggruppamento
- Aggiungere salti di pagina selettivi per specifici livelli
- Generare riepiloghi per ciascun gruppo con operazioni personalizzate
- Aggiungere contenuto HTML personalizzato dopo ogni gruppo

### Controllo Preciso delle Interruzioni di Pagina

La proprietà `pageBreak` nei gruppi consente di:
- Specificare quali livelli devono iniziare su una nuova pagina
- Evitare intestazioni di gerarchia ridondanti dopo i salti di pagina
- Ottimizzare lo spazio utilizzato all'interno del documento
- Mantenere un layout coerente anche in report complessi

### Formattazione e Stile delle Colonne

La classe `ColumnConfig` gestisce:
- Tipi di dati per la formattazione (numeri, date, prezzi, ecc.)
- Allineamento del testo (sinistra, centro, destra)
- Stili di font (normale, grassetto, corsivo)
- Colori personalizzati per testo e sfondo
- Larghezze fisse delle colonne
- Padding delle celle

### Riepiloghi e Aggregazioni

È possibile aggiungere:
- Riepiloghi testuali formatati dopo ogni gruppo di dati
- Righe di riepilogo integrate nelle tabelle
- Operazioni di aggregazione (somma, media, conteggio, min, max)
- Formattazione personalizzata dei valori di riepilogo
- Stili personalizzati per le sezioni di riepilogo

### Personalizzazione del Layout

È possibile personalizzare:
- Orientamento delle pagine (verticale/orizzontale)
- Formato del documento (A4, A3, A0, ecc.)
- Margini del documento
- Dimensioni e stili dei font
- Colori e bordi delle tabelle
- Contenuti HTML prima e dopo le tabelle

## Integrazione con Altri Sistemi

### Esempio di Chiamata API da JavaScript

```javascript
fetch('http://tuo-server/generate-pdf.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    "title": "Report Generato",
    "columns": ["Prodotto", "Prezzo"],
    "rows": [
      ["Prodotto A", "10.50"],
      ["Prodotto B", "25.00"]
    ],
    "column_config": {
      "Prezzo": {"type": "price", "align": "right"}
    }
  })
})
.then(response => response.json())
.then(data => {
  // Usa data.base64 per visualizzare il PDF o scaricarlo
});
```

### Esempio di Chiamata API da PHP

```php
$data = [
  "title" => "Report Generato",
  "columns" => ["Prodotto", "Prezzo"],
  "rows" => [
    ["Prodotto A", "10.50"],
    ["Prodotto B", "25.00"]
  ],
  "column_config" => [
    "Prezzo" => ["type" => "price", "align" => "right"]
  ]
];

$ch = curl_init('http://tuo-server/generate-pdf.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
// Usa $result['base64'] per visualizzare/salvare il PDF
```

## Requisiti di Sistema

- PHP 7.2 o superiore
- Estensione mbstring abilitata
- Estensione gd abilitata
- Libreria TCPDF (installata tramite Composer)

## Installazione

1. Assicurarsi che i requisiti di sistema siano soddisfatti
2. Copiare i file del progetto nella directory webserver
3. Eseguire `composer install` per installare le dipendenze
4. Assicurarsi che la directory `pdf_output` esista e abbia permessi di scrittura

## Risoluzione dei Problemi

### Problemi Comuni

1. **Errore: "Failed to write PDF file"**
   - Verificare i permessi di scrittura nella directory `pdf_output`

2. **Errore: "Invalid JSON payload"**
   - Controllare il formato JSON inviato all'API

3. **Errore: "Invalid base64 data"**
   - Verificare che i dati base64 siano validi e rappresentino un PDF

4. **Errore: "Required fields missing"**
   - Assicurarsi di includere i campi obbligatori: `columns` e `rows`