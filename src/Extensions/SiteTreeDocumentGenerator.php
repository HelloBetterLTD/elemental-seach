<?php
/**
 * Created by Nivanka Fonseka (nivanka@silverstripers.com).
 * User: nivankafonseka
 * Date: 9/7/18
 * Time: 12:21 PM
 * To change this template use File | Settings | File Templates.
 */

namespace SilverStripers\ElementalSearch\Extensions;

class SiteTreeDocumentGenerator extends SearchDocumentGenerator
{

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
        self::make_document_for($this->owner);
    }

    public function onBeforeArchive()
    {
        return null;
    }

    public function onAfterArchive()
    {
        self::delete_doc($this->owner);
    }

}
