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
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\TemplateGlobalProvider;
use SilverStripers\ElementalSearch\Model\SearchDocument;

class SearchDocumentGenerator extends DataExtension implements TemplateGlobalProvider
{

    public function getGenerateSearchLink()
    {
        $owner = $this->owner;
        if(method_exists($owner, 'Link')) {
            $link = $owner->Link();
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


    public function onAfterWrite()
    {
        if(!self::is_versioned($this->owner)) {
            self::make_document_for($this->owner);
        }
    }

    public function onAfterDelete()
    {
        if(!self::is_versioned($this->owner)) {
            self::delete_doc($this->owner);
        }
    }

    public function onAfterPublish()
    {
        self::make_document_for($this->owner);
    }

    public function onAfterArchive()
    {
        self::delete_doc($this->owner);
    }

    public static function make_document_for(DataObject $object)
    {
        if(self::case_create_document($object)) { 
            $doc = self::find_or_make_document($object);
            $doc->makeSearchContent();
        }
        else {
            self::delete_doc($object);
        }
    }

    public static function case_create_document(DataObject $object)
    {
        $schema = DataObject::getSchema();
        $fields = $schema->databaseFields($object->ClassName);
        if(array_key_exists('ShowInSearch', $fields)) {
            return $object->getField('ShowInSearch');
        }
        return true;
    }

    public static function is_versioned(DataObject $object)
    {
        return $object->hasExtension(Versioned::class);
    }


    public static function delete_doc(DataObject $object)
    {
        $doc = self::find_document($object);
        if($doc) {
            $doc->delete();
        }
    }

    public static function find_or_make_document(DataObject $object)
    {
        $doc = self::find_document($object);
        if(!$doc) {
            $doc = new SearchDocument([
                'Type' => get_class($object),
                'OriginID' => $object->ID
            ]);
            $doc->write();
        }
        return $doc;
    }

    public static function find_document(DataObject $object)
    {
        $doc = SearchDocument::get()->filter([
            'Type' => get_class($object),
            'OriginID' => $object->ID
        ])->first();
        return $doc;
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
