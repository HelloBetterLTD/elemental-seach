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
        return $page ? $page->Link() : null;
    }

    public function onAfterWrite()
    {
        return null;
    }

    public function onAfterDelete()
    {
        return null;
    }

    public function onAfterPublish()
    {
        if ($this->isThisAStandAloneClass()) {
            self::make_document_for($this->owner);
        }
        if (!SearchDocumentGenerator::search_documents_prevented()) {
            $this->makeSearchDocumentForPage();
        }
    }

    public function onBeforeArchive()
    {
        return null;
    }

    public function onAfterArchive()
    {
        if ($this->isThisAStandAloneClass()) {
            self::delete_doc($this->owner);
        }
        if (!SearchDocumentGenerator::search_documents_prevented()) {
            $this->makeSearchDocumentForPage();
        }
    }

    public function makeSearchDocumentForPage()
    {
        /* @var $element BaseElement */
        $element = $this->owner;
        $page = $element->getPage();
        if($page) {
            self::make_document_for($page);
        }
    }

    private function isThisAStandAloneClass()
    {
        if (($classes = $this->getStandAloneElementClasses()) && in_array(get_class($this->owner), $classes)) {
            return true;
        }
        return false;
    }

    public function getStandAloneElementClasses()
    {
        return SearchDocument::config()->get('stand_alone_search_elements');
    }

}
