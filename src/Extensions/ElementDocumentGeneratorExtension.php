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
        $this->makeSearchDocumentForPage();
    }

    public function onBeforeArchive()
    {
        return null;
    }

    public function onAfterArchive()
    {
        $this->makeSearchDocumentForPage();
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

}
