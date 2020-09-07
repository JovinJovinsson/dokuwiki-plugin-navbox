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
 * Midgard Apps OpenWiki Example
 * https://openwiki.midgardapps.com/
 *
 * @license The Unlicense http://unlicense.org/
 * @author Jovin Sveinbjornsson
 * @author Midgard Apps <hello@midgardapps.com>
 */

use dokuwiki\Parsing\Parser;

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
        return 205;
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
        // Switches
        $groupType = 0; // 0 = none, 1 = group, 2 = subgroup
        $autoSub = false;
        // Temporary Variables
        $currentGroup = array();
        $current = '';
        $currentSub = '';
        
        
        // Loop over while we continue to have more to process
        while(count($lines) > 0) {
            // Clean up and work only with the current line, remove it from the remaining array
            $line = trim(array_shift($lines));
            // If it's not valid, skip
            if (strlen($line) < 1) continue;
            
            // This if/else cascade proceeds in Specific -> Less Specific for syntax
            if (strpos($line, '### !') !== false) {
                // Subgroup with Advanced Syntax
                // Turn on the 'subgroup' flag
                $autoSub = true;
            } else if (strpos($line, '### ') !== false) {
                // Subgroup
                // Name our Subgroup
                $currentSub = substr($line, 4);
                // Set the group type so we an add links appropriately
                $groupType = 2;
                // No further processing required
                continue;
            } else if (strpos($line, '## ') !== false) {
                // Group
                // Check if we already have a group, if so, do this
                if (!empty($currentGroup)) {
                    // Store the current group
                    $navbox[$current] = $currentGroup;
                    // Start a new group
                    $currentGroup = array();
                    // Clear the Subgroup name too
                    $currentSub = '';
                }
                // Name our new group
                $current = substr($line, 3);
                // Set the group type so we can add links appropriately
                $groupType = 1;
                // No further processing required
                continue;
            } else if (strpos($line, '# ') !== false) {
                // Title
                // Store the title
                $navbox['title'] = substr($line, 2);
                // No further processing required
                continue;
            } else if (substr($line, 0, 2) == '[[') {
                // We have a list of links
                // These are the valid separators for the links, also no separators are valid too
                $separators = [',', ';'];
                // If we are dealign with a Group
                if ($groupType == 1) {
                    // Store the links in the 'default' section
                    $currentGroup['default'] = str_replace($separators, '', $line);
                } else if ($groupType == 2) {
                    // We are dealing with a Subgroup instead
                    // Store the links in the current Subgroup
                    $currentGroup[$currentSub] = str_replace($separators, '', $line);
                }
            } else {
                // This is a automated flag, unset all switches
                $autoSub = false;
                $groupType = 0;
                
                // We need to store the current group (if we have one) as it was not a SubGroup for the Automated tag
                if (!empty($currentGroup)) {
                    $navbox[$current] = $currentGroup;
                    $currentGroup = array();
                    $currentSub = '';
                }
            }            
        
            // The below will identify what kind of automated generation is required
            if (strpos($line, '!ns') !== false) {
                // A Namespace listing
                // Offset if auto space is used
                $offset = 0;
                if ($autoSub) {
                    $offset = 4;
                }
                // Get the current namespace
                $namespace = pageinfo()['namespace'];
                // If the +n parameter is used, change the namspace
                if (strpos($line, '+n') !== false) {
                    // Custom Namespace
                    $namespace = substr($line, 8 + $offset, -2);
                }
                // Get the lowest level namespace, this is our automatic title
                $title = array_pop(explode(':', $namespace));
                // If the +t parameter is used, change the title
                if (strpos($line, '+t') !== false) {
                    // Custom Title
                    $title = substr($line, 6 + $offset);
                }
                // If the +nt parameter is used, change the namespace and title
                if (strpos($line, '+nt') !== false) {
                    // Find where the namespace begins
                    $nsStart = strpos($line, '[[') + 2;
                    // Find where the title begins
                    $tStart = strpos($line, '|') + 1;
                    // Extract the title
                    $title = substr($line, $tStart, -2);
                    // Extract the namespace
                    $namespace = substr($line, $nsStart, ($tStart - $nsStart - 1));
                }
                // String for the working directory of the namespace
                $dir = './data/pages/'.str_replace(':', '/', $namespace);
                // Instantiate our Links variable
                $links = '';
                // Look in the directory and get all .txt files (doku pages)
                foreach (glob($dir.'/*.txt') as $filename) {
                    // Store each file as a new markup link
                    $links .= '[['.str_replace('/', ':', substr($filename, 13, -4)).']]';
                }
                // Identify if this should be a subgroup
                if ($autoSub) {
                    // Append to the parent group
                    $currentGroup[$title] = $links;
                } else {
                    // Add as a main level group
                    $navbox[$title]['default'] = $links;
                }
            } else if (strpos($line, '!tree') !== false) {
                // The hierarchy of this page
            } else if (strpos($line, '!tag') !== false) {
                // Tag listing, need to use the pagelist plugin for this one
                // This is a stretch goal, well and truly
            }
            
            // We are working on the last line group, store our groups
            if (count($lines) == 1) {
                if (!empty($currentGroup)) {
                    $navbox[$current] = $currentGroup;
                }
            }
        }
        //echo '<pre>';
        //var_dump($navbox);
        //echo '</pre>';
        
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
        
        // Placeholder for our xhtml formatted URL
        $url = '';

        // Add in the title, parse it first to generate any URLs present
        if (strpos($data['title'], '[[') !== false) {
            $html .= $this->urlRender($data['title']);
        } else {
            $html .= $data['title'];
        }
        // Prepare for the groups
        $html .= '</span></th></tr>';
        
        // Get rid of the title to iterate over the groups
        array_shift($data);
        
        // Placeholder for our Group
        $ghtml = '';
        
        // Go through each item group and build their row
        foreach ($data as $group => $items) {
            // Placeholder for group HTML while we build it,  Add in the group title, and prepare for the items
            $ghtml = '<tr><th class="pgnb_group_title">';
            
            if (strpos($group, '[[') !== false) {
                $ghtml .= $this->urlRender($group);
            } else {
                $ghtml .= $group;
            }
            
            $ghtml .= '</th><td class="pgnb_group">';
            
            // Flag for formatting the child table
            $subgroupPresent = false;
            
            // Iterate over each subgroup, there will always be a 'default'
            foreach ($items as $subgroup => $subitems) {
                // Render all the links
                $urls = $this->urlRender($subitems);
                // Format into the list
                $urls = str_replace("<a", "<li><a", $urls);
                $urls = str_replace("</a>", "</a></li>", $urls);

                // The base group
                if ($subgroup == 'default') {
                    // Append the list of URLs
                    $ghtml .= '<div style="padding:0.25em;"><ul class="pgnb_list">'.$urls.'</ul></div>';
                } else {
                    // We are working with a subgroup, additional HTML tags required
                    // If we don't already have a child table for the subgroups, create one
                    if (!$subgroupPresent) {
                        // This is our first subgroup
                        $ghtml .= '<table class="pgnb_child_table">';
                        // Turn on the switch
                        $subgroupPresent = true;
                    }
                    // Append the row for the subgroup
                    $ghtml .= '<tr><th class="pgnb_subgroup_title">';
                    
                    if (strpos($subgroup, '[[') !== false) {
                        $ghtml .= $this->urlRender($subgroup);
                    } else {
                        $ghtml .= $subgroup;
                    }
                    
                    $ghtml .= '</th><td class="pgnb_group"><div style=padding:0.25em;"<ul class="pgnb_list">'.$urls."</ul></div></td></tr>";
                }
            }
            
            // We had subgroups, close off the child table
            if ($subgroupPresent) {
                $ghtml .= '</table>';
            }
            
            // Close the group
            $ghtml .= '</td></tr>';
            
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
     * Handles rendering of DokuWiki links to URLs for all kinds of URL
     *
     * @param string $item The DokuWiki markup to be converted
     *
     * @return string The XHTML rendering of the markup
     */
    private function urlRender($item) {
        // Create the parser
        $urlParser = new Parser(new Doku_Handler());

        // Add all the parsing modes for various URLs
        $modes = p_get_parsermodes();
        foreach($modes as $mode){
            $urlParser->addMode($mode['mode'],$mode['obj']);
        }

        // Parse the string into instructions
        $instructions = $urlParser->parse($item);

        // Create the renderer
        $urlRenderer = new Doku_Renderer_XHTML();
        // Iterate over each instruction
        foreach ($instructions as $instruction) {
            // Execute the callback against the renderer
            call_user_func_array(array(&$urlRenderer, $instruction[0]), $instruction[1]);
        }
        // Extract the XHTML data
        $url = $urlRenderer->doc;
        // Return the XHTML excluding the <p> and </p> tags
        return substr($url, 5, strlen($url)-11);
    }
}

?>
