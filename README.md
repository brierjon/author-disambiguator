# author-disambiguator
Wikidata service to help create or link author items to published articles

To use the tool go to https://tools.wmflabs.org/author-disambiguator/

To run in development all it needs is a php server with an https front-end. Install the files as provided in this distribution directly in an appropriate subdirectory of your local web server's documents root directory.

# Features

* find possible author items that match a particular author name string, listing works with that author name string and allowing selection of which works should have the strings replaced with the author links
* list all works with a particular author, and allow a selection of those works to be moved to another author or replaced with the appropriate author name string value (no author item) if previously matched to the wrong author
* list all the authors on a single work, allowing author order to be rearranged, allowing duplicate author/author name string entries to be merged, and allowing multiple author strings to be matched to author items (OAuth version only)
* allow SPARQL filters to be applied to any of these lists of works, and automate those filters in many cases
