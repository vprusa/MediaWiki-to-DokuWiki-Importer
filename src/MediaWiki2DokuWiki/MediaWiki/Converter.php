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
 * Find things from MediaWiki which can be converted.
 *
 * @author Andrei Nicholson
 * @since  2013-01-01
 */
class MediaWiki2DokuWiki_MediaWiki_Converter
{
    /**
     * Path to the base directory of DokuWiki.
     */
    private $dokuWikiDir = '';

    /**
     * Path to the base directory of MediaWiki.
     */
    private $mediaWikiDir = '';

    /**
     * Language array.
     */
    private $lang = array();

    /**
     * Database table prefix used in MediaWiki queries.
     */
    private $dbPrefix = '';


    /** [group] -> [array of files] */
    public static $filesOfGroups ;

    /** [group] -> [array of ids under this group] */
    public static $idsOfGroups ;

    /** [pageId] -> [array of tags] */
    public static $idsOfCategories ;

    /** [ namespace ] -> [header] -> [ [pageId, pageFullName ] ] */
    public static $customNamespaces = array();

    /** [ tag -> true] */
    public static $tags = array();

    //public static $possibleCategoriesPrefixes = array(":Kategorie",":kategorie","Kategorie","kategorie");
    public static $possibleCategoriesPrefixes = array(/*":Kategorie",":kategorie",*/"Kategorie","kategorie","..:Kategorie","..:kategorie");
    public static $possibleLinksToCategoriesPrefixes = array(":Kategorie",":kategorie");

    /** [ old_page_title -> new_page_title ] */
    public static $changedPageTitles = array();

    /** [ pages ids ] */
    public static $specificPagesToConvert = array();


    /**
     * Constructor.
     *
     * @param string $dokuWikiDir  Path to base directory of DokuWiki.
     * @param string $mediaWikiDir Path to base directory of MediaWiki.
     * @param array  $lang         Language array.
     * @param string $dbPrefix     Database table prefix.
     */
    public function __construct(
        $dokuWikiDir,
        $mediaWikiDir,
        array $lang,
        $dbPrefix
    ) {

        $this->parseCustomGroup();

        if(empty(MediaWiki2DokuWiki_MediaWiki_Converter::$filesOfGroups)) {
            MediaWiki2DokuWiki_MediaWiki_Converter::$filesOfGroups = array();
        }
        if(empty(MediaWiki2DokuWiki_MediaWiki_Converter::$idsOfGroups)) {
            MediaWiki2DokuWiki_MediaWiki_Converter::$idsOfGroups = array();
        }

        if(empty(MediaWiki2DokuWiki_MediaWiki_Converter::$idsOfCategories)) {
            MediaWiki2DokuWiki_MediaWiki_Converter::$idsOfCategories= array();
        }

        $this->dokuWikiDir = $dokuWikiDir;
        $this->mediaWikiDir = $mediaWikiDir;
        $this->lang = $lang;
        $this->dbPrefix = $dbPrefix;

        global $ARG_OPTIONS_PAGES;

        if(!empty($ARG_OPTIONS_PAGES) && sizeof($ARG_OPTIONS_PAGES) > 0) {
            MediaWiki2DokuWiki_MediaWiki_Converter::$specificPagesToConvert = $ARG_OPTIONS_PAGES;
            var_dump(MediaWiki2DokuWiki_MediaWiki_Converter::$specificPagesToConvert);
            //exit();
        }
    }

    public function saveStaticPage($name) {
        global $SPECIFICS_DATA_DIR;
        $filePath = "${SPECIFICS_DATA_DIR}/StaticPages/" . $name;
        echo "Saving static page from file: $filePath\n";
        if(file_exists($filePath)){
          $staticPage = file_get_contents($filePath);
          saveWikiText($name,$staticPage,$name);
        }
    }

    /**
     * Convert pages from MediaWiki.
     *
     * @param PDO $db DB handle.
     */
    public function convert(PDO $db, $what)
    {

        $textTable = $db->getAttribute(PDO::ATTR_DRIVER_NAME) == 'pgsql' ? "pagecontent" : "text";


        if(!empty(MediaWiki2DokuWiki_MediaWiki_Converter::$specificPagesToConvert)){
            $glue = "p.page_title = '";
            $stringWithPagesIdsQuery = $glue . implode( "' OR " . $glue . "'", MediaWiki2DokuWiki_MediaWiki_Converter::$specificPagesToConvert) . "'";
            var_dump($stringWithPagesIdsQuery );
            var_dump($this->dbPrefix);

            $sql = "SELECT      p.page_title, p.page_namespace, t.old_text
                FROM        {$this->dbPrefix}page p
                INNER JOIN  {$this->dbPrefix}revision r ON
                            p.page_latest = r.rev_id
                INNER JOIN  {$this->dbPrefix}{$textTable} t ON
                            r.rev_text_id = t.old_id WHERE {$stringWithPagesIdsQuery}
                ORDER BY    p.page_title";
        } else {
            $sql = "SELECT      p.page_title, p.page_namespace, t.old_text
                FROM        {$this->dbPrefix}page p
                INNER JOIN  {$this->dbPrefix}revision r ON
                            p.page_latest = r.rev_id
                INNER JOIN  {$this->dbPrefix}{$textTable} t ON
                            r.rev_text_id = t.old_id
                ORDER BY    p.page_title";

        }
        try {
            $statement = $db->prepare($sql);

            if (!$statement->execute()) {
                $error = $statement->errorInfo();
                throw new Exception('Could not fetch MediaWiki: ' . $error[2]);
            }

            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                MediaWiki2DokuWiki_Environment::out(
                    'Processing ' . $row['page_title'] . '... ',
                    false
                );

                if( $what == "dry"){
                    // todo move 'dry' check inside MediaWiki2DokuWiki_MediaWiki_SyntaxConverter if necessary

                    if(!empty($row['old_text'])){
                        MediaWiki2DokuWiki_MediaWiki_SyntaxConverter::getPossibleCategoriesToTags($row['old_text']);
                    }

                    if (strpos($row['old_text'], '{{GROUPSPECIALTAG-Only}}') !== false) {
                        $row['group'] = "GROUPSPECIALTAG-only";
                    }

                    $row = $this->getCustomGroupForDry($row);

                    if(isset($row['group'])) {
                        if(empty(MediaWiki2DokuWiki_MediaWiki_Converter::$idsOfGroups[$row['group']])){
                            MediaWiki2DokuWiki_MediaWiki_Converter::$idsOfGroups[$row['group']] = array();
                        }
                        if(!in_array(strtolower($row['page_title']), MediaWiki2DokuWiki_MediaWiki_Converter::$idsOfGroups[$row['group']], true)){
                            array_push(MediaWiki2DokuWiki_MediaWiki_Converter::$idsOfGroups[$row['group']],cleanId($row['page_title']));
                        }
                    }
                }else if($what == "files") {
                    $row = $this->getCustomGroupForFiles($row);
                }else if($what == "images") {
                    $row = $this->getCustomGroupForImages($row);
                }

                if($what =="dry"){
                    //$converter->convert();
                    continue;
                }
                switch ($row['page_namespace']) {
                    case MediaWiki2DokuWiki_MediaWiki_Namespace_Page::NAME_SPACE:
                    case MediaWiki2DokuWiki_MediaWiki_Namespace_Page::NAME_SPACE2:
                    case MediaWiki2DokuWiki_MediaWiki_Namespace_Page::NAME_SPACE3:
                    case MediaWiki2DokuWiki_MediaWiki_Namespace_Page::NAME_SPACE4:
                        if($what == "files") {
                            $page = new MediaWiki2DokuWiki_MediaWiki_Namespace_Page(
                                $this->dokuWikiDir,
                                $this->mediaWikiDir,
                                $this->lang,
                            $db
                               //, $what
                            );

                            $page->process($row);
                        }
                        break;

                    case MediaWiki2DokuWiki_MediaWiki_Namespace_Image::NAME_SPACE:
                        if($what == "images"){
                            $image = new MediaWiki2DokuWiki_MediaWiki_Namespace_Image(
                                $this->dokuWikiDir,
                                $this->mediaWikiDir,
                                $this->lang,
                                $db
                            );
                            $image->process($row);
                        }
                        break;

                    default:
                        var_dump($row);
                        MediaWiki2DokuWiki_Environment::out(
                            'Unknown type. Skipping. ',
                            false
                        );
                        break;
                }

                MediaWiki2DokuWiki_Environment::out(PHP_EOL);
            }
            //$statement->closeCursor();
            //$db->
        } catch (PDOException $e) {
            throw new Exception('Error: Could not select all pages: ' . $e->getMessage());
        }

        if(strpos($what, "dry") === false ) {

        }
        $this->createMainPagesForCustomGroups();
    }

    public function getCustomGroupForImages(&$row) {

        foreach (MediaWiki2DokuWiki_MediaWiki_Converter::$filesOfGroups as $groupId => $filesIdsPair) {
            $filesIds  = array_keys($filesIdsPair);
            $toBreak = false;
            foreach($filesIds as $fileId){
                if(strpos($fileId, $row['page_title']) != false
                    || strpos($fileId, cleanID($row['page_title'])) != false){
                    $row['group'] = $groupId;
                    $toBreak  = true;
                    break;
                }
            }
            if($toBreak == true){
                break;
            }

        }
        return $row;
    }

    public function getCustomGroupForFiles(&$row) {
        foreach (MediaWiki2DokuWiki_MediaWiki_Converter::$idsOfGroups as $groupId => $pagesIds) {
            if(in_array($row['page_title'],$pagesIds)
                || in_array(cleanID($row['page_title']),$pagesIds)
                || in_array(strtolower(cleanID($row['page_title'])),$pagesIds)){
                $row['group'] = $groupId;
            }
        }
        return $row;
    }

    public function getCustomGroupForDry(&$row) {
        foreach (MediaWiki2DokuWiki_MediaWiki_Converter::$customNamespaces as $namespace => &$tagsAndPages) {
            foreach ($tagsAndPages as $tag => &$pages) {
                foreach($pages as $page) {
                    if (!empty($page)) {
                        foreach($page as $pageTitle=> $pageTitleOriginal) {
                            $newPageId = $row["page_title"];
                            resolve_pageid("",$newPageId, $exists);
                            resolve_pageid("",$pageTitle, $exists);

                            if (strcmp(strtolower($newPageId), strtolower($pageTitle)) === 0) {
                                $row['group'] = $namespace;
                            }
                        }
                    }
                }
            }
        }
        return $row;
    }

    public function parseCustomGroup(){
        global $SPECIFICS_DATA_DIR;
        $CUSTOM_NAMESPACES_DIR="$SPECIFICS_DATA_DIR/CustomNamespacesTagsWithPages.json";
        if(!file_exists($CUSTOM_NAMESPACES_DIR)) {
          echo "File $CUSTOM_NAMESPACES_DIR does not exists\n";
          return;
        }
        // ~~/convertor/src/MediaWiki2DokuWiki/CustomNamespacesTagsWithPages.json
        $string = file_get_contents($CUSTOM_NAMESPACES_DIR);
        MediaWiki2DokuWiki_MediaWiki_Converter::$customNamespaces= json_decode($string, true);
        // var_dump(MediaWiki2DokuWiki_MediaWiki_Converter::$customNamespaces);

        foreach (MediaWiki2DokuWiki_MediaWiki_Converter::$customNamespaces as $namespace => &$tagsAndPages) {
            foreach ($tagsAndPages as $tag => &$pages) {
                //echo $person_a['status'];
                foreach ($pages as $id => &$page) {
                    //resolve_pageid()ID()
                    $original = $page;
                    //$pageId = getID($page);
                    resolve_pageid("", $page, $exists);
                    MediaWiki2DokuWiki_MediaWiki_Converter::$customNamespaces[$namespace][$tag][$id] = array($page => $original);
                }
            }
        }
    }

    public function createMainPagesForCustomGroups(){
        foreach (MediaWiki2DokuWiki_MediaWiki_Converter::$customNamespaces as $namespace => &$tagsAndPages) {
            $text = "====== $namespace ======".PHP_EOL.PHP_EOL;
            foreach ($tagsAndPages as $tag => &$pages) {

                $header="===== $tag =====".PHP_EOL;
                $text="$text$header";
                //var_dump($pages);
                foreach ($pages as $id => $page) {
                    $pageId = array_pop(array_keys($page));
                    $pageName = $page[$pageId];
                    $link = " * [[$namespace:$pageId|$pageName]]";
                    $text="$text $link".PHP_EOL.PHP_EOL;
                }
                $text="$text".PHP_EOL.PHP_EOL;
            }
            saveWikiText("$namespace:",$text.PHP_EOL .PHP_EOL . "{{tag>infrastruktura}}",$namespace);
        }
        //var_dump(MediaWiki2DokuWiki_MediaWiki_Converter::$customNamespaces);
        //$row
        //exit();
    }


}
