<?php

/**
 * Column Configuration Class
 * 
 * Manages configuration and properties for table columns in PDFGenerator.
 * This class handles column types, formatting options, and styling.
 */
class ColumnConfig
{
    /** @var string The column type (e.g., 'string', 'price', 'date', etc.) */
    private $type = 'string';
    
    /** @var string Text alignment ('L'=left, 'C'=center, 'R'=right) */
    private $align = 'L';
    
    /** @var string|null Fixed width of the column (e.g., '20mm', '2cm') */
    private $width = null;
    
    /** @var string Font weight ('N'=normal, 'B'=bold, 'I'=italic, 'BI'=bold italic) */
    private $fontWeight = 'N';
    
    /** @var string|null Background color in HTML format (e.g., '#ffffff') */
    private $backgroundColor = null;
    
    /** @var string|null Text color in HTML format (e.g., '#000000') */
    private $textColor = null;
    
    /** @var array Custom formatter callback function */
    private $formatter = null;
    
    /** @var int Column padding */
    private $padding = 1;
    
    /** @var array Additional properties that might be added in the future */
    private $additionalProps = [];

    /**
     * Constructor - Initialize column configuration
     * 
     * @param array $config Configuration options
     */
    public function __construct(array $config = [])
    {
        // Apply provided configuration
        $this->setProperties($config);
    }

    /**
     * Set multiple properties at once
     * 
     * @param array $properties Properties to set
     * @return ColumnConfig
     */
    public function setProperties(array $properties)
    {
        foreach ($properties as $property => $value) {
            $setter = 'set' . ucfirst($property);
            
            // Use setter method if exists
            if (method_exists($this, $setter)) {
                $this->$setter($value);
            } 
            // Store in additionalProps for future extensibility
            else {
                $this->additionalProps[$property] = $value;
            }
        }
        
        return $this;
    }

    /**
     * Get a property value
     * 
     * @param string $property Property name
     * @param mixed $default Default value if property doesn't exist
     * @return mixed
     */
    public function getProperty($property, $default = null)
    {
        $getter = 'get' . ucfirst($property);
        
        // Use getter method if exists
        if (method_exists($this, $getter)) {
            return $this->$getter();
        }
        
        // Try to get from additionalProps
        if (isset($this->additionalProps[$property])) {
            return $this->additionalProps[$property];
        }
        
        return $default;
    }

    /**
     * Set column type
     * 
     * @param string $type Column type
     * @return ColumnConfig
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get column type
     * 
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set text alignment
     * 
     * @param string $align Text alignment ('L'=left, 'C'=center, 'R'=right)
     * @return ColumnConfig
     */
    public function setAlign($align)
    {
        $validAlignments = ['L', 'C', 'R', 'left', 'center', 'right'];
        
        // Normalize alignment values
        if ($align === 'left') $align = 'L';
        if ($align === 'center') $align = 'C';
        if ($align === 'right') $align = 'R';
        
        // Validate alignment
        if (in_array($align, $validAlignments)) {
            $this->align = substr($align, 0, 1); // Take just first letter
        }
        
        return $this;
    }

    /**
     * Get text alignment
     * 
     * @return string
     */
    public function getAlign()
    {
        return $this->align;
    }

    /**
     * Set column width
     * 
     * @param string|null $width Column width (e.g., '20mm', '2cm')
     * @return ColumnConfig
     */
    public function setWidth($width)
    {
        $this->width = $width;
        return $this;
    }

    /**
     * Get column width
     * 
     * @return string|null
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * Set font weight
     * 
     * @param string $fontWeight Font weight ('N'=normal, 'B'=bold, 'I'=italic, 'BI'=bold italic)
     * @return ColumnConfig
     */
    public function setFontWeight($fontWeight)
    {
        $validWeights = ['N', 'B', 'I', 'BI', 'normal', 'bold', 'italic', 'bold-italic'];
        
        // Normalize font weight values
        if ($fontWeight === 'normal') $fontWeight = 'N';
        if ($fontWeight === 'bold') $fontWeight = 'B';
        if ($fontWeight === 'italic') $fontWeight = 'I';
        if ($fontWeight === 'bold-italic') $fontWeight = 'BI';
        
        // Validate font weight
        if (in_array($fontWeight, $validWeights)) {
            $this->fontWeight = $fontWeight;
        }
        
        return $this;
    }

    /**
     * Get font weight
     * 
     * @return string
     */
    public function getFontWeight()
    {
        return $this->fontWeight;
    }

    /**
     * Set background color
     * 
     * @param string|null $color Background color in HTML format
     * @return ColumnConfig
     */
    public function setBackgroundColor($color)
    {
        $this->backgroundColor = $color;
        return $this;
    }

    /**
     * Get background color
     * 
     * @return string|null
     */
    public function getBackgroundColor()
    {
        return $this->backgroundColor;
    }

    /**
     * Set text color
     * 
     * @param string|null $color Text color in HTML format
     * @return ColumnConfig
     */
    public function setTextColor($color)
    {
        $this->textColor = $color;
        return $this;
    }

    /**
     * Get text color
     * 
     * @return string|null
     */
    public function getTextColor()
    {
        return $this->textColor;
    }

    /**
     * Set custom formatter callback
     * 
     * @param callable|null $formatter Formatter function
     * @return ColumnConfig
     */
    public function setFormatter($formatter)
    {
        if (is_callable($formatter) || $formatter === null) {
            $this->formatter = $formatter;
        }
        return $this;
    }

    /**
     * Get formatter callback
     * 
     * @return callable|null
     */
    public function getFormatter()
    {
        return $this->formatter;
    }

    /**
     * Set padding
     * 
     * @param int $padding Column padding
     * @return ColumnConfig
     */
    public function setPadding($padding)
    {
        $this->padding = (int)$padding;
        return $this;
    }

    /**
     * Get padding
     * 
     * @return int
     */
    public function getPadding()
    {
        return $this->padding;
    }

    /**
     * Get HTML style attribute string based on configured properties
     * 
     * @return string HTML style string
     */
    public function getStyleString()
    {
        $styles = [];
        
        // Add text alignment
        if ($this->align) {
            $alignMap = ['L' => 'left', 'C' => 'center', 'R' => 'right'];
            $styles[] = 'text-align: ' . $alignMap[$this->align];
        }
        
        // Add background color
        if ($this->backgroundColor) {
            $styles[] = 'background-color: ' . $this->backgroundColor;
        }
        
        // Add text color
        if ($this->textColor) {
            $styles[] = 'color: ' . $this->textColor;
        }
        
        // Add font weight
        if ($this->fontWeight === 'B' || $this->fontWeight === 'BI') {
            $styles[] = 'font-weight: bold';
        }
        
        // Add font style
        if ($this->fontWeight === 'I' || $this->fontWeight === 'BI') {
            $styles[] = 'font-style: italic';
        }
        
        // Add padding
        if ($this->padding !== 1) {
            $styles[] = 'padding: ' . $this->padding . 'pt';
        }
        
        return implode('; ', $styles);
    }

    /**
     * Format a cell value based on the column configuration
     * 
     * @param mixed $value Cell value
     * @return string Formatted value
     */
    public function formatValue($value)
    {
        // If a custom formatter is set, use it
        if (is_callable($this->formatter)) {
            return call_user_func($this->formatter, $value);
        }
        
        // Otherwise use the built-in formatters based on type
        switch ($this->type) {
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
        }

        // Default formatting when no specific type handling applies
        if (!is_string($value)) {
            return json_encode($value);
        }

        return $value;
    }
}
