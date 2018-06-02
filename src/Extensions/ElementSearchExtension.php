<?php
/**
 * Created by PhpStorm.
 * User: Nivanka Fonseka
 * Date: 02/06/2018
 * Time: 07:29
 */

namespace SilverStripers\ElementalSearch\Extensions;


use SilverStripe\ORM\DataExtension;

class ElementSearchExtension extends DataExtension
{

    private static $fulltext_fields = [
        'Title'
    ];

}