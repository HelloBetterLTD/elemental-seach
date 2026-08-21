<?php
/**
 * Created by Nivanka Fonseka (nivanka@silverstripers.com).
 * User: nivankafonseka
 * Date: 9/7/18
 * Time: 12:32 PM
 * To change this template use File | Settings | File Templates.
 */

namespace SilverStripers\ElementalSearch\Tasks;


use Symfony\Component\Console\Input\InputInterface;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripers\ElementalSearch\Extensions\SearchDocumentGenerator;
use SilverStripers\ElementalSearch\Extensions\SiteTreeDocumentGenerator;

class GenerateSearchDocument extends BuildTask
{

    protected string $title = 'Re-generate all search documents';

    protected static string $description = 'Generate search documents for items.';

    protected static string $commandName = 'make-search-docs';

    /**
     * Implement this method in the task subclass to
     * execute via the TaskRunner
     *
     * @param HTTPRequest $request
     * @return
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $eol = Director::is_cli() ? PHP_EOL . PHP_EOL : '<br>';
        set_time_limit(50000);
        $classes = $this->getAllSearchDocClasses();
        foreach ($classes as $class) {
            foreach ($list = DataList::create($class) as $record) {
				$output = sprintf(
						'Making record for %s type %s, link %s',
						$record->getTitle(),
						$record->ClassName,
						ClassInfo::hasMethod($record, 'getGenerateSearchLink') ? $record->getGenerateSearchLink() : $record->Title);

                $output .= $eol;

                echo $output;
				try {
					SearchDocumentGenerator::make_document_for($record);
				} catch (Exception $e) {
				}
            }
        }
        $output->writeln('Completed');
        return Command::SUCCESS;
    }

    public function getAllSearchDocClasses()
    {
        $list = [];
        foreach (ClassInfo::subclassesFor(DataObject::class) as $class) {
            $configs = Config::inst()->get($class, 'extensions', Config::UNINHERITED);
            if($configs) {
                $valid = in_array(SearchDocumentGenerator::class, $configs)
                    || in_array(SiteTreeDocumentGenerator::class, $configs);

                if ($valid) {
                    $list[] = $class;
                }
            }
        }
        return $list;
    }

}
