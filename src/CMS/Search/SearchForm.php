<?php
/**
 * Created by Nivanka Fonseka (nivanka@silverstripers.com).
 * User: nivankafonseka
 * Date: 6/2/18
 * Time: 12:06 PM
 * To change this template use File | Settings | File Templates.
 */

namespace SilverStripers\ElementalSearch\CMS\Search;

use BadMethodCallException;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Search\SearchForm as SS_SearchForm;
use SilverStripe\Control\Controller;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\SS_List;
use SilverStripers\ElementalSearch\Model\SearchDocument;

class SearchForm extends SS_SearchForm
{

    protected $classesToSearch = array(
        SearchDocument::class,
        File::class
    );

	public function classesToSearch($classes)
	{
		$supportedClasses = array(SearchDocument::class, File::class);
        if(empty($classes)){
            $classes = $supportedClasses;
        }

		$illegalClasses = array_diff($classes, $supportedClasses);
		if ($illegalClasses) {
			throw new BadMethodCallException(
				"SearchForm::classesToSearch() passed illegal classes '" . implode("', '", $illegalClasses)
				. "'.  At this stage, only File and SiteTree are allowed"
			);
		}
		$legalClasses = array_intersect($classes, $supportedClasses);
		$this->classesToSearch = $legalClasses;
	}

    /**
     * Return dataObjectSet of the results using current request to get info from form.
     * Wraps around {@link searchEngine()}.
     *
     * @return SS_List
     */
    public function getResults()
    {
        // Get request data from request handler
        $request = $this->getRequestHandler()->getRequest();

        // set language (if present)
        $locale = null;
        $origLocale = null;
        if (class_exists('Translatable')) {
            $locale = $request->requestVar('searchlocale');
            if (SiteTree::singleton()->hasExtension('Translatable') && $locale) {
                if ($locale === "ALL") {
                    Translatable::disable_locale_filter();
                } else {
                    $origLocale = Translatable::get_current_locale();

                    Translatable::set_current_locale($locale);
                }
            }
        }

        $keywords = $request->requestVar('Search');

        $andProcessor = function ($matches) {
            return ' +' . $matches[2] . ' +' . $matches[4] . ' ';
        };
        $notProcessor = function ($matches) {
            return ' -' . $matches[3];
        };

        $keywords = preg_replace_callback('/()("[^()"]+")( and )("[^"()]+")()/i', $andProcessor, $keywords);
        $keywords = preg_replace_callback('/(^| )([^() ]+)( and )([^ ()]+)( |$)/i', $andProcessor, $keywords);
        $keywords = preg_replace_callback('/(^| )(not )("[^"()]+")/i', $notProcessor, $keywords);
        $keywords = preg_replace_callback('/(^| )(not )([^() ]+)( |$)/i', $notProcessor, $keywords);

        $keywords = $this->addStarsToKeywords($keywords);

        $pageLength = $this->getPageLength();
        $start = $request->requestVar('start') ?: 0;

        $booleanSearch =
                strpos($keywords, '"') !== false ||
                strpos($keywords, '+') !== false ||
                strpos($keywords, '-') !== false ||
                strpos($keywords, '*') !== false;

        $this->extend('updateBooleanSearchParam', $booleanSearch);

        $results = DB::get_conn()->searchEngine($this->classesToSearch, $keywords, $start, $pageLength, "\"Relevance\" DESC", "", $booleanSearch);

        // filter by permission
        if ($results) {
            foreach ($results as $result) {
                if (!$result->canView()) {
                    $results->remove($result);
                }
            }
        }

        // reset locale
        if (class_exists('Translatable')) {
            if (SiteTree::singleton()->hasExtension('Translatable') && $locale) {
                if ($locale == "ALL") {
                    Translatable::enable_locale_filter();
                } else {
                    Translatable::set_current_locale($origLocale);
                }
            }
        }

        return $results;
    }

}
