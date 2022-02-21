<?php
/**
 * Created by Nivanka Fonseka (nivanka@silverstripers.com).
 * User: nivankafonseka
 * Date: 9/7/18
 * Time: 11:48 AM
 * To change this template use File | Settings | File Templates.
 */

namespace SilverStripers\ElementalSearch\Extensions;

use \Exception;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\TemplateGlobalProvider;
use SilverStripers\ElementalSearch\Model\SearchDocument;
use SilverStripers\ElementalSearch\Page\SearchPage;

class SearchDocumentGenerator extends DataExtension implements TemplateGlobalProvider
{

    use Configurable;

    private static $excluded_classes = [
        'SilverStripe\ErrorPage\ErrorPage',
        SearchPage::class
    ];

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

    /**
     * @return bool
     */
    public function canCreateDocument()
    {
        $ret = true;
        $object = $this->owner;
        $class = get_class($object);
        $excludedTypes = self::config()->get('excluded_classes');
        if (count($excludedTypes) && in_array($class, $excludedTypes)) {
            $ret = false;
        }
        $schema = DataObject::getSchema();
        $fields = $schema->databaseFields($class);
        if (array_key_exists('ShowInSearch', $fields)) {
            $ret = $object->ShowInSearch;
        }

        $this->owner->invokeWithExtensions('updateCanCreateDocument', $ret);

        return $ret;
    }


    public function onAfterWrite()
    {
        if(!self::is_versioned($this->owner)) {
            $this->owner->createOrDeleteSearchDocument();
        }
    }

    public function onAfterDelete()
    {
        if(!self::is_versioned($this->owner)) {
            $this->owner->deleteSearchDocument();
        }
    }

    public function onAfterPublish()
    {
        $this->owner->createOrDeleteSearchDocument();
    }

    public function onAfterUnpublish()
    {
        $this->owner->deleteSearchDocument();
    }

    public function onAfterArchive()
    {
        $this->owner->deleteSearchDocument();
    }

    public function deleteSearchDocument()
    {
        if ($document = SearchDocument::find_doc($this->owner)) {
            $document->delete();
        }
    }

    public function createOrDeleteSearchDocument()
    {
        if ($this->owner->canCreateDocument()) {
            $this->owner->createSearchDocument();
        } else {
            $this->owner->deleteSearchDocument();
        }
    }

    public function createSearchDocument()
    {
        if ($this->owner->canCreateDocument()) {
            $document = SearchDocument::find_or_make_doc($this->owner);
            $document->makeSearchContent();
        }
    }

    public static function is_versioned(DataObject $object)
    {
        return $object->hasExtension(Versioned::class);
    }

    public static function is_search()
	{
		return isset($_REQUEST['SearchGen']) ? true : false;
	}

    public static function get_template_global_variables()
	{
		return [
			'IsSearch' => 'is_search'
		];
	}

}
