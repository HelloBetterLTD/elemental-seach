<?php
/**
 * Created by PhpStorm.
 * User: Nivanka Fonseka
 * Date: 02/06/2018
 * Time: 07:23
 */

namespace SilverStripers\ElementalSearch\ORM\Search;

use Exception;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\ORM\DataExtension;
use SilverStripers\ElementalSearch\Model\SearchDocument;
use SilverStripe\ORM\Search\FulltextSearchable as SS_FulltextSearchable;

class FulltextSearchable extends DataExtension
{

    protected $searchFields;

    protected static $searchable_classes;

    public static function enable($searchableClasses = [SearchDocument::class, File::class])
    {
        $defaultColumns = array(
            SearchDocument::class => ['Title', 'Content'],
            File::class => ['Name','Title'],
        );

        if (!is_array($searchableClasses)) {
            $searchableClasses = array($searchableClasses);
        }
        foreach ($searchableClasses as $class) {
            if (!class_exists($class)) {
                continue;
            }

            if (isset($defaultColumns[$class])) {
                $class::add_extension(sprintf('%s(%s)', static::class, "'" . implode("','", $defaultColumns[$class]) . "''"));
            } else {
                throw new Exception(
                    "FulltextSearchable::enable() I don't know the default search columns for class '$class'"
                );
            }
        }
        self::$searchable_classes = $searchableClasses;
        if (class_exists("SilverStripe\\CMS\\Controllers\\ContentController")) {
            ContentController::add_extension("SilverStripe\\CMS\\Search\\ContentControllerSearchExtension");
        }
    }

    public function __construct($searchFields = array())
    {
        parent::__construct();
        if (is_array($searchFields)) {
            $this->searchFields = $searchFields;
        } else {
            $this->searchFields = explode(',', $searchFields);
            foreach ($this->searchFields as &$field) {
                $field = trim($field);
            }
        }
    }

    public static function get_extra_config($class, $extensionClass, $args)
    {
        return array(
            'indexes' => array(
                'SearchFields' => array(
                    'type' => 'fulltext',
                    'name' => 'SearchFields',
                    'columns' => $args,
                )
            )
        );
    }

    public static function get_searchable_classes()
    {
        return self::$searchable_classes;
    }
}
