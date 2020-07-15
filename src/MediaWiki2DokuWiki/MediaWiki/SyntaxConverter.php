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
 * @author    Vojtech Prusa
 * @copyright Copyright (C) 2011-2013 Andrei Nicholson
 * @link      https://github.com/tetsuo13/MediaWiki-to-DokuWiki-Importer
 */

/**
 * Convert syntaxes.
 *
 * Regular expressions originally by Johannes Buchner
 * <buchner.johannes [at] gmx.at>.
 *
 * Changes by Frederik Tilkin:
 *
 * <ul>
 * <li>uses sed instead of perl</li>
 * <li>resolved some bugs ('''''IMPORTANT!!!''''' becomes //**IMPORTANT!!!** //,
 *     // becomes <nowiki>//</nowiki> if it is not in a CODE block)</li>
 * <li>added functionality (multiple lines starting with a space become CODE
 *     blocks)</li>
 * </ul>
 *
 * @author Andrei Nicholson
 * @author Johannes Buchner
 * @author Frederik Tilkin
 * @since  2012-05-07
 */
class MediaWiki2DokuWiki_MediaWiki_SyntaxConverter
{
    /** Original MediaWiki record. */
    public $record = '';
    /** Original MediaWiki data .*/
    public $recordData = '';

    /** Stored code blocks to prevent further conversions. */
    private $codeBlock = array();

    /** What string should never occur in user content? */
    private $placeholder = '@@MediaWiki2DokuWiki_MediaWiki_SyntaxConverter_';//'' . __CLASS__ . '_';//'';

    private $db;

    /**
     * Constructor.
     *
     * @param string $record MediaWiki record.
     */
    public function __construct($record, PDO $db)
    {
        $this->placeholder = '@@MediaWiki2DokuWiki_MediaWiki_SyntaxConverter_';//' . __CLASS__ . '_';
        //var_dump($this->placeholder );
        //exit();
        $this->record = $record['old_text'];
        $this->recordData = $record;
        //var_dump($record);
        //exit();
        $this->db = $db;
       // unset($this->recordData['old_text']);
        if(isset($this->recordData['group'])){
            if(empty(MediaWiki2DokuWiki_MediaWiki_Converter::$filesOfGroups[$this->recordData['group']])){
                MediaWiki2DokuWiki_MediaWiki_Converter::$filesOfGroups[$this->recordData['group']] = array();
            }
        }
    }

    /**
     * Convert page syntax from MediaWiki to DokuWiki.
     *
     * @return string DokuWiki page.
     * @author Johannes Buchner <buchner.johannes [at] gmx.at>
     * @author Frederik Tilkin
     */
    public function convert()
    {
        $record = $this->record;

        foreach(MediaWiki2DokuWiki_MediaWiki_Converter::$possibleLinksToCategoriesPrefixes as $categoriesString) {
            $record = $this->convertCategoriesToTagsLinks($record, $categoriesString);
        }

        $record = $this->convertGroupsLinks($record);
        $record = $this->convertList($record);

        $record = $this->convertCodeBlocks($record);


        $record = $this->convertUrlText($record);
        $record = $this->convertLink($record);
        $record = $this->convertDoubleSlash($record);
        $record = $this->convertBoldItalic($record);
        $record = $this->convertTalks($record);

        $record = $this->convertImagesFiles($record);
        $record = $this->convertLinksByGroup($record);



        $record = $this->convertHeadings($record);
        $record = $this->convertGROUPSPECIALTAGTag($record);
        $record = $this->convertLinksWithMediaCategorySlash($record);

        foreach(MediaWiki2DokuWiki_MediaWiki_Converter::$possibleCategoriesPrefixes as $categoriesString) {
            $record = $this->convertCategoryLink($record,$categoriesString);
        }

        $record = $this->removeUnused($record);

        if (count($this->codeBlock) > 0) {
            $record = $this->replaceStoredCodeBlocks($record);
        }
        //$record = $this->convertIndentBlocks($record);

        return $record;
    }

    public static function getPossibleCategoriesToTags($record) {

        if(empty(MediaWiki2DokuWiki_MediaWiki_Converter::$tags)){
            MediaWiki2DokuWiki_MediaWiki_Converter::$tags = array();
        }
        foreach(MediaWiki2DokuWiki_MediaWiki_Converter::$possibleCategoriesPrefixes as $categoriesString) {
            if(strpos($record, $categoriesString) <= 0){
                continue;
            }
            $patterns = array(
                '/\[\[(' . $categoriesString . ':)(.*)\|(.*)\]\]/',
                '/\[\[(' . $categoriesString . ':)(.*)\]\]/'
            );

            foreach($patterns as $pattern){
                preg_match_all(
                    $pattern,
                    $record,
                    $matches
                );
                //var_dump($matches);
                if(!empty($matches) && !empty($matches[2]) && !empty($matches[2][0])){
                    MediaWiki2DokuWiki_MediaWiki_Converter::$tags[$matches[2][0]] = $matches;
                } else {

                }
            }
        }
        //var_dump(MediaWiki2DokuWiki_MediaWiki_Converter::$tags);

    }


    /**
     * Double forward slashes are not italic. There is no double slash syntax
     * rule in MediaWiki. This conversion must happen before the conversion of
     * italic markup.
     *
     * @param string $record
     *
     * @return string
     */
    private function convertDoubleSlash($record)
    {
        $patterns = array(
            '/([^:])\/\//m' => '\1<nowiki>//</nowiki>',
        );
        return preg_replace(
            array_keys($patterns),
            array_values($patterns),
            $record
        );
    }


    private function convertIndentBlocks($record)
    {
        $patterns = array(
            '/\n (.*)/' => '<pre>\1</pre>',
        );

        $record = preg_replace(
            array_keys($patterns),
            array_values($patterns),
            $record
        );

        return $record;
    }

    /**
     * Code blocks.
     *
     * @param string $record
     *
     * @return string
     */
    private function convertCodeBlocks($record)
    {

        //$codePattern = '@</code>\n[ \t]*\n<code>@';
        $codePattern = '@<code>.*</code>@';
        $prePattern = '@<pre>(.*?)?</pre>@s';
        $patterns = array(
            // Change the ones that have been replaced in a link [] BACK to
            // normal (do it twice in case
            // [http://addres.com http://address.com] ) [quick and dirty]

            '/([\[][^\[]*)(<nowiki>)(\/\/+)(<\/nowiki>)([^\]]*)/' => '\1\3\5',
            '/([\[][^\[]*)(<nowiki>)(\/\/+)(<\/nowiki>)([^\]]*)/' => '\1\3\5',
            '/\n\n [-\*#](.*)/' => PHP_EOL . ' '. PHP_EOL . ' -\1',
            '/\n (.*)/' => PHP_EOL.'  \1',
            //$codePattern  => '',
            // '@<pre>@' => '<code>',
            // '@</pre>@' => '</code>'
        );




        $result = preg_replace(
            array_keys($patterns),
            array_values($patterns),
            $record
        );


        return preg_replace_callback(
            $prePattern,
            array($this, 'storeCodeBlock'),
            $result
        );
        //return $result;
    }

    /**
     * Replace content in PRE tag with placeholder. This is done so no more
     * conversions are performed with the contents. The last thing this class
     * will do is replace those placeholders with their original content.
     *
     * @param string[] $matches Contents of PRE tag in second element.
     *
     * @return string CODE tag with placeholder in content.
     */
    private function storeCodeBlock($code)
    {
        $this->codeBlock[] = $code[1];
        $replace = $this->placeholder . (count($this->codeBlock) - 1) . '@@';
        return "<code>$replace</code>";
    }

    /**
     * Replace PRE tag placeholders back with their original content.
     *
     * @param string $record Converted record.
     *
     * @return string Record with placeholders removed.
     */
    private function replaceStoredCodeBlocks($record)
    {
        for ($i = 0, $numBlocks = count($this->codeBlock); $i < $numBlocks; $i++) {
            $record = str_replace(
                //$this->placeholder .
                $i . '@@',
                $this->codeBlock[$i],
                $record
            );

        }
        return $record;
    }

    /**
     * Convert images and files.
     *
     * @param string $record Converted record.
     *
     * @return string
     */
    private function convertImagesFiles($record) {
        $numMatches = preg_match_all(
            '/\[\[(Image|File|Soubor|Media|Média):(.*?)\]\]/',
            $record,
            $matches
        );

        if ($numMatches === 0 || $numMatches === false) {
            return $record;
        }

        for ($i = 0; $i < $numMatches; $i++) {
            $converted = $this->convertImage($matches[2][$i]);

            // try move to right namespace
            if(!empty($this->recordData['group'])) {
                $convertedWithGroup = str_replace("wiki:", "wiki:".strtolower($this->recordData['group']) . ":", $converted);
                MediaWiki2DokuWiki_MediaWiki_Converter::$filesOfGroups[$this->recordData['group']]["$converted"] = $convertedWithGroup;
                $converted = $convertedWithGroup;
            }
            // Replace the full tag, [[File:example.jpg|options|caption]],
            // with the DokuWiki equivalent.
            $record = str_replace($matches[0][$i], $converted, $record);
        }

        return $record;
    }

    public static $exitCounter = 0;

    private function convertCategoriesToTagsLinks($record, $categoriesString) {

        $patterns = array(
            '/\[\[(' . $categoriesString . ':)(.*)\|(.*)\]\]/'
                => '[[tag:'.'\2'.'?do=showtag&tag=\2|\3]]',
            '/\[\[(' . $categoriesString . ':)(.*)\]\]/'
                => '[[tag:'.'\2'.'?do=showtag&tag=\2]]',
        );

        $record =  preg_replace_callback(
            array_keys($patterns)[0],
            function ($matches) {
                return  '[[tag:'.str_replace(" ", "_",$matches[2]).'?do=showtag&tag='.str_replace(" ", "_",$matches[2]).'|'.$matches[3].']]';
            },
            $record
        );

        $record =  preg_replace_callback(
            array_keys($patterns)[1],
            function ($matches) {
                return  '[[tag:'.str_replace(" ", "_",$matches[2]).'?do=showtag&tag='.str_replace(" ", "_",$matches[2]).']]';
            },
            $record
        );

        return $record;
    }


    private function convertGroupsLinks($record) {

        //  preg_match(array_keys($patterns)[0], $record, $matches);

        $patternKey = '/(?<=\[\[)(.*)(?=\]\])/';

        $check_hash = preg_match_all( $patternKey, $record, $hashtweet);


        if(!empty($hashtweet) && !empty($hashtweet[0])){



            if(isset(MediaWiki2DokuWiki_MediaWiki_Converter::$idsOfGroups)) {
                foreach ($hashtweet[0] as $match) {
                    $brokeFlag = false;
                    foreach (MediaWiki2DokuWiki_MediaWiki_Converter::$idsOfGroups as $group => $ids) {
                        foreach ($ids as $id) {

                            if (strcmp(
                                    cleanId(
                                        str_replace("wiki:", "",
                                            str_replace(
                                                strtolower($group) . ":", "", $id)
                                        )
                                    ), cleanID($match)) === 0 ) {

                                $record = str_replace("[[" . $match . "]]", "[[" . strtolower($group). ":" . $match. "]]" , $record);
                                $brokeFlag = true;
                            }

                        }
                    }
                    if ($brokeFlag) {
                       // break;
                    }

                }


            }

        }

        return $record;
    }

    public static $stopCounter = 0;


    /**
     *
     * @param string $record Converted record.
     *
     * @return string
     */
    private function convertGROUPSPECIALTAGTag($record) {
        $record = str_replace("{{GROUPSPECIALTAG-only}}","{{tag>GROUPSPECIALTAG-only}}",$record);
        $record = str_replace("{{GROUPSPECIALTAG-Only}}","{{tag>GROUPSPECIALTAG-only}}",$record);
        $record = str_replace("{{GROUPSPECIALTAG-ONLY}}","{{tag>GROUPSPECIALTAG-only}}",$record);
        $record = str_replace("{{GROUPSPECIALTAG-internal}}","{{tag>GROUPSPECIALTAG-only}}",$record);
        $record = str_replace("{{Studenti}}","{{tag>Studenti_studentum}}",$record);
        $record = str_replace("{{studenti}}","{{tag>Studenti_studentum}}",$record);
        //$record = str_replace("{{studenti}}","{{tag>Studenti_studentum}}",$record);
        $record = str_replace("GROUPSPECIALTAG,,","",$record);
        $record = str_replace("GROUPSPECIALTAG,,","",$record);

        $matchPattern =  '/({{Studenti-(.*)}})/';
        $matchValue = '{{tag>Studenti_studentum Studenti_studentum-\2}}';
        $patterns = array(
          $matchPattern => $matchValue,
          strtolower($matchPattern) => $matchValue
        );

        $record = preg_replace(
          array_keys($patterns),
          array_values($patterns),
          $record
        );

        return $record;
    }

    /**
     *
     * @param string $record Converted record.
     *
     * @return string
     */
    private function convertLinksByGroup($record)
    {
        $matchPattern =  '/\[\[(?:(?!mailto|http))(.*?)\]\]/';

        $patterns = array(
            $matchPattern => '[['.'..:\1'.']]'
        );

        if(isset($this->recordData['group'])){
            $record = preg_replace(
                array_keys($patterns),
                array_values($patterns),
                $record
            );
        }

        return $record;
    }

    /**
     *
     *  Why?: There are links like [[/Some/Category/]] and [[/Some/Category/Subcategory/]]
     *  and these need to be converted to format [[Some_Category]] and [[Some_Category_Subcategory]]
     *  or to [[Some_Category_]] and [[Some_Category_Subcategory_]]
     *
     * @param string $record Converted record.
     *
     * @return string
     */
    private function convertLinksWithMediaCategorySlash($record)
    {
        $matchPattern =  '/\[\[(\/)(.*)(\/)\]\]/';

        $patterns = array(
            //$matchPattern => '\1'.'..:\2'.'\3'
            //$matchPattern => '[['.'\1'.']]'
            //'/\[\[(\/)(.*)(\/)\]\]/' => '[['.cleanID($this->recordData['page_title']).'_\3'.']]'
            '/\[\[(\/)(.*)(\/)\]\]/' => '[['.cleanID($this->recordData['page_title']).'_\2'.']]'
        );

        $record = preg_replace(
            array_keys($patterns),
            array_values($patterns),
            $record
        );

        return $record;
    }



    /**
     * Remove unused content
     * G.e. remove tags like <br />
     *
     * @param string $record Converted record.
     *
     * @return string
     */
    private function removeUnused($record)
    {


        $record = strip_tags($record);
        $record = str_replace("__NOTOC__", "~~NOTOC~~", $record);

        $numMatches = preg_match_all(
            '/\[\[(Image|File|Soubor):(.*?)\]\]/',
            $record,
            $matches
        );

        if ($numMatches === 0 || $numMatches === false) {
            return $record;
        }

        for ($i = 0; $i < $numMatches; $i++) {
            $converted = $this->convertImage($matches[2][$i]);

            // Replace the full tag, [[File:example.jpg|options|caption]],
            // with the DokuWiki equivalent.
            $record = str_replace($matches[0][$i], $converted, $record);
        }


        if(!empty($this->recordData['group']) && strtolower($this->recordData['group']) == "infrastruktura"){
            $record = $record . PHP_EOL . PHP_EOL. "{{tag>infrastruktura}}";
        }
        return $record;
    }

    /**
     * Process a MediaWiki image tag.
     *
     * @param string $detail Filename and options, ie.
     *                       example.jpg|options|caption.
     *
     * @return string DokuWiki version of tag.
     */
    private function convertImage($detail)
    {
        $parts = explode('|', $detail);
        $numParts = count($parts);

        // Image link.
        if ($numParts == 2 && substr($parts[1], 0, 5) == 'link=') {
            return '[[' . substr($parts[1], 5) . '|{{wiki:' . $parts[0] . '}}]]';
        }

        $converted = '{{';
        $leftAlign = '';
        $rightAlign = '';
        $imageSize = '';
        $caption = '';

        if ($numParts > 1) {
            $imageFilename = array_shift($parts);

            foreach ($parts as $part) {
                if ($part == 'left') {
                    $leftAlign = ' ';
                    continue;
                } else if ($part == 'right') {
                    $rightAlign = ' ';
                    continue;
                } else if ($part == 'center') {
                    $leftAlign = $rightAlign = ' ';
                    continue;
                }

                if (substr($part, -2) == 'px') {
                    preg_match('/((\d+)x)?(\d+)px/', $part, $matches);

                    if (count($matches) > 0) {
                        if ($matches[1] == '') {
                            $imageSize = $matches[3];
                        } else {
                            $imageSize = $matches[2] . 'x' . $matches[3];
                        }
                    }

                    continue;
                }

                $caption = $part;
            }

            $converted .= $leftAlign . 'wiki:' . $imageFilename . $rightAlign;

            if ($imageSize != '') {
                $converted .= '?' . $imageSize;
            }

            if ($caption != '') {
                $converted .= '|' . $caption;
            }
        } else {
            $converted .= "wiki:$detail";
        }

        $converted .= '}}';

        return $converted;
    }

    /**
     * Convert talks.
     *
     * @param string $record
     *
     * @return string
     */
    private function convertTalks($record)
    {
        $patterns = array(
            '/^[ ]*:/'  => '>',
            '/>:/'      => '>>',
            '/>>:/'     => '>>>',
            '/>>>:/'    => '>>>>',
            '/>>>>:/'   => '>>>>>',
            '/>>>>>:/'  => '>>>>>>',
            '/>>>>>>:/' => '>>>>>>>'
        );

        return preg_replace(
            array_keys($patterns),
            array_values($patterns),
            $record
        );
    }

    /**
     * Convert bold and italic.
     *
     * @param string $record
     *
     * @return string
     */
    private function convertBoldItalic($record)
    {
        $patterns = array(
            "/'''''(.*)'''''/" => '//**\1**//',
            // '''text''' needs to be solved first
            "/'''(.*)'''/" => '**\1**',
            "/'''/"            => '**',
            "/''/"             => '//',

            // Changes by Reiner Rottmann: - fixed erroneous interpretation
            // of combined bold and italic text.
            '@\*\*//@'         => '//**'
        );


        $result =  preg_replace(
            array_keys($patterns),
            array_values($patterns),
            $record
        );
        var_dump($record);
        var_dump($result);

        return $result;
    }

    public static $categoriesStringS = "";

    /**
     * Handle Categories for dry run only
     *
     * @param string $record
     *
     * @return string
     */
    private function convertCategoryLink($record, $categoriesString)
    {
    MediaWiki2DokuWiki_MediaWiki_SyntaxConverter::$categoriesStringS =$categoriesString;

       $patternValue = '{{tag>"\2"}}';
        $patterns = array(
            '/\[\[(' . $categoriesString . ':)(.*)\|(.*)\]\]/'
            => $patternValue,
            '/\[\[(' . $categoriesString . ':)(.*)\]\]/'
            => $patternValue,
        );

        foreach(array_keys($patterns) as $patternKey){
            preg_match_all($patternKey, $record,$matches);

            if (!empty($matches) && !empty($matches[0])){
                foreach($matches[0] as $index => $match) {
                    if (strpos("$match", "$categoriesString:") !== false){
                        $trimmedMatch = str_replace("]]","", str_replace("[[:$categoriesString:", "",$match));
                        //var_dump($trimmedMatch);
                        if(empty(MediaWiki2DokuWiki_MediaWiki_Converter::$idsOfCategories[$this->recordData['page_title']])){
                            MediaWiki2DokuWiki_MediaWiki_Converter::$idsOfCategories[$this->recordData['page_title']] = array();
                        }
                        if(!in_array($trimmedMatch , MediaWiki2DokuWiki_MediaWiki_Converter::$idsOfCategories[$this->recordData['page_title']], true)){
                            array_push(MediaWiki2DokuWiki_MediaWiki_Converter::$idsOfCategories[$this->recordData['page_title']], $trimmedMatch );
                        }
                    }
                }
            }
        }

        return preg_replace_callback(
            array_keys($patterns),
            //array_values($patterns),
            function ($matches) {
                return  '{{tag>"'.str_replace(" ", "_",$matches[2]).'"}}';//preg_replace(array_keys($patterns),$patternValue,$matches[0]);
            },
            $record
        );
    }

    /**
     * Convert [link] => [[link]].
     *
     * @param string $record
     *
     * @return string
     */
    private function convertLink($record)
    {

        $patternKey = '/([^[]|^)(\[[^]]*\])([^]]|$)/';

        $patterns = array(
            $patternKey => '\1[\2]\3');

        return preg_replace(
            array_keys($patterns),
            array_values($patterns),
            $record
        );
    }

    /**
     * Convert [url text] => [url|text].
     *
     * @param string $record
     *
     * @return string
     */
    private function convertUrlText($record)
    {

        $patternKey = '/([^[]|^)(\[[^] ]*) ([^]]*\])([^]]|$)/';
        $patterns = array(
            $patternKey => '\1\2|\3\4'
        );

        $result = preg_replace(
            array_keys($patterns),
            array_values($patterns),
            $record
        );

        return $result;
    }

    /**
     * Convert lists.
     *
     * @param string $record
     *
     * @return string
     */
    private function convertList($record)
    {
        $patterns = array(
            '/^\*{4}/m' => '        * ',
            '/^\*{3}/m' => '      * ',
            '/^\*{2}/m' => '    * ',
            '/^\* /m'   => '  * ',

            '/^#{4}/m'  => '        - ',
            '/^#{3}/m'  => '      - ',
            '/^#{2}/m'  => '    - ',
            '/^# /m'     => '  - '
        );

        return preg_replace(
            array_keys($patterns),
            array_values($patterns),
            $record
        );
    }

    /**
     * Convert headings. Syntax between MediaWiki and DokuWiki is completely
     * opposite: the largest heading in MediaWiki is two equal marks while in
     * DokuWiki it's six equal marks. This creates a problem since the first
     * replaced string of two marks will be caught by the last search string
     * also of two marks, resulting in eight total equal marks.
     *
     * @param string $record
     *
     * @return string
     */
    private function convertHeadings($record)
    {
        $patterns = array(
            '/^======.*\[\[(.*)\]\](.*)======\s*$/m' => '== \1\2 == '."\n".' [[\1]] \2',
            '/^=====.*\[\[(.*)\]\](.*)=====\s*$/m'   => '== \1\2 == '."\n".' [[\1]] \2',
            '/^====.*\[\[(.*)\]\](.*)====\s*$/m'     => '=== \1\2 === '."\n".' [[\1]] \2',
            '/^===.*\[\[(.*)\]\](.*)===\s*$/m'       => '==== \1\2 ==== '."\n".' [[\1]] \2',
            '/^==.*\[\[(.*)\]\](.*)==\s*$/m'         => '===== \1\2 ===== '."\n".' [[\1]] \2',
            '/^=.*\[\[(.*)\]\](.*)=\s*$/m'           => '====== \1\2 ====== '."\n".' [[\1]] \2',
            '/^======(.+)======\s*$/m' => '==\1==',
            '/^=====(.+)=====\s*$/m'   => '==\1==',
            '/^====(.+)====\s*$/m'     => '===\1===',
            '/^===(.+)===\s*$/m'       => '====\1====',
            '/^==(.+)==\s*$/m'         => '=====\1=====',
            '/^=(.+)=\s*$/m'           => '======\1======'

        );

        // Insert a unique string to the replacement so that it won't be
        // caught in a search later.
        // @todo A lambda function can be used when PHP 5.4 is required.
        array_walk(
            $patterns,
            create_function(
                '&$v, $k',
                '$v = "' . $this->placeholder . '" . $v;'
            )
        );

        $convertedRecord = preg_replace(
            array_keys($patterns),
            array_values($patterns),
            $record
        );

        if(strpos($record,"Hitachi") !== false){
          // dump and exit?
          // var_dump($convertedRecord);
//           exit();
        }

        // No headings were found.
        if ($convertedRecord == $record) {
            return $record;
        }


        // Strip out the unique strings.
        return str_replace($this->placeholder, '', $convertedRecord);
    }
}
