# SilverStripe - Elemental search 

## Introduction

This module integrates [dnadesign/silverstripe-elemental](https://github.com/dnadesign/silverstripe-elemental)
into [SilverStripe's Fulltext search](https://docs.silverstripe.org/en/4/developer_guides/search/fulltextsearch/) 
and provides extensions to create search documents for your Pages or any data objects which you need to work with the full text search. The module comes in handy for systems where you dont want to make use of complex search systems like Solr. 

## Requirements

* SilverStripe ^4.0
* Elemental ^2.0

## Installation

Install with Composer:

```
composer require silverstripers/elemental-search dev-master
```

Ensure you run `dev/build?flush=1` to build your database and flush your cache.

## Usage

### Enable Full text search 

The module once installed elables Full text search by itself on the SearchDocument dataobject. There are no additional configs you want to use.

### Create a search form 

Create a search form with the code below and display on a template 

```
use SilverStripe\CMS\Search\SearchForm;


class  MyController extends Controller {

   public function SearchForm() 
   {
      return SearchForm::create($this, 'SearchForm');
   }
   
   public function results($data, $form, $request)
    {
        $data = array(
            'Results' => $form->getResults(),
            'Query' => DBField::create_field('Text', $form->getSearchQuery()),
            'Title' => _t('SilverStripe\\CMS\\Search\\SearchForm.SearchResults', 'Search Results')
        );
        return $this->owner->customise($data)->renderWith(array('Page_results', 'Page'));
    }

}

```

### Include objects to search. 

Any data object you'd like to include in the search has to be decorated with the `SilverStripers\ElementalSearch\Extensions\SearchDocumentGenerator`. After doing this this extension will make a search document each time when the data object is created, updated, and when deleted it will delete the search document. For Versioned data objects it will only create search documents when the object is published. 

### Specify which contents needs to be searched. 

For the webpages you have the option to configure which dom elements to include in the search. Eg: You wont need it to cache the whole page and run search on certain info which duplicates over the site like the navigations. 

```
SilverStripers\ElementalSearch\Model\SearchDocument:
    search_x_path:
        - main-content
```

The above settings will render the page and exract content within the `main-content` DOM node, and create the document. 


## How it works 

The module adds a new DataObject `SearchDocument`. This gets created when each of the search enabled pages. And it is how it creates aggregated search results. 

The module overrides the MySQLDatabase and the search result queries.

## Reporting Issues

Please [create an issue](https://github.com/SilverStripers/elemental-seach/issues) for any bugs, or submit merge requests. 




