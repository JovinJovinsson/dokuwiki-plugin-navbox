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
 * @version 1.0
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
        return 275;
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
        $current = '';
        
        // Loop over while we continue to have more to process
        while(count($lines) > 0) {
            // Clean up and work only with the current line, remove it from the remaining array
            $line = trim(array_shift($lines));
            // If it's not valid, skip
            if (strlen($line) < 1) continue;
            
            // Check if this is the title
            if (strpos($line, 'nb-title') !== false) {
                // Store it
                $navbox['nb-title'] = substr($line, 9);
            } else if (strpos($line, 'nbg-title') !== false) { // Check for the group title
                // Store it
                $current = substr($line, 10);
            } else if (strpos($line, 'nbg-items') !== false && strlen($current) > 0) { // Check that we have a valid group, and get the items
                // Begin at the first link
                $items = substr($line, 10);
                
                // Minimum length for a link is 5 characters, [[z]]
                while (strlen($items) > 5) {
                    // Find the opening of the link
                    $start = strpos($items, '[[');
                    // Find the close of the link, increment by 2 to offset the ]]
                    $end = strpos($items, ']]');
                    
                    // Check that we have a valid link
                    if ($start !== false && $end !== false) {
                        // Offset the end to account for the ]]
                        $end += 2;
                        // Store this in a temporary array
                        array_push($navgroup, substr($items, $start, $end));
                        // Remove this link from the items
                        $items = substr($items, $end);
                    } else {
                        // Not valid, just break
                        break;
                    }
                }
                // Store our links against their title name
                $navbox[$current] = $navgroup;
                // Reset our placeholders
                $current = '';
                $navgroup = array();
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
        
        //$renderer->doc .= var_dump($data);
        
        // Build the beginnings of the table
        $html = '<div class="pgnb_container"><table class="pgnb_table"><tr><th class="pgnb_title" colspan="2"><span class="pgnb_title_text">';
        // Add in the title
        $html .= $data['nb-title'];
        // Prepare for the groups
        $html .= '</span></th></tr>';
        
        // Get rid of the title to iterate over the groups
        array_shift($data);
        
        // Placeholder for our Group
        $ghtml = '';
        
        // Go through each item group and build their row
        foreach ($data as $group => $items) {
            // Placeholder for group HTML while we build it,  Add in the group title, and prepare for the items
            $ghtml = '<tr><th class="pgnb_group_title">'.$group.'</th><td class="pgnb_group"><div style="padding:0em 0.25em;"><ul class="pgnb_list">';
            // Iterate over each item and append the HTML to our placeholder
            foreach ($items as $item) {
                $url = $renderer->render($item, $format='xhtml');
                $ghtml .= '<li>'.substr($url, 5, strlen($url)-11).'</li>';
            }
            // Close the group
            $ghtml .= '</ul></div></td></tr>';
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
}

?>