<?php
/**
 * Created by Nivanka Fonseka (nivanka@silverstripers.com).
 * User: nivankafonseka
 * Date: 9/7/18
 * Time: 12:32 PM
 * To change this template use File | Settings | File Templates.
 */

namespace SilverStripers\ElementalSearch\Tasks;


use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripers\ElementalSearch\Extensions\ElementDocumentGeneratorExtension;
use SilverStripers\ElementalSearch\Extensions\SearchDocumentGenerator;
use SilverStripers\ElementalSearch\Extensions\SiteTreeDocumentGenerator;
use SilverStripers\ElementalSearch\Extensions\VersionedDocumentGenerator;

class GenerateSearchDocument extends BuildTask
{

    protected $title = 'Re-generate all search documents';

    protected $description = 'Generate search documents for items.';

    private static $segment = 'make-search-docs';

    /**
     * Implement this method in the task subclass to
     * execute via the TaskRunner
     *
     * @param HTTPRequest $request
     * @return
     */
    public function run($request)
    {
        set_time_limit(50000);
        $classes = $this->getAllSearchDocClasses();
        foreach ($classes as $class) {
            foreach ($list = DataList::create($class) as $record) {
                echo sprintf(
                        'Updating record for %s type %s, link %s',
                        $record->getTitle(),
                        $record->ClassName,
                        $record->getGenerateSearchLink()) . '<br>';
                try {
                    $record->createOrDeleteSearchDocument();
                } catch (Exception $e) {
                }
            }
        }
        echo 'Completed';
    }

    public function getAllSearchDocClasses()
    {
        $list = [];
        foreach (ClassInfo::subclassesFor(DataObject::class) as $class) {
            $configs = Config::inst()->get($class, 'extensions', Config::UNINHERITED);
            if($configs) {
                $valid = in_array(SearchDocumentGenerator::class, $configs)
                    || in_array(VersionedDocumentGenerator::class, $configs);

                if ($valid) {
                    $list[] = $class;
                }
            }
        }
        return $list;
    }

}
