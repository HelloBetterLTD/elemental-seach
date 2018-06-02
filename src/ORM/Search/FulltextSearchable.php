<?php
/**
 * Created by PhpStorm.
 * User: Nivanka Fonseka
 * Date: 02/06/2018
 * Time: 07:23
 */

namespace SilverStripers\ElementalSearch\ORM\Search;

use Exception;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;

class FulltextSearchable extends \SilverStripe\ORM\Search\FulltextSearchable
{

    public static function enable($searchableClasses = [SiteTree::class, File::class, BaseElement::class])
    {
        $defaultColumns = array(
            SiteTree::class => ['Title','MenuTitle','Content','MetaDescription'],
            File::class => ['Name','Title'],
        );

        if(in_array(BaseElement::class, $searchableClasses)) {
            $elements = ClassInfo::subclassesFor(BaseElement::class);
            foreach ($elements as $element) {
                $singleton = singleton($element);
                $configs = $singleton->config()->uninherited('fulltext_fields');
                if($configs){
                    if (!in_array($element, $searchableClasses)) {
                        $searchableClasses[] = $element;
                    }
                    $defaultColumns[$element] = $configs;
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


}