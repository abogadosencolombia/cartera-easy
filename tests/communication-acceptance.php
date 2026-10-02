<?php
// Isolated SQLite and synthetic messages. Provider configuration is unavailable during flush.
require '/code/vendor/autoload.php';
$app=require '/code/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\AbogadosBot\Communication;
use App\Services\AbogadosBot\Policy;
use App\Services\AbogadosBot\Runtime;
use Illuminate\Support\Facades\Crypt;
$checks=[];
$check=function($name,$ok)use(&$checks){$checks[$name]=(bool)$ok;if(!$ok)throw new RuntimeException('TEST_FAILED_'.$name);};
$customer='573009990001@s.whatsapp.net';
$unsafe=['Voy a verificar con Diego o Sandra.','Estoy verificando el horario con Sandra.','Ya le pregunté al equipo.','Consultaré con el profesional.','Estoy transcribiendo el audio.','Transcribo tu audio.','Estamos procesando el archivo.','Usaremos un webhook para responderte.'];
foreach($unsafe as $i=>$text)$check('reject_internal_detail_'.$i,Communication::issue($customer,$text)!==null);
foreach(['¿Para qué día necesitas el servicio?','Gracias por tu audio. ¿Me confirmas la ciudad?','No pude escuchar bien tu audio. ¿Me escribes el dato?','Sandra, ¿para qué día necesitas la cita?','Tu solicitud quedó registrada y está pendiente de orientación profesional.'] as $i=>$text)$check('allow_pertinent_reply_'.$i,Communication::issue($customer,$text)===null);
$check('chief_internal_context_allowed',Communication::issue(Policy::SANDRA,'Voy a verificar con Diego o Sandra.')===null);
$check('verified_group_internal_context_allowed',Communication::issue(Policy::GROUP,'Estoy transcribiendo el audio.',true)===null);
$check('group_not_public_bypass',Communication::issue(Policy::GROUP,'Estoy transcribiendo el audio.',false)!==null);
foreach(['case_status','payment','payment_terms','third_party','legal','fees'] as $intent){
 $plan=Policy::plan($intent,'',false,['service'=>true,'city'=>true],'Mensaje sintético');
 $check('template_private_'.$intent,Communication::issue($customer,$plan['reply'])===null);
}
$root=storage_path('app/private/abogados-bot/test-communication-'.bin2hex(random_bytes(6)));mkdir($root,0700,true);
file_put_contents($root.'/runtime.json',json_encode(['owner'=>Policy::OWNER,'instance'=>'abogados','enabled'=>true,'activated_at'=>time()-120]));
file_put_contents($root.'/webhook-token','synthetic-test-secret');
$bot=new Runtime($root);
$thrown=false;try{$bot->queue('synthetic-unsafe',$customer,$unsafe[0]);}catch(RuntimeException $e){$thrown=$e->getMessage()==='EXTERNAL_COMMUNICATION_REVIEW';}
$check('unsafe_rejected_before_queue',$thrown && (int)$bot->query('SELECT COUNT(*) FROM outbox')->fetchColumn()===0);
$bot->queue('synthetic-chief',Policy::SANDRA,'Sandra, ¿cuánto dura el refuerzo de este servicio?',true);
$bot->queue('synthetic-chief',Policy::SANDRA,'Sandra, ¿cuánto dura el refuerzo de este servicio?',true);
$check('internal_question_deduplicated',(int)$bot->query('SELECT COUNT(*) FROM outbox')->fetchColumn()===1);
$row=$bot->query('SELECT body FROM outbox')->fetchColumn();
$check('body_encrypted',!str_contains($row,'refuerzo'));
$bot->query("UPDATE outbox SET state='READ'");
$bot->query("INSERT INTO outbox VALUES(?,?,?,?,?,?,?,?,?)",['synthetic-legacy',$customer,Crypt::encryptString($unsafe[4]),hash('sha256',$unsafe[4]),'READY',null,time(),time(),0]);
// Reset to an empty base directory: even a failed privacy guard cannot access real provider keys.
$previousBase=$app->basePath();$app->setBasePath($root);
try{$bot->flush();}finally{$app->setBasePath($previousBase);}
$check('legacy_message_review_before_provider',$bot->query('SELECT state FROM outbox WHERE id=?',['synthetic-legacy'])->fetchColumn()==='COMMUNICATION_REVIEW');
$check('legacy_message_no_uncertain_delivery',(int)$bot->query("SELECT COUNT(*) FROM outbox WHERE state IN ('SENDING','UNCERTAIN','ACCEPTED')")->fetchColumn()===0);
$check('legacy_message_preserves_human_review',(int)$bot->query('SELECT hold FROM chats WHERE jid=?',[$customer])->fetchColumn()===1);
echo json_encode(['suite'=>'communication','checks'=>count($checks),'passed'=>count(array_filter($checks)),'externalSends'=>0,'businessWrites'=>0],JSON_UNESCAPED_UNICODE)."\n";
