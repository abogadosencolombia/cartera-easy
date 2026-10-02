<?php
require '/code/vendor/autoload.php';$a=require '/code/bootstrap/app.php';$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\AbogadosBot\{ProgramAdmin,Policy,Runtime};use Illuminate\Support\Facades\{DB,Crypt};
$checks=[];$check=function($n,$v)use(&$checks){$checks[$n]=$v;if(!$v)throw new RuntimeException('TEST_FAILED_'.$n);};
config(['database.connections.bot_admin_test'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]]);$db=DB::connection('bot_admin_test');
foreach(['casos','proceso_radicados'] as $t)$db->statement("CREATE TABLE $t (id INTEGER PRIMARY KEY,radicado TEXT,deleted_at TEXT,notas_legales TEXT,observaciones TEXT,link_drive TEXT,ubicacion_drive TEXT,link_expediente TEXT,estado TEXT,updated_at TEXT,fecha_revision TEXT)");
$db->statement('CREATE TABLE auditoria_eventos (id INTEGER PRIMARY KEY,user_id INT,evento TEXT,descripcion_breve TEXT,auditable_id INT,auditable_type TEXT,criticidad TEXT CHECK (criticidad IN (\'baja\',\'media\',\'alta\')),detalle_anterior TEXT,detalle_nuevo TEXT,user_agent TEXT,created_at TEXT,updated_at TEXT)');
$id='00000000000000000000001';$db->table('casos')->insert(['id'=>1,'radicado'=>$id,'notas_legales'=>'Nota anterior','estado'=>'ACTIVO']);
$p=ProgramAdmin::plan('Jeison, agrega una nota al proceso '.$id.': "Llamar el lunes para pedir los documentos"');
$check('exact_note_parsed',$p['value']==='Llamar el lunes para pedir los documentos');$check('unrelated_command_rejected',ProgramAdmin::plan('Jeison, cambia el saldo a cero')===null);
$check('negation_rejected',ProgramAdmin::plan('Jeison, no agrega nota '.$id.' "ejemplo"')===null);$check('third_party_story_not_command',ProgramAdmin::plan('Jeison, me dijeron agrega nota '.$id.' "texto"')===null);
$check('ambiguous_identifiers',isset(ProgramAdmin::plan('Jeison, agrega nota '.$id.' 00000000000000000000002 "texto"')['ask']));
$check('note_without_exact_text',isset(ProgramAdmin::plan('Jeison, agrega nota '.$id)['ask']));
$check('spoofed_drive_host',isset(ProgramAdmin::plan('Jeison, cambia enlace '.$id.' https://drive.google.com.evil.test/a')['ask']));
$check('credential_url_rejected',isset(ProgramAdmin::plan('Jeison, cambia enlace '.$id.' https://secret@drive.google.com/a')['ask']));
$check('official_link_allowed',ProgramAdmin::plan('Jeison, actualiza enlace '.$id.' https://www.ramajudicial.gov.co/a')['operation']==='file');
$snapshot=ProgramAdmin::store($db,$p,null);$check('preview_read_only',$db->table('casos')->value('notas_legales')==='Nota anterior'&&$db->table('auditoria_eventos')->count()===0);
$plan=$p+$snapshot+['confirmation_event'=>'CONFIRM_SYNTHETIC'];$saved=ProgramAdmin::store($db,$plan,'PREVIEW_SYNTHETIC');
$check('note_saved',$saved['saved']&&str_contains($db->table('casos')->value('notas_legales'),$p['value']));$check('previous_note_preserved',str_starts_with($db->table('casos')->value('notas_legales'),'Nota anterior'));
$check('legal_state_unchanged',$db->table('casos')->value('estado')==='ACTIVO');$check('audit_actor_source_and_confirmation',str_contains($db->table('auditoria_eventos')->value('detalle_nuevo'),'CONFIRM_SYNTHETIC'));
$check('retry_does_not_duplicate',ProgramAdmin::store($db,$plan,'PREVIEW_SYNTHETIC')['duplicate']&&$db->table('auditoria_eventos')->count()===1);
$stale=false;try{ProgramAdmin::store($db,$plan,'OTHER_EVENT');}catch(RuntimeException $e){$stale=$e->getMessage()==='ADMIN_CHANGED_SINCE_PREVIEW';}$check('concurrent_change_rejected',$stale);
$db->table('proceso_radicados')->insert(['id'=>2,'radicado'=>$id]);$amb=false;try{ProgramAdmin::store($db,$p,null);}catch(RuntimeException $e){$amb=$e->getMessage()==='ADMIN_AMBIGUOUS';}$check('cross_module_duplicate_rejected',$amb);$db->table('proceso_radicados')->delete();
$p2=ProgramAdmin::plan('Jeison, cambia enlace '.$id.' https://drive.google.com/file/d/EXAMPLE/view');$p2+=ProgramAdmin::store($db,$p2,null);
$db->statement("CREATE TRIGGER audit_reject BEFORE INSERT ON auditoria_eventos BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
$fail=false;try{ProgramAdmin::store($db,$p2,'FAIL_EVENT');}catch(Throwable){$fail=true;}$check('audit_failure_rolls_back_field',$fail&&$db->table('casos')->value('link_drive')===null);$db->statement('DROP TRIGGER audit_reject');
$root=storage_path('app/private/abogados-bot/test-admin-'.bin2hex(random_bytes(5)));mkdir($root,0700,true);file_put_contents($root.'/runtime.json',json_encode(['owner'=>Policy::OWNER,'instance'=>'abogados','enabled'=>false,'activated_at'=>time()-7200]));$b=new Runtime($root);$admin=new ProgramAdmin($b,fn($plan,$event)=>ProgramAdmin::store($db,$plan,$event));
$text='Jeison, actualiza enlace '.$id.' https://drive.google.com/file/d/EXAMPLE/view';$e=['id'=>'ADMIN_PREVIEW','chat'=>Policy::SANDRA,'at'=>time(),'body'=>Crypt::encryptString(json_encode(['message'=>['conversation'=>$text]]))];
$reply=$admin->handle($e,$text);$check('preview_requests_confirmation',str_contains($reply,'¿Confirmas')&&$db->table('casos')->value('link_drive')===null);
$confirm=$e;$confirm['id']='ADMIN_CONFIRM';$confirm['body']=Crypt::encryptString(json_encode(['message'=>['conversation'=>'sí']]));$reply=$admin->handle($confirm,'sí');$check('undelivered_preview_not_confirmation',str_contains($reply,'primero')&&$db->table('casos')->value('link_drive')===null);
$b->queue($e['id'].'|reply',Policy::SANDRA,'synthetic preview');$b->query("UPDATE outbox SET state='READ',mid='TEST_PREVIEW'");$reply=$admin->handle($confirm,'sí');$check('confirmed_save_verified',str_contains($reply,'quedó actualizado')&&$db->table('casos')->value('link_drive')===$p2['value']);
$check('pending_cleared',$admin->pending()===null);$bad=false;$e['chat']='573000000000@s.whatsapp.net';try{$admin->handle($e,$text);}catch(RuntimeException){$bad=true;}$check('wrong_sender_cannot_write',$bad);

// Exercise the actual receive -> authority -> preview -> confirmation path using only the isolated database.
config(['database.default'=>'bot_admin_test']);
$db->table('casos')->where('id',1)->update(['link_expediente'=>null]);
$b->query('DELETE FROM events');$b->query('DELETE FROM outbox');$b->query('DELETE FROM chats');
$b->query('INSERT INTO chats(jid,hold,baseline,phase,last_reply,updated) VALUES(?,0,1,?,?,?)',[Policy::SANDRA,'','',time()]);
$method=new ReflectionMethod(Runtime::class,'processEvent');
$run=function($event,$text,$forward=false)use($b,$method){
 $b->receive(['instance'=>'abogados','event'=>'messages.upsert','data'=>['key'=>['id'=>$event,'remoteJid'=>Policy::SANDRA,'fromMe'=>false],'messageTimestamp'=>time(),'messageType'=>'extendedTextMessage','message'=>['extendedTextMessage'=>['text'=>$text,'contextInfo'=>['isForwarded'=>$forward]]]]]);
 $e=$b->query('SELECT * FROM events WHERE id=?',[$event])->fetch();$method->invoke($b,$e);return $b->query('SELECT state,reason FROM events WHERE id=?',[$event])->fetch();
};
$txt='Jeison, actualiza enlace '.$id.' https://www.ramajudicial.gov.co/EXAMPLE';
$r=$run('RUNTIME_FORWARD',$txt,true);$check('runtime_forward_cannot_preview',$r['state']==='OBSERVED_NOT_ADDRESSED'&&$admin->pending()===null);
$r=$run('RUNTIME_PREVIEW',$txt);$check('runtime_explicit_order_previews',$r['reason']==='ADMIN_REPLY'&&$admin->pending()['event']==='RUNTIME_PREVIEW');
$check('runtime_preview_has_no_business_write',$db->table('casos')->where('id',1)->value('link_expediente')===null);
$b->query("UPDATE outbox SET state='READ',mid='RUNTIME_PREVIEW_MID' WHERE id='RUNTIME_PREVIEW|reply'");
$r=$run('RUNTIME_CONFIRM','sí');$check('runtime_natural_confirmation_saves',$r['reason']==='ADMIN_REPLY'&&$db->table('casos')->where('id',1)->value('link_expediente')==='https://www.ramajudicial.gov.co/EXAMPLE');
$before=$db->table('auditoria_eventos')->count();$method->invoke($b,$b->query("SELECT * FROM events WHERE id='RUNTIME_CONFIRM'")->fetch());$check('runtime_confirm_retry_no_duplicate',$db->table('auditoria_eventos')->count()===$before);
$b->query('UPDATE chats SET hold=1 WHERE jid=?',[Policy::SANDRA]);$r=$run('RUNTIME_HUMAN',$txt);$check('runtime_human_hold_respected_direct_order_pending',$r['state']==='REVIEW' && $r['reason']==='CHIEF_SINGLE_TURN_PENDING' && $admin->pending()===null && (int)$b->query('SELECT hold FROM chats WHERE jid=?',[Policy::SANDRA])->fetchColumn()===1 && $b->query('SELECT state FROM chief_turn_requests WHERE event=?',['RUNTIME_HUMAN'])->fetchColumn()==='REVIEW' && $db->table('auditoria_eventos')->count()===$before);

echo json_encode(['passed'=>count($checks),'failed'=>0,'tests'=>$checks,'externalMessages'=>0,'productionBusinessWrites'=>0],JSON_UNESCAPED_UNICODE).PHP_EOL;
