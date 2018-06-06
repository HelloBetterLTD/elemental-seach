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
use SilverStripe\Versioned\Versioned;
use SilverStripers\ElementalSearch\ORM\Search\FulltextSearchable;


class MySQLDatabase extends \SilverStripe\ORM\Connect\MySQLDatabase
{

	/**
	 * The core search engine, used by this class and its subclasses to do fun stuff.
	 * Searches both SiteTree and File.
	 *
	 * @param array $classesToSearch
	 * @param string $keywords Keywords as a string.
	 * @param int $start
	 * @param int $pageLength
	 * @param string $sortBy
	 * @param string $extraFilter
	 * @param bool $booleanSearch
	 * @param string $alternativeFileFilter
	 * @param bool $invertedMatch
	 * @return PaginatedList
	 * @throws Exception
	 */
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

		$searchElemental = FulltextSearchable::is_elemental_search($classesToSearch);
		$elementalRelatedClasses = FulltextSearchable::get_objects_with_elemental();

		$pageClass = SiteTree::class;
		$fileClass = File::class;
		if (!class_exists($pageClass)) {
			throw new Exception('MySQLDatabase->searchEngine() requires "SiteTree" class');
		}
		if (!class_exists($fileClass)) {
			throw new Exception('MySQLDatabase->searchEngine() requires "File" class');
		}

		$keywords = $this->escapeString($keywords);
		$htmlEntityKeywords = htmlentities($keywords, ENT_NOQUOTES, 'UTF-8');

		$extraFilters = array($pageClass => '', $fileClass => '');
		if($searchElemental) foreach ($elementalRelatedClasses as $elementalClass => $relation) {
			$baseTable = MySQLDatabase::versioned_tables($elementalClass, DataObject::getSchema()->baseDataTable($elementalClass));
			$fields = $this->getSchemaManager()->fieldList($baseTable);
			if (array_key_exists('ShowInSearch', $fields)) {
				$extraFilters[$elementalClass] = " AND {$baseTable}.ShowInSearch <> 0";
			}
			else {
				$extraFilters[$elementalClass] = '';
			}


		}

		$boolean = '';
		if ($booleanSearch) {
			$boolean = "IN BOOLEAN MODE";
		}

		if ($extraFilter) {
			$extraFilters[$pageClass] = " AND $extraFilter";
			if ($alternativeFileFilter) {
				$extraFilters[$fileClass] = " AND $alternativeFileFilter";
			} else {
				$extraFilters[$fileClass] = $extraFilters[$pageClass];
			}
		}

		// Always ensure that only pages with ShowInSearch = 1 can be searched
		$extraFilters[$pageClass] .= " AND ShowInSearch <> 0";

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
			$match[$pageClass] = "
				MATCH (Title, MenuTitle, Content, MetaDescription) AGAINST ('$keywords' $boolean)
				+ MATCH (Title, MenuTitle, Content, MetaDescription) AGAINST ('$htmlEntityKeywords' $boolean)
			";
			$fileClassSQL = Convert::raw2sql($fileClass);
			$match[$fileClass] = "MATCH (Name, Title) AGAINST ('$keywords' $boolean) AND ClassName = '$fileClassSQL'";

			// We make the relevance search by converting a boolean mode search into a normal one
			$relevanceKeywords = str_replace(array('*', '+', '-'), '', $keywords);
			$htmlEntityRelevanceKeywords = str_replace(array('*', '+', '-'), '', $htmlEntityKeywords);
			$relevance[$pageClass] = "MATCH (Title, MenuTitle, Content, MetaDescription) "
				. "AGAINST ('$relevanceKeywords') "
				. "+ MATCH (Title, MenuTitle, Content, MetaDescription) AGAINST ('$htmlEntityRelevanceKeywords')";
			$relevance[$fileClass] = "MATCH (Name, Title) AGAINST ('$relevanceKeywords')";


			if($searchElemental) foreach ($elementalRelatedClasses as $relatedFromClass => $relation) {
				$matches = [];
				$relevances = [];

				$relatedFromBaseTable = self::versioned_tables($relatedFromClass, DataObject::getSchema()->baseDataTable($relatedFromClass));
				$relatedTableFields = $this->getSchemaManager()->fieldList($relatedFromBaseTable);
				if(array_key_exists('Title', $relatedTableFields)) {
					$matches[] = "MATCH (\"$relatedFromBaseTable\".\"Title\") AGAINST ('$keywords' $boolean)"
						. " + MATCH (\"$relatedFromBaseTable\".\"Title\") AGAINST ('$htmlEntityKeywords' $boolean)";

					$relevances[] = "MATCH (\"$relatedFromBaseTable\".\"Title\") AGAINST ('$relevanceKeywords' $boolean)"
						. " + MATCH (\"$relatedFromBaseTable\".\"Title\") AGAINST ('$htmlEntityRelevanceKeywords' $boolean)";
				}



				foreach (FulltextSearchable::get_elemental_columns(true) as $elementalColumns){
					$matches[] = "MATCH ("
						. implode(', ', $elementalColumns)
						. ") AGAINST ('$keywords' $boolean)"
						. " + MATCH ("
						. implode(', ', $elementalColumns)
						. ") AGAINST ('$htmlEntityKeywords' $boolean)";


					$relevances[] = "(MATCH ("
						. implode(', ', $elementalColumns)
						. ") AGAINST ('$relevanceKeywords')"
						. " + MATCH ("
						. implode(', ', $elementalColumns)
						. ") AGAINST ('$htmlEntityRelevanceKeywords'))";
				}

				$relevance[$relatedFromClass] = implode(' + ', $relevances);
				$match[$relatedFromClass] = implode(' + ', $matches);

			}

		} else {
			$relevance[$pageClass] = $relevance[$fileClass] = 1;
			$match[$pageClass] = $match[$fileClass] = "1 = 1";

			if($searchElemental) foreach ($elementalRelatedClasses as $elementalClass => $relation) {
				$relevance[$elementalClass] = 1;
				$match[$elementalClass] = "1 = 1";
			}

		}

		// Generate initial DataLists and base table names
		$lists = array();
		$sqlTables = array($pageClass => '', $fileClass => '');
		foreach ($classesToSearch as $class) {
			if($class != BaseElement::class) {
				$lists[$class] = DataList::create($class)->where($notMatch . $match[$class] . $extraFilters[$class]);
				$sqlTables[$class] = '"' . DataObject::getSchema()->tableName($class) . '"';
			}
		}

		$elementalAreaTable = MySQLDatabase::versioned_tables(ElementalArea::class,
			DataObject::getSchema()->baseDataTable(ElementalArea::class));
		$baseElementTable = MySQLDatabase::versioned_tables(BaseElement::class,
			DataObject::getSchema()->baseDataTable(BaseElement::class));

		foreach ($elementalRelatedClasses as $elementalClass => $relations) {
			$joinOn = [];
			foreach ($relations as $relation) {
				$joinOn[] = "{$elementalAreaTable}.ID = {$relation}ID";
			}
			$list = DataList::create($elementalClass)
				->leftJoin("{$elementalAreaTable}", implode(' OR ', $joinOn))
				->leftJoin("{$baseElementTable}", "{$baseElementTable}.ParentID = {$elementalAreaTable}.ID");


			foreach (FulltextSearchable::get_elemental_classes() as $currentClass) {
				$elementTable = MySQLDatabase::versioned_tables($currentClass,
					DataObject::getSchema()->tableName($currentClass));
				if($elementTable != $baseElementTable) {
					$list = $list->leftJoin("$elementTable", '"' . $elementTable . '"."ID" = "' . $baseElementTable . '"."ID"');
				}
			}

			$list = $list->where($notMatch . $match[$elementalClass] . $extraFilters[$elementalClass]);

			$lists[$elementalClass] = $list;
			$sqlTables[$elementalClass] = '"' . DataObject::getSchema()->tableName($elementalClass) . '"';
		}


		$charset = static::config()->get('charset');

		// Make column selection lists
		$select = array(
			$pageClass => array(
				"ClassName", "{$sqlTables[$pageClass]}.\"ID\"", "ParentID",
				"Title", "MenuTitle", "URLSegment", "Content",
				"LastEdited", "Created",
				"Name" => "_{$charset}''",
				"Relevance" => $relevance[$pageClass], "CanViewType"
			),
			$fileClass => array(
				"ClassName", "{$sqlTables[$fileClass]}.\"ID\"", "ParentID",
				"Title", "MenuTitle" => "_{$charset}''", "URLSegment" => "_{$charset}''", "Content" => "_{$charset}''",
				"LastEdited", "Created",
				"Name",
				"Relevance" => $relevance[$fileClass], "CanViewType" => "NULL"
			),
		);


		foreach ($elementalRelatedClasses as $elementalClass => $relations) {

			$baseTable = DataObject::getSchema()->baseDataTable($elementalClass);
			$fields = $this->getSchemaManager()->fieldList($baseTable);

			$select[$elementalClass] = [
				"\"$baseTable\".\"ClassName\" AS \"ClassName\"",
				"\"$baseTable\".\"ID\" AS \"ID\"",
			];

			$selectableFields = [
				'ParentID',
				'Title',
				'MenuTitle',
				'URLSegment',
				'Content',
				'LastEdited',
				'Created',
				'Name'
			];

			foreach ($selectableFields as $selectableField) {
				if(array_key_exists($selectableField, $fields)) {
					$select[$elementalClass][] = "\"$baseTable\".\"{$selectableField}\" AS \"{$selectableField}\"";
				}
				else {
					$select[$elementalClass][$selectableField] = "_{$charset}''";
				}
			}
			$select[$elementalClass]['Relevance'] = $relevance[$elementalClass];
			$select[$elementalClass]['CanViewType'] = "_{$charset}''";
		}


		// Process and combine queries
		$querySQLs = array();
		$queryParameters = array();
		$totalCount = 0;
		foreach ($lists as $class => $list) {
			/** @var SQLSelect $query */
			$query = $list->dataQuery()->query();


			// There's no need to do all that joining
			// $query->setFrom($sqlTables[$class]);
			$query->setSelect($select[$class]);
			$query->setOrderBy(array());

			$querySQLs[] = $query->sql($parameters);
			$queryParameters = array_merge($queryParameters, $parameters);

		}

		$countQuery = "SELECT COUNT(ID) AS Rows FROM ( " . implode(" UNION ", $querySQLs) . ") SEARCH_TABLES";
		$fullQuery = implode(" UNION ", $querySQLs) . " ORDER BY $sortBy LIMIT $limit";


		$totalCount = $this->preparedQuery($countQuery, $queryParameters)->value();
		$records = $this->preparedQuery($fullQuery, $queryParameters);

		$objects = array();
		foreach ($records as $record) {
			$objects[] = new $record['ClassName']($record);
		}
		$list = new PaginatedList(new ArrayList($objects));
		$list->setPageStart($start);
		$list->setPageLength($pageLength);
		$list->setTotalItems($totalCount);

		// The list has already been limited by the query above
		$list->setLimitItems(false);

		return $list;

	}

	public static function versioned_tables($class, $table)
	{
		if(ClassInfo::exists('SilverStripe\\Versioned\\Versioned') && $class::has_extension('SilverStripe\\Versioned\\Versioned')) {
			if(Versioned::get_stage() == 'Live') {
				return $table . '_Live';
			}
		}
		return $table;
	}

}