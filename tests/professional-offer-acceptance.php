<?php
// Isolated administrative routing; no WhatsApp sends or business database writes.
require '/code/vendor/autoload.php';
$app=require '/code/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\AbogadosBot\{Policy,Runtime};
use Illuminate\Support\Facades\Crypt;
$root=storage_path('app/private/abogados-bot/test-professional-offer-'.bin2hex(random_bytes(6)));
mkdir($root,0700,true);
file_put_contents($root.'/runtime.json',json_encode(['owner'=>Policy::OWNER,'instance'=>'abogados','enabled'=>false,'activated_at'=>time()-120]));
file_put_contents($root.'/webhook-token','synthetic-test-secret');
$bot=new Runtime($root);$tests=[];
$check=function($name,$ok)use(&$tests){$tests[$name]=(bool)$ok;if(!$ok)throw new RuntimeException('TEST_FAILED_'.$name);};
$message='Hola Buenas Doctora, quisiera prestar mis servicios de manea gratuita a la firma.';
$known=['service'=>false,'city'=>false,'date_time'=>false,'modality'=>false];
$old=Policy::plan('new_service','purpose',true,$known,$message);
$check('reproduces_wrong_service_direction',str_contains($old['reply'],'qué necesitas resolver'));
$p=Policy::plan('professional_offer','purpose',true,$known,$message);
$check('recognizes_proposal',str_contains($p['reply'],'ofrecer tus servicios'));
$check('proposal_requires_human_review',$p['phase']==='review'&&$p['ticket']==='Propuesta de colaboración: revisión humana');
$check('no_acceptance_or_payment_promise',!preg_match('/contratad|aceptamos|te pagaremos|comienza|aprobada|gratis/i',$p['reply']));
$check('no_repeated_service_city_question',!str_contains($p['reply'],'?'));
$check('external_privacy',!preg_match('/Sandra|grupo|equipo|técnic|intern/i',$p['reply']));
$check('known_city_does_not_change_direction',Policy::plan('professional_offer','service_detail',false,['service'=>true,'city'=>true],$message)['ticket']===$p['ticket']);
$check('ordinary_customer_still_asks_service',str_contains(Policy::plan('new_service','',false,$known,'Necesito una asesoría')['reply'],'necesitas resolver'));
$check('legal_dispute_still_professional_review',Policy::plan('legal','',false,[],'Me despidieron sin pagar')['ticket']==='Consulta jurídica: revisión profesional');
$phone='573000000071@s.whatsapp.net';
$fixture=function($id,$text)use($phone){return ['instance'=>'abogados','event'=>'messages.upsert','data'=>['key'=>['id'=>$id,'remoteJid'=>$phone,'fromMe'=>false],'messageTimestamp'=>time(),'messageType'=>'conversation','message'=>['conversation'=>$text]]];};
$bot->query('INSERT INTO chats(jid,hold,baseline,phase,last_reply,updated) VALUES(?,1,1,?,?,?)',[$phone,'','',time()]);
$held=$fixture('SYNTHETIC_OFFER_HOLD',$message);$bot->receive($held);
$process=new ReflectionMethod(Runtime::class,'processEvent');
$process->invoke($bot,$bot->query('SELECT * FROM events WHERE id=?',['SYNTHETIC_OFFER_HOLD'])->fetch());
$check('human_hold_blocks_offer_response',$bot->query('SELECT state FROM events WHERE id=?',['SYNTHETIC_OFFER_HOLD'])->fetchColumn()==='OBSERVED_HUMAN'&&(int)$bot->query('SELECT COUNT(*) FROM outbox')->fetchColumn()===0);
$bot->query("UPDATE chats SET hold=0,phase='review' WHERE jid=?",[$phone]);
$bot->receive($fixture('SYNTHETIC_OFFER_REVIEW',$message));
$process->invoke($bot,$bot->query('SELECT * FROM events WHERE id=?',['SYNTHETIC_OFFER_REVIEW'])->fetch());
$check('existing_review_not_reopened',$bot->query('SELECT state FROM events WHERE id=?',['SYNTHETIC_OFFER_REVIEW'])->fetchColumn()==='OBSERVED_REVIEW');
if(getenv('AB_OFFER_LIVE_MODEL')==='1'){
    copy(storage_path('app/private/abogados-bot/openai-provider.json'),$root.'/openai-provider.json');
    chmod($root.'/openai-provider.json',0600);
    $cases=[
        ['observed_offer',$message,[],'professional_offer'],
        ['employment_application','Soy abogado y quiero enviarles mi hoja de vida para trabajar con ustedes.',[],'professional_offer'],
        ['employment_legal_dispute','Mi empresa me despidió sin pagar mi liquidación. ¿Puedo demandar?',[],'legal'],
        ['client_requests_advice','Quiero contratar una asesoría para saber cómo organizar mi caso.',[],'new_service'],
        ['client_asks_price','¿Cuánto cuesta una asesoría de familia?',[],'fees'],
        ['offer_followup','De forma gratuita.', ['priorMessages'=>['Quisiera prestar mis servicios a la firma.'],'lastQuestion'=>'¿Cómo deseas colaborar?'],'professional_offer'],
        ['customer_followup','En Medellín.', ['priorMessages'=>['Quiero contratar una asesoría.'],'lastQuestion'=>'¿Desde qué ciudad nos escribes?'],'new_service'],
    ];
    $classifications=[];
    foreach($cases as [$name,$text,$context,$intent]){
        $c=$bot->classify($text,$context);$classifications[$name]=$c;
        $check('live_'.$name,$c['intent']===$intent&&$c['confidence']==='high');
    }
    $bot->query("UPDATE chats SET phase='' WHERE jid=?",[$phone]);
    $e=$fixture('SYNTHETIC_OFFER_RECEIVE',$message);$bot->receive($e);
    $process->invoke($bot,$bot->query('SELECT * FROM events WHERE id=?',['SYNTHETIC_OFFER_RECEIVE'])->fetch());
    $row=$bot->query('SELECT state,reason FROM events WHERE id=?',['SYNTHETIC_OFFER_RECEIVE'])->fetch();
    $check('offer_classified_and_persisted',$row['state']==='DONE'&&$row['reason']==='professional_offer');
    $ticket=$bot->query('SELECT * FROM tickets WHERE event=?',['SYNTHETIC_OFFER_RECEIVE'])->fetch();
    $check('ticket_saved_before_reply',$ticket&&$ticket['category']==='Propuesta de colaboración: revisión humana');
    $reply=Crypt::decryptString($bot->query('SELECT body FROM outbox WHERE id=?',['SYNTHETIC_OFFER_RECEIVE|reply'])->fetchColumn());
    $check('real_pipeline_uses_review_template',str_contains($reply,'Tu propuesta quedó registrada para revisión'));
    $internal=$bot->query('SELECT chat,body FROM outbox WHERE internal=1')->fetchAll();
    $check('both_authorized_internal_destinations',array_column($internal,'chat')===[Policy::SANDRA,Policy::GROUP]);
    $check('correct_internal_offer_reason',count($internal)===2&&str_contains(Crypt::decryptString($internal[0]['body']),'propuesta de colaboración'));
    $check('duplicate_does_not_create_another_reply',!empty($bot->receive($e)['duplicate'])&&(int)$bot->query('SELECT COUNT(*) FROM outbox')->fetchColumn()===3);
    $check('original_event_never_replayed',!(bool)$bot->query('SELECT 1 FROM events WHERE id=?',['3EB0B63291ED7D8054AB44'])->fetchColumn());
    unlink($root.'/openai-provider.json');
}
$bot->flush();
$check('disabled_test_never_sends',(int)$bot->query("SELECT COUNT(*) FROM outbox WHERE state IN ('ACCEPTED','DELIVERED','READ','SENDING')")->fetchColumn()===0);
echo json_encode(['passed'=>count($tests),'failed'=>0,'tests'=>$tests,'classifications'=>$classifications??[],'externalMessages'=>0,'businessWrites'=>0],JSON_UNESCAPED_UNICODE).PHP_EOL;
