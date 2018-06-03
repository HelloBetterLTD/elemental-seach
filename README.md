# SilverStripe - Elemental search 

## Introduction

This module integrates [dnadesign/silverstripe-elemental](https://github.com/dnadesign/silverstripe-elemental)
into [SilverStripe's Fulltext searc](https://docs.silverstripe.org/en/4/developer_guides/search/fulltextsearch/) 
and provides extensions over for SilverStriper's core classes to create database indexes, search forms, and perform full text 
search over the elemetal blocks connecting to various elemental area / areas. 

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

To enable Full text search add the following code to your projects `_config.php` file

```
<?php

use SilverStripers\ElementalSearch\ORM\Search\FulltextSearchable;

FulltextSearchable::enable();

```

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

### Specify Fulltext search fields. 

On eh of the Elemental classes add a config which specify the fields which needs to be searched for. 

```
private static $fulltext_fields = [
  'MyField',
  'MyField2'
];
```

## How it works 

The module overrides the MySQLDatabase and the search result queries. It joins the tables of the objects which has ElementalAreas and the Elemental classes to the related from tables. 

Eg: Page is joined to ElementalArea joined to Element 

This performs match and relevance queries over the tables to provide results lists, and return the matches. 

## Reporting Issues

Please [create an issue](https://github.com/SilverStripers/elemental-seach/issues) for any bugs, or submit merge requests. 




