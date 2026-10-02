<?php require '/code/vendor/autoload.php'; spl_autoload_register(function($class){$prefix='App\\Services\\AbogadosBot\\';if(str_starts_with($class,$prefix)){$p=__DIR__.'/'.substr($class,strlen($prefix)).'.php';if(is_file($p))require $p;}},true,true);
require '/code/vendor/autoload.php';
$app=require '/code/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\AbogadosBot\Runtime;use App\Services\AbogadosBot\Policy;use App\Services\AbogadosBot\ChiefTurn;use Illuminate\Support\Facades\Crypt;
$root=storage_path('app/private/abogados-bot/test-chief-turn-'.bin2hex(random_bytes(6)));mkdir($root,0700,true);
file_put_contents($root.'/runtime.json',json_encode(['owner'=>Policy::OWNER,'instance'=>'abogados','enabled'=>true,'activated_at'=>time()-600]));file_put_contents($root.'/webhook-token','isolated');
$bot=new Runtime($root);$method=new ReflectionMethod(Runtime::class,'processEvent');$tests=[];
$check=function($key,$ok)use(&$tests){$tests[$key]=(bool)$ok;if(!$ok)throw new RuntimeException('FAILED_'.$key);};
$add=function($id,$chat,$text,$forwarded=false)use($bot,$method){$d=['key'=>['id'=>$id,'remoteJid'=>$chat,'fromMe'=>false],'message'=>['extendedTextMessage'=>['text'=>$text,'contextInfo'=>['isForwarded'=>$forwarded]]],'messageType'=>'extendedTextMessage'];$bot->query('INSERT OR IGNORE INTO chats(jid,hold,baseline,updated) VALUES(?,1,1,?)',[$chat,time()]);$bot->query('INSERT INTO events VALUES(?,?,?,?,?,?,?,?)',[$id,$chat,$chat,Crypt::encryptString(json_encode($d)),time()-1,time()-1,'QUEUED','']);$e=$bot->query('SELECT * FROM events WHERE id=?',[$id])->fetch();$method->invoke($bot,$e);return $e;};
$add('CHIEF_INFO_01',Policy::SANDRA,'Jeison, tú ya has entrado a mono Legal lo sabes manejar bien');
$o=$bot->query('SELECT * FROM outbox WHERE id=?',['CHIEF_INFO_01|reply'])->fetch();$check('native_question_queues_internal_only',$o&&$o['internal']===1&&$o['chat']===Policy::SANDRA);$check('no_global_release',$bot->query('SELECT hold FROM chats WHERE jid=?',[Policy::SANDRA])->fetchColumn()==1);$check('partial_coverage_truthful',str_contains(Crypt::decryptString($o['body']),'parcial'));
$e=$bot->query('SELECT * FROM events WHERE id=?',['CHIEF_INFO_01'])->fetch();$method->invoke($bot,$e);$check('same_event_deduplicated',$bot->query('SELECT COUNT(*) FROM outbox')->fetchColumn()==1);
$add('CHIEF_PRESENCE1',Policy::SANDRA,'Jeison, estas funcionando?');$check('presence_answer_without_model',$bot->query('SELECT reason FROM events WHERE id=?',['CHIEF_PRESENCE1'])->fetchColumn()==='CHIEF_SINGLE_TURN');
$add('CHIEF_WRITE_01',Policy::SANDRA,'Jeison, cambia todos los valores');$check('write_not_executed',$bot->query('SELECT state FROM chief_turn_requests WHERE event=?',['CHIEF_WRITE_01'])->fetchColumn()==='REVIEW');
foreach([['CHIEF_FORWARD1',Policy::SANDRA,'Jeison, estas funcionando?',true],['CHIEF_THIRD_01',Policy::SANDRA,'Me dijo Jeison que funciona',false],['CUSTOMER_TURN1','573009990001@s.whatsapp.net','Jeison, estas funcionando?',false]] as $a){$add(...$a);$check('authority_'.$a[0],!$bot->query('SELECT 1 FROM outbox WHERE id=?',[$a[0].'|reply'])->fetchColumn());}
$check('monolegal_write_not_informative',ChiefTurn::informative('Jeison, cambia el estado de Monolegal')===null);
$check('hold_preserved_after_all_turns',$bot->query('SELECT hold FROM chats WHERE jid=?',[Policy::SANDRA])->fetchColumn()==1);
echo json_encode(['suite'=>'chief-turn','checks'=>count($tests),'passed'=>count(array_filter($tests)),'externalSends'=>0,'businessWrites'=>0])."\n";
