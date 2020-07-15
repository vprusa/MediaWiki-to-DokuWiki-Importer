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
 * Convert image from MediaWiki.
 *
 * @author Andrei Nicholson
 * @since  2013-01-01
 */
class MediaWiki2DokuWiki_MediaWiki_Namespace_Image extends MediaWiki2DokuWiki_MediaWiki_Namespace_Base
{
    /**
     * Namespace ID in MediaWiki.
     */
    const NAME_SPACE = 6;

    /**
     * Inject image.
     *
     * @param array $record Info on page.
     */
    public function process(array $record)
    {
        // Hashed Upload Directory.
        $md5Filename = md5($record['page_title']);
        $dir1 = substr($md5Filename, 0, 1);
        $dir2 = substr($md5Filename, 0, 2);

        $srcFilePath = realpath("{$this->mediaWikiDir}/images/$dir1/$dir2/{$record['page_title']}");

        // From inc/pageutils.php
        $dstFilePathPart = realpath("{$this->dokuWikiDir}/data/media/wiki")
                     . '/';
        $dstInPreviousRunFilePath = $dstFilePathPart  . cleanID($record['page_title']);
        $brokeFlag = false;
/*
        if(!empty($record['group'])){
            var_dump($record);
            var_dump($srcFilePath);
            var_dump($dstFilePathPart);
          //  exit();
        }
*/

        if(isset(MediaWiki2DokuWiki_MediaWiki_Converter::$filesOfGroups)) {
            //var_dump(MediaWiki2DokuWiki_MediaWiki_Converter::$filesOfGroups);
            //exit();
            foreach (MediaWiki2DokuWiki_MediaWiki_Converter::$filesOfGroups as $group => $files) {
                $brokeFlag = false;
                foreach ($files as $original => $new) {
                    if (strpos(
                        cleanId(
                            str_replace("}","",
                                str_replace("{","",
                                    str_replace("wiki:","",
                                        str_replace(
                                            strtolower($group).":","",$original)
                                    )
                                )
                            )
                        ), cleanID($record['page_title'])) !== false) {
                        $dstFilePathPart = $dstFilePathPart . strtolower($group) . "/";
                        $brokeFlag = true;

                        break;
                    }

                }
                if ($brokeFlag) {
                    break;
                }

            }
            //  var_dump(MediaWiki2DokuWiki_MediaWiki_SyntaxConverter::$filesOfGroups);
            // exit();
        }
        $dstFilePath = $dstFilePathPart . cleanID($record['page_title']);

        if ($srcFilePath === false) {
            MediaWiki2DokuWiki_Environment::out(
                'Does not exist in MediaWiki installation. Skipping'
            );
            return;
        }




        if (!is_dir(dirname($dstFilePath))) {
            mkdir(dirname($dstFilePath));
        }
/*
        if ($brokeFlag) {
            try {
                if (!unlink($dstInPreviousRunFilePath)) {
                    MediaWiki2DokuWiki_Environment::out('Error while deleting redundant file. Skipping.');
                    return;
                }
            } catch(Exception $e){

            }


        }
*/

     /*   if(!empty($record['group'] )){
            var_dump($record);
            var_dump($srcFilePath);
            var_dump($dstFilePath);
            exit();
        }
*/
        if (file_exists($dstFilePath)) {

            MediaWiki2DokuWiki_Environment::out('File already exists. Skipping.');
            //var_dump($record);
            //exit();

            return;
        }


        if (!copy($srcFilePath, $dstFilePath)) {
            MediaWiki2DokuWiki_Environment::out('Error while copying. Skipping.');
            return;
        }
    }
}

