The Bettersearch plugin adds a fulltext search to your website with a modern modal window that integrates smoothly into all themes. The plugin requires a [MAKER license](https://typemill.net/license/buy) and works with Typemill version 2.19.3 and higher. It is compatible with PHP 8.2 to PHP 8.5 and is based on flexsearch.js.

Just upload the plugin and activate it in the plugin area. You can customize the wordings of the search-placeholder and the no-result-message. The plugin will add a search-bar to your website and open a modal search window if a user clicks on the search-bar. Test the search functionality on the [Typemill website](https://typemill.net/getting-started).

![Animated gif demonstrates the bettersearch plugin](media/live/typemill-bettersearch.gif){.center loading="lazy" width="820" height="489"}

The modal window will also provide a quick-filter on the left side that lists all the first-level-folders of your website. This is a super simple and highly effective way to filter the results by main-topics.

The plugin does not require any third-party-software like Google Search or Algolia. Instead it includes the JavaScript search library [flexsearch.js](https://github.com/nextapps-de/flexsearch) and generate a full-text search index on the fly.

## GitHub

Found a bug? Or do you want to contribute some improvements? You can find the repository on [GitHub](https://github.com/typemill-resources/bettersearch). Create a pull request or open a new issue if you found a bug.

## Updates

### Version 3.4.0

* Fixed dynamic property deprecation for PHP 8.2 to PHP 8.5 compatibility.

### Version 3.3.1

Fixed error with cross-project search [GitHub Issue 384](https://github.com/typemill/typemill/issues/584)

### Version 3.3.0

[Markus Ritter](https://github.com/doppelrittberger) switched the library from lunr.js to the more modern and reliable solution flexsearch.js. Thank you for the contribution!

### Version 3.2.0

* Implemented search for current project
* Implemented option to search through all projects (fullindex search)
* Removed session logic and added token security with 15 min lifetime to get the index.
* Added error message when index load fails.
* Added the homepage to the search index.

### Version 3.1.2

* Updated readme
* Added license check
* Fix html error in search form
* remove empty lines and preserve links in index for seo plugin
* Fix error when search and bettersearch are active

### Version 3.1.0

Added option to show only an inline icon instead of a search field. This might not work for all themes

### Version 3.0.1

* Path to cached version is fixed so it get's deleted properly now.
* Error with language attribute is fixed so language stemmer is initialized now.

### Version 3.0.0

Initial version based on the search plugin, published on 22.07.2024.

