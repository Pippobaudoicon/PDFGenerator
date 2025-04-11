<?php
// Set headers for API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include classes
require_once(__DIR__ . '/../src/PDFGenerator.php');

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
        'format' => $data['format'] ?? 'A4',
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

    // Set column configurations if provided
    if (isset($data['column_config'])) {
        $generator->setColumnConfigs($data['column_config']);
    }

    // Table options for both grouped and non-grouped tables
    $tableOptions = [
        'border' => $data['table_border'] ?? 1,
        'padding' => $data['table_padding'] ?? 5,
        'header_bg' => $data['header_bg'] ?? '#f2f2f2',
    ];
    
    // Add summary row options if present
    if (isset($data['show_summary'])) {
        $tableOptions['show_summary'] = $data['show_summary'];
        
        // Additional summary options (all optional)
        if (isset($data['summary_bg'])) {
            $tableOptions['summary_bg'] = $data['summary_bg'];
        }
        if (isset($data['summary_label_col'])) {
            $tableOptions['summary_label_col'] = $data['summary_label_col'];
        }
        if (isset($data['summary_label'])) {
            $tableOptions['summary_label'] = $data['summary_label'];
        }
    }

    // Check if we should group by category
    if (isset($data['group_by'])) {
        // Get the group by column(s)
        $groupByColumn = $data['group_by'];
        
        // Get the optional sort column for sorting within each category
        $sortColumn = $data['sort_by'] ?? null;
        $sortAscending = $data['sort_ascending'] ?? true;

        // Create the first page for the document if there's content_before
        // Otherwise, let the table methods add the first page
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

        // Check if we're using multi-level grouping (array of columns) or single category
        if (is_array($groupByColumn) && count($groupByColumn) > 1) {
            // Multi-level grouping with multiple columns
            $generator->addTablesByMultipleCategories(
                $groupByColumn,
                $sortColumn,
                $sortAscending,
                $tableOptions,
                $hasContentBefore
            );
        } else {
            // Single-level grouping (backward compatibility)
            // If $groupByColumn is an array with one element, extract it
            if (is_array($groupByColumn) && count($groupByColumn) === 1) {
                $groupByColumn = $groupByColumn[0];
            }
            
            // Add tables grouped by a single category
            $generator->addTablesByCategoryPerPage(
                $groupByColumn,
                $sortColumn,
                $sortAscending,
                $tableOptions,
                $hasContentBefore
            );
        }

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
    $strfilename = $data['filename'] ?? '';
    $filename = $generator->setFilename($strfilename)->getFilename();
    
    $result = $generator->output($outputMode, $filename);

    // Return JSON response
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'PDF generation failed: ' . $e->getMessage()]);
    exit();
}
