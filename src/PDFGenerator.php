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
        'title_level_reduction' => 2,  // Font size reduction per level in hierarchical titles
        'title_min_font_size' => 10,     // Minimum font size for hierarchical titles
    ];

    /** @var string PDF filename */
    private $filename;

    /** @var array Data to be rendered in the PDF */
    private $data;

    /** @var array Column configurations */
    private $columnConfigs = [];

    /** @var array|null Header/logo configuration */
    private $headerLogo = null;

    /**
     * Constructor - Initialize the PDF generator with configuration
     * 
     * @param array $config Configuration options to override defaults
     */
    public function __construct(array $config = [])
    {
        // Load TCPDF library
        require_once(dirname(__DIR__) . '/vendor/autoload.php');
        require_once(__DIR__ . '/constants.php');

        // Merge provided config with defaults
        $this->config = array_merge($this->defaultConfig, $config);

        // Store header/logo config if present
        if (isset($this->config['header_logo'])) {
            $this->headerLogo = $this->config['header_logo'];
        }

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
     * @throws Exception If data doesn't contain required keys
     */
    public function setData(array $data): PDFGenerator
    {
        if (!isset($data['columns']) || !isset($data['rows'])) {
            throw new Exception('Data must contain "columns" and "rows" keys');
        }

        // Check if rows contain associative arrays and normalize them if needed
        if (isset($data['columns']) && isset($data['rows']) && is_array($data['rows'])) {
            $data['rows'] = $this->normalizeRowsFormat($data['rows'], $data['columns']);
        }

        $this->data = $data;
        return $this;
    }

    /**
     * Set column configurations for formatting
     * 
     * @param array $configs Column configurations can be specified in multiple formats
     * @return PDFGenerator
     */
    public function setColumnConfigs(array $configs): PDFGenerator
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
    public function getColumnConfigs(): array
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
    public function setColumnConfig($columnKey, $config): PDFGenerator
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
    public function getColumnConfig($columnKey): ColumnConfig
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
    public function setFilename(string $filename = ''): PDFGenerator
    {
        if (empty($filename)) {
            // Generate a unique filename based on timestamp and random string
            $timestamp = date('Ymd_His') . '_' . substr(microtime(), 2, 3);
            $randomStr = substr(md5(uniqid(mt_rand(), true)), 0, 8);
            $this->filename = "document_{$timestamp}_{$randomStr}.pdf";
        } else {
            // Add timestamp to ensure uniqueness of the filename
            $timestamp = date('Ymd_His') . '_' . substr(microtime(), 2, 3);
            
            // Extract the filename without extension
            $pathInfo = pathinfo($filename);
            $name = $pathInfo['filename'];
            
            // Clean filename - replace spaces with underscores and remove special characters
            $name = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $name));

            // Always use .pdf extension
            $this->filename = "{$timestamp}-{$name}.pdf";
        }
        return $this;
    }

    /**
     * Get the current filename
     * 
     * @return string Current filename
     */
    public function getFilename(): string
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
    public function sortData($columnIndex, bool $ascending = true): PDFGenerator
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
    public function getData(): array
    {
        return $this->data ?? [];
    }

    /**
     * Add a new page to the PDF
     * 
     * @param string $orientation Page orientation (P or L)
     * @param string $format Override the page format (A4, A3, etc.)
     * @return PDFGenerator
     */
    public function addPage(string $orientation = '', string $format = ''): PDFGenerator
    {
        // If format is provided, use it; otherwise use the default
        if (!empty($format)) {
            $this->pdf->AddPage($orientation, $format);
        } else {
            $this->pdf->AddPage($orientation);
        }
        // Draw header/logo if configured
        if ($this->headerLogo) {
            $this->drawHeaderLogo();
        }
        return $this;
    }

    /**
     * Draws the header logo/image on the current page if configured
     */
    private function drawHeaderLogo()
    {
        $logo = $this->headerLogo;
        if (empty($logo['image'])) return;
        $img = $logo['image'];
        $height = isset($logo['height']) ? floatval($logo['height']) : 20;
        $align = isset($logo['align']) ? strtolower($logo['align']) : 'right';
        $margin = 5;
        // Default position: top-right
        $x = null;
        $y = $this->config['margins'][1];
        // Get page width and margins
        $pageWidth = $this->pdf->getPageWidth();
        $leftMargin = $this->config['margins'][0];
        $rightMargin = $this->config['margins'][2];
        // Calculate X based on alignment
        if ($align === 'left') {
            $x = $leftMargin + $margin;
        } else { // right or default
            // Estimate image width (TCPDF can auto-scale, but we need a guess for placement)
            $imgWidth = $height * 2; // assume logo is wider than tall
            $x = $pageWidth - $rightMargin - $imgWidth - $margin;
        }
        // Draw image (TCPDF will keep aspect ratio if width is 0)
        $this->pdf->Image($img, $x, $y, 0, $height, '', '', '', false, 300, '', false, false, 0, false, false, false);
    }

    /**
     * Add a title to the current page
     * 
     * @param string $title Title text
     * @param int|null $fontSize Font size for the title
     * @param int $spacing Spacing after the title
     * @return PDFGenerator
     */
    public function addTitle(string $title, ?int $fontSize = null, int $spacing = 0): PDFGenerator
    {
        $fontSize = $fontSize ?? $this->config['title_font_size'];

        $this->pdf->SetFont('helvetica', 'B', $fontSize);
        $this->pdf->Cell(0, 10, $title, 0, 1, 'C');
        $this->pdf->Ln($spacing);

        return $this;
    }

    /**
     * Calculate appropriate font size based on nesting level
     * 
     * @param int $level Nesting level (0 for top level)
     * @return int Font size
     */
    private function calculateTitleFontSize(int $level): int
    {
        $baseFontSize = $this->config['title_font_size'];
        $levelReduction = $this->config['title_level_reduction'];
        $minFontSize = $this->config['title_min_font_size'];
        
        return max($baseFontSize - ($level * $levelReduction), $minFontSize);
    }
    
    /**
     * Add a table to the current page
     * 
     * @param array|null $columns Column names (if null, use from data)
     * @param array|null $rows Row data (if null, use from data)
     * @param array $options Table formatting options
     * @param array|null $summaryConfig Summary configuration for the table
     * @return PDFGenerator
     */
    public function addTable(?array $columns = null, ?array $rows = null, array $options = [], ?array $summaryConfig = null): PDFGenerator
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
        
        // Add summary row if configured
        if (!empty($summaryConfig) && !empty($summaryConfig['summary'])) {
            // Add the summary row
            $this->addSummaryRowToTable($html, $columns, $rows, $summaryConfig);
        }

        $html .= '</table>';

        // Write the table to the PDF
        $this->pdf->writeHTML($html, true, false, true, false, '');

        return $this;
    }

    /**
     * Generate and add a summary row to a table
     * 
     * @param string &$html HTML table being built
     * @param array $columns Column names
     * @param array $rows Table data rows
     * @param array $summaryConfig Summary configuration
     */
    private function addSummaryRowToTable(string &$html, array $columns, array $rows, array $summaryConfig): void
    {
        // Process each summary column and create a summary row
        $summaryRow = array_fill(0, count($columns), ''); // Initialize with empty values
        
        // Add the summary header styling
        $summaryTitle = $summaryConfig['summaryTitle'] ?? 'Summary';
        $summaryBgColor = $summaryConfig['summaryBgColor'] ?? '#f9f9f9';
        $summaryTextColor = $summaryConfig['summaryTextColor'] ?? '#000000';
        
        // Style the summary row
        $html .= sprintf(
            '<tr style="font-weight: bold; background-color: %s; color: %s;">',
            $summaryBgColor,
            $summaryTextColor
        );
        
        // First cell contains the summary title, spans multiple columns if configured
        $titleSpan = $summaryConfig['titleSpan'] ?? 1;
        if ($titleSpan > 1) {
            $html .= sprintf(
                '<td colspan="%d" style="text-align: left;">%s</td>',
                $titleSpan,
                htmlspecialchars($summaryTitle)
            );
        } else {
            $html .= sprintf(
                '<td style="text-align: left;">%s</td>',
                htmlspecialchars($summaryTitle)
            );
        }
        
        // Skip cells that were spanned
        for ($i = 1; $i < $titleSpan; $i++) {
            unset($summaryRow[$i]);
        }
        
        // Calculate summary values and populate the summary row
        foreach ($summaryConfig['summary'] as $colKey => $summaryDef) {
            // Get the column index if a name was specified
            $colIndex = $colKey;
            
            if (is_string($colKey) && !is_numeric($colKey)) {
                $colIndex = array_search($colKey, $columns);
            }
            
            if ($colIndex !== false && isset($columns[$colIndex])) {
                // Get the column config for formatting
                $columnConfig = $this->getColumnConfig($colIndex);
                
                // Parse summary definition
                $operation = '';
                
                if (is_array($summaryDef)) {
                    $operation = $summaryDef['operation'] ?? '';
                } else {
                    // If just a string operation is provided
                    $operation = $summaryDef;
                }
                
                if (!empty($operation)) {
                    // Extract values for this column from all rows
                    $values = array_column($rows, $colIndex);
                    
                    // Calculate summary value based on operation
                    $summaryValue = $this->calculateSummaryValue($values, $operation);
                    
                    // Format the summary value according to the column type
                    $formattedValue = $columnConfig->formatValue($summaryValue);
                    
                    // Add to the summary row
                    $summaryRow[$colIndex] = $formattedValue;
                }
            }
        }
        
        // Add the rest of the summary row cells
        for ($i = $titleSpan; $i < count($columns); $i++) {
            if (isset($summaryRow[$i])) {
                // Get column styling
                $columnConfig = $this->getColumnConfig($i);
                $styleAttr = $columnConfig->getStyleString();
                
                $html .= sprintf(
                    '<td%s>%s</td>',
                    $styleAttr ? ' style="' . $styleAttr . '"' : '',
                    htmlspecialchars($summaryRow[$i])
                );
            } else {
                // Empty cell
                $html .= '<td></td>';
            }
        }
        
        $html .= '</tr>';
    }

    /**
     * Add text to the current page
     * 
     * @param string $text Text to add
     * @param array $options Text formatting options
     * @return PDFGenerator
     */
    public function addText(string $text, array $options = []): PDFGenerator
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
    public function addSpacing(int $spacing = 5): PDFGenerator
    {
        $this->pdf->Ln($spacing);
        return $this;
    }

    /**
     * Generate the PDF and return the result
     * 
     * @param string $outputMode Output mode (B64 = base64, I = inline, D = download, F = file)
     * @param string $filename Filename for download or save modes
     * @return array Result with status, filename, and content
     * @throws Exception If output mode is invalid
     */
    public function output(string $outputMode = 'B64', string $filename = ''): array
    {
        // Default filename based on title if not provided
        if (empty($filename)) {
            $filename = $this->filename ?? strtolower(str_replace(' ', '_', $this->config['title'])) . '.pdf';
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
                $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'pdf_output';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                $filepath = $dir . DIRECTORY_SEPARATOR . $filename;
                $this->pdf->Output($filepath, 'F');
                return [
                    'success' => true,
                    'message' => 'PDF saved to file successfully',
                    'filename' => $filename,
                    'file_path' => $filepath,
                    'url' => BASE_URL_PDF . $filename
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
    public function addTablesByCategoryPerPage($categoryColumn, $sortColumn = null, bool $ascending = true, array $options = [], bool $hasContentBefore = false): PDFGenerator
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

            // Get the category column name for display
            $categoryColName = is_numeric($categoryColumn) ?
                ($columns[$categoryColumn] ?? "Category") :
                $categoryColumn;

            // Add the category as a title with the column name
            $this->addTitle("$categoryColName: $category");

            $this->addSpacing(5);

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
     * Add tables grouped by multiple categories in a hierarchical manner with per-level configuration
     * 
     * @param array $categoryColumns Array of column indices or names to group by, in order of hierarchy
     * @param int|string|null $sortColumn Column index or name to sort by within each lowest-level category (optional)
     * @param bool $ascending Whether to sort in ascending order
     * @param array $options Table formatting options
     * @param bool $hasContentBefore Whether there is content before the first table
     * @param array $groupConfig Optional per-level group configuration
     * @return PDFGenerator
     */
    public function addTablesByMultipleCategories(
        array $categoryColumns, 
        $sortColumn = null, 
        bool $ascending = true, 
        array $options = [], 
        bool $hasContentBefore = false,
        array $groupConfig = []
    ): PDFGenerator {
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

        // Default group configuration
        $defaultGroupConfig = [
            'pageBreak' => false,     // Whether this level should start on a new page
            'showSummary' => false,   // Whether to show summary for this group
            'summary' => [],          // Columns to include in the summary
            'titleFormat' => null,    // Custom format for group titles
            'contentAfter' => null    // Text to add after each group
        ];
        
        // Normalize group config to support both numeric indices and column names as keys
        $normalizedGroupConfig = $this->normalizeGroupConfig($groupConfig, $categoryColumns);

        // Always add the first page if there's no content before
        if (!$hasContentBefore) {
            $this->addPage($pageOrientation);
        }
        
        // We'll use this to track which categories we've processed
        $processedCategories = [];
        
        // Process each top-level category
        foreach ($groupedData as $topCategory => $subData) {
            // Get top-level group configuration
            $topLevelConfig = isset($normalizedGroupConfig[0]) ? 
                array_merge($defaultGroupConfig, $normalizedGroupConfig[0]) : $defaultGroupConfig;
            
            // Add page break for top-level categories after the first one
            // (unless the category specifies no page break)
            if (!$isFirstCategory && !empty($topLevelConfig['pageBreak'])) {
                $this->addPage($pageOrientation);
            }
            
            // First category doesn't need a page break since we already added one
            $isFirstCategory = false;

            // Add the top-level category title with column name prefix
            $title = $topCategoryName . ": " . $topCategory;
            if (isset($topLevelConfig['titleFormat']) && is_callable($topLevelConfig['titleFormat'])) {
                $title = call_user_func($topLevelConfig['titleFormat'], $topCategoryName, $topCategory);
            }
            $this->addTitle($title);

            // Extract all rows from this top-level category for summary calculation
            $allRowsInCategory = $this->extractAllRowsFromNestedData($subData);

            // Process the hierarchical data with the enhanced path tracking
            $this->renderNestedCategories(
                $subData, 
                1, 
                [$topCategory], 
                $categoryColNames, 
                $columns, 
                $options, 
                $normalizedGroupConfig, 
                $defaultGroupConfig,
                $processedCategories
            );
            
            // If top level has summary configuration, add summary for all data in this top-level category
            if ($topLevelConfig['showSummary'] && !empty($topLevelConfig['summary'])) {
                // Add a group summary for the top-level category
                $this->addGroupSummary($allRowsInCategory, $columns, $topLevelConfig);
            }
            
            // Add custom content after top-level group if specified
            if (!empty($topLevelConfig['contentAfter'])) {
                if (is_callable($topLevelConfig['contentAfter'])) {
                    $contentText = call_user_func($topLevelConfig['contentAfter'], $topCategory, $allRowsInCategory, []);
                } else {
                    $contentText = $topLevelConfig['contentAfter'];
                }
                $this->addText($contentText);
            }
        }

        return $this;
    }

    /**
     * Normalize group configuration to support both numeric indices and column names as keys
     * 
     * @param array $groupConfig The original group configuration
     * @param array $categoryColumns The column indices or names for grouping
     * @return array Normalized group configuration with numeric indices
     */
    private function normalizeGroupConfig(array $groupConfig, array $categoryColumns): array
    {
        $normalized = [];
        $columns = $this->data['columns'] ?? [];
        
        // Handle column name keys at the top level
        foreach ($groupConfig as $key => $config) {
            $index = $key;
            
            // If the key is a string, try to match it against the category columns
            if (is_string($key) && !is_numeric($key)) {
                $found = false;
                foreach ($categoryColumns as $idx => $colName) {
                    if (strcasecmp($key, $colName) === 0) {
                        $index = $idx;
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    // It might be a column name not in the category columns - leave it as is
                    $index = $key;
                }
            }
            
            $normalized[$index] = $config;
        }
        
        return $normalized;
    }

    /**
     * Extract all leaf rows from nested grouped data structure
     * 
     * @param array $nestedData Nested data from grouping
     * @return array Flattened array of all rows
     */
    private function extractAllRowsFromNestedData($nestedData): array
    {
        $allRows = [];
        
        if (empty($nestedData)) {
            return $allRows;
        }
        
        // Check if this is already a leaf node (array of rows)
        if (isset($nestedData[0]) && is_array($nestedData[0])) {
            return $nestedData;
        }
        
        // Otherwise, this is a branch node, so traverse all children
        foreach ($nestedData as $category => $contents) {
            if (isset($contents[0]) && is_array($contents[0])) {
                // This is a leaf node, add all rows
                $allRows = array_merge($allRows, $contents);
            } else {
                // This is another branch, recursively extract rows
                $childRows = $this->extractAllRowsFromNestedData($contents);
                $allRows = array_merge($allRows, $childRows);
            }
        }
        
        return $allRows;
    }

    /**
     * Add a summary table for a group of data
     * 
     * @param array $rows The rows to calculate summary for
     * @param array $columns Column names
     * @param array $groupConfig Group configuration
     */
    private function addGroupSummary(array $rows, array $columns, array $groupConfig): void
    {
        // Skip if no data or no summary columns specified
        if (empty($rows) || empty($groupConfig['summary'])) {
            return;
        }
        
        // Create summary options
        $summaryTitle = $groupConfig['summaryTitle'] ?? 'Summary';
        $titleFontSize = $groupConfig['summaryTitleFontSize'] ?? 14;
        $summaryTextFormat = $groupConfig['summaryTextFormat'] ?? '{label}: {value}';
        $summaryTextSeparator = '<br>'; // Always use <br> instead of comma
        
        $summaryValues = []; // Store calculated summary values for text summary
        
        // Process each summary column
        foreach ($groupConfig['summary'] as $colKey => $summaryDef) {
            // Get the column index if a name was specified
            $colIndex = $colKey;
            $colName = $colKey;
            
            if (is_string($colKey) && !is_numeric($colKey)) {
                $colIndex = array_search($colKey, $columns);
                $colName = $colKey;
            } else if (is_numeric($colKey) && isset($columns[$colKey])) {
                $colName = $columns[$colKey];
            }
            
            if ($colIndex !== false) {
                // Get the column config
                $columnConfig = $this->getColumnConfig($colIndex);
                
                // Parse summary definition
                $operation = '';
                $label = '';
                
                if (is_array($summaryDef)) {
                    $operation = $summaryDef['operation'] ?? '';
                    $label = $summaryDef['label'] ?? '';
                } else {
                    // If just a string operation is provided
                    $operation = $summaryDef;
                }
                
                if (!empty($operation)) {
                    // Extract values for this column from all rows
                    $values = array_column($rows, $colIndex);
                    
                    // Calculate summary value based on operation
                    $summaryValue = $this->calculateSummaryValue($values, $operation);
                    
                    // Format the summary value according to the column type
                    $formattedValue = $columnConfig->formatValue($summaryValue);
                    
                    // Use the provided label or default to column name
                    $summaryLabel = $label ?: $colName;
                    
                    // Store for text summary
                    $summaryValues[$colName] = [
                        'label' => $summaryLabel,
                        'value' => $formattedValue,
                        'operation' => $operation
                    ];
                }
            }
        }
        
        // Add a title for the summary if specified
        if (!empty($groupConfig['summaryTitle'])) {
            $this->addTitle($summaryTitle, $titleFontSize);
        }
        
        // Format summary values into text
        $textParts = [];
        foreach ($summaryValues as $colName => $data) {
            $textParts[] = str_replace(
                ['{label}', '{value}', '{column}', '{operation}'], 
                [$data['label'], $data['value'], $colName, $data['operation']], 
                $summaryTextFormat
            );
        }
        
        // Combine into a complete summary text
        $summaryText = implode($summaryTextSeparator, $textParts);
        
        // Apply custom text formatting if provided
        if (isset($groupConfig['summaryTextStyle'])) {
            $style = $groupConfig['summaryTextStyle'];
            if (is_array($style)) {
                // Apply HTML formatting
                $bgColor = $style['bgColor'] ?? '';
                $textColor = $style['textColor'] ?? '';
                $fontWeight = $style['fontWeight'] ?? '';
                $fontSize = $style['fontSize'] ?? '';
                $fontStyle = $style['fontStyle'] ?? '';
                
                $styleAttr = [];
                if ($bgColor) $styleAttr[] = "background-color: $bgColor";
                if ($textColor) $styleAttr[] = "color: $textColor";
                if ($fontWeight) $styleAttr[] = "font-weight: $fontWeight";
                if ($fontSize) $styleAttr[] = "font-size: $fontSize";
                if ($fontStyle) $styleAttr[] = "font-style: $fontStyle";
                
                if (!empty($styleAttr)) {
                    $summaryText = '<div style="' . implode('; ', $styleAttr) . '">' . $summaryText . '</div>';
                }
            }
        }
        
        // Add summary as text
        $this->addText($summaryText);
    }

    /**
     * Calculate summary value for a set of values and operation
     * 
     * @param array $values Array of values to calculate summary for
     * @param string $operation The operation to perform (sum, avg, count, min, max)
     * @return mixed Calculated summary value
     */
    private function calculateSummaryValue(array $values, string $operation)
    {
        // Filter out non-numeric values for numeric operations
        $numericOperations = ['sum', 'avg', 'min', 'max'];
        $numericValues = [];
        
        if (in_array($operation, $numericOperations)) {
            foreach ($values as $value) {
                // Try to extract numeric value even from formatted strings
                if (is_string($value)) {
                    // Remove currency symbols, commas, etc.
                    $cleanValue = preg_replace('/[^0-9.-]/', '', $value);
                    if (is_numeric($cleanValue)) {
                        $numericValues[] = (float)$cleanValue;
                    }
                } 
                else if (is_numeric($value)) {
                    $numericValues[] = (float)$value;
                }
            }
        }
        
        switch ($operation) {
            case 'sum':
                return array_sum($numericValues);
                
            case 'avg':
                return count($numericValues) > 0 ? array_sum($numericValues) / count($numericValues) : 0;
                
            case 'count':
                return count($values);
                
            case 'min':
                return !empty($numericValues) ? min($numericValues) : null;
                
            case 'max':
                return !empty($numericValues) ? max($numericValues) : null;
                
            default:
                return '';
        }
    }

    /**
     * Process nested categories for multi-level grouping with clean page break handling
     * 
     * @param array $data Nested data to process
     * @param int $level Current nesting level
     * @param array $path Current path in the category hierarchy
     * @param array $categoryColNames Category column names for display
     * @param array $columns Data columns
     * @param array $options Table formatting options
     * @param array $normalizedGroupConfig Normalized group configuration
     * @param array $defaultGroupConfig Default group configuration
     * @param array $processedCategories Track processed categories
     * @return bool Whether any content was actually rendered
     */
    private function renderNestedCategories(
        array $data, 
        int $level, 
        array $path, 
        array $categoryColNames, 
        array $columns, 
        array $options,
        array $normalizedGroupConfig,
        array $defaultGroupConfig,
        array &$processedCategories
    ): bool {
        if (empty($data)) {
            return false;
        }

        // Get configuration for this level
        $levelConfig = isset($normalizedGroupConfig[$level]) ? 
            array_merge($defaultGroupConfig, $normalizedGroupConfig[$level]) : 
            $defaultGroupConfig;

        $currentCategoryName = $categoryColNames[$level] ?? "Category";
        
        // Track if any content was rendered at this level
        $contentRendered = false;
        
        // This level's full path key (used to track if we need a page break)
        $pathKey = implode(':', $path);
        $isFirstItemAtThisLevel = !isset($processedCategories[$level]) || !isset($processedCategories[$level][$pathKey]);
        
        // Initialize the tracking for this level and path if needed
        if (!isset($processedCategories[$level])) {
            $processedCategories[$level] = [];
        }
        if (!isset($processedCategories[$level][$pathKey])) {
            $processedCategories[$level][$pathKey] = 0;
        }

        foreach ($data as $category => $contents) {
            // Build the current path for display
            $currentPath = array_merge($path, [$category]);
            $currentPathKey = implode(':', $currentPath);
            
            // Check if this is a leaf node with actual data or an intermediate node
            $isLeafNode = isset($contents[0]) && is_array($contents[0]);
            
            // Check if there's actual content in this node (either direct rows or nested data)
            $hasContent = $isLeafNode ? !empty($contents) : $this->hasContentInNestedData($contents);
            
            // Skip entirely if there's no actual content
            if (!$hasContent) {
                continue;
            }
            
            // Decide if we need a page break for this category
            $needsPageBreak = !$isFirstItemAtThisLevel && !empty($levelConfig['pageBreak']);
            if ($needsPageBreak) {
                $this->addPage();
                
                // After a page break, we'll show a simple title without the full path
                $simplePath = [];
            } else {
                // If no page break, use normal path
                $simplePath = $path;
            }
            
            // Set the title and adjust font size based on nesting level/depth
            $categoryColName = $categoryColNames[$level] ?? "Category";
            $title = $categoryColName . ": " . $category;
            
            // Calculate font size based on level - the deeper the level, the smaller the font
            $titleFontSize = $this->calculateTitleFontSize($level);
            
            // Use custom title format if provided
            if (isset($levelConfig['titleFormat']) && is_callable($levelConfig['titleFormat'])) {
                $title = call_user_func($levelConfig['titleFormat'], $currentCategoryName, $category, $simplePath);
            }
            
            // Add the title for this group with the calculated font size
            $this->addTitle($title, $titleFontSize);

            // Add spacing before the table
            $this->addSpacing(5);
            
            // Process leaf nodes (actual data) or continue recursion
            if ($isLeafNode) {
                // This is a leaf node with rows (actual data)
                
                // Check if this level has table summary configuration
                $tableSummaryConfig = null;
                if (!empty($levelConfig['tableSummary']) && !empty($levelConfig['summary'])) {
                    $tableSummaryConfig = [
                        'summaryTitle' => $levelConfig['tableSummaryTitle'] ?? 'Total',
                        'summaryBgColor' => $levelConfig['tableSummaryBgColor'] ?? '#f2f2f2',
                        'summaryTextColor' => $levelConfig['tableSummaryTextColor'] ?? '#000000',
                        'titleSpan' => $levelConfig['tableSummaryTitleSpan'] ?? 1,
                        'summary' => $levelConfig['summary']
                    ];
                }
                
                // Add the table with the rows and summary if configured
                $this->addTable($columns, $contents, $options, $tableSummaryConfig);
                
                // If this level has text summary configuration, add it after the table
                if ($levelConfig['showSummary'] && !empty($levelConfig['summary'])) {
                    $this->addGroupSummary($contents, $columns, $levelConfig);
                }
                
                // Add custom content after the table if specified
                if (!empty($levelConfig['contentAfter'])) {
                    if (is_callable($levelConfig['contentAfter'])) {
                        $contentText = call_user_func($levelConfig['contentAfter'], $category, $contents, $currentPath);
                    } else {
                        $contentText = $levelConfig['contentAfter'];
                    }
                    $this->addText($contentText);
                }
                
                $this->pdf->Ln(5); // Add spacing after table
                $contentRendered = true;
            } else {
                // This is an intermediate node with child categories
                
                // Extract all rows from this level for summary calculation
                $allRowsInLevel = $this->extractAllRowsFromNestedData($contents);
                
                // Process all nested categories and track if content was rendered
                $childContentRendered = $this->renderNestedCategories(
                    $contents, 
                    $level + 1, 
                    $currentPath, 
                    $categoryColNames, 
                    $columns, 
                    $options, 
                    $normalizedGroupConfig, 
                    $defaultGroupConfig,
                    $processedCategories
                );
                
                // Only show summary and content after if child content was actually rendered
                if ($childContentRendered) {
                    // Show summary for this branch if configured 
                    if ($levelConfig['showSummary'] && !empty($levelConfig['summary'])) {
                        $this->addGroupSummary($allRowsInLevel, $columns, $levelConfig);
                    }
                    
                    // Add custom content after the group if specified
                    if (!empty($levelConfig['contentAfter'])) {
                        if (is_callable($levelConfig['contentAfter'])) {
                            $contentText = call_user_func($levelConfig['contentAfter'], $category, $allRowsInLevel, $currentPath);
                        } else {
                            $contentText = $levelConfig['contentAfter'];
                        }
                        $this->addText($contentText);
                    }
                    
                    $contentRendered = true;
                }
            }
            
            // Update that we've processed this item
            $processedCategories[$level][$pathKey]++;
            $isFirstItemAtThisLevel = false;
        }
        
        return $contentRendered;
    }

    /**
     * Check if there is any actual content in nested data structure
     * 
     * @param array $nestedData Nested data structure to check
     * @return bool True if there is actual content, false otherwise
     */
    private function hasContentInNestedData(array $nestedData): bool
    {
        if (empty($nestedData)) {
            return false;
        }
        
        // If this is a leaf node with rows, check if there are any rows
        if (isset($nestedData[0]) && is_array($nestedData[0])) {
            return !empty($nestedData);
        }
        
        // Otherwise, recursively check each child
        foreach ($nestedData as $category => $contents) {
            if ($this->hasContentInNestedData($contents)) {
                return true;
            }
        }
        
        // No content found
        return false;
    }

    /**
     * Convert associative array rows to indexed arrays based on column names
     * 
     * @param array $rows The rows data which may contain associative arrays
     * @param array $columns The column names to use as reference
     * @return array Normalized rows with indexed arrays
     */
    private function normalizeRowsFormat(array $rows, array $columns): array
    {
        $normalizedRows = [];
        
        foreach ($rows as $row) {
            // If row is not an array at all, skip it
            if (!is_array($row)) {
                continue;
            }
            
            // Check if this is an associative array (has string keys)
            $hasStringKeys = false;
            foreach ($row as $key => $value) {
                if (is_string($key)) {
                    $hasStringKeys = true;
                    break;
                }
            }
            
            if ($hasStringKeys) {
                // This is an associative array, convert to indexed based on columns
                $indexedRow = [];
                foreach ($columns as $columnIndex => $columnName) {
                    // Look for the column by name (case-insensitive)
                    $found = false;
                    foreach ($row as $key => $value) {
                        if (is_string($key) && strcasecmp($key, $columnName) === 0) {
                            $indexedRow[$columnIndex] = $value;
                            $found = true;
                            break;
                        }
                    }
                    
                    // If not found, use empty value
                    if (!$found) {
                        $indexedRow[$columnIndex] = '';
                    }
                }
                $normalizedRows[] = $indexedRow;
            } else {
                // This is already an indexed array, keep as is
                $normalizedRows[] = $row;
            }
        }
        
        return $normalizedRows;
    }
}
