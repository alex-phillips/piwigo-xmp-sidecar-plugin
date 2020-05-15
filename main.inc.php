<?php

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

// Define the path to our plugin.
define('XMPSIDECAR_DIR', basename(dirname(__FILE__)));
define('XMPSIDECAR_PATH', PHPWG_PLUGINS_PATH . XMPSIDECAR_DIR . '/');

add_event_handler(
    'format_exif_data',
    'import_xmp_sidecar'
);

/**
 * Process XMP sidecar files and merge the data into the EXIF data.
 *
 * Function logic was pulled from https://surniaulula.com/2013/apps/wordpress/read-adobe-xmp-xml-in-php/
 * and took some inspiration from http://piwigo.org/forum/viewtopic.php?id=25709
 *
 * @param array $exif
 * @param string $filename
 * @return void
 */
function import_xmp_sidecar($exif, $filename)
{
    $sidecarFilename = "$filename.xmp";
    if (!file_exists($sidecarFilename)) {
        return $exif;
    }

    $xmpRaw = file_get_contents($sidecarFilename);
    $xmpData = array();
    foreach ([
        'Creator Email'         => '<Iptc4xmpCore:CreatorContactInfo[^>]+?CiEmailWork="([^"]*)"',
        'Owner Name'            => '<rdf:Description[^>]+?aux:OwnerName="([^"]*)"',
        'Creation Date'         => '<rdf:Description[^>]+?xmp:CreateDate="([^"]*)"',
        'Modification Date'     => '<rdf:Description[^>]+?xmp:ModifyDate="([^"]*)"',
        'Label'                 => '<rdf:Description[^>]+?xmp:Label="([^"]*)"',
        'Credit'                => '<rdf:Description[^>]+?photoshop:Credit="([^"]*)"',
        'Source'                => '<rdf:Description[^>]+?photoshop:Source="([^"]*)"',
        'Headline'              => '<rdf:Description[^>]+?photoshop:Headline="([^"]*)"',
        'City'                  => '<rdf:Description[^>]+?photoshop:City="([^"]*)"',
        'State'                 => '<rdf:Description[^>]+?photoshop:State="([^"]*)"',
        'Country'               => '<rdf:Description[^>]+?photoshop:Country="([^"]*)"',
        'Country Code'          => '<rdf:Description[^>]+?Iptc4xmpCore:CountryCode="([^"]*)"',
        'Location'              => '<rdf:Description[^>]+?Iptc4xmpCore:Location="([^"]*)"',
        'Title'                 => '<dc:title>\s*<rdf:Alt>\s*(.*?)\s*<\/rdf:Alt>\s*<\/dc:title>',
        'Description'           => '<dc:description>\s*<rdf:Alt>\s*(.*?)\s*<\/rdf:Alt>\s*<\/dc:description>',
        'Creator'               => '<dc:creator>\s*<rdf:Seq>\s*(.*?)\s*<\/rdf:Seq>\s*<\/dc:creator>',
        'Keywords'              => '<dc:subject>\s*<rdf:Bag>\s*(.*?)\s*<\/rdf:Bag>\s*<\/dc:subject>',
        'Hierarchical Keywords' => '<lr:hierarchicalSubject>\s*<rdf:Bag>\s*(.*?)\s*<\/rdf:Bag>\s*<\/lr:hierarchicalSubject>'
     ] as $key => $regex) {
        $key = "xmp_" . str_replace(' ', '_', strtolower($key));

        // get a single text string
        $xmpData[$key] = preg_match("/$regex/is", $xmpRaw, $match) ? $match[1] : '';

        // if string contains a list, then re-assign the variable as an array with the list elements
        $xmpData[$key] = preg_match_all("/<rdf:li[^>]*>([^>]*)<\/rdf:li>/is", $xmpData[$key], $match) ? $match[1] : $xmpData[$key];

        // hierarchical keywords need to be split into a third dimension
        if (!empty($xmpData[$key]) && $key == 'Hierarchical Keywords') {
            foreach ($xmpData[$key] as $li => $val) $xmpData[$key][$li] = explode('|', $val);
            unset($li, $val);
        }
    }

    $exif = array_merge($exif, $xmpData);

    return $exif;
}
