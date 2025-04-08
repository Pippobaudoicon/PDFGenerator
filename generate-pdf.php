<?php
// Set headers for API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

// Get JSON payload
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate input
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit();
}

try {    
    // Configure the PDF generator
    $config = [
        'orientation' => $data['orientation'] ?? 'P',
        'title' => $data['title'] ?? 'Generated Document',
        'author' => $data['author'] ?? 'PDF Generator API',
        'margins' => $data['margins'] ?? [15, 15, 15],
        'title_font_size' => $data['title_font_size'] ?? 16,
        'content_font_size' => $data['content_font_size'] ?? 10,
        'format' => $data['format'] ?? 'A4', // Add format option (A4, A3, A0, etc.)
    ];
    
    // Initialize the PDF generator
    $generator = new PDFGenerator($config);
    
    // Check for required fields
    if (!isset($data['columns']) || !isset($data['rows'])) {
        throw new Exception('Required fields missing: columns, rows');
    }
    
    // Set data
    $generator->setData([
        'columns' => $data['columns'],
        'rows' => $data['rows']
    ]);

    // Set column types if provided - now supports associative arrays
    if (isset($data['column_types'])) {
        $generator->setColumnTypes($data['column_types']);
    }

    // Table options for both grouped and non-grouped tables
    $tableOptions = [
        'border' => $data['table_border'] ?? 1,
        'padding' => $data['table_padding'] ?? 5,
        'header_bg' => $data['header_bg'] ?? '#f2f2f2',
    ];

    // Check if we should group by category
    if (isset($data['group_by'])) {
        // Get the group by column
        $groupByColumn = $data['group_by'];
        
        // Get the optional sort column for sorting within each category
        $sortColumn = $data['sort_by'] ?? null;
        $sortAscending = $data['sort_ascending'] ?? true;
        
        // Create the first page for the document if there's content_before
        // Otherwise, let addTablesByCategoryPerPage add the first page
        $hasContentBefore = isset($data['content_before']);
        $pageOrientation = $data['orientation'] ?? $config['orientation'];
        
        if ($hasContentBefore) {
            // Add first page for the content
            $generator->addPage($pageOrientation);
            
            // Add title if provided
            if (isset($data['title'])) {
                $generator->addTitle($data['title']);
            }
            
            $generator->addText($data['content_before']);
            
            // Don't add a page break here - the first category should appear
            // on this same page right after the content_before text
        }
        
        // Add tables grouped by category, specifying if there's already content on the first page
        $generator->addTablesByCategoryPerPage(
            $groupByColumn, 
            $sortColumn, 
            $sortAscending, 
            $tableOptions,
            $hasContentBefore
        );
        
        // Check if we should add content after all tables
        if (isset($data['content_after'])) {
            // Add a new page for content after
            $generator->addPage($pageOrientation);
            $generator->addText($data['content_after']);
        }
    } else {
        // Regular sorting (not grouped by category)
        if (isset($data['sort_by'])) {
            $generator->sortData(
                $data['sort_by'],
                $data['sort_ascending'] ?? true
            );
        }
        
        // Create a single page PDF
        $pageOrientation = $data['orientation'] ?? $config['orientation'];
        $generator->addPage($pageOrientation);
        
        // Add title if provided
        if (isset($data['title'])) {
            $generator->addTitle($data['title']);
        }
        
        // Add content before table if specified
        if (isset($data['content_before'])) {
            $generator->addText($data['content_before']);
        }
        
        // Add table 
        $generator->addTable(null, null, $tableOptions);
        
        // Add content after table if specified
        if (isset($data['content_after'])) {
            $generator->addText($data['content_after']);
        }
    }
    
    // Generate output based on requested format
    $outputMode = $data['output_mode'] ?? 'B64'; // B64, F
    $filename = $data['filename'] ?? '';
    
    $result = $generator->output($outputMode, $filename);
    
    // Return JSON response
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'PDF generation failed: ' . $e->getMessage()]);
    exit();
}

/**
 * PDF Generator Class
 * 
 * A flexible and feature-rich class for generating PDF documents with tables,
 * multiple pages, and various formatting options.
 */
class PDFGenerator
{
    /** @var TCPDF The TCPDF instance */
    private $pdf;
    
    /** @var array Configuration options */
    private $config;

    /** @var DataSorter Data manipulation helper */
    private $sorter;
    
    /** @var array Default configuration options */
    private $defaultConfig = [
        'orientation' => 'P',           // P = Portrait, L = Landscape
        'unit' => 'mm',                 // mm, cm, in
        'format' => 'A4',               // A4, A3, A0, etc.
        'unicode' => true,
        'encoding' => 'UTF-8',
        'diskcache' => false,
        'margins' => [15, 15, 15],      // left, top, right
        'auto_page_break' => true,
        'page_break_margin' => 15,
        'creator' => 'PDF Generator API',
        'author' => 'PDF Generator',
        'title' => 'Generated Document',
        'subject' => 'Generated Document',
        'show_header' => false,
        'show_footer' => false,
        'title_font_size' => 16,
        'content_font_size' => 10,
        'table_border' => 1,
        'table_padding' => 5,
    ];
    
    /** @var array Data to be rendered in the PDF */
    private $data;
    
    /** @var array Column types for formatting */
    private $columnTypes = [];
    
    /**
     * Constructor - Initialize the PDF generator with configuration
     * 
     * @param array $config Configuration options to override defaults
     */
    public function __construct(array $config = [])
    {
        // Load TCPDF library
        require_once('vendor/autoload.php');
        
        // Merge provided config with defaults
        $this->config = array_merge($this->defaultConfig, $config);
        
        // Initialize TCPDF instance
        $this->initializePDF();
        
        // Create the data sorter instance
        $this->sorter = new DataSorter();
    }
    
    /**
     * Initialize the TCPDF instance with configuration
     */
    private function initializePDF()
    {
        $this->pdf = new TCPDF(
            $this->config['orientation'],
            $this->config['unit'],
            $this->config['format'],
            $this->config['unicode'],
            $this->config['encoding'],
            $this->config['diskcache']
        );
        
        // Set document properties
        $this->pdf->SetCreator($this->config['creator']);
        $this->pdf->SetAuthor($this->config['author']);
        $this->pdf->SetTitle($this->config['title']);
        $this->pdf->SetSubject($this->config['subject']);
        
        // Configure header and footer
        $this->pdf->setPrintHeader($this->config['show_header']);
        $this->pdf->setPrintFooter($this->config['show_footer']);
        
        // Set margins
        $this->pdf->SetMargins(
            $this->config['margins'][0], 
            $this->config['margins'][1], 
            $this->config['margins'][2]
        );
        
        // Set auto page break
        $this->pdf->SetAutoPageBreak(
            $this->config['auto_page_break'],
            $this->config['page_break_margin']
        );
    }
    
    /**
     * Set data for the PDF
     * 
     * @param array $data Data to be rendered in the PDF (columns and rows)
     * @return PDFGenerator
     */
    public function setData(array $data)
    {
        if (!isset($data['columns']) || !isset($data['rows'])) {
            throw new Exception('Data must contain "columns" and "rows" keys');
        }
        
        $this->data = $data;
        return $this;
    }
    
    /**
     * Set column types for formatting
     * 
     * @param array $types Column types can be specified in multiple formats:
     *                     - By index: [0 => 'price', 2 => 'number']
     *                     - By column name: ['Price' => 'price', 'Quantity' => 'number']
     *                     - With metadata: [0 => ['type' => 'price'], 'Total' => ['type' => 'price']]
     * @return PDFGenerator
     */
    public function setColumnTypes(array $types)
    {
        // Process the column types into a normalized format
        $normalizedTypes = [];
        
        foreach ($types as $key => $value) {
            // If value is a string, it's a simple type definition
            if (is_string($value)) {
                $normalizedTypes[$key] = ['type' => $value];
            } 
            // If value is an array, it already has metadata
            else if (is_array($value)) {
                $normalizedTypes[$key] = $value;
            }
        }
        
        $this->columnTypes = $normalizedTypes;
        return $this;
    }
    
    /**
     * Get column types
     * 
     * @return array Column types
     */
    public function getColumnTypes()
    {
        return $this->columnTypes;
    }
    
    /**
     * Sort data by a specific column
     * 
     * @param int|string $columnIndex Column index or name to sort by
     * @param bool $ascending Whether to sort in ascending order
     * @return PDFGenerator
     */
    public function sortData($columnIndex, bool $ascending = true)
    {
        $this->sorter->setData($this->data);
        $this->sorter->setColumnTypes($this->columnTypes);
        $this->sorter->byColumn($columnIndex, $ascending);
        $this->data = $this->sorter->getData();
        
        return $this;
    }
    
    /**
     * Get the current data
     * 
     * @return array Current data
     */
    public function getData()
    {
        return $this->data;
    }
    
    /**
     * Add a new page to the PDF
     * 
     * @param string $orientation Page orientation (P or L)
     * @param string $format Override the page format (A4, A3, etc.)
     * @return PDFGenerator
     */
    public function addPage(string $orientation = '', string $format = '')
    {
        // If format is provided, use it; otherwise use the default
        if (!empty($format)) {
            $this->pdf->AddPage($orientation, $format);
        } else {
            $this->pdf->AddPage($orientation);
        }
        return $this;
    }
    
    /**
     * Add a title to the current page
     * 
     * @param string $title Title text
     * @param int $fontSize Font size for the title
     * @param int $spacing Spacing after the title
     * @return PDFGenerator
     */
    public function addTitle(string $title, int $fontSize = null, int $spacing = 5)
    {
        $fontSize = $fontSize ?? $this->config['title_font_size'];
        
        $this->pdf->SetFont('helvetica', 'B', $fontSize);
        $this->pdf->Cell(0, 10, $title, 0, 1, 'C');
        $this->pdf->Ln($spacing);
        
        return $this;
    }
    
    /**
     * Add a table to the current page
     * 
     * @param array|null $columns Column names (if null, use from data)
     * @param array|null $rows Row data (if null, use from data)
     * @param array $options Table formatting options
     * @return PDFGenerator
     */
    public function addTable(array $columns = null, array $rows = null, array $options = [])
    {
        // Use provided data or fall back to stored data
        $columns = $columns ?? $this->data['columns'] ?? [];
        $rows = $rows ?? $this->data['rows'] ?? [];
        
        // Table options
        $border = $options['border'] ?? $this->config['table_border'];
        $padding = $options['padding'] ?? $this->config['table_padding'];
        $headerBg = $options['header_bg'] ?? '#f2f2f2';
        
        // Set font for table content
        $this->pdf->SetFont('helvetica', '', $this->config['content_font_size']);
        
        // Initialize table HTML
        $html = sprintf(
            '<table border="%d" cellpadding="%d" cellspacing="0">',
            $border,
            $padding
        );
        
        // Add table header
        $html .= sprintf(
            '<tr style="font-weight: bold; background-color: %s;">',
            $headerBg
        );
        
        foreach ($columns as $column) {
            $html .= '<td>' . htmlspecialchars((string)$column) . '</td>';
        }
        
        $html .= '</tr>';
        
        // Add table rows
        foreach ($rows as $row) {
            $html .= '<tr>';
            
            // Ensure each row has the right number of columns
            $rowData = array_pad(is_array($row) ? $row : [], count($columns), '');
            
            foreach ($rowData as $index => $cell) {
                // Format cell value based on column type
                $formattedValue = $this->formatCellValue($cell, $index);
                
                $html .= '<td>' . htmlspecialchars($formattedValue) . '</td>';
            }
            
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        
        // Write the table to the PDF
        $this->pdf->writeHTML($html, true, false, true, false, '');
        
        return $this;
    }
    
    /**
     * Format cell value based on column type
     * 
     * @param mixed $value Cell value
     * @param int|string $columnIndex Column index
     * @return string Formatted value
     */
    private function formatCellValue($value, $columnIndex)
    {
        // Get column type (defaults to 'string')
        $columnType = $this->getColumnType($columnIndex);
        
        switch ($columnType) {
            case 'price':
                if (is_numeric($value)) {
                    // Format as price with euro symbol
                    return '€ ' . number_format((float)$value, 2, ',', '.');
                }
                break;
                
            case 'string':
                // For string type, ensure value is a string
                if (!is_string($value)) {
                    return (string)$value;
                }
                break;
            
            case 'percentage':
                if (is_numeric($value)) {
                    // Format as percentage
                    return number_format((float)$value, 2) . '%';
                }
                break;

            case 'number':
                if (is_numeric($value)) {
                    // Format as number
                    return number_format((float)$value, 0, ',', '.');
                }
                break;

            case 'date':
                if ($value instanceof DateTime) {
                    // Format as date
                    return $value->format('d/m/Y');
                } else if (is_string($value)) {
                    // Try to parse string as date
                    $date = DateTime::createFromFormat('Y-m-d', $value);
                    if ($date) {
                        return $date->format('d/m/Y');
                    }
                }
                break;

            default:                
                break;
        }
        
        // Default formatting when no specific type handling applies
        if (!is_string($value)) {
            return json_encode($value);
        }
        
        return $value;
    }
    
    /**
     * Get the type for a specific column
     * 
     * @param mixed $columnKey Column index or name
     * @return string The column type (defaults to 'string' if not defined)
     */
    private function getColumnType($columnKey)
    {
        // Direct match by column index or name
        if (isset($this->columnTypes[$columnKey])) {
            return $this->columnTypes[$columnKey]['type'] ?? 'string';
        }
        
        // If the key is a string (column name), try to find a matching column index
        if (is_string($columnKey) && isset($this->data['columns'])) {
            foreach ($this->data['columns'] as $index => $columnName) {
                if (strcasecmp($columnName, $columnKey) === 0 && isset($this->columnTypes[$index])) {
                    return $this->columnTypes[$index]['type'] ?? 'string';
                }
            }
        }
        
        // If the key is a numeric index, try to find a matching column name
        if (is_numeric($columnKey) && isset($this->data['columns'][$columnKey])) {
            $columnName = $this->data['columns'][$columnKey];
            if (isset($this->columnTypes[$columnName])) {
                return $this->columnTypes[$columnName]['type'] ?? 'string';
            }
        }
        
        // Default to string type if no type is specified
        return 'string';
    }
    
    /**
     * Add text to the current page
     * 
     * @param string $text Text to add
     * @param array $options Text formatting options
     * @return PDFGenerator
     */
    public function addText(string $text, array $options = [])
    {
        $fontSize = $options['font_size'] ?? $this->config['content_font_size'];
        $fontStyle = $options['font_style'] ?? '';
        
        // Check if the text is already HTML (contains any HTML tags)
        $isHtml = preg_match('/<[^>]+>/', $text) > 0;
        
        // Only wrap in HTML tags if it's not already HTML
        if (!$isHtml) {
            $text = '<p>' . $text . '</p>';
        }
        
        $this->pdf->SetFont('helvetica', $fontStyle, $fontSize);
        $this->pdf->writeHTML($text, true, false, true, false, '');
        
        return $this;
    }
    
    /**
     * Generate the PDF and return the result
     * 
     * @param string $outputMode Output mode (B64 = base64, I = inline, D = download, F = file)
     * @param string $filename Filename for download or save modes
     * @return mixed Base64 string, file path, or direct output depending on mode
     */
    public function output(string $outputMode = 'B64', string $filename = '')
    {
        // Default filename based on title if not provided
        if (empty($filename)) {
            $filename = strtolower(str_replace(' ', '_', $this->config['title'])) . '.pdf';
        }
        
        // Handle different output modes
        switch ($outputMode) {
            case 'B64':
                // Base64 output
                $pdfString = $this->pdf->Output('', 'S');
                return [
                    'success' => true,
                    'message' => 'PDF generated successfully',
                    'filename' => $filename,
                    'base64' => base64_encode($pdfString)
                ];
            
            case 'F':
                // File output
                $dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'pdf_output';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                
                $filepath = $dir . DIRECTORY_SEPARATOR . $filename;
                $this->pdf->Output($filepath, 'F');
                
                return [
                    'success' => true,
                    'message' => 'PDF saved to file successfully',
                    'file_path' => $filepath
                ];
            
            default:
                throw new Exception("Invalid output mode: $outputMode");
        }
    }
    
    /**
     * Add tables grouped by category, with each category on a new page
     * 
     * @param int|string $categoryColumn Column index or name to group by
     * @param int|string|null $sortColumn Column index or name to sort by within each category (optional)
     * @param bool $ascending Whether to sort in ascending order
     * @param array $options Table formatting options
     * @param bool $hasContentBefore Whether there is content before the first table
     * @return PDFGenerator
     */
    public function addTablesByCategoryPerPage($categoryColumn, $sortColumn = null, bool $ascending = true, array $options = [], bool $hasContentBefore = false)
    {
        // Use internal DataSorter to group data by category
        $this->sorter->setData($this->data);
        $this->sorter->setColumnTypes($this->columnTypes);
        $groupedData = $this->sorter->byCategory($categoryColumn, $sortColumn, $ascending);
        
        // If no data groups were created, add an empty page to avoid errors
        if (empty($groupedData)) {
            if (!$hasContentBefore) {
                $this->addPage($this->config['orientation']);
            }
            return $this;
        }
        
        // Get the column names from the data
        $columns = $this->data['columns'] ?? [];
        
        // Get the category column name for display
        $categoryColName = is_numeric($categoryColumn) ? 
            ($columns[$categoryColumn] ?? "Category") : 
            $categoryColumn;
        
        $pageOrientation = $this->config['orientation'];
        $isFirstCategory = true;
        
        // Loop through each category and create a table
        foreach ($groupedData as $category => $rows) {
            // Skip empty categories
            if (empty($rows)) {
                continue;
            }
            
            // For the first category:
            // If there's already content on the page (hasContentBefore=true), don't add a new page
            // Otherwise, add the first page
            if ($isFirstCategory) {
                if (!$hasContentBefore) {
                    $this->addPage($pageOrientation);
                }
                $isFirstCategory = false;
            } else {
                // For subsequent categories, always add a new page
                $this->addPage($pageOrientation);
            }
            
            // Add the category as a title
            $this->addTitle("$categoryColName: $category");
            
            // Add the table for this category
            $this->addTable($columns, $rows, $options);
            
            // Add a spacer after the table (but don't call Ln on the last page to prevent errors)
            if (next($groupedData) !== false) {
                $this->pdf->Ln(5);
            }
        }
        
        return $this;
    }
}

/**
 * DataSorter Class
 * 
 * Helper class for sorting and grouping data
 */
class DataSorter
{
    /** @var array Data to be sorted */
    private $data;
    
    /** @var array Column types for sorting */
    private $columnTypes = [];
    
    /**
     * Set data for sorting
     * 
     * @param array $data Data to be sorted
     * @return DataSorter
     */
    public function setData(array $data)
    {
        $this->data = $data;
        return $this;
    }
    
    /**
     * Get the current data
     * 
     * @return array Current data
     */
    public function getData()
    {
        return $this->data;
    }
    
    /**
     * Set column types for sorting
     * 
     * @param array $types Array of column types
     * @return DataSorter
     */
    public function setColumnTypes(array $types)
    {
        $this->columnTypes = $types;
        return $this;
    }
    
    /**
     * Perform sorting operation on the data
     * 
     * @param int|string $columnIndex Column index or name to sort by
     * @param bool $ascending Whether to sort in ascending order
     * @return DataSorter
     */
    public function byColumn($columnIndex, bool $ascending = true)
    {
        if (!isset($this->data['rows']) || !is_array($this->data['rows'])) {
            throw new Exception("No data rows found to sort");
        }
        
        $rows = $this->data['rows'];
        
        // Get the column index if a column name is provided
        if (is_string($columnIndex)) {
            $originalColumnName = $columnIndex; // Store original name for error
            
            if (!isset($this->data['columns']) || !is_array($this->data['columns'])) {
                throw new Exception("No columns defined to find column '$originalColumnName'");
            }
            
            $columnNames = $this->data['columns'];
            
            // Case-insensitive column search
            $found = false;
            foreach ($columnNames as $idx => $name) {
                if (strcasecmp($name, $columnIndex) === 0) {
                    $columnIndex = $idx;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                throw new Exception("Column '$originalColumnName' not found in: " . implode(', ', $columnNames));
            }
        }
        
        // Sort the rows
        usort($rows, function($a, $b) use ($columnIndex, $ascending) {
            // Make sure arrays have the index
            if (!isset($a[$columnIndex]) && !isset($b[$columnIndex])) {
                return 0;
            }
            
            // Handle cases where one value exists and other doesn't
            if (!isset($a[$columnIndex])) return $ascending ? -1 : 1;
            if (!isset($b[$columnIndex])) return $ascending ? 1 : -1;
            
            $valueA = $a[$columnIndex];
            $valueB = $b[$columnIndex];
            
            // Get column type if available
            $columnType = $this->getColumnType($columnIndex);
            
            return $this->compareValues($valueA, $valueB, $ascending, $columnType);
        });
        
        $this->data['rows'] = $rows;
        return $this;
    }

        
    /**
     * Group data by a specific category column and sort within each category
     * 
     * @param int|string $categoryColumn Column index or name to group by
     * @param int|string|null $sortColumn Column index or name to sort by within each category (optional)
     * @param bool $ascending Whether to sort in ascending order
     * @return array Grouped data with categories as keys and rows as values
     */
    public function byCategory($categoryColumn, $sortColumn = null, bool $ascending = true)
    {
        if (!isset($this->data['rows']) || !is_array($this->data['rows'])) {
            throw new Exception("No data rows found to group");
        }
        
        // Get the column index if a column name is provided for category column
        if (is_string($categoryColumn)) {
            $categoryColumn = $this->findColumnIndex($categoryColumn);
        }
        
        // Get the column index if a column name is provided for sort column
        if ($sortColumn !== null && is_string($sortColumn)) {
            $sortColumn = $this->findColumnIndex($sortColumn);
        }
        
        // Group the rows by category
        $groupedData = [];
        foreach ($this->data['rows'] as $row) {
            // Get the category value and ensure it's a string
            $categoryValue = isset($row[$categoryColumn]) ? (string)$row[$categoryColumn] : 'Uncategorized';
            
            // Initialize the category array if it doesn't exist
            if (!isset($groupedData[$categoryValue])) {
                $groupedData[$categoryValue] = [];
            }
            
            // Add the row to its category
            $groupedData[$categoryValue][] = $row;
        }
        
        // Sort rows within each category if a sort column is specified
        if ($sortColumn !== null) {
            foreach ($groupedData as $category => $rows) {
                if (count($rows) > 1) {
                    // Sort the rows in this category
                    usort($rows, function($a, $b) use ($sortColumn, $ascending) {
                        // Handle cases where values don't exist
                        if (!isset($a[$sortColumn]) && !isset($b[$sortColumn])) {
                            return 0;
                        }
                        if (!isset($a[$sortColumn])) return $ascending ? -1 : 1;
                        if (!isset($b[$sortColumn])) return $ascending ? 1 : -1;
                        
                        $valueA = $a[$sortColumn];
                        $valueB = $b[$sortColumn];
                        
                        // Get column type for proper comparison
                        $columnType = $this->getColumnType($sortColumn);
                        
                        return $this->compareValues($valueA, $valueB, $ascending, $columnType);
                    });
                    
                    // Update the sorted rows in the grouped data
                    $groupedData[$category] = $rows;
                }
            }
        }
        
        // Sort the categories alphabetically if needed
        ksort($groupedData);
        
        return $groupedData;
    }
    
    /**
     * Compare two values based on their type
     * 
     * @param mixed $valueA First value
     * @param mixed $valueB Second value
     * @param bool $ascending Sort direction
     * @param string|null $columnType Column type
     * @return int Comparison result (-1, 0, 1)
     */
    public function compareValues($valueA, $valueB, bool $ascending, $columnType = null)
    {
        // If no column type provided, get it from the column definitions
        if ($columnType === null) {
            $columnType = 'string'; // Default to string
        }
        
        switch ($columnType) {
            case 'price':
                return $this->compareNumeric($valueA, $valueB, $ascending);
                
            case 'number':
                return $this->compareNumeric($valueA, $valueB, $ascending);
                
            case 'percentage':
                // Remove % symbols if present and compare as numeric
                $numA = is_string($valueA) ? str_replace('%', '', $valueA) : $valueA;
                $numB = is_string($valueB) ? str_replace('%', '', $valueB) : $valueB;
                return $this->compareNumeric($numA, $numB, $ascending);
                
            case 'date':
                return $this->compareDates($valueA, $valueB, $ascending);
                
            case 'string':
                // For explicit string type, force string comparison
                return $this->compareStrings($valueA, $valueB, $ascending);
        }
        
        // Default to string comparison
        return $this->compareStrings($valueA, $valueB, $ascending);
    }
    
    /**
     * Compare price values
     */
    private function comparePrices($valueA, $valueB, bool $ascending)
    {
        // Remove currency symbols and convert to float
        $numericA = (float) preg_replace('/[^\d.]/', '', $valueA);
        $numericB = (float) preg_replace('/[^\d.]/', '', $valueB);
        
        return $ascending ? ($numericA <=> $numericB) : ($numericB <=> $numericA);
    }
    
    /**
     * Compare numeric values
     */
    private function compareNumeric($valueA, $valueB, bool $ascending)
    {
        $numericA = (float) $valueA;
        $numericB = (float) $valueB;
        
        return $ascending ? ($numericA <=> $numericB) : ($numericB <=> $numericA);
    }
    
    /**
     * Compare string values
     */
    private function compareStrings($valueA, $valueB, bool $ascending)
    {
        return $ascending ? 
            strcmp((string)$valueA, (string)$valueB) : 
            strcmp((string)$valueB, (string)$valueA);
    }
    
    /**
     * Compare date values
     * 
     * @param mixed $valueA First date value
     * @param mixed $valueB Second date value
     * @param bool $ascending Sort direction
     * @return int Comparison result (-1, 0, 1)
     */
    private function compareDates($valueA, $valueB, bool $ascending)
    {
        // Convert dates to DateTime objects if they're not already
        $dateA = $this->ensureDateTime($valueA);
        $dateB = $this->ensureDateTime($valueB);
        
        // If either value couldn't be converted to a date, fall back to string comparison
        if (!$dateA || !$dateB) {
            return $this->compareStrings((string)$valueA, (string)$valueB, $ascending);
        }
        
        // Compare the dates
        $comparison = $dateA <=> $dateB;
        
        return $ascending ? $comparison : -$comparison;
    }
    
    /**
     * Convert a value to a DateTime object
     * 
     * @param mixed $value Value to convert
     * @return DateTime|null DateTime object or null if conversion failed
     */
    private function ensureDateTime($value)
    {
        // If already a DateTime object, return it
        if ($value instanceof DateTime) {
            return $value;
        }
        
        // If string, try to convert to DateTime
        if (is_string($value)) {
            // Try standard formats
            $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'Y-m-d H:i:s', 'd-m-Y', 'Y/m/d'];
            
            foreach ($formats as $format) {
                $date = DateTime::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date;
                }
            }
            
            // Try strtotime as a fallback
            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return new DateTime('@' . $timestamp);
            }
        }
        
        // If numeric, treat as timestamp
        if (is_numeric($value)) {
            return new DateTime('@' . $value);
        }
        
        return null;
    }
    
    /**
     * Get the type for a specific column
     * 
     * @param mixed $columnKey Column index or name
     * @return string The column type (defaults to 'string' if not defined)
     */
    private function getColumnType($columnKey)
    {
        // Direct match by column index or name
        if (isset($this->columnTypes[$columnKey])) {
            return $this->columnTypes[$columnKey]['type'] ?? 'string';
        }
        
        // If the key is a string (column name), try to find a matching column index
        if (is_string($columnKey) && isset($this->data['columns'])) {
            foreach ($this->data['columns'] as $index => $columnName) {
                if (strcasecmp($columnName, $columnKey) === 0 && isset($this->columnTypes[$index])) {
                    return $this->columnTypes[$index]['type'] ?? 'string';
                }
            }
        }
        
        // If the key is a numeric index, try to find a matching column name
        if (is_numeric($columnKey) && isset($this->data['columns'][$columnKey])) {
            $columnName = $this->data['columns'][$columnKey];
            if (isset($this->columnTypes[$columnName])) {
                return $this->columnTypes[$columnName]['type'] ?? 'string';
            }
        }
        
        // Default to string type if no type is specified
        return 'string';
    }
    
    /**
     * Find the column index by name
     * 
     * @param string $columnName Column name to find
     * @return int Column index
     * @throws Exception if column not found
     */
    private function findColumnIndex($columnName)
    {
        if (!isset($this->data['columns']) || !is_array($this->data['columns'])) {
            throw new Exception("No columns defined to find column '$columnName'");
        }
        
        // Case-insensitive column search
        foreach ($this->data['columns'] as $idx => $name) {
            if (strcasecmp($name, $columnName) === 0) {
                return $idx;
            }
        }
        
        throw new Exception("Column '$columnName' not found in: " . implode(', ', $this->data['columns']));
    }
}
?>