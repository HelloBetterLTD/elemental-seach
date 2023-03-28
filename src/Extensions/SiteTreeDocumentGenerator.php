<?php
/**
 * Created by Nivanka Fonseka (nivanka@silverstripers.com).
 * User: nivankafonseka
 * Date: 9/7/18
 * Time: 12:21 PM
 * To change this template use File | Settings | File Templates.
 */

namespace SilverStripers\ElementalSearch\Extensions;

use SilverStripe\Control\Director;
use SilverStripe\Versioned\Versioned;

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

    public function getGenerateSearchLink()
    {
        $owner = $this->owner;
        if(method_exists($owner, 'Link')) {
            $mode = Versioned::get_reading_mode();
            Versioned::set_reading_mode('Stage.Live');
            $link = Director::absoluteURL($owner->Link());
            $link = str_replace('stage=Stage', '', $link);
            Versioned::set_reading_mode($mode);
            if(strpos($link, '?') !== false) {
                return $link . '&SearchGen=1';
            }
            return $link . '?SearchGen=1';
        }
        $class = get_class($owner);
        throw new Exception(
            "SearchDocumentGenerator::getGenerateSearchLink() There is no Link method defined on class '$class'"
        );
    }

}
