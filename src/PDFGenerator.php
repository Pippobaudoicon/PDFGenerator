<?php

require_once(__DIR__ . '/DataSorter.php');

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
        require_once(dirname(__DIR__) . '/vendor/autoload.php');

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
                $dir = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'pdf_output';
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
