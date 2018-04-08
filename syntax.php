<?php

/**
 * NavBox Plugin for DokuWiki (Syntax Component)
 *
 * This plugin enables the ability to have a 'navbox' of related articles
 * similar to the way Wikipedia does on some pages.
 *
 * Wikipedia Example: https://en.wikipedia.org/wiki/Singapore
 * Scroll to the bottom to see "Singapore Articles" section
 *
 * @license GPL 2 https://www.gnu.org/licenses/gpl-2.0.html
 * @author Jovin Sveinbjornsson
 * @author Midgard Apps <hello@midgardapps.com>
 *
 * @version 0.1
 */

// Must be run within DokuWiki
if (!defined('DOKU_INC')) die();

class syntax_plugin_navbox extends DokuWiki_Syntax_Plugin {
    /**
     * What kind of syntax?
     */
    public function getType() {
        return 'container';
    }
    
    /**
     * How do we handle paragraphs?
     */
    public function getPType() {
        return 'block';
    }
    
    /*
     * When should this be executed?
     */
    public function getSort() {
        return 59;
    }
    
    public function getAllowedTypes() {
        return array('container', 'formatting', 'substition', 'disabled', 'protected', 'paragraphs');
    }
    
    /**
     * Connect Lookup pattern to lexer
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<navbox>.*?</navbox>', $mode, 'plugin_navbox');
    }
    
    /**
     * Handler to match the data and kick off rendering
     *
     * @param string $match The text matched by the patterns
     * @param int $state The lexer state for the match
     * @param int $pos The character position of the matched text
     * @param Doku_Handler $handler The Doku_Handler object
     *
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler $handler) {
        // Remove the <naxbox> and </navbox>
        $match = substr($match, 8, -9);
        // Separate the content into individual lines
        $lines = explode("\n", $match);
        // We'll store all our variables in here for processing later
        $navbox = array();
        // Temporary area to store groups
        $navgroup = array();
        
        // Loop over while we continue to have more to process
        while(count($lines) > 0) {
            // Clean up and work only with the current line, remove it from the remaining array
            $line = trim(array_shift($lines));
            // If it's not valid, skip
            if(!$line) continue;
            // Use our function to cleanly split the data
            $args = $this->grab_data($line);
            
            // Place our args into the $navbox array in the correct method
            // 2D Array with structure:
            // $navbox[0] = "The title";
            // $navbox[1..n] = ["nbg-title", "Group title", "items" => ["[[Link1]]", "[[Link n]]"]];
            // If we can't find any of our 'sort tags' then just skip this
            if (in_array($args[0], array('nb-title', 'nbg-title', 'nbg-items'))) {
                // In this case, we only have our 'sort tag', this is invalid, skip it
                if (count($args) < 2) {
                    msg(sprintf($this->getLang('e_missingargs'), hsc($args[0]), hsc($args[1])), -1);
                    continue;
                }
                
                // nb-title is the NavBox title
                if ($args[0] == 'nb-title') {
                    // Make this the first element in the NavBox
                    array_unshift($navbox, $args[1]);
                    continue;
                }
                
                // nbg-title is the Group (left column) title
                if ($args[1] == 'nbg-title') {
                    // Store it in our temporary variable
                    $navgroup = $args;
                }
                
                // nbg-items is a list of the links to contain
                if ($args[1] = "nbg-items") {
                    // Remove the 'nbg-items' value
                    array_shift($args);
                    // Append them to the key "items"
                    $navgroup["items"] = $args;
                    // Store this group at the end of our $navbox
                    $navbox[] = $navgroup;
                    // Clear the array
                    $navgroup = array();
                }
            }
        }
        
        return $navbox;
    }
    
    /**
     * Handles the actual output creation
     *
     * @param string $mode Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer $renderer The renderer
     * @param array $data The data from the handler() function
     *
     * @return bool If rendering was successful
     */
    public function render($mode, Doku_Renderer $renderer, $data) {
        if ($mode != 'xhtml') return false;
        // Prevent caching
        $renderer->info['cache'] = false;
        
        // Build the beginnings of the table
        $html = '<div class="pgnb_container"><table class="pgnb_table"><tr><th class="pgnb_title" colspan="2"><span class="pgnb_title_text">';
        // Add in the title
        $html .= $data[0];
        // Prepare for the groups
        $html .= '</span></th></tr>';
        // Get rid of the title to iterate over the groups
        array_shift($data);
        
        foreach ($data as $group) {
            // Something is wrong, skip this one
            if ($group[0] != 'nbg-title') continue;
            // Placeholder for group HTML while we build it,  Add in the group title, and prepare for the items
            $ghtml = '<tr><th class="pgnb_group_title">'.$group[1].'</th><td class="pgnb_group"><div style="padding:0em 0.25em;"><ul class="pgnb_list">';
            // Iterate over each item and append the HTML to our placeholder
            foreach ($group["items"] as $item) {
                $ghtml = '<li>'.$item.'</li>';
            }
            // Close the group
            $ghtml = '</ul></div></td></tr>';
            // Append the group to our HTML
            $html .= $ghtml;
            // Reset our placeholder
            $ghtml = '';
        }
        
        // Close out the table
        $html .= '</table></div>';
        
        $renderer->doc .= $html;
        
        return true;
    }
    
    /**
     * Function to identify the data points for the table
     * Adapted from the function _parse_line in the Bureaucracy plugin
     * @source https://github.com/splitbrain/dokuwiki-plugin-bureaucracy/blob/master/syntax.php#L399
     *
     * @param string $line The current working line
     * 
     * @return array The identified details to be utilised
     */
    private function grab_data($line) {
        // Our data to return
        $args = array();
        // Identify if we're inside quotation marks
        $inQuote = false;
        // Identify if we're inside a DokuWiki Link [[]]
        $inLink = false;
        // The current tag we're working with
        $arg = '';
        
        // Identify the number of characters to parse
        $len = strlen($line);
        // Iterate over every character
        for ($i = 0; $i < $len; $i++) {
            // Is this a "
            if ($line[$i] == '"') {
                if ($inQuote) { // If this is the 2nd " we've seen
                    // End the current item we're parsing
                    array_push($args, $arg);
                    // Switch off the inQuote
                    $inQuote = false;
                    // Empty the placeholder
                    $arg = '';
                    // Don't append anything
                    continue;
                } else { // This is the first " we've seen
                    // Turn on the inQuote
                    $inQuote = true;
                    // Don't append anything
                    continue;
                }
            } else if ($line[$i] == '[' && $line[$i+1] == '[') { // This signals a DokuWiki link
                if (!$inLink) { // We're not currently in a link
                    // Append this to our current tag
                    $arg .= $line[$i];
                    // Get the next as well
                    $i++;
                    // Append it too
                    $arg .= $line[$i];
                    // Turn on the inLink
                    $inLink = true;
                    continue;
                } else { //Something has gone wrong
                    // Let's tell the user we had a bad link
                    $arg = 'Bad Link';
                    // Kill this line off
                    array_push($args, $arg);
                    $arg = '';
                    continue;
                }
            } else if ($line[$i] == ']' && $line[$i+1] == ']') { // This signals the closure of a DokuWiki link
                if ($inLink) { // We're currently building a link
                    $arg .= $line[$i];
                    $i++;
                    $arg .= $line[$i];
                    $inLink = false;
                    
                    if ($inQuote) { // This link was in a string arg
                        continue;
                    } else { // This is a standalone link
                        array_push($args, $arg);
                        $arg = '';
                        continue;
                    }
                }
            } else if ($line[$i] == ' ') {
                if ($inQuote || $inLink) { // We're allowed spaces in quotes and links
                    $arg .= ' ';
                    continue;
                } else {
                    if (strlen($arg) < 1) continue; // Don't append if it was a random or double space
                    array_push($args, $arg);
                    $arg = '';
                    continue;
                }
            }
            $arg .= $line[$i];
        }
        // Catch any tailing things (this could break the user's input)
        if (strlen($arg) > 0) array_push($args, $arg);
        return $args;
    }
}

?>