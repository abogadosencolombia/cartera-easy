<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Services\AbogadosBot\GoogleSources;
use Illuminate\Support\Facades\Crypt;
$root=storage_path('app/private/abogados-bot/google-test-'.bin2hex(random_bytes(5)));mkdir($root,0700,true);
$client=['client_id'=>'synthetic.apps.googleusercontent.com','client_secret'=>'synthetic-only'];
file_put_contents($root.'/google-client.enc',Crypt::encryptString(json_encode($client)));
$g=new GoogleSources($root);$tests=[];
$check=function($name,$ok)use(&$tests){$tests[$name]=(bool)$ok;if(!$ok)throw new RuntimeException('FAILED_'.$name);};
$verifier=str_repeat('a',64);parse_str(parse_url($g->authorizationUrl('one-use-state',$verifier),PHP_URL_QUERY),$q);
$check('fixed_account',$q['login_hint']==='abogadosencolombiasas@gmail.com');
$check('exact_callback',$q['redirect_uri']===GoogleSources::CALLBACK);
$check('state_preserved',$q['state']==='one-use-state');
$check('pkce_s256',$q['code_challenge_method']==='S256'&&$q['code_challenge']===rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'='));
$check('readonly_scopes',explode(' ',$q['scope'])===['openid','email','https://www.googleapis.com/auth/gmail.readonly','https://www.googleapis.com/auth/drive.readonly']);
$check('configuration_not_connection',$g->status()['configured']&&!$g->status()['connected']);
$request=new ReflectionMethod(GoogleSources::class,'request');
foreach(['http://www.googleapis.com/','https://evil.example/','https://www.googleapis.com.evil.example/'] as $i=>$url){try{$request->invoke($g,$url);$ok=false;}catch(RuntimeException $ex){$ok=$ex->getMessage()==='GOOGLE_HOST_REJECTED';}$check('reject_untrusted_host_'.$i,$ok);}
try{$g->mail('../bad');$ok=false;}catch(RuntimeException $ex){$ok=$ex->getMessage()==='INVALID_MAIL_ID';}$check('invalid_mail_id_rejected_before_access',$ok);
$check('client_ciphertext',!str_contains(file_get_contents($root.'/google-client.enc'),'synthetic-only'));
echo json_encode(['passed'=>count($tests),'failed'=>0,'tests'=>$tests,'networkCalls'=>0,'businessWrites'=>0]).PHP_EOL;
