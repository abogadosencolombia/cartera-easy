<?php
require '/code/vendor/autoload.php';
if(getenv('AB_NATIVE_CANDIDATE')){
    require getenv('AB_NATIVE_CANDIDATE').'/Policy.php';
    require getenv('AB_NATIVE_CANDIDATE').'/Runtime.php';
}
$app=require '/code/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\AbogadosBot\{Policy,Runtime};
use Illuminate\Support\Facades\Crypt;
$tests=[];
function checkNative(string $name,bool $ok):void{global $tests;$tests[$name]=$ok;if(!$ok){fwrite(STDERR,'TEST_FAILED_'.$name.PHP_EOL);exit(1);}}
$root='/code/.codex-work/native-private-20261006-0023/isolated-'.bin2hex(random_bytes(6));mkdir($root,0700,true);
file_put_contents($root.'/runtime.json',json_encode(['owner'=>Policy::OWNER,'instance'=>'abogados','enabled'=>true,'activated_at'=>time()-20000]));
$bot=new Runtime($root);
$phone='573000000001@s.whatsapp.net';
function fixtureNative(string $id,string $native,?string $alt=null,bool $fromMe=false):array{
    return ['instance'=>'abogados','event'=>'messages.upsert','data'=>['key'=>['id'=>$id,'remoteJid'=>$native,'remoteJidAlt'=>$alt,'fromMe'=>$fromMe],'messageTimestamp'=>time(),'messageType'=>'videoMessage','message'=>['videoMessage'=>['caption'=>'Synthetic status','url'=>'https://example.invalid/not-fetched','mediaKey'=>'synthetic-not-used']]]];
}
$first=fixtureNative('SYNTHETIC_STATUS_001','status@broadcast',$phone);
checkNative('reproduced_native_status_is_ignored',($bot->receive($first)['ignored']??'')==='non_direct_native_origin');
foreach(['status@broadcast','123@broadcast','123@newsletter','123@g.us',' STATUS@BROADCAST '] as $i=>$jid){
    foreach([$phone,Policy::SANDRA] as $j=>$alt){
        $reply=$bot->receive(fixtureNative('SYNTHETIC_NON_DIRECT_'.$i.'_'.$j,$jid,$alt,(bool)$j));
        checkNative('native_origin_ignored_'.$i.'_'.$j,isset($reply['ignored']));
    }
}
checkNative('ignored_native_origins_do_not_create_events',(int)$bot->query('SELECT COUNT(*) FROM events')->fetchColumn()===0);
checkNative('status_echo_never_creates_human_hold',(int)$bot->query('SELECT COUNT(*) FROM chats')->fetchColumn()===0);
checkNative('native_guard_cannot_map_status_to_sandra',Policy::jid(['remoteJid'=>'status@broadcast','remoteJidAlt'=>Policy::SANDRA])===null);
checkNative('newsletter_cannot_map_private',Policy::jid(['remoteJid'=>'123@newsletter','remoteJidAlt'=>$phone])===null);
checkNative('legacy_group_identity_preserved',Policy::jid(['remoteJid'=>Policy::GROUP,'remoteJidAlt'=>$phone])===Policy::GROUP);
checkNative('non_direct_alternate_cannot_override_private',isset($bot->receive(fixtureNative('SYNTHETIC_ALT_STATUS',$phone,'status@broadcast'))['ignored']));
$private=fixtureNative('SYNTHETIC_PRIVATE_001',$phone);
$private['data']['message']['videoMessage']['contextInfo']=['isForwarded'=>true,'remoteJid'=>'status@broadcast'];
checkNative('private_forwarded_video_keeps_private_scope',!empty($bot->receive($private)['accepted']));
checkNative('private_source_deduplicates',!empty($bot->receive($private)['duplicate']));
$lid=fixtureNative('SYNTHETIC_LID_001','123456789012@lid',$phone);
checkNative('native_lid_private_alternate_is_allowed',!empty($bot->receive($lid)['accepted']));
$batch=['instance'=>'abogados','event'=>'messages.upsert','data'=>[fixtureNative('SYNTHETIC_BATCH_BAD','status@broadcast',$phone)['data'],fixtureNative('SYNTHETIC_BATCH_PRIVATE',$phone)['data']]];
$bot->receive($batch);
checkNative('mixed_batch_preserves_private_only',(int)$bot->query('SELECT COUNT(*) FROM events')->fetchColumn()===3);
checkNative('private_video_does_not_load_original',!str_contains(Crypt::decryptString($bot->query('SELECT body FROM events WHERE id=?',['SYNTHETIC_PRIVATE_001'])->fetchColumn()),'not-fetched'));

function insertNative(Runtime $bot,string $id,string $raw,string $phone,int $at,string $state='QUEUED'):array{
    $data=fixtureNative($id,$raw,$phone)['data'];
    $body=Crypt::encryptString(json_encode($data));
    $bot->query('INSERT INTO events VALUES(?,?,?,?,?,?,?,?)',[$id,$phone,$raw,$body,$at,time(),$state,'']);
    return $bot->query('SELECT * FROM events WHERE id=?',[$id])->fetch();
}
$method=new ReflectionMethod(Runtime::class,'processEvent');
foreach(['videoMessage','audioMessage'] as $i=>$kind){
    $id='SYNTHETIC_QUEUED_NON_DIRECT_'.$i;
    $event=insertNative($bot,$id,'status@broadcast',$phone,time()-9000);
    if($kind==='audioMessage'){$d=json_decode(Crypt::decryptString($event['body']),true);$d['messageType']=$kind;$d['message']=[$kind=>[]];$event['body']=Crypt::encryptString(json_encode($d));$bot->query('UPDATE events SET body=? WHERE id=?',[$event['body'],$id]);}
    $bot->queue($id.'|reply',$phone,'Gracias, el archivo quedó recibido.');
    $method->invoke($bot,$event);
    checkNative('queued_native_guard_before_stale_media_'.$kind,$bot->query('SELECT state FROM events WHERE id=?',[$id])->fetchColumn()==='OBSERVED_NON_DIRECT');
    checkNative('queued_reply_suppressed_'.$kind,$bot->query('SELECT state FROM outbox WHERE id=?',[$id.'|reply'])->fetchColumn()==='SUPPRESSED_NON_DIRECT');
}
checkNative('no_history_query_or_hold_for_queued_status',(int)$bot->query('SELECT COUNT(*) FROM chats')->fetchColumn()===0);
checkNative('no_ticket_or_transcription_for_queued_status',(int)$bot->query('SELECT COUNT(*) FROM tickets')->fetchColumn()===0);
$event=insertNative($bot,'SYNTHETIC_READY_STATUS','status@broadcast',$phone,time(),'DONE');
$bot->queue($event['id'].'|reply',$phone,'Gracias, el archivo quedó recibido.');
$ticket='AB-A123456789';$bot->query('INSERT INTO tickets VALUES(?,?,?,?,?,?)',[$ticket,$phone,$event['id'],'Synthetic review','OPEN',time()]);
$bot->queue($ticket.'|'.Policy::SANDRA,Policy::SANDRA,'Hay una solicitud pendiente de revisión.',true);
$bot->flush();
checkNative('flush_suppresses_status_reply_without_network',$bot->query('SELECT state FROM outbox WHERE id=?',[$event['id'].'|reply'])->fetchColumn()==='SUPPRESSED_NON_DIRECT');
checkNative('flush_suppresses_status_ticket_notification',$bot->query('SELECT state FROM outbox WHERE id=?',[$ticket.'|'.Policy::SANDRA])->fetchColumn()==='SUPPRESSED_NON_DIRECT');
checkNative('flush_has_no_sending_uncertain_or_accepted',(int)$bot->query("SELECT COUNT(*) FROM outbox WHERE state IN ('SENDING','ACCEPTED','UNCERTAIN')")->fetchColumn()===0);
$sourceCheck=new ReflectionMethod(Runtime::class,'nonDirectOutbox');
checkNative('nightly_report_source_is_not_suppressed',!$sourceCheck->invoke($bot,['id'=>'daily-mail-review|2026-10-05|'.Policy::SANDRA,'internal'=>1]));
checkNative('judicial_mail_source_is_not_suppressed',!$sourceCheck->invoke($bot,['id'=>'judicial-mail|synthetic-mail|'.Policy::SANDRA,'internal'=>1]));
checkNative('private_native_reply_is_not_suppressed',!$sourceCheck->invoke($bot,['id'=>'SYNTHETIC_PRIVATE_001|reply','internal'=>0]));

$bot->queue('SYNTHETIC_PRIVATE_001|reply',$phone,'Gracias, el archivo quedó recibido.');
$bot->query("UPDATE outbox SET state='READ' WHERE id=?",['SYNTHETIC_PRIVATE_001|reply']);
$bot->query("UPDATE outbox SET state='READ' WHERE id=?",[$event['id'].'|reply']);
checkNative('customer_metric_excludes_historical_status_reply',$bot->answeredPrivateChats()===1);
checkNative('historical_delivered_status_is_preserved',$bot->query('SELECT state FROM outbox WHERE id=?',[$event['id'].'|reply'])->fetchColumn()==='READ');
checkNative('health_declares_installed_native_guard',$bot->healthSummary()['privateChatGuard']===Policy::PRIVATE_CHAT_GUARD);

$legacy='573000000002@s.whatsapp.net';$legacyEvent=insertNative($bot,'SYNTHETIC_LEGACY_STATUS','status@broadcast',$legacy,time()-300,'DONE');
$bot->query("UPDATE events SET reason='REVIEW' WHERE id=?",[$legacyEvent['id']]);
$bot->query('INSERT INTO tickets VALUES(?,?,?,?,?,?)',['AB-B123456789',$legacy,$legacyEvent['id'],'Archivo recibido: revisión de contenido','CONTENT_UNREVIEWED',time()]);
$bot->query("INSERT INTO chats VALUES(?,0,1,'review',?,?)",[$legacy,'Gracias, el archivo quedó recibido.',time()]);
$repair=new ReflectionMethod(Runtime::class,'clearStatusOnlyReview');
$chat=$bot->query('SELECT * FROM chats WHERE jid=?',[$legacy])->fetch();
$bot->query('UPDATE chats SET hold=1 WHERE jid=?',[$legacy]);$held=$chat;$held['hold']=1;
checkNative('status_only_repair_never_lifts_human_hold',!$repair->invoke($bot,$held));
checkNative('held_chat_remains_held',(int)$bot->query('SELECT hold FROM chats WHERE jid=?',[$legacy])->fetchColumn()===1);
$bot->query('UPDATE chats SET hold=0 WHERE jid=?',[$legacy]);
checkNative('status_only_review_phase_can_be_corrected',$repair->invoke($bot,$chat));
checkNative('phase_correction_keeps_original_status_ticket',(int)$bot->query('SELECT COUNT(*) FROM tickets WHERE event=?',[$legacyEvent['id']])->fetchColumn()===1);
$bot->query("UPDATE chats SET phase='review',last_reply=? WHERE jid=?",['Gracias, el archivo quedó recibido.',$legacy]);
$legit=insertNative($bot,'SYNTHETIC_LEGIT_REVIEW',$legacy,$legacy,time(),'DONE');
$chat=$bot->query('SELECT * FROM chats WHERE jid=?',[$legacy])->fetch();
checkNative('any_legitimate_review_preserves_phase',!$repair->invoke($bot,$chat));
$bot->query('DELETE FROM events WHERE id=?',[$legit['id']]);
$bot->query('INSERT INTO tickets VALUES(?,?,?,?,?,?)',['AB-C123456789',$legacy,'missing-source','Synthetic review','OPEN',time()]);
checkNative('missing_ticket_source_requires_review',!$repair->invoke($bot,$chat));
echo json_encode(['passed'=>count($tests),'failed'=>0,'tests'=>$tests,'isolatedRoot'=>$root,'productionMessages'=>0,'businessWrites'=>0],JSON_UNESCAPED_UNICODE).PHP_EOL;
