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
| `column_types` | object | - | Tipi di dati per le colonne (per formattazione) |
| `table_border` | number | `1` | Spessore del bordo tabella |
| `table_padding` | number | `5` | Padding delle celle in tabella |
| `header_bg` | string | `"#f2f2f2"` | Colore di sfondo intestazione tabella (formato hex) |
| `content_before` | string | - | Testo da inserire prima della tabella |
| `content_after` | string | - | Testo da inserire dopo la tabella |
| `group_by` | string/number | - | Colonna da utilizzare per raggruppamento |
| `sort_by` | string/number | - | Colonna da utilizzare per ordinamento |
| `sort_ascending` | boolean | `true` | Ordine crescente (`true`) o decrescente (`false`) |
| `output_mode` | string | `"B64"` | Modalità output: `"B64"` (Base64) o `"F"` (File) |
| `filename` | string | - | Nome del file PDF generato |

### Tipi di Dati Supportati per Colonne

Utilizzando il parametro `column_types`, è possibile specificare il formato di visualizzazione per ciascuna colonna:

| Tipo | Descrizione | Esempio |
|------|-------------|---------|
| `string` | Testo standard (default) | `"Testo esempio"` |
| `price` | Valore monetario | `"€ 1.234,56"` |
| `percentage` | Percentuale | `"12,34%"` |
| `number` | Numero intero formattato | `"1.234"` |
| `date` | Data formattata (gg/mm/aaaa) | `"01/01/2023"` |

### Definizione dei Tipi di Colonna

È possibile specificare i tipi di colonna in diversi modi:

1. **Per indice**: `{"column_types": {"0": "price", "2": "number"}}`
2. **Per nome**: `{"column_types": {"Prezzo": "price", "Quantità": "number"}}`
3. **Con metadati aggiuntivi**: `{"column_types": {"0": {"type": "price"}, "Totale": {"type": "price"}}}`

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
  "column_types": {
    "Prezzo": "price",
    "Quantità": "number",
    "Totale": "price"
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
  "column_types": {
    "Prezzo": "price",
    "Quantità": "number"
  },
  "group_by": "Categoria",
  "sort_by": "Prezzo",
  "sort_ascending": false
}
```

#### Esempio 3: Tabella Completa di funzionalità

```json
{
  "title": "Products Report",
  "orientation": "L",
  "author": "Sales Department",
  "margins": [10, 15, 10],

  "columns": ["Product", "Category", "Price", "Stock"],
  "rows": [
    ["Product A", "Electronics", 299.99, 45],
    ["Product B", "Furniture", 199.50, 12],
    ["Product C", "Electronics", 99.99, 200],
    ["Product D", "Clothing", 49.99, 75],
    ["Product E", "Furniture", 399.99, 8],
    ["Product F", "Clothing", 29.99, 150]
  ],
  
  "column_types": {
    "Price": "price",
    "Stock": "number"
  },
    
  "content_before": "<h2>Product Sales Summary</h2>",
  "content_after": "<p>Total Items: 35</p><p>Total Revenue: €7,599.65</p>",
  "format": "A4",

  "group_by": "Category",
  "sort_by": "Price",
  "sort_ascending": false,
  
  "table_border": 1,
  "table_padding": 6,
  "header_bg": "#e6f2ff",
  
  "output_mode": "B64",
  "filename": "product_sales_report.pdf"
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

### Raggruppamento per Categoria

La funzione `addTablesByCategoryPerPage` consente di:
- Raggruppare i dati per una colonna specifica
- Creare una tabella separata per ogni categoria
- Disporre ogni categoria su una nuova pagina
- Ordinare i dati all'interno di ciascuna categoria

### Formattazione dei Dati

La classe `DataSorter` gestisce:
- Ordinamento di dati per qualsiasi colonna
- Supporto per diversi tipi di dati (numeri, date, prezzi, ecc.)
- Confronti personalizzati basati sul tipo di dati

### Personalizzazione del Layout

È possibile personalizzare:
- Orientamento delle pagine (verticale/orizzontale)
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
    "column_types": {
      "Prezzo": "price"
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
  "column_types" => [
    "Prezzo" => "price"
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