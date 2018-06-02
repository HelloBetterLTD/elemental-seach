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
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;

class SearchForm extends \SilverStripe\CMS\Search\SearchForm
{

	public function classesToSearch($classes)
	{
		$supportedClasses = array(SiteTree::class, File::class, BaseElement::class);

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

}