<?php

namespace App\Services\AbogadosBot;

use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/** Google access is read-only, account-bound and separate from the chat model. */
final class GoogleSources
{
    public const ACCOUNT='abogadosencolombiasas@gmail.com';
    public const CALLBACK='https://cobrocartera.abogadosencolombiasas.com/abogados-bot/google/callback';
    public const SCOPES=['openid','email','https://www.googleapis.com/auth/gmail.readonly','https://www.googleapis.com/auth/drive.readonly'];
    private string $root;
    public function __construct(?string $root=null){ $this->root=$root??storage_path('app/private/abogados-bot'); }
    public function configured():bool{return is_file($this->root.'/google-client.enc');}
    private function read(string $file):array{return json_decode(Crypt::decryptString(file_get_contents($this->root.'/'.$file)),true,512,JSON_THROW_ON_ERROR);}
    private function save(string $file,array $data):void
    {
        $path=$this->root.'/'.$file;$tmp=$path.'.'.bin2hex(random_bytes(5));
        file_put_contents($tmp,Crypt::encryptString(json_encode($data)),LOCK_EX);chmod($tmp,0660);rename($tmp,$path);
    }
    private function request(string $url,?array $form=null,?string $bearer=null):array
    {
        $allowed=['oauth2.googleapis.com','www.googleapis.com','gmail.googleapis.com'];
        if(!in_array(parse_url($url,PHP_URL_HOST),$allowed,true)||parse_url($url,PHP_URL_SCHEME)!=='https')throw new RuntimeException('GOOGLE_HOST_REJECTED');
        $raw='';$large=false;
        $c=curl_init($url);$o=[CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_WRITEFUNCTION=>function($c,$chunk)use(&$raw,&$large){if(strlen($raw)+strlen($chunk)>6*1024*1024){$large=true;return 0;}$raw.=$chunk;return strlen($chunk);}];
        if($bearer)$o[CURLOPT_HTTPHEADER]=['Authorization: Bearer '.$bearer];
        if($form){$o[CURLOPT_POST]=true;$o[CURLOPT_POSTFIELDS]=http_build_query($form);}
        curl_setopt_array($c,$o);$ok=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
        if($large)throw new RuntimeException('GOOGLE_RESPONSE_TOO_LARGE');
        if($status<200||$status>=300||!$ok)throw new RuntimeException('GOOGLE_HTTP_'.$status);
        return json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    }
    public function authorizationUrl(string $state,string $verifier):string
    {
        $client=$this->read('google-client.enc');
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(['client_id'=>$client['client_id'],'redirect_uri'=>self::CALLBACK,'response_type'=>'code','scope'=>implode(' ',self::SCOPES),'state'=>$state,'access_type'=>'offline','prompt'=>'consent','login_hint'=>self::ACCOUNT,'code_challenge'=>rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'='),'code_challenge_method'=>'S256']);
    }
    public function exchange(string $code,string $verifier):array
    {
        $c=$this->read('google-client.enc');
        $token=$this->request('https://oauth2.googleapis.com/token',['client_id'=>$c['client_id'],'client_secret'=>$c['client_secret'],'code'=>$code,'code_verifier'=>$verifier,'redirect_uri'=>self::CALLBACK,'grant_type'=>'authorization_code']);
        $who=$this->request('https://www.googleapis.com/oauth2/v2/userinfo',null,$token['access_token']);
        if(($who['email']??'')!==self::ACCOUNT||empty($who['verified_email']))throw new RuntimeException('GOOGLE_WRONG_ACCOUNT');
        $scopes=explode(' ',$token['scope']??'');
        foreach(array_slice(self::SCOPES,2) as $required)if(!in_array($required,$scopes,true))throw new RuntimeException('GOOGLE_READ_SCOPE_MISSING');
        if(empty($token['refresh_token']))throw new RuntimeException('GOOGLE_OFFLINE_ACCESS_MISSING');
        $gmail=$this->request('https://gmail.googleapis.com/gmail/v1/users/me/profile',null,$token['access_token']);
        $drive=$this->request('https://www.googleapis.com/drive/v3/about?fields=user(emailAddress)',null,$token['access_token']);
        if(($gmail['emailAddress']??'')!==self::ACCOUNT||($drive['user']['emailAddress']??'')!==self::ACCOUNT)throw new RuntimeException('GOOGLE_SOURCE_IDENTITY_MISMATCH');
        $token['account']=self::ACCOUNT;$token['expires_at']=time()+$token['expires_in'];$token['verified_at']=gmdate('c');
        $this->save('google-token.enc',$token);
        return ['account'=>self::ACCOUNT,'gmail'=>'READ_ONLY_VERIFIED','drive'=>'READ_ONLY_VERIFIED','verified_at'=>$token['verified_at']];
    }
    private function token():string
    {
        $mask=umask(0007);$lock=fopen($this->root.'/google-refresh.lock','c');umask($mask);flock($lock,LOCK_EX);
        try{
            $t=$this->read('google-token.enc');if(($t['account']??'')!==self::ACCOUNT)throw new RuntimeException('GOOGLE_ACCOUNT_MISMATCH');
            if(($t['expires_at']??0)<time()+90){$c=$this->read('google-client.enc');$fresh=$this->request('https://oauth2.googleapis.com/token',['client_id'=>$c['client_id'],'client_secret'=>$c['client_secret'],'refresh_token'=>$t['refresh_token'],'grant_type'=>'refresh_token']);$t=array_replace($t,$fresh);$t['expires_at']=time()+$fresh['expires_in'];$this->save('google-token.enc',$t);}
            return $t['access_token'];
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public function status():array
    {
        $s=['configured'=>$this->configured(),'connected'=>is_file($this->root.'/google-token.enc'),'account'=>self::ACCOUNT];
        if($s['connected']){$t=$this->read('google-token.enc');$s['verified_at']=$t['verified_at']??null;}
        return $s;
    }
    public function listMail(string $query,?string $page=null,int $limit=100):array
    {
        return $this->request('https://gmail.googleapis.com/gmail/v1/users/me/messages?'.http_build_query(array_filter(['q'=>$query,'maxResults'=>max(1,min(100,$limit)),'pageToken'=>$page])),null,$this->token());
    }
    public function mail(string $id,bool $metadata=false):array
    {
        if(!preg_match('/^[a-f0-9]{8,64}$/D',$id))throw new RuntimeException('INVALID_MAIL_ID');
        return $this->request('https://gmail.googleapis.com/gmail/v1/users/me/messages/'.$id.'?format='.($metadata?'metadata':'full'),null,$this->token());
    }
    public function mailThreadMetadata(string $id):array
    {
        if(!preg_match('/^[a-f0-9]{8,64}$/D',$id))throw new RuntimeException('INVALID_THREAD_ID');
        return $this->request('https://gmail.googleapis.com/gmail/v1/users/me/threads/'.$id.'?format=metadata',null,$this->token());
    }
    public function listFiles(string $query,?string $page=null,int $limit=100):array
    {
        return $this->request('https://www.googleapis.com/drive/v3/files?'.http_build_query(array_filter(['q'=>$query,'pageSize'=>max(1,min(100,$limit)),'pageToken'=>$page,'fields'=>'nextPageToken,files(id,name,mimeType,modifiedTime,parents,size)','orderBy'=>'modifiedTime desc','supportsAllDrives'=>'true','includeItemsFromAllDrives'=>'true'])),null,$this->token());
    }

    public function verifyIdentity():array
    {
        $token=$this->token();
        $gmail=$this->request('https://gmail.googleapis.com/gmail/v1/users/me/profile',null,$token);
        $drive=$this->request('https://www.googleapis.com/drive/v3/about?fields=user(emailAddress)',null,$token);
        if(($gmail['emailAddress']??'')!==self::ACCOUNT||($drive['user']['emailAddress']??'')!==self::ACCOUNT)throw new RuntimeException('GOOGLE_SOURCE_IDENTITY_MISMATCH');
        return ['account'=>self::ACCOUNT,'verified_at'=>gmdate('c')];
    }

    public function file(string $id):array
    {
        self::fileId($id);
        return $this->request('https://www.googleapis.com/drive/v3/files/'.$id.'?'.http_build_query(['fields'=>'id,name,mimeType,modifiedTime,size,trashed,capabilities(canDownload)','supportsAllDrives'=>'true']),null,$this->token());
    }
    private static function fileId(string $id):void
    {if(!preg_match('/^[a-zA-Z0-9_-]{10,160}$/D',$id))throw new RuntimeException('INVALID_FILE_ID');}

    /** Only fixed Google endpoints; never follows URLs found in mail or documents. */
    public function fileBytes(array $file):string
    {
        self::fileId($file['id']??'');
        if(!empty($file['trashed'])||empty($file['capabilities']['canDownload']))throw new RuntimeException('FILE_DOWNLOAD_NOT_ALLOWED');
        $mime=$file['mimeType']??'';
        if(!in_array($mime,['application/vnd.google-apps.document','text/plain','application/pdf','application/vnd.openxmlformats-officedocument.wordprocessingml.document'],true))throw new RuntimeException('FILE_FORMAT_UNSUPPORTED');
        $max=5*1024*1024;
        if(($file['size']??0)>$max)throw new RuntimeException('FILE_TOO_LARGE');
        $url='https://www.googleapis.com/drive/v3/files/'.$file['id'].($mime==='application/vnd.google-apps.document'?'/export?mimeType=text%2Fplain':'?alt=media&supportsAllDrives=true');
        $bytes='';$oversize=false;$c=curl_init($url);
        curl_setopt_array($c,[CURLOPT_TIMEOUT=>25,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->token()],CURLOPT_WRITEFUNCTION=>function($c,$chunk)use(&$bytes,&$oversize,$max){if(strlen($bytes)+strlen($chunk)>$max){$oversize=true;return 0;}$bytes.=$chunk;return strlen($chunk);}]);
        $ok=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
        if($oversize)throw new RuntimeException('FILE_TOO_LARGE');
        if(!$ok||$status<200||$status>=300)throw new RuntimeException('GOOGLE_HTTP_'.$status);
        return $bytes;
    }
}
