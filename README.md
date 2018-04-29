# dokuwiki-plugin-navbox
This plugin enables the ability to have a 'navbox' of related articles similar to the way Wikipedia does on some pages.

The DokuWiki Home Page will be https://www.dokuwiki.org/plugin:navbox however there is currently no data on there.

To use this plugin, follow standard installation instructions and use the below syntax:
```
<navbox>
nb-title TITLE
nbg-title GROUP TITLE
nbg-items [[links]]
nbg-namespace [[self]]
</navbox>
```

**nb-title** & **nbg-title** accept text and wiki markup links

**nbg-items** accepts a series of wiki markup links, no delimiters required

**nbg-namespace** can list all pages in the current namespace, title can be overridden with the parameter of |Override Title]] to close the tag, yet to come, listing all pages in a specified namespace.

You can continue to repeat any **nbg-** item as many times as you like
