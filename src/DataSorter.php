<?php

require_once(__DIR__ . '/ColumnConfig.php');

/**
 * DataSorter Class
 * 
 * Helper class for sorting and grouping data
 */
class DataSorter
{
    /** @var array Data to be sorted */
    private $data;

    /** @var array Column configurations */
    private $columnConfigs = [];

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
     * Set column configurations for sorting
     * 
     * @param array $configs Array of ColumnConfig objects
     * @return DataSorter
     */
    public function setColumnConfigs(array $configs)
    {
        $this->columnConfigs = $configs;
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
        usort($rows, function ($a, $b) use ($columnIndex, $ascending) {
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
                    usort($rows, function ($a, $b) use ($sortColumn, $ascending) {
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
     * Group data by multiple category columns in hierarchical order
     * 
     * @param array $categoryColumns Array of column indices or names to group by, in order of hierarchy
     * @param int|string|null $sortColumn Column index or name to sort by within each lowest-level category (optional)
     * @param bool $ascending Whether to sort in ascending order
     * @return array Hierarchically grouped data with nested structure
     */
    public function byMultipleCategories(array $categoryColumns, $sortColumn = null, bool $ascending = true)
    {
        if (!isset($this->data['rows']) || !is_array($this->data['rows'])) {
            throw new Exception("No data rows found to group");
        }

        if (empty($categoryColumns)) {
            throw new Exception("At least one category column must be specified");
        }

        // Convert all string column names to indices
        $categoryIndices = [];
        foreach ($categoryColumns as $column) {
            if (is_string($column)) {
                $categoryIndices[] = $this->findColumnIndex($column);
            } else {
                $categoryIndices[] = $column;
            }
        }

        // Get the column index if a column name is provided for sort column
        if ($sortColumn !== null && is_string($sortColumn)) {
            $sortColumn = $this->findColumnIndex($sortColumn);
        }

        // Recursive function to group data based on the category hierarchy
        $groupData = function ($rows, $categoryIdx, $depth = 0) use (&$groupData, $categoryIndices, $sortColumn, $ascending) {
            // If we've processed all category levels, return the rows
            if ($depth >= count($categoryIndices)) {
                // If a sort column is specified, sort the rows
                if ($sortColumn !== null && count($rows) > 1) {
                    usort($rows, function ($a, $b) use ($sortColumn, $ascending) {
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
                }
                return $rows;
            }

            // Get current category column index
            $currentCategoryIdx = $categoryIndices[$depth];

            // Group rows by current category
            $grouped = [];
            foreach ($rows as $row) {
                $categoryValue = isset($row[$currentCategoryIdx]) ? (string)$row[$currentCategoryIdx] : 'Uncategorized';

                if (!isset($grouped[$categoryValue])) {
                    $grouped[$categoryValue] = [];
                }

                $grouped[$categoryValue][] = $row;
            }

            // Process next level for each group
            $result = [];
            foreach ($grouped as $category => $categoryRows) {
                // Recursively group the next level
                $result[$category] = $groupData($categoryRows, $currentCategoryIdx, $depth + 1);
            }

            // Sort categories alphabetically at each level
            ksort($result);

            return $result;
        };

        // Start the recursive grouping with all rows
        return $groupData($this->data['rows'], $categoryIndices[0]);
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
        if (isset($this->columnConfigs[$columnKey])) {
            return $this->columnConfigs[$columnKey]->getType();
        }

        // If the key is a string (column name), try to find a matching column index
        if (is_string($columnKey) && isset($this->data['columns'])) {
            foreach ($this->data['columns'] as $index => $columnName) {
                if (strcasecmp($columnName, $columnKey) === 0 && isset($this->columnConfigs[$index])) {
                    return $this->columnConfigs[$index]->getType();
                }
            }
        }

        // If the key is a numeric index, try to find a matching column name
        if (is_numeric($columnKey) && isset($this->data['columns'][$columnKey])) {
            $columnName = $this->data['columns'][$columnKey];
            if (isset($this->columnConfigs[$columnName])) {
                return $this->columnConfigs[$columnName]->getType();
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
