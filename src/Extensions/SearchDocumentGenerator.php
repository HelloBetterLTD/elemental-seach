<?php
/**
 * Created by Nivanka Fonseka (nivanka@silverstripers.com).
 * User: nivankafonseka
 * Date: 9/7/18
 * Time: 11:48 AM
 * To change this template use File | Settings | File Templates.
 */

namespace SilverStripers\ElementalSearch\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\TemplateGlobalProvider;
use SilverStripers\ElementalSearch\Model\SearchDocument;

class SearchDocumentGenerator extends Extension implements TemplateGlobalProvider
{

    private static $prevent_search_documents = false;

    public static function search_documents_prevented()
    {
        return self::$prevent_search_documents;
    }

    public static function prevent_search_documents($prevent = true)
    {
        self::$prevent_search_documents = $prevent;
    }

    public function onAfterWrite()
    {
        if(!self::is_versioned($this->getOwner()) && !self::$prevent_search_documents) {
            self::make_document_for($this->getOwner());
        }
    }

    public function onAfterDelete()
    {
        if(!self::is_versioned($this->getOwner())) {
            self::delete_doc($this->getOwner());
        }
    }

    public function onAfterPublish()
    {
        if (!self::$prevent_search_documents) {
            self::make_document_for($this->getOwner());
        }
    }

    public function onAfterUnpublish()
    {
        if ($this->getOwner()->isOnDraftOnly() && self::find_document($this->getOwner())) {
            self::delete_doc($this->getOwner());
        }
    }

    public function onAfterArchive()
    {
        self::delete_doc($this->getOwner());
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
        $ret = true;
        if (self::is_versioned($object)) {
            if (!$object->isPublished()) {
                $ret = false;
            }
        }
        if ($ret) {
            if (array_key_exists('ShowInSearch', $fields)) {
                $ret = $object->getField('ShowInSearch');
            }
        }
        return $ret;
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
            $doc = SearchDocument::create([
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
