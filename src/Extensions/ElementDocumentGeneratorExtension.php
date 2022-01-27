<?php
/**
 * Created by Nivanka Fonseka (nivanka@silverstripers.com).
 * User: nivankafonseka
 * Date: 9/7/18
 * Time: 12:21 PM
 * To change this template use File | Settings | File Templates.
 */

namespace SilverStripers\ElementalSearch\Extensions;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Core\Config\Config;
use SilverStripers\ElementalSearch\Model\SearchDocument;

class ElementDocumentGeneratorExtension extends SearchDocumentGenerator
{

    public function getGenerateSearchLink()
    {
        /* @var $element BaseElement */
        $element = $this->owner;
        $page = $element->getPage();
        return $page->getGenerateSearchLink();
    }

    public function canCreateDocument()
    {
        $page = $element->getPage();
        return $page->canCreateDocument();
    }

    public function createSearchDocument()
    {
        $page = $element->getPage();
        return $page->createSearchDocument();
    }

    public function deleteSearchDocument()
    {
        $page = $element->getPage();
        return $page->deleteSearchDocument();
    }

    public function onAfterDelete()
    {
        return; // we dont want to delete the page document just because an element was deleted
    }

    public function onAfterUnpublish()
    {
        return; // we dont want to delete the page document just because an element was deleted
    }

    public function onAfterArchive()
    {
        return; // we dont want to delete the page document just because an element was deleted
    }
}
