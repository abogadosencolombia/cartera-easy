<?php
// Run from the application root with a reviewed JSON source bundle argument.
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);chdir($root);
$bundle=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);
$allow=['app/Services/AbogadosBot/Policy.php','app/Services/AbogadosBot/Runtime.php','app/Services/AbogadosBot/GoogleSources.php','app/Http/Controllers/Api/AbogadosBotController.php','app/Console/Commands/RunAbogadosBot.php','routes/abogados_bot_api.php','routes/abogados_bot_web.php','routes/abogados_bot_console.php','resources/views/abogados-bot/dashboard.blade.php','tests/abogados-bot-acceptance.php','tests/abogados-google-acceptance.php'];
$private=$root.'/storage/app/private/abogados-bot';
$backup=$private.'/release-'.gmdate('Ymd-His');mkdir($backup,0700,true);
foreach($bundle as $path=>$source){
    if(!in_array($path,$allow,true))throw new RuntimeException('PATH_NOT_ALLOWED');
    if(file_exists($path)){if(!is_dir(dirname($backup.'/'.$path)))mkdir(dirname($backup.'/'.$path),0700,true);copy($path,$backup.'/'.$path);}
    if(!is_dir(dirname($path)))mkdir(dirname($path),0755,true);
    file_put_contents($path.'.pending',$source,LOCK_EX);
    exec(PHP_BINARY.' -l '.escapeshellarg($path.'.pending').' 2>&1',$lint,$code);
    if($code!==0)throw new RuntimeException('SYNTAX_'.$path);
    rename($path.'.pending',$path);chmod($path,0644);if(function_exists('opcache_invalidate'))opcache_invalidate($path,true);
}
foreach(['api','web','console'] as $kind){
    $path='routes/'.$kind.'.php';$current=file_get_contents($path);$include="require __DIR__.'/abogados_bot_".$kind.".php';";
    if(!str_contains($current,$include)){
        copy($path,$backup.'/routes-'.$kind.'.php');
        file_put_contents($path,$current."\n// Isolated administrative bot for Abogados.\n".$include."\n",LOCK_EX);
        exec(PHP_BINARY.' -l '.escapeshellarg($path).' 2>&1',$lint,$code);
        if($code!==0){file_put_contents($path,$current,LOCK_EX);throw new RuntimeException('ROUTE_SYNTAX');}
    }
}
if(!file_exists($private.'/webhook-token')){file_put_contents($private.'/webhook-token',bin2hex(random_bytes(32)),LOCK_EX);chmod($private.'/webhook-token',0600);}
if(!file_exists($private.'/runtime.json'))file_put_contents($private.'/runtime.json',json_encode(['owner'=>'573152819233@s.whatsapp.net','instance'=>'abogados','enabled'=>false,'activated_at'=>time(),'version'=>'2026-09-30.1'],JSON_PRETTY_PRINT),LOCK_EX);
chgrp($private,'www-data');chmod($private,02770);
foreach(['runtime.json','webhook-token','openai-provider.json','openai-key-20260930.enc'] as $file){
    if(file_exists($private.'/'.$file)){chgrp($private.'/'.$file,'www-data');chmod($private.'/'.$file,0640);}
}
foreach(['runtime.sqlite','runtime.sqlite-wal','runtime.sqlite-shm','worker.lock'] as $file){
    if(file_exists($private.'/'.$file)){chown($private.'/'.$file,'www-data');chgrp($private.'/'.$file,'www-data');chmod($private.'/'.$file,0660);}
}
echo json_encode(['installed'=>array_keys($bundle),'backup'=>$backup,'enabled'=>json_decode(file_get_contents($private.'/runtime.json'),true)['enabled']]).PHP_EOL;
