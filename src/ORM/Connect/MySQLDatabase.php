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
            if ($keywords == $htmlEntityKeywords) {
                $match[$documentClass] = "MATCH (Title, Content) AGAINST ('$keywords' $boolean)";
            } else {
                $match[$documentClass] = "
                    MATCH (Title, Content) AGAINST ('$keywords' $boolean)
                    + MATCH (Title, Content) AGAINST ('$htmlEntityKeywords' $boolean)
                ";
            }
            $fileClassSQL = Convert::raw2sql($fileClass);
            $match[$fileClass] = "MATCH (Name, Title) AGAINST ('$keywords' $boolean) AND ClassName = '$fileClassSQL'";

            // We make the relevance search by converting a boolean mode search into a normal one
            $relevanceKeywords = str_replace(array('*', '+', '-'), '', $keywords);
            $htmlEntityRelevanceKeywords = str_replace(array('*', '+', '-'), '', $htmlEntityKeywords);
            if ($relevanceKeywords == $htmlEntityRelevanceKeywords) {
                $relevance[$documentClass] = "MATCH (Title, Content) AGAINST ('$relevanceKeywords')";
            } else {
                $relevance[$documentClass] = "MATCH (Title, Content) AGAINST ('$relevanceKeywords') "
                    . "+ MATCH (Title, Content) AGAINST ('$htmlEntityRelevanceKeywords')";
            }
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
                "ClassName" => "OwnerClass",
                "ID" => "OwnerID",
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
            $object = DataObject::get_by_id($record['ClassName'], $record['ID']);
            if ($object && $object->exists() && $object->canView()) {
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

}
