<?php

require_once(__DIR__ . '/ColumnConfig.php');
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

    /** @var string PDF filename */
    private $filename;

    /** @var array Data to be rendered in the PDF */
    private $data;

    /** @var array Column configurations */
    private $columnConfigs = [];

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
     * Set column configurations for formatting
     * 
     * @param array $configs Column configurations can be specified in multiple formats:
     *                     - By index: [0 => ['type' => 'price'], 2 => ['type' => 'number']]
     *                     - By column name: ['Price' => ['type' => 'price'], 'Quantity' => ['type' => 'number']]
     *                     - With formatting: ['Price' => ['type' => 'price', 'align' => 'right', 'width' => '20mm']]
     * @return PDFGenerator
     */
    public function setColumnConfigs(array $configs)
    {
        // Clear existing column configs
        $this->columnConfigs = [];

        // Process the column configs into ColumnConfig objects
        foreach ($configs as $key => $value) {
            // If value is a simple string, it's just a type definition
            if (is_string($value)) {
                $this->columnConfigs[$key] = new ColumnConfig(['type' => $value]);
            }
            // If value is an array, it contains multiple properties
            else if (is_array($value)) {
                $this->columnConfigs[$key] = new ColumnConfig($value);
            }
        }

        return $this;
    }

    /**
     * Get column configurations
     * 
     * @return array Array of ColumnConfig objects
     */
    public function getColumnConfigs()
    {
        return $this->columnConfigs;
    }

    /**
     * Set or update configuration for a specific column
     * 
     * @param mixed $columnKey Column index or name
     * @param array|string $config Config array or type string
     * @return PDFGenerator
     */
    public function setColumnConfig($columnKey, $config)
    {
        // If config is just a string, treat it as the type
        if (is_string($config)) {
            $config = ['type' => $config];
        }

        // If column already has a config, update it
        if (isset($this->columnConfigs[$columnKey])) {
            $this->columnConfigs[$columnKey]->setProperties($config);
        } else {
            // Otherwise create a new config
            $this->columnConfigs[$columnKey] = new ColumnConfig($config);
        }

        return $this;
    }

    /**
     * Get the column configuration for a specific column
     * 
     * @param mixed $columnKey Column index or name
     * @return ColumnConfig Column configuration (or default if not found)
     */
    public function getColumnConfig($columnKey)
    {
        // Direct match by column index or name
        if (isset($this->columnConfigs[$columnKey])) {
            return $this->columnConfigs[$columnKey];
        }

        // If the key is a string (column name), try to find a matching column index
        if (is_string($columnKey) && isset($this->data['columns'])) {
            foreach ($this->data['columns'] as $index => $columnName) {
                if (strcasecmp($columnName, $columnKey) === 0 && isset($this->columnConfigs[$index])) {
                    return $this->columnConfigs[$index];
                }
            }
        }

        // If the key is a numeric index, try to find a matching column name
        if (is_numeric($columnKey) && isset($this->data['columns'][$columnKey])) {
            $columnName = $this->data['columns'][$columnKey];
            if (isset($this->columnConfigs[$columnName])) {
                return $this->columnConfigs[$columnName];
            }
        }

        // Return a default column configuration
        return new ColumnConfig();
    }

    /**
     * Set the filename for the PDF
     * 
     * @param string $filename Filename for the PDF
     * @return PDFGenerator
     */
    public function setFilename(string $filename = '')
    {
        if (empty($filename)) {
            // Generate a unique filename based on timestamp and random string
            $timestamp = date('Ymd_His') . '_' . substr(microtime(), 2, 3);

            $randomStr = substr(md5(uniqid(mt_rand(), true)), 0, 8);
            $this->filename = "document_{$timestamp}_{$randomStr}.pdf";
        } else {
            // Add timestamp to ensure uniqueness of the filename
            $timestamp = date('Ymd_His') . '_' . substr(microtime(), 2, 3);
            // Extract the filename without extension even if it doens't have one
            $pathInfo = pathinfo($filename);
            $name = $pathInfo['filename'];
            // Replace spaces with underscores and remove special characters in the filename
            $name = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $name));

            // Always use .pdf extension
            $this->filename = "{$name}_{$timestamp}.pdf";
        }
        return $this;
    }

    /**
     * Get the current filename
     * 
     * @return string Current filename
     */
    public function getFilename()
    {
        return $this->filename;
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
        $this->sorter->setColumnConfigs($this->columnConfigs);
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
        $showSummary = $options['show_summary'] ?? false;
        $summaryBg = $options['summary_bg'] ?? '#f9f9f9';
        $summaryLabelCol = $options['summary_label_col'] ?? 0;
        $summaryDefaultLabel = $options['summary_label'] ?? 'Total';

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

        foreach ($columns as $index => $column) {
            // Get column configuration for width only
            $columnConfig = $this->getColumnConfig($index);

            // Apply only column width if specified
            $widthAttr = '';
            if ($columnConfig->getWidth() !== null) {
                $widthAttr = ' width="' . $columnConfig->getWidth() . '"';
            }

            // Create header cell with center alignment - always centered regardless of column config
            $html .= sprintf(
                '<td%s style="text-align: center;">%s</td>',
                $widthAttr,
                htmlspecialchars((string)$column)
            );
        }

        $html .= '</tr>';

        // Add table rows
        foreach ($rows as $row) {
            $html .= '<tr>';

            // Ensure each row has the right number of columns
            $rowData = array_pad(is_array($row) ? $row : [], count($columns), '');

            foreach ($rowData as $index => $cell) {
                // Get column configuration for styling and formatting
                $columnConfig = $this->getColumnConfig($index);
                $styleAttr = $columnConfig->getStyleString();

                // Format cell value using the column configuration
                $formattedValue = $columnConfig->formatValue($cell);

                // Create data cell with styles
                $html .= sprintf(
                    '<td%s>%s</td>',
                    $styleAttr ? ' style="' . $styleAttr . '"' : '',
                    htmlspecialchars($formattedValue)
                );
            }

            $html .= '</tr>';
        }
        
        // Add summary row if enabled
        if ($showSummary && !empty($rows)) {
            $hasActiveOperations = false;
            $summaryValues = array_fill(0, count($columns), '');
            
            // First pass: Gather column values and calculate summaries
            foreach ($columns as $index => $column) {
                $columnConfig = $this->getColumnConfig($index);
                $operation = $columnConfig->getSummaryOperation();
                
                // Skip if no summary operation defined for this column
                if (empty($operation)) {
                    continue;
                }
                
                // Mark that we have at least one operation to show summary row
                $hasActiveOperations = true;
                
                // Extract values for this column from all rows
                $values = array_column($rows, $index);
                
                // Calculate summary value
                $summaryValue = $columnConfig->calculateSummaryValue($values);
                
                // Format the summary value according to the column type
                $formattedValue = $columnConfig->formatValue($summaryValue);
                
                // Get column-specific label if it exists
                $columnLabel = $columnConfig->getSummaryLabel();
                if ($columnLabel !== null) {
                    // If a column has its own summary label, add it before the value
                    $summaryValues[$index] = $columnLabel . ': ' . $formattedValue;
                } else {
                    // Otherwise just show the value
                    $summaryValues[$index] = $formattedValue;
                }
            }
            
            // Add the main summary label to the designated column if it doesn't have a specific operation
            if (empty($summaryValues[$summaryLabelCol])) {
                $summaryValues[$summaryLabelCol] = $summaryDefaultLabel;
            }
            
            // Add the summary row if any operations were performed
            if ($hasActiveOperations) {
                // Add summary row with slightly different background
                $html .= sprintf(
                    '<tr style="font-weight: bold; background-color: %s;">',
                    $summaryBg
                );
                
                foreach ($summaryValues as $index => $value) {
                    // Get the column styles
                    $columnConfig = $this->getColumnConfig($index);
                    $styleAttr = $columnConfig->getStyleString();
                    
                    // Add font-weight to always make summary row bold
                    $styleAttr = $styleAttr ? $styleAttr . '; font-weight: bold' : 'font-weight: bold';
                    
                    $html .= sprintf(
                        '<td%s>%s</td>',
                        $styleAttr ? ' style="' . $styleAttr . '"' : '',
                        htmlspecialchars((string)$value)
                    );
                }
                
                $html .= '</tr>';
            }
        }

        $html .= '</table>';

        // Write the table to the PDF
        $this->pdf->writeHTML($html, true, false, true, false, '');

        return $this;
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
     * Add spacing to the current page
     * 
     * @param int $spacing The amount of spacing to add in the current unit (usually mm)
     * @return PDFGenerator
     */
    public function addSpacing(int $spacing = 5)
    {
        $this->pdf->Ln($spacing);
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
        $this->sorter->setColumnConfigs($this->columnConfigs);
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
                $this->addSpacing(5);
            }
        }

        return $this;
    }

    /**
     * Add tables grouped by multiple categories in a hierarchical manner
     * 
     * @param array $categoryColumns Array of column indices or names to group by, in order of hierarchy
     * @param int|string|null $sortColumn Column index or name to sort by within each lowest-level category (optional)
     * @param bool $ascending Whether to sort in ascending order
     * @param array $options Table formatting options
     * @param bool $hasContentBefore Whether there is content before the first table
     * @return PDFGenerator
     */
    public function addTablesByMultipleCategories(array $categoryColumns, $sortColumn = null, bool $ascending = true, array $options = [], bool $hasContentBefore = false)
    {
        // Use internal DataSorter to group data by multiple categories
        $this->sorter->setData($this->data);
        $this->sorter->setColumnConfigs($this->columnConfigs);
        $groupedData = $this->sorter->byMultipleCategories($categoryColumns, $sortColumn, $ascending);

        // If no data groups were created, add an empty page to avoid errors
        if (empty($groupedData)) {
            if (!$hasContentBefore) {
                $this->addPage($this->config['orientation']);
            }
            return $this;
        }

        // Get the column names from the data for display
        $columns = $this->data['columns'] ?? [];

        // Resolve category column names for display
        $categoryColNames = [];
        foreach ($categoryColumns as $colKey) {
            if (is_numeric($colKey) && isset($columns[$colKey])) {
                $categoryColNames[] = $columns[$colKey];
            } else {
                $categoryColNames[] = $colKey;
            }
        }

        $pageOrientation = $this->config['orientation'];
        $isFirstCategory = true;

        // Get category column name for the top level
        $topCategoryName = $categoryColNames[0] ?? "Category";

        // Process each top-level category - add a page break for each one (except the first if hasContentBefore=true)
        foreach ($groupedData as $topCategory => $subData) {
            // For the first top-level category:
            // If there's already content on the page (hasContentBefore=true), don't add a new page
            // Otherwise, add the first page
            if ($isFirstCategory) {
                if (!$hasContentBefore) {
                    $this->addPage($pageOrientation);
                }
                $isFirstCategory = false;
            } else {
                // For subsequent top-level categories, always add a new page
                $this->addPage($pageOrientation);
            }

            // Add the top-level category title
            $this->addTitle("$topCategoryName: $topCategory");

            // Process nested levels for this top-level category
            $processNestedCategories = function($data, $level = 1, $path = []) use (
                &$processNestedCategories, 
                $categoryColNames, 
                $columns, 
                $options,
                $topCategory
            ) {
                if (empty($data)) {
                    return;
                }

                $currentCategoryName = $categoryColNames[$level] ?? "Category";

                foreach ($data as $category => $contents) {
                    // Build the current path for display
                    $currentPath = array_merge($path, [$category]);
                    
                    // Build the title with full path
                    $title = "$currentCategoryName: $category";
                    if (!empty($path)) {
                        $title = implode(" -> ", $path) . " -> $title";
                    }

                    // Process leaf nodes (actual data) or continue recursion
                    if (isset($contents[0])) {
                        // This is a leaf node with rows
                        $this->addTitle($title);
                        $this->addTable($columns, $contents, $options);
                        $this->pdf->Ln(5); // Add spacing after table
                    } else {
                        // This is an intermediate node, add title and process children
                        $this->addTitle($title);
                        $processNestedCategories($contents, $level + 1, $currentPath);
                    }
                }
            };

            // Process all nested categories under this top-level category
            $processNestedCategories($subData, 1, [$topCategory]);
        }

        return $this;
    }
}
