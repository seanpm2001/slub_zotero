<?php
namespace Slub\SlubZotero\Controller;

/***
 *
 * This file is part of the "SLUB Zotero Bibliografie" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2019
 *
 ***/

/**
 * BibliografieController
 */
class BibliografieController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{
    /**
     * action list
     *
     * @return void
     */
    public function listAction()
    {
        $headline = $this->settings['zotero']['headline'];
        $urlToCall = $this->buildUrl();
        $collection = $this->callParentCollection($urlToCall);
        $secondRun = $this->getSubCollection($collection);
        $sortedList = $this->sortCollection($secondRun);



        $this->view->assign('presentation', $sortedList);
        $this->view->assign('headline', $headline);

    }

    /**
     * action show
     *
     *
     * @return void
     */
    public function showAction()
    {
        $headline = $this->settings['zotero']['headline'];
        $yearKey = $this->request->getArgument('collection');
        $arrayForPresentation = $this->callSubCollections($this->request->getArgument('collection'));


        $this->view->assign('headline', $headline);
        $this->view->assign('yearKey', $yearKey);
        $this->view->assign('subCollection', $arrayForPresentation);
    }

    //url is build with user selected/provided informations
    function buildUrl()
    {
        $subCollections = '';

        if($this->settings['zotero']['selection'] == 'users')
        {
            $url = 'https://api.zotero.org/users/';
        }else
        {
            $url = 'https://api.zotero.org/groups/';
        }

        if ($this->settings['zotero']['id']) $url .= $this->settings['zotero']['id'].'/';

        $url .= ($this->settings['zotero']['subCollectionID']) ? 'collections/'.$this->settings['zotero']['subCollectionID'].'/collections?' : '/collections?';
        $url .= 'format='.'json';
        $url .= '&limit='.'5';
        $url .= '&key='.$this->settings['zotero']['key'];

        return $url;
    }

    //api call of a collection in a group
    function callParentCollection($url)
    {

        $apiAnswer = file_get_contents($url);


        $apiAnswerDecode = json_decode($apiAnswer, true);
        return $apiAnswerDecode;
    }

    //callParentCollection provides infos about its subcollections like name, key if its empty or not, here a helper array is created with the needed keys and the year
    function getSubCollection($apiAnswerDecode)
    {
        $i = 0;
        //~ var_dump($apiAnswerDecode);
        foreach($apiAnswerDecode as $apiAnswerDecodeKey => $apiAnswerDecodeValue)
        {
            if($apiAnswerDecode[$i]['meta']['numItems'] != 0 || $apiAnswerDecode[$i]['meta']['numCollections'] != 0)
            {
                $wanted[$i]['key'] = $apiAnswerDecode[$i]['key'];
                $wanted[$i]['year'] = substr($apiAnswerDecode[$i]['data']['name'], -4, 4);
            }
            $i++;
        }
        return $wanted;
    }

    //api needs to be called again to get the actual items of the subcollections, needs to be called a third time because parameters don't work as suggested
    function callSubCollections($subCollection)
    {
        $i = 0;
        $final = array();
        $subCollections = '';

        if($this->settings['zotero']['selection'] == 'users')
        {
            $begin = 'https://api.zotero.org/users/';
        }else
        {
            $begin = 'https://api.zotero.org/groups/';
        }

        $userOrGroupID = $this->settings['zotero']['id'].'/collections/';

            $apiAnswer = file_get_contents($begin.$userOrGroupID.$subCollection['key'].'/items?format=json&key='.$this->settings['zotero']['key']);

            //"&include=citation" doesn't work as wanted, so api needs to be called again with diff parameters
            $apiAnswerCitationWanted = file_get_contents($begin.$userOrGroupID.$subCollection['key'].'/items?format=json&include=citation&style='.$this->settings['zotero']['style'].'&key='.$this->settings['zotero']['key']);

            $subCollectionItems = json_decode ($apiAnswer, true);
            $subCollectionItemsCitationWanted = json_decode ($apiAnswerCitationWanted, true);


            foreach($subCollectionItems as $subCollectionItemsKey => $subCollectionItemsValue)
            {
                $final[$i][$subCollectionItemsKey] = $subCollectionItemsValue;
            }
            //second call is handled and citation is added to a combined array
            foreach($subCollectionItemsCitationWanted as $subCollectionItemsCitationWantedKey => $subCollectionItemsCitationWantedValue)
            {
                $final[$i][$subCollectionItemsCitationWantedKey]['citation'] = $subCollectionItemsCitationWantedValue['citation'];
            }
        return $final;
    }

    //own handling because "&sort=date&direction=desc" doesn't work
    function sortCollection($unsortedCollection)
    {
        $i = 0;
        $sortedArray = array();

        foreach($unsortedCollection as $unsortedCollectionKey => $unsortedCollectionValue)
        {
            $year= $unsortedCollectionValue['year'];
            $sortedArray[$year] = $sortedArray[$i];
            unset($sortedArray[$i]);

            $sortedArray[$year]['year'] = $unsortedCollectionValue['year'];
            $sortedArray[$year]['key'] = $unsortedCollectionValue['key'];

            $i++;
        }

        if($this->settings['zotero']['sorting'] == 'desc')
        {
            krsort($sortedArray);
        }
        else
        {
            ksort($sortedArray);
        }
        return $sortedArray;
    }

}
