<?php
/**
 * MediaWiki2DokuWiki importer.
 * Copyright (C) 2011-2013  Andrei Nicholson
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package   MediaWiki2DokuWiki
 * @author    Andrei Nicholson
 * @copyright Copyright (C) 2011-2013 Andrei Nicholson
 * @link      https://github.com/tetsuo13/MediaWiki-to-DokuWiki-Importer
 */

/**
 * Convert page from MediaWiki.
 *
 * @author Andrei Nicholson
 * @since  2013-01-01
 */
class MediaWiki2DokuWiki_MediaWiki_Namespace_Page extends MediaWiki2DokuWiki_MediaWiki_Namespace_Base
{
    /**
     * Namespace ID in MediaWiki.
     */
    const NAME_SPACE = 0;
    const NAME_SPACE2 = 1;
    const NAME_SPACE3 = 2;
    const NAME_SPACE4 = 14;

    static $counter = 0;

    /**
     * Inject new page into DokuWiki.
     *
     * @param array $record Info on page.
     */
    public function process(array $record) {

      if(!empty(MediaWiki2DokuWiki_MediaWiki_Converter::$tags)) {
            foreach(MediaWiki2DokuWiki_MediaWiki_Converter::$tags as $tagKey => $tagValues){
                if($record['page_title'] == $tagKey) {
                    $break = false;
                    foreach(MediaWiki2DokuWiki_MediaWiki_Converter::$possibleCategoriesPrefixes as $prefix){
                        if(strpos($record['old_text'],$prefix.":".$record['page_title']) !== false){
                            $break = true;
                        }
                    }
                    if($break == true){
                        break;
                    }
                    // todo  check if at the end of conversion is this empty or not  becaus eit coudl mean that there are some lost pages..
                    MediaWiki2DokuWiki_MediaWiki_Converter::$changedPageTitles[$record['page_title']] =  $record['page_title'] . "_page";
                    $record['page_title'] = $record['page_title'] . "_page";
                    $record['old_text'] = $record['old_text'] . "";
                  }
            }

            /* if($record['page_title'] == "Joby_page"){
                //var_dump($record);
                if(MediaWiki2DokuWiki_MediaWiki_Namespace_Page::$counter > 0){
                //exit();
                }
            }*/
        }

        $converter = new MediaWiki2DokuWiki_MediaWiki_SyntaxConverter($record, $this->db);
        $title = str_replace('"',"",$record['page_title']);

        if(isset($record['group'])){
            $title = $record['group'] . ":" . $title;
            //var_dump($record);
            if(empty(MediaWiki2DokuWiki_MediaWiki_Converter::$idsOfGroups[$record['group']])){
                MediaWiki2DokuWiki_MediaWiki_Converter::$idsOfGroups[$record['group']] = array();
            }
            array_push(MediaWiki2DokuWiki_MediaWiki_Converter::$idsOfGroups[$record['group']],$record['page_title']);
        }

        $finalResult =  $converter->convert();
        saveWikiText($title,
            con('', "====== " . str_replace("_"," ", $record['page_title']) . " ======\n\n" . $finalResult , ''),
            $this->lang['created']);

    }
}

