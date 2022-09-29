<?php

/**
 * Created by Nivanka Fonseka (nivanka@silverstripers.com).
 * User: nivankafonseka
 * Date: 9/7/18
 * Time: 11:36 AM
 * To change this template use File | Settings | File Templates.
 */

namespace SilverStripers\ElementalSearch\Model;

use DNADesign\Elemental\Models\ElementalArea;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\SSViewer;

class SearchDocument extends DataObject
{

    private static $db = [
        'Title' => 'Text',
        'Content' => 'Text',
    ];

    private static $has_one = [
        'Owner' => DataObject::class
    ];

    private static $searchable_fields = [
        'Title',
        'Content',
    ];

    private static $table_name = 'SearchDocument';

    private static $search_x_path;

    public function makeSearchContent()
    {
        /* @var $origin DataObject */
        $origin = $this->Owner();
        if (!$origin->exists()) {
            return;
        }

        $output = [];
        $searchLink = $origin->getGenerateSearchLink();

        try {
            $isSiteTree = is_a($origin, SiteTree::class);
            $hasSearchableLink = method_exists($origin, 'getGenerateSearchLink');
            $contents = '';


            if ($isSiteTree || $hasSearchableLink) {
                $url = $origin->getGenerateSearchLink();
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_TIMEOUT, 20);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_USERAGENT, str_replace(',', '/', 'SilverStripe'));
                $html = curl_exec($ch);

                $xpath = $origin->config()->get('search_x_path');
                if (!$xpath) {
                    $xpath = self::config()->get('search_x_path');
                }
                if ($xpath) {
                    if (is_array($xpath)) {
                        foreach ($xpath as $xpathElment) {
                            $contents .= $this->searchXPath($xpathElment, $html);
                        }
                    } else {
                        $contents .= $this->searchXPath($xpath, $html);
                    }
                } else {
                    $contents = $html;
                }
                $contents = strip_tags($contents);
            } else {
                $contents = strip_tags($origin->forTemplate());
            }

            $title = $origin->getTitle();
            $origin->invokeWithExtensions('updateSearchContents', $contents);
            if ($contents) {
                $contents = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $contents);
            }
            $this->update([
                'Title' => $title,
                'Content' => $contents
            ]);
            $this->write();
        } catch (\Exception $e) {
            $this->delete();
        } finally {
        }
        return implode($output);
    }

    /**
     * @param $xPath
     * @param $html
     * @return string
     */
    protected function searchXPath($xPath, $html)
    {
        $contents = strip_tags($html);
        if ($html) {
            try {
                $domDoc = new \DOMDocument();
                @$domDoc->loadHTML($html);
                $finder = new \DOMXPath($domDoc);
                $nodes = $finder->query("//*[contains(@class, '$xPath')]");
                $nodeValues = [];
                if ($nodes->length) {
                    foreach ($nodes as $node) {
                        $nodeValues[] = $node->nodeValue;
                    }
                }
                if ($nodeValues) {
                    $contents = implode("\n\n", $nodeValues);
                }
            } catch (\Exception $e) {
            }
        }

        return $contents;
    }

    function removeEmptyLines($string)
    {
        return preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $string);
    }

    /**
     * @param $object
     * @return SearchDocument|null
     */
    public static function find_doc($object)
    {

        $classes = ClassInfo::ancestry($object, true);
        $baseClass = reset($classes);
        return SearchDocument::get()->filter([
            'OwnerID' => $object->ID,
            'OwnerClass' => $baseClass
        ])->first();
    }

    /**
     * @param $object
     * @return DataObject|SearchDocument|null
     * @throws \SilverStripe\ORM\ValidationException
     */
    public static function find_or_make_doc($object)
    {
        $classes = ClassInfo::ancestry($object, true);
        $baseClass = reset($classes);
        $doc = self::find_doc($object);
        if (!$doc) {
            $doc = SearchDocument::create([
                'OwnerID' => $object->ID,
                'OwnerClass' => $baseClass
            ]);
            $doc->write();
        }
        return $doc;
    }

}
