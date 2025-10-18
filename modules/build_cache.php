<?php
ob_start();

$cache_file      = __DIR__ . '/../cache/packages.php';
$hash_file       = __DIR__ . '/../cache/packages.hash';
$icon_dir        = __DIR__ . '/../icons/';
$packages_url    = 'https://slackware.uk/slackdce/packages/15.0/x86_64/PACKAGES.TXT.gz';
$packages_file   = __DIR__ . '/../cache/PACKAGES.TXT.gz';
$slackbuilds_tar = __DIR__ . '/../cache/slackbuilds.tar.gz';
$slackbuilds_dir = __DIR__ . '/../cache/slackbuilds';
$tag_file        = __DIR__ . '/../cache/slackbuilds.tag';
$logstore        = __DIR__ . '/../tmp/logstore.txt';
$products_cache  = [];

function logmsg($m){
    global $logstore;
    file_put_contents($logstore,date('[Y-m-d H:i:s] ').$m."\n",FILE_APPEND);
}

function fetch_url($url){
    if(function_exists('curl_version')){
        $ch=curl_init($url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);
        curl_setopt($ch,CURLOPT_USERAGENT,'SlackBuildsChecker/1.0');
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,true);
        $content=curl_exec($ch);
        $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code'=>$code,'content'=>$content];
    } else {
        $opts=['http'=>['method'=>'GET','header'=>"User-Agent: SlackBuildsChecker/1.0\r\nAccept: application/vnd.github.v3+json\r\n"],'ssl'=>['verify_peer'=>true]];
        $ctx=stream_context_create($opts);
        $content=@file_get_contents($url,false,$ctx);
        $code=0;
        if(isset($http_response_header)){
            foreach($http_response_header as $h){
                if(preg_match('#HTTP/\d+\.\d+\s+(\d+)#',$h,$m)){ $code=(int)$m[1]; break; }
            }
        }
        return ['code'=>$code,'content'=>$content];
    }
}

logmsg('start');

if(!is_dir(__DIR__.'/../cache')) mkdir(__DIR__.'/../cache',0755,true);
if(!is_dir(dirname($logstore))) mkdir(dirname($logstore),0755,true);
if(!is_dir($icon_dir)) mkdir($icon_dir,0755,true);

// ---- PACKAGES.TXT.gz ----
if(!file_exists($packages_file) || time()-filemtime($packages_file)>86400){
    $r=fetch_url($packages_url);
    if($r['code']===200 && $r['content']!==''){
        file_put_contents($packages_file,$r['content']);
        logmsg('downloaded PACKAGES.TXT.gz');
    } else logmsg('failed PACKAGES.TXT.gz code='.$r['code']);
}

// ---- SLACKBUILDS TAR ----
$r=fetch_url('https://api.github.com/repos/SlackBuildsOrg/slackbuilds/tags');
if($r['code']===200 && $r['content']!==''){
    $tags=json_decode($r['content'],true);
    $latest_tag='';
    foreach($tags as $t){
        if(preg_match('/^\d+\.\d+\-\d{8}\.\d+$/',$t['name'])){
            $latest_tag=$t['name'];
            break;
        }
    }
    if($latest_tag!==''){
        $current_tag=file_exists($tag_file)?trim(file_get_contents($tag_file)):'';
        $download_new = !file_exists($slackbuilds_tar) || $current_tag !== $latest_tag;

        if($download_new){
            $tar_url="https://github.com/SlackBuildsOrg/slackbuilds/archive/refs/tags/$latest_tag.tar.gz";
            $t=fetch_url($tar_url);
            if($t['code']===200 && strlen($t['content'])>100000){
                file_put_contents($slackbuilds_tar,$t['content']);
                file_put_contents($tag_file,$latest_tag);
                logmsg("downloaded new slackbuilds $latest_tag");

                if(is_dir($slackbuilds_dir)) shell_exec("rm -rf ".escapeshellarg($slackbuilds_dir));
                mkdir($slackbuilds_dir,0755,true);
                shell_exec("tar -xzf ".escapeshellarg($slackbuilds_tar)." -C ".escapeshellarg($slackbuilds_dir)." --strip-components=1");
                logmsg("extracted slackbuilds to cache/slackbuilds");
            } else logmsg("failed download tar code=".$t['code']);
        } else if(!is_dir($slackbuilds_dir) || count(scandir($slackbuilds_dir)) <= 2){
            mkdir($slackbuilds_dir,0755,true);
            shell_exec("tar -xzf ".escapeshellarg($slackbuilds_tar)." -C ".escapeshellarg($slackbuilds_dir)." --strip-components=1");
            logmsg("extracted slackbuilds to cache/slackbuilds (folder missing)");
        } else logmsg("slackbuilds up to date $latest_tag");
    } else logmsg('no valid version tag');
} else logmsg('error fetching tags code='.$r['code']);

// ---- rebuild cache ----
$current_hash=file_exists($packages_file)?hash_file('sha256',$packages_file):'';
$needs_update=true;
if($current_hash && file_exists($hash_file) && trim(file_get_contents($hash_file))===$current_hash){
    $needs_update=false;
    logmsg('no changes detected');
}

if($needs_update && $current_hash){
    logmsg('rebuilding cache');
    $icons=[];
    foreach(glob($icon_dir.'*.svg') as $f) $icons[strtolower(basename($f,'.svg'))]=$f;
    $lines=@gzfile($packages_file);
    if($lines!==false){
        $skip=true;
        foreach($lines as $i=>$line){
            $line=trim($line);
            if($skip){ if(strpos($line,'PACKAGE NAME:')===0) $skip=false; else continue; }
            if($line==='') continue;
            if(strpos($line,'PACKAGE NAME:')===0){
                $pkg=["name"=>"","category"=>"","version"=>"","desc"=>"","sizec"=>"","sizeu"=>"","req"=>"","icon"=>""];
                $pkg['full']=trim(substr($line,14));
                if(preg_match('/-([0-9][^-]*)-/',$pkg['full'],$v)) $pkg['version']=$v[1];
            } elseif(strpos($line,'PACKAGE LOCATION:')===0){
                $loc=trim(substr($line,18));
                if(preg_match('#\./([^/]+)/([^/]+)$#',$loc,$m)){ $pkg['category']=ucfirst(strtolower($m[1])); $pkg['name']=$m[2]; }
            } elseif(strpos($line,'PACKAGE SIZE (compressed):')===0) $pkg['sizec']=trim(substr($line,28));
            elseif(strpos($line,'PACKAGE SIZE (uncompressed):')===0) $pkg['sizeu']=trim(substr($line,30));
            elseif(strpos($line,'PACKAGE REQUIRED:')===0) $pkg['req']=trim(substr($line,18));
            elseif(strpos($line,'PACKAGE DESCRIPTION:')===0){
                $desc='';
                for($j=$i+1;$j<count($lines);$j++){
                    $next=trim($lines[$j]);
                    if($next==''||strpos($next,'PACKAGE NAME:')===0) break;
                    if($next!=''&&strpos($next,':')!==false){ $desc=trim(substr($next,strpos($next,':')+1)); break; }
                }
                $pkg['desc']=$desc;
                $base=strtolower($pkg['name']);
                $icon='/../icons/terminal.svg';
                foreach($icons as $n=>$f) if($n==$base||strpos($base,$n)!==false||strpos($n,$base)!==false){ $icon='/../icons/'.basename($f); break; }
                $pkg['icon']=$icon;
                $products_cache[]=$pkg;
            }
        }
        if(!is_dir(dirname($cache_file))) mkdir(dirname($cache_file),0755,true);
        file_put_contents($cache_file,'<?php $products_cache='.var_export($products_cache,true).';');
        file_put_contents($hash_file,$current_hash);
        logmsg('cache updated');
    }
}

logmsg('end');
echo '<script>location="/../index.php";</script>';
?>
