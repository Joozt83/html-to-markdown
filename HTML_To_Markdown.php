<?php
/**
 * @documentation public
 */ 

/**
 * Class HTML_To_Markdown
 *
 * A helper class to convert HTML to Markdown
 *
 * @version 2.1.1
 * @author Nick Cernis <nick@cern.is>
 * @link https://github.com/nickcernis/html2markdown/ Latest version on GitHub.
 * @link http://twitter.com/nickcernis Nick on twitter.
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 * 
 * Branche created from version 2.1.1
 * 
 * All private function and members are now protected, this makes it easier 
 * for other people to extend from this calls.
 * 
 * Improved output, now all leftover HTML code striped 
 * Improved parsing of links
 * Code cleanup
 * 
 * @author Joost Horstman <j.horstman@gmail.com>
 * @link https://github.com/nickcernis/html2markdown/
 */
class HTML_To_Markdown
{
    /**
     * The original html source
     * 
     * @var string
     */
    protected $source = '';
    
    /**
     * @var array Class-wide options users can override.
     */
    protected $options = array(
        'header_style'    => 'setext', // Set to "atx" to output H1 and H2 headers as # Header1 and ## Header2
        'suppress_errors' => true, // Set to false to show warnings when loading malformed HTML
        'strip_tags'      => false, // Set to true to strip tags that don't have markdown equivalents. N.B. Strips tags, not their content. Useful to clean MS Word HTML output.
        'bold_style'      => '**', // Set to '__' if you prefer the underlined style
        'italic_style'    => '*', // Set to '_' if you prefer the underlined style
        'strip_tags'      => true,
    );

    /**
     * Temp quotes for addresses in markdown
     * 
     * Normally we want < > to quote addresses in markdown,
     * but because strip tags is used to remove unwanted tags from the markdown source
     * 
     * We temporarily replace them with an other symbol combination  which is unlikely to be used
     * 
     * @var array
     */ 
    protected $temp_href_quotes = array(
        '<' => '{=#$',
        '>' => '$#=}',
    );
    
    /**
     * Constructor
     *
     * Set up a new DOMDocument from the supplied HTML, convert it to Markdown, and store it in $this->$output.
     *
     * @param string $html The HTML to convert to Markdown.
     * @param array $overrides [optional] List of style and error display overrides.
     */
    public function __construct($html = null, $overrides = null)
    {
        // are there overrides?
        if ($overrides) $this->options = array_merge($this->options, $overrides);
    
        // store the source
        $this->source = $html;
    }


    /**
     * Setter for conversion options
     *
     * @param $name
     * @param $value
     */
    public function set_option($name, $value)
    {
        $this->options[$name] = $value;
    }
    
    /**
     * Convert
     *
     * Loads HTML and passes it to markdown
     *
     * @param  string the html code
     * @return string The Markdown version of the html
     */
    public static function convert($html)
    {
        // is the input?
        if (empty($html)) return '';
        
        // return the converted source
        return (string)new Self($html);
    }

    /**
     * Is Child Of?
     *
     * Is the node a child of the given parent tag?
     *
     * @param   string The name of the parent node to search for (e.g. 'code')
     * @param   DOMNode
     * @return  boolean
     */
    protected static function is_child_of($parent_name, $node)
    {
        //  loop
        for ($p = $node->parentNode; $p != false; $p = $p->parentNode) 
        {
            // is there a parent?
            if (is_null($p)) return false;
            
            // is this the node we are looking for?
            if ($p->nodeName == $parent_name) return true;
        }
        
        // not found
        return false;
    }

    /**
     * Convert Children
     *
     * Recursive function to drill into the DOM and convert each node into Markdown from the inside out.
     *
     * Finds children of each node and convert those to #text nodes containing their Markdown equivalent,
     * starting with the innermost element and working up to the outermost element.
     *
     * @param DOMNode
     * @param DOMDocument
     */
    protected function convert_children($node, $document)
    {
        // Don't convert HTML code inside <code> blocks to Markdown - that should stay as HTML
        if (self::is_child_of('code', $node)) return;

        // If the node has children, convert those to Markdown first
        if ($node->hasChildNodes()) 
        {
            //store the length
            $length = $node->childNodes->length;

            // loop
            for ($i = 0; $i < $length; $i++) 
            {
                $child = $node->childNodes->item($i);
                $this->convert_children($child, $document);
            }
        }

        // Now that child nodes have been converted, convert the original node
        $this->convert_to_markdown($node, $document);
    }

    /**
     * Get Markdown
     *
     * Sends the body node to convert_children() to change inner nodes to Markdown #text nodes, then saves and
     * returns the resulting converted document as a string in Markdown format.
     *
     * @return string The converted HTML as Markdown, or false if conversion failed
     */
    protected function get_markdown()
    {
        // do we have source html?
        if (empty($this->source)) return '';
        
        // Use the body tag as our root element
        // reset the allowed tags
        $this->allowedTags = array();
        
        // strip white spaces
        $html = preg_replace('~>\s+<~', '><', $this->source); // Strip white space between tags to prevent creation of empty #text nodes

        // create the dom document
        $document = new DOMDocument();
    
        // Suppress conversion errors (from http://bit.ly/pCCRSX )
        if ($this->options['suppress_errors']) libxml_use_internal_errors(true);

        // load the html and set the encoding
        $document->loadHTML('<?xml encoding="UTF-8">' . $html); // Hack to load utf-8 HTML (from http://bit.ly/pVDyCt )
        $document->encoding = 'UTF-8';
        
        //  clear the errors
        if ($this->options['suppress_errors']) libxml_clear_errors();
        $body = $document->getElementsByTagName("body")->item(0);
        
        // Try the head tag if there's no body tag (e.g. the user's passed a single <script> tag for conversion)
        if (!$body) $body = $document->getElementsByTagName("head")->item(0);

        // no body found
        if (!$body) return '';

        // Convert all children of the body element. The DOMDocument stored in $this->doc will
        // then consist of #text nodes, each containing a Markdown version of the original node
        // that it replaced.
        $this->convert_children($body, $document);

        // Sanitize and return the body contents as a string.
        $markdown = $document->saveHTML(); // stores the DOMDocument as a string
        $markdown = html_entity_decode($markdown, ENT_QUOTES, 'UTF-8');
        $markdown = html_entity_decode($markdown, ENT_QUOTES, 'UTF-8'); // Double decode to cover cases like &amp;nbsp; http://www.php.net/manual/en/function.htmlentities.php#99984
        
        
        // should we strip the tags?
        if (!empty($this->options['strip_tags'])) $markdown = strip_tags($markdown);
        
        // 
        $markdown = trim($markdown);
        
        // replace the temporary quote tags
        return str_replace(array_values($this->temp_href_quotes), array_keys($this->temp_href_quotes), $markdown);
    }

    /**
     * Convert to Markdown
     *
     * Converts an individual node into a #text node containing a string of its Markdown equivalent.
     *
     * Example: An <h3> node with text content of "Title" becomes a text node with content of "### Title"
     *
     * @param DOMNode
     * @param DOMDocument
     */
    protected function convert_to_markdown($node, $document)
    {
        $tag = $node->nodeName; // the type of element, e.g. h1
        $value = $node->nodeValue; // the value of that element, e.g. The Title

        switch ($tag) {
            case "p":
            case "pre":
                $markdown = (trim($value)) ? rtrim($value) . PHP_EOL . PHP_EOL : '';
                break;
            case "h1":
            case "h2":
                $markdown = $this->convert_header($tag, $node);
                break;
            case "h3":
                $markdown = "### " . $value . PHP_EOL . PHP_EOL;
                break;
            case "h4":
                $markdown = "#### " . $value . PHP_EOL . PHP_EOL;
                break;
            case "h5":
                $markdown = "##### " . $value . PHP_EOL . PHP_EOL;
                break;
            case "h6":
                $markdown = "###### " . $value . PHP_EOL . PHP_EOL;
                break;
            case "em":
            case "i":
            case "strong":
            case "b":
                $markdown = $this->convert_emphasis($tag, $value);
                break;
            case "hr":
                $markdown = "- - - - - -" . PHP_EOL . PHP_EOL;
                break;
            case "br":
                $markdown = "  " . PHP_EOL;
                break;
            case "blockquote":
                $markdown = $this->convert_blockquote($node);
                break;
            case "code":
                $markdown = $this->convert_code($node);
                break;
            case "ol":
            case "ul":
                $markdown = $value . PHP_EOL;
                break;
            case "li":
                $markdown = $this->convert_list($node);
                break;
            case "img":
                $markdown = $this->convert_image($node);
                break;
            case "a":
                $markdown = $this->convert_anchor($node);
                break;
            case "#text":
                $markdown = preg_replace('~\s+~', ' ', $value);
                break;
            case "#comment":
                $markdown = '';
                break;
            default:
                // If strip_tags is false (the default), preserve tags that don't have Markdown equivalents,
                // such as <span> and #text nodes on their own. C14N() canonicalizes the node to a string.
                // See: http://www.php.net/manual/en/domnode.c14n.php
                $markdown = ($this->options['strip_tags']) ? $value : html_entity_decode($node->C14N());
        }

        // Create a DOM text node containing the Markdown equivalent of the original node
        $markdown_node = $document->createTextNode($markdown);

        // Replace the old $node e.g. "<h3>Title</h3>" with the new $markdown_node e.g. "### Title"
        $node->parentNode->replaceChild($markdown_node, $node);
    }

    /**
     * Convert Header
     *
     * Converts h1 and h2 headers to Markdown-style headers in setext style,
     * matching the number of underscores with the length of the title.
     *
     * e.g.     Header 1    Header Two
     *          ========    ----------
     *
     * Returns atx headers instead if $this->options['header_style'] is "atx"
     *
     * e.g.    # Header 1   ## Header Two
     *
     * @param   string $level The header level, including the "h". e.g. h1
     * @param   string $node The node to convert.
     * @return  string The Markdown version of the header.
     */
    protected function convert_header($level, $node)
    {
        // get the content
        $content = $node->nodeValue;
    
        if (!$this->is_child_of('blockquote', $node) && $this->options['header_style'] == "setext") 
        {
            $length = (function_exists('mb_strlen')) ? mb_strlen($content, 'utf-8') : strlen($content);
            $underline = ($level == "h1") ? "=" : "-";
            return $content . PHP_EOL . str_repeat($underline, $length) . PHP_EOL . PHP_EOL; // setext style
        } 
       
        $prefix = ($level == "h1") ? "# " : "## ";
        return $prefix . $content . PHP_EOL . PHP_EOL; // atx style
    }

    /**
     * Converts inline styles
     * This function is used to render strong and em tags
     * 
     * eg <strong>bold text</strong> becomes **bold text** or __bold text__
     * 
     * @param   string $tag
     * @param   string $value
     * @return  string
     */
     protected function convert_emphasis($tag, $value)
     {
        // for i and em return italic_style
        if ($tag == 'i' || $tag == 'em') return $this->options['italic_style'] . $value . $this->options['italic_style'];
        
        // return the markdown bold string
        return $this->options['bold_style'] . $value . $this->options['bold_style'];
     }

    /**
     * Convert Image
     *
     * Converts <img /> tags to Markdown.
     *
     * e.g.     <img src="/path/img.jpg" alt="alt text" title="Title" />
     * becomes  ![alt text](/path/img.jpg "Title")
     *
     * @param   DOMNode
     * @return  string
     */
    protected function convert_image($node)
    {
        $src = $node->getAttribute('src');
        $alt = $node->getAttribute('alt');
        $title = $node->getAttribute('title');
        
        // is there a title?
        // No newlines added. <img> should be in a block-level element.
        if ($title != "")  return '![' . $alt . '](' . $src . ' "' . $title . '")';
       
        // return without title
        return '![' . $alt . '](' . $src . ')';
    }

    /**
     * Convert Anchor
     *
     * Converts <a> tags to Markdown.
     *
     * e.g.     <a href="http://modernnerd.net" title="Title">Modern Nerd</a>
     * becomes  [Modern Nerd](http://modernnerd.net "Title")
     *
     * @param   DOMNode
     * @return  string
     */
    protected function convert_anchor($node)
    {
        // get the attributes
        $href = $node->getAttribute('href');
        $title = $node->getAttribute('title');
        $text = $node->nodeValue;
        
        // create the tag with the temp quotes
        $hrefTag = $this->temp_href_quotes['<'].$href.$this->temp_href_quotes['>'];

        // is there a title?
        if ($title != "") $markdown = '[' . $text . '](' . $hrefTag . ' "' . $title . '")';
        
        // without title
        else $markdown = '[' . $text . '](' . $hrefTag . ')';
        
        // is the next node an anchor
        if ($this->get_next_node_name($node) != 'a') $markdown;
        
        // Append a space if the node after this one is also an anchor
        return $markdown . ' ';
    }

    /**
     * Convert List
     *
     * Converts <ul> and <ol> lists to Markdown.
     *
     * @param   DOMNode
     * @return  string
     */
    protected function convert_list($node)
    {
        // If parent is an ol, use numbers, otherwise, use dashes
        $list_type = $node->parentNode->nodeName;
        $value = $node->nodeValue;
        
        // are we dealing with an ul tag?
        if ($list_type == "ul") return $markdown = "- " . trim($value) . PHP_EOL;
        
        $number = $this->get_position($node);
        return $number . ". " . trim($value) . PHP_EOL;
    }

    /**
     * Convert Code
     *
     * Convert code tags by indenting blocks of code and wrapping single lines in backticks.
     *
     * @param   DOMNode
     * @return  string
     */
    protected function convert_code($node)
    {
        // Store the content of the code block in an array, one entry for each line
        $markdown = '';
        $code_content = html_entity_decode($node->C14N());
        $code_content = str_replace(array("<code>", "</code>"), "", $code_content);

        $lines = preg_split('/\r\n|\r|\n/', $code_content);
        $total = count($lines);

        // If there's more than one line of code, prepend each line with four spaces and no backticks.
        if ($total > 1) 
        {
            // Remove the first and last line if they're empty
            $first_line = trim($lines[0]);
            $last_line = trim($lines[$total - 1]);
            $first_line = trim($first_line, "&#xD;"); //trim XML style carriage returns too
            $last_line = trim($last_line, "&#xD;");

            if (empty($first_line)) array_shift($lines);

            if (empty($last_line)) array_pop($lines);

            $count = 1;
            foreach ($lines as $line) 
            {
                $line = str_replace('&#xD;', '', $line);
                $markdown .= "    " . $line;
                
                // Add newlines, except final line of the code
                if ($count != $total) $markdown .= PHP_EOL;
                $count++;
            }
            
            $markdown .= PHP_EOL;
            return $markdown;
        } 
        
        // There's only one line of code. It's a code span, not a block. Just wrap it with backticks.
        return "`{$lines[0]}`";

    }
    
    /**
     * Convert blockquote
     *
     * Prepend blockquotes with > chars.
     *
     * @param   DOMNode
     * @return  string
     */
    protected function convert_blockquote($node)
    {
        // Contents should have already been converted to Markdown by this point,
        // so we just need to add ">" symbols to each line.

        $markdown = '';

        $quote_content = trim($node->nodeValue);

        $lines = preg_split('/\r\n|\r|\n/', $quote_content);

        $total_lines = count($lines);

        foreach ($lines as $i => $line) 
        {
            $markdown .= "> " . $line . PHP_EOL;
            if ($i + 1 == $total_lines) $markdown .= PHP_EOL;
        }

        return $markdown;
    }

    /**
     * Get Position
     *
     * Returns the numbered position of a node inside its parent
     *
     * @param   DOMNode
     * @return  int The numbered position of the node, starting at 1.
     */
    protected function get_position($node)
    {
        // Get all of the nodes inside the parent
        $list_nodes = $node->parentNode->childNodes;
        $total_nodes = $list_nodes->length;

        $position = 1;

        // Loop through all nodes and find the given $node
        for ($a = 0; $a < $total_nodes; $a++) 
        {
            $current_node = $list_nodes->item($a);

            if ($current_node->isSameNode($node)) $position = $a + 1;
        }

        return $position;
    }

    /**
     * Get Next Node Name
     *
     * Return the name of the node immediately after the passed one.
     *
     * @param   DOMNode
     * @return  string|null The node name (e.g. 'h1') or null.
     */
    protected function get_next_node_name($node)
    {
        $next_node_name = null;

        $current_position = $this->get_position($node);
        $next_node = $node->parentNode->childNodes->item($current_position);

        if ($next_node) $next_node_name = $next_node->nodeName;

        return $next_node_name;
    }

    /**
     * To String
     *
     * Magic method to return Markdown output when HTML_To_Markdown instance is treated as a string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->output();
    }

    /**
     * Output
     *
     * Getter for the converted Markdown contents stored in $this->output
     *
     * @return string
     */
    public function output()
    {
        // return the markdown output
        return $this->get_markdown();
    }
    
    /**
     * The original html source
     *
     * @return string
     */
    public function source()
    {
        return $this->source;
    }
}
