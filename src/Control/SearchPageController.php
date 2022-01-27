<?php

namespace SilverStripers\ElementalSearch\Control;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\FieldType\DBText;
use SilverStripers\ElementalSearch\CMS\Search\SearchForm;
use SilverStripers\ElementalSearch\Model\SearchDocument;

class SearchPageController extends \PageController
{

    private static $allowed_actions = [
        'results'
    ];

    public function SearchForm()
    {
        $form = SearchForm::create($this, 'SearchForm');
        $form->setFormMethod('get');
        $form->setFormAction($this->Link('results'));
        $form->classesToSearch([
            SearchDocument::class
        ]);
        $form->loadDataFrom($this->getRequest()->getVars());
        $this->invokeWithExtensions('updateSearchForm', $form);
        return $form;
    }

    public function results()
    {
        $form = $this->SearchForm();
        $data = array(
            'Results' => $form->getResults(),
            'Query' => DBText::create_field('Text', $form->getSearchQuery()),
            'Title' => _t(
                'SilverStripe\\CMS\\Search\\SearchForm.SearchResultsFor',
                'Search Results for "{query}"',
                ['query' => $form->getSearchQuery()]
            )
        );
        return $this->owner->customise($data);
    }

}
