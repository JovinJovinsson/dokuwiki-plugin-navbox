# dokuwiki-plugin-navbox
This plugin enables the ability to have a 'navbox' of related articles similar to the way Wikipedia does on some pages.

The DokuWiki Home Page will be https://www.dokuwiki.org/plugin:navbox however there is currently no data on there.

To use this plugin, follow standard installation instructions and use the below syntax:
<navbox>
nb-title TITLE
nbg-title GROUP TITLE
nbg-items [[links]]
</navbox>

nb-title & nbg-title accept text and wiki markup links
nbg-items accepts a series of wiki markup links, no delimiters required
