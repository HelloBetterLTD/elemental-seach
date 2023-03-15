<?php
/**
 * Created by Nivanka Fonseka (nivanka@silverstripers.com).
 * User: nivankafonseka
 * Date: 6/2/18
 * Time: 11:44 AM
 * To change this template use File | Settings | File Templates.
 */

namespace SilverStripers\ElementalSearch\ORM\Connect;

use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use Exception;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\ORM\Connect\MySQLDatabase as SS_MySQLDatabase;
use SilverStripe\Versioned\Versioned;
use SilverStripers\ElementalSearch\Model\SearchDocument;
use SilverStripers\ElementalSearch\ORM\Search\FulltextSearchable;



class MySQLDatabase extends SS_MySQLDatabase
{

    public function searchEngine(
        $classesToSearch,
        $keywords,
        $start,
        $pageLength,
        $sortBy = "Relevance DESC",
        $extraFilter = "",
        $booleanSearch = false,
        $alternativeFileFilter = "",
        $invertedMatch = false
    ) {

        $documentClass = SearchDocument::class;
        $fileClass = File::class;
        if (!class_exists($documentClass)) {
            throw new Exception('MySQLDatabase->searchEngine() requires "SearchDocument" class');
        }
        if (!class_exists($fileClass)) {
            throw new Exception('MySQLDatabase->searchEngine() requires "File" class');
        }

        $keywords = $this->escapeString($keywords);
        $htmlEntityKeywords = htmlentities($keywords, ENT_NOQUOTES, 'UTF-8');

        $extraFilters = array($documentClass => '', $fileClass => '');

        $boolean = '';
        if ($booleanSearch) {
            $boolean = "IN BOOLEAN MODE";
        }

        if ($extraFilter) {
            $extraFilters[$documentClass] = " AND $extraFilter";

            if ($alternativeFileFilter) {
                $extraFilters[$fileClass] = " AND $alternativeFileFilter";
            } else {
                $extraFilters[$fileClass] = $extraFilters[$documentClass];
            }
        }

        // File.ShowInSearch was added later, keep the database driver backwards compatible
        // by checking for its existence first
        $fileTable = DataObject::getSchema()->tableName($fileClass);
        $fields = $this->getSchemaManager()->fieldList($fileTable);
        if (array_key_exists('ShowInSearch', $fields)) {
            $extraFilters[$fileClass] .= " AND ShowInSearch <> 0";
        }

        $limit = (int)$start . ", " . (int)$pageLength;

        $notMatch = $invertedMatch
            ? "NOT "
            : "";
        if ($keywords) {
            $match[$documentClass] = "
				MATCH (Title, Content) AGAINST ('$keywords' $boolean)
				+ MATCH (Title, Content) AGAINST ('$htmlEntityKeywords' $boolean)
			";
            $fileClassSQL = Convert::raw2sql($fileClass);
            $match[$fileClass] = "MATCH (Name, Title) AGAINST ('$keywords' $boolean) AND ClassName = '$fileClassSQL'";

            // We make the relevance search by converting a boolean mode search into a normal one
            $relevanceKeywords = str_replace(array('*', '+', '-'), '', $keywords);
            $htmlEntityRelevanceKeywords = str_replace(array('*', '+', '-'), '', $htmlEntityKeywords);
            $relevance[$documentClass] = "MATCH (Title, Content) "
                . "AGAINST ('$relevanceKeywords') "
                . "+ MATCH (Title, Content) AGAINST ('$htmlEntityRelevanceKeywords')";
            $relevance[$fileClass] = "MATCH (Name, Title) AGAINST ('$relevanceKeywords')";
        } else {
            $relevance[$documentClass] = $relevance[$fileClass] = 1;
            $match[$documentClass] = $match[$fileClass] = "1 = 1";
        }

        // Generate initial DataLists and base table names
        $lists = array();
        $sqlTables = array($documentClass => '', $fileClass => '');
        foreach ($classesToSearch as $class) {
            $lists[$class] = DataList::create($class)->where($notMatch . $match[$class] . $extraFilters[$class]);
            $sqlTables[$class] = '"' . DataObject::getSchema()->tableName($class) . '"';
        }

        $charset = static::config()->get('charset');

        // Make column selection lists
        $select = array(
            $documentClass => array(
                "ClassName" => "Type",
                "ID" => "OriginID",
                "ParentID" => "_{$charset}''",
                "Title",
                "MenuTitle" => "_{$charset}''",
                "URLSegment" => "_{$charset}''",
                "Content",
                "LastEdited" => "_{$charset}''",
                "Created" => "_{$charset}''",
                "Name" => "_{$charset}''",
                "Relevance" => $relevance[$documentClass],
                "CanViewType" => "NULL"
            ),
            $fileClass => array(
                "ClassName",
                "{$sqlTables[$fileClass]}.\"ID\"",
                "ParentID",
                "Title",
                "MenuTitle" => "_{$charset}''",
                "URLSegment" => "_{$charset}''",
                "Content" => "_{$charset}''",
                "LastEdited",
                "Created",
                "Name",
                "Relevance" => $relevance[$fileClass],
                "CanViewType" => "NULL"
            ),
        );

        // Process and combine queries
        $querySQLs = array();
        $queryParameters = array();
        $totalCount = 0;
        foreach ($lists as $class => $list) {
            /** @var SQLSelect $query */
            $query = $list->dataQuery()->query();

            // There's no need to do all that joining
            $query->setFrom($sqlTables[$class]);
            $query->setSelect($select[$class]);
            $query->setOrderBy(array());

            $querySQLs[] = $query->sql($parameters);
            $queryParameters = array_merge($queryParameters, $parameters);

            $totalCount += $query->unlimitedRowCount();
        }
        $fullQuery = implode(" UNION ", $querySQLs) . " ORDER BY $sortBy LIMIT $limit";

        // Get records
        $records = $this->preparedQuery($fullQuery, $queryParameters);
        $objects = array();
        foreach ($records as $record) {
            $object = DataList::create($record['ClassName'])->byID($record['ID']);
            if ($object && $object->canView()) {
                $object->SearchSnippet =  $this->generateSearchSnippet($keywords, $record['Content']);
                $objects[] = $object;
            }
        }

        $list = new PaginatedList(new ArrayList($objects));
        $list->setPageStart($start);
        $list->setPageLength($pageLength);
        $list->setTotalItems($totalCount);

        // The list has already been limited by the query above
        $list->setLimitItems(false);

        return $list;
    }

    public function generateSearchSnippet($keywords, $content)
    {
        $snippetLength = 200;
        $content = preg_replace('/\s+/', ' ', $content);
        $length = mb_strlen($content);
        $words = preg_split(
            '/[^\p{L}\p{N}\p{Pc}\p{Pd}@]+/u',
            mb_strtolower($keywords),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        $occurrences = $this->findOccurrences($words, $content);
        $start = $this->findUsageBaseOnDensity($occurrences);
        if ($length - $start < $snippetLength) {
            $start = $start - ($length - $start) / 2;
        }
        if ($start < 0) {
            $start = 0;
        } else { // we need to get a start of a sentence
            $firstChar = substr($content, $start, 1);
            if ($firstChar === '.') {
                $start += 1; // exclude the dot
            } else {
                $offsetString = substr($content, 0, $start);
                $lastFullStop = strrpos($offsetString, '.');
                if ($lastFullStop !== false) {
                    $start = $lastFullStop + 1;
                } else { // this is the first sentence
                    $start = 0;
                }
            }
        }

        return trim(mb_substr($content, $start, $snippetLength));
    }

    protected function findUsageBaseOnDensity($occurences)
    {
        if (empty($occurences)) {
            return -1;
        }
        $preOffset = 10;
        $start = $occurences[0];
        $nofOccurrences = count($occurences);
        $closest = PHP_INT_MAX;

        if ($nofOccurrences > 2) {
            for ($i = 1; $i < $nofOccurrences; $i++) {
                if ($i + 1 === $nofOccurrences) { // last
                    $diff = $occurences[$i] - $occurences[$i - 1];
                } else {
                    $diff = $occurences[$i + 1] - $occurences[$i];
                }
                if ($diff < $closest) {
                    $closest = $diff;
                    $start = $occurences[$i];
                }
            }
        }
        return $start > $preOffset ? $start - $preOffset : 0;
    }

    protected function findOccurrences($words, $content)
    {
        $occurences = [];
        foreach ($words as $word) {
            $length = strlen($word);
            $occurence = mb_stripos($content, $word);
            while ($occurence !== false) {
                $occurences[] = $occurence;
                $occurence = mb_stripos($content, $word, $occurence + $length);
            }
        }
        $occurences = array_unique($occurences);
        sort($occurences);
        return $occurences;
    }



}
