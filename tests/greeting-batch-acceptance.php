<?php
require '/code/vendor/autoload.php';
if(getenv('AB_GREETING_CANDIDATE'))require getenv('AB_GREETING_CANDIDATE').'/Runtime.php';
$app=require '/code/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\AbogadosBot\{Runtime,Policy};use Illuminate\Support\Facades\Crypt;
$tests=[];
function checkGreeting(string $name,bool $ok):void{global $tests;$tests[$name]=$ok;if(!$ok){echo json_encode(['failed'=>$name,'passed'=>count(array_filter($tests)),'productionMessages'=>0]).PHP_EOL;exit(1);}}
checkGreeting('received_purpose_requires_greeting_batch_guard',method_exists(Runtime::class,'deferGreetingBatch'));
$root='/code/.codex-work/greeting-batch-20261006-1855/isolated-'.bin2hex(random_bytes(6));mkdir($root,0700,true);
file_put_contents($root.'/runtime.json',json_encode(['owner'=>Policy::OWNER,'instance'=>'abogados','enabled'=>true,'activated_at'=>time()-20000]));
$bot=new Runtime($root);$phone='573000000011@s.whatsapp.net';$now=time()-50;
function greetEvent(Runtime $b,string $id,string $chat,string $text,int $at,array $extra=[],string $state='QUEUED'):array{
    $key=array_merge(['id'=>$id,'remoteJid'=>$chat,'fromMe'=>false],$extra['key']??[]);
    $data=['key'=>$key,'messageType'=>$extra['kind']??'conversation','message'=>$extra['message']??['conversation'=>$text]];
    $b->query('INSERT INTO events VALUES(?,?,?,?,?,?,?,?)',[$id,$chat,$key['remoteJid'],Crypt::encryptString(json_encode($data)),$at,time()-20,$state,'']);
    return $b->query('SELECT * FROM events WHERE id=?',[$id])->fetch();
}
$greet=greetEvent($bot,'SYNTHETIC_GREETING',$phone,'Hola buenas tardes',$now);
$purpose=greetEvent($bot,'SYNTHETIC_PURPOSE',$phone,'Quiero consultar algo para poder trabajar en conjunto un caso',$now+20);
$bot->query("INSERT INTO chats VALUES(?,0,1,'','',?)",[$phone,time()]);
$process=new ReflectionMethod(Runtime::class,'processEvent');$defer=new ReflectionMethod(Runtime::class,'deferGreetingBatch');
$process->invoke($bot,$greet);
checkGreeting('actual_handler_does_not_reask_received_need',$bot->query('SELECT reason FROM events WHERE id=?',[$greet['id']])->fetchColumn()==='GREETING_BATCH_DEFERRED');
checkGreeting('actual_handler_leaves_purpose_queued',$bot->query('SELECT state FROM events WHERE id=?',[$purpose['id']])->fetchColumn()==='QUEUED');
checkGreeting('no_model_or_message_needed_to_skip_greeting',(int)$bot->query('SELECT COUNT(*) FROM outbox')->fetchColumn()===0);
checkGreeting('no_ticket_or_hold_created_for_skipped_greeting',(int)$bot->query('SELECT COUNT(*) FROM tickets')->fetchColumn()===0 && (int)$bot->query('SELECT hold FROM chats WHERE jid=?',[$phone])->fetchColumn()===0);
$cases=[
 'lone'=>['later'=>null],
 'other_chat'=>['later'=>'Quiero consultar','chat'=>'573000000012@s.whatsapp.net'],
 'same_second'=>['later'=>'Quiero consultar','delta'=>0],
 'outside_batch'=>['later'=>'Quiero consultar','delta'=>91],
 'forwarded_purpose'=>['later'=>'Quiero consultar','extra'=>['kind'=>'extendedTextMessage','message'=>['extendedTextMessage'=>['text'=>'Quiero consultar','contextInfo'=>['isForwarded'=>true]]]]],
 'native_status'=>['later'=>'Quiero consultar','extra'=>['key'=>['remoteJid'=>'status@broadcast','remoteJidAlt'=>$phone]]],
 'staff_message'=>['later'=>'Quiero consultar','extra'=>['key'=>['fromMe'=>true]]],
 'unheard_audio'=>['later'=>'Quiero consultar','extra'=>['kind'=>'audioMessage','message'=>['audioMessage'=>[]]]],
 'unread_image'=>['later'=>'Quiero consultar','extra'=>['kind'=>'imageMessage','message'=>['imageMessage'=>['caption'=>'Quiero consultar']]]],
 'done_purpose'=>['later'=>'Quiero consultar','state'=>'DONE'],
 'not_mature'=>['later'=>'Quiero consultar','seen'=>time()],
 'courtesy'=>['later'=>'Gracias'],
];
foreach($cases as $name=>$c){
 $bot->query('DELETE FROM events');$e=greetEvent($bot,'SYNTHETIC_G_'.$name,$phone,'Hola buenas tardes',$now);
 if($c['later']!==null){$n=greetEvent($bot,'SYNTHETIC_P_'.$name,$c['chat']??$phone,$c['later'],$now+($c['delta']??20),$c['extra']??[],$c['state']??'QUEUED');if(isset($c['seen']))$bot->query('UPDATE events SET seen=? WHERE id=?',[$c['seen'],$n['id']]);}
 checkGreeting('preserves_'.$name,!$defer->invoke($bot,$e,'Hola buenas tardes',false));
}
$bot->query('DELETE FROM events');$e=greetEvent($bot,'SYNTHETIC_G_FORWARDED',$phone,'Hola buenas tardes',$now);greetEvent($bot,'SYNTHETIC_P_FORWARDED',$phone,'Necesito una consulta',$now+20);
checkGreeting('forwarded_greeting_not_inherited',!$defer->invoke($bot,$e,'Hola buenas tardes',true));
checkGreeting('greeting_with_request_is_not_discarded',!$defer->invoke($bot,$e,'Hola buenas tardes, necesito consultar',false));
$bot->query('DELETE FROM events');$e=greetEvent($bot,'SYNTHETIC_G_CHIEF',Policy::SANDRA,'Hola',$now);greetEvent($bot,'SYNTHETIC_P_CHIEF',Policy::SANDRA,'Quiero consultar',$now+20);
checkGreeting('chief_authority_not_changed',!$defer->invoke($bot,$e,'Hola',false));
foreach(['held','review']as $type){
 $bot->query('DELETE FROM events');$e=greetEvent($bot,'SYNTHETIC_G_'.$type,$phone,'Hola',$now);greetEvent($bot,'SYNTHETIC_P_'.$type,$phone,'Necesito consultar',$now+20);
 $bot->query('UPDATE chats SET hold=?,phase=? WHERE jid=?',[$type==='held'?1:0,$type==='review'?'review':'',$phone]);$process->invoke($bot,$e);
 checkGreeting('human_control_precedes_batch_'.$type,$bot->query('SELECT state FROM events WHERE id=?',[$e['id']])->fetchColumn()===($type==='held'?'OBSERVED_HUMAN':'OBSERVED_REVIEW'));
}
checkGreeting('runtime_declares_installed_guard',$bot->healthSummary()['greetingBatchGuard']==='own-private-queued-purpose-before-greeting-question-v1');
checkGreeting('isolated_check_did_not_queue_any_sends',(int)$bot->query('SELECT COUNT(*) FROM outbox')->fetchColumn()===0);
echo json_encode(['passed'=>count($tests),'failed'=>0,'tests'=>$tests,'isolatedRoot'=>$root,'productionMessages'=>0,'businessWrites'=>0,'modelCalls'=>0],JSON_UNESCAPED_UNICODE).PHP_EOL;
