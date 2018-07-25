<?php
/**
 * Created by PhpStorm.
 * User: Nivanka Fonseka
 * Date: 02/06/2018
 * Time: 07:23
 */

namespace SilverStripers\ElementalSearch\ORM\Search;

use DNADesign\Elemental\Models\ElementalArea;
use Exception;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripers\ElementalSearch\ORM\Connect\MySQLDatabase;

class FulltextSearchable extends \SilverStripe\ORM\Search\FulltextSearchable
{

    public static function enable($searchableClasses = [SiteTree::class, File::class, BaseElement::class])
    {
        $defaultColumns = array(
            SiteTree::class => ['Title','MenuTitle','Content','MetaDescription'],
            File::class => ['Name','Title'],
        );

        if(in_array(BaseElement::class, $searchableClasses)) {
            self::add_elemental_classes($searchableClasses);
            $defaultColumns = array_merge($defaultColumns, self::get_elemental_columns());
        }
        $dbConn = DB::get_conn();
        foreach(self::get_objects_with_elemental() as $class => $areas) {
            if(!in_array($class, ClassInfo::subclassesFor(\Page::class))) {
                $baseTable = MySQLDatabase::versioned_tables($class, DataObject::getSchema()->baseDataTable($class));
                $baseFields = $dbConn->getSchemaManager()->fieldList($baseTable);
                if (array_key_exists('Title', $baseFields)) {
                    $class::add_extension(sprintf('%s(%s)', static::class, "'" . implode("','", ['Title']) . "''"));
                }
            }
        }

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

    public static function get_elemental_classes()
    {
        $classes = [];
        $elements = ClassInfo::subclassesFor(BaseElement::class);
        foreach ($elements as $element) {
            $singleton = singleton($element);
            if($configs = $singleton->config()->uninherited('fulltext_fields')){
                $classes[] = $element;
            }
        }
        return $classes;
    }

    public static function get_elemental_columns($tablePrefix = false)
    {
        $columns = [];
        $elements = ClassInfo::subclassesFor(BaseElement::class);
        foreach ($elements as $element) {
            $singleton = singleton($element);
            if($configs = $singleton->config()->uninherited('fulltext_fields')){
                if($tablePrefix) {
                    $cols = [];
                    foreach ($configs as $field) {
                        $elementTable = MySQLDatabase::versioned_tables($element,
                            DataObject::getSchema()->tableName($element));
                        $cols[] = '"' . $elementTable . '"."' . $field . '"';
                    }
                    $columns[$element] = $cols;
                }
                else {
                    $columns[$element] = $configs;
                }
            }
        }
        return $columns;
    }

    public static function add_elemental_classes(&$searchableClasses)
    {
        if(in_array(BaseElement::class, $searchableClasses)) {
            $searchableClasses = array_unique(array_merge($searchableClasses, self::get_elemental_classes()));
        }
    }

    public static function is_elemental_search($searchableClasses)
    {
        return in_array(BaseElement::class, $searchableClasses);
    }

    public static function get_objects_with_elemental()
    {
        $classesAndRelations = [];
        $dataClasses = ClassInfo::subclassesFor(DataObject::class);
        $elementalClasses = ClassInfo::subclassesFor(BaseElement::class);
        foreach ($dataClasses as $class) {
            if(!in_array($class, $elementalClasses) && ($hasOne = singleton($class)->uninherited('has_one')) && in_array(ElementalArea::class, $hasOne)) {
                $relations = [];
                foreach ($hasOne as $relation => $type) {
                    if($type == ElementalArea::class) {
                        $relations[] = $relation;
                    }
                }
                $classesAndRelations[$class] = $relations;
            }
        }
        return $classesAndRelations;
    }

}
