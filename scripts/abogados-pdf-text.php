<?php
// Separate process: bounded runtime and memory, no model and no network requests.
ini_set('display_errors','0');
$root=realpath('/code/storage/app/private/abogados-bot');
$path=isset($argv[1])?realpath($argv[1]):false;
if(!$root||!$path||!str_starts_with($path,$root.DIRECTORY_SEPARATOR)||!str_starts_with(basename($path),'source-')||filesize($path)>5*1024*1024)exit(2);
require $root.'/pdf-reader/vendor/autoload.php';
try{
    $pdf=(new Smalot\PdfParser\Parser())->parseFile($path);$pages=$pdf->getPages();$chunks=[];
    foreach(array_slice($pages,0,3) as $page)$chunks[]=mb_substr($page->getText(),0,30000);
    echo json_encode(['text'=>implode("\n",$chunks),'pages_extracted'=>min(3,count($pages)),'pages_total'=>count($pages)],JSON_INVALID_UTF8_SUBSTITUTE);
}catch(Throwable $ex){exit(3);}
