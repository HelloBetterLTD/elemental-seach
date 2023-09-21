<?php
/**
 * Created by Nivanka Fonseka (nivanka@silverstripers.com).
 * User: nivankafonseka
 * Date: 9/7/18
 * Time: 2:24 PM
 * To change this template use File | Settings | File Templates.
 */

namespace SilverStripers\ElementalSearch\Extensions;


use SilverStripe\CMS\Controllers\CMSMain;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\ORM\DataList;

class CMSMainExtension extends Extension
{

    use Configurable;

    private static $display_create_button = true;
    
    public function updateEditForm(Form $form)
    {
        $record = $this->owner->getRecord($this->owner->currentPageID());

        if(!$record->isOnDraftOnly() && self::config()->get('display_create_button')){
            $form->Actions()->insertAfter('action_publish',
                FormAction::create('makeSearch', 'Create Search Doc')
                    ->setUseButtonTag(true)
                    ->addExtraClass('btn btn-outline-primary')
            );
        }
    }

    public function makeSearch($data, Form $form)
    {
        /* @var $owner CMSMain */
        $owner = $this->owner;
        $id = $owner->currentPageID();
        $record = $owner->getRecord($id);
        if($record) {
            if (SearchDocumentGenerator::is_transalated()) {
                $list = DataList::create('TractorCow\\Fluent\\Model\\Locale');
                foreach ($list as $locale) {
                    SearchDocumentGenerator::make_document_for($record, $locale->Locale);
                }
            } else {
                SearchDocumentGenerator::make_document_for($record);
            }
            $message = 'Search document generator';
        }
        else {
            $message = 'There was a problem';
        }

        $owner->getResponse()->addHeader('X-Status', rawurlencode($message));
        return $owner->getResponseNegotiator()->respond($owner->getRequest());

    }

}
