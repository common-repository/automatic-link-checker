<?php 
class lc_http_parse {
 
  protected $url;
  public $user_agent='Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US;
rv:1.8.1.1) Gecko/20061204 Firefox/2.0.0.1';


  public $sleep_time=0; // пауза между запросами в секундах
  public $HTTPHEADER=null; // для CURLOPT_HTTPHEADER
  public $ENCODING=null; // для CURLOPT_ENCODING
  public $time_curl=3; // время ожидания запроса к одной странице
  public $cache_dir=false; // папка с файлами кэша './tmp/cache_parser/'
  public $file_coockies=false; // файл с куками './tmp/coockies_http_get.dat'
  public $cache_time_limit=30; // время хранения кэша в секундах (3 суток)
  public $charset='utf-8'; // кодировка результата данных и хранения кэша
  public $post_array;
  public $charset_ifno='windows-1251';     // если не указана кодировка то считать,
                    // что windows-1251
  public $pref_file_b='.dat'; // Расширения файла с базой
  public $pref_file_s='.html'; // Расширения файла кэша страницы
  public $on_cached; // включение кэширование на +1
  
  function lc_http_parse(){
	$this->_initUserAgent();
  }
  
  
 
protected function get_parse($url){
    $ch=curl_init ($url);
    curl_setopt ($ch,CURLOPT_RETURNTRANSFER,1);
    curl_setopt ($ch,CURLOPT_VERBOSE,1);
    curl_setopt ($ch,CURLOPT_HEADER,1);
    curl_setopt ($ch,CURLOPT_TIMEOUT,$this->time_curl);
    curl_setopt ($ch,CURLOPT_USERAGENT,$this->user_agent);
    
    if (isset($this->post_array)){
       curl_setopt ($ch,CURLOPT_POST,1);
       curl_setopt ($ch,CURLOPT_POSTFIELDS,$this->post_array);
    }
        if ($HTTPHEADER!=null){
       curl_setopt ($ch,CURLOPT_HTTPHEADER,$HTTPHEADER);
    }
        if ($ENCODING!=null){
       curl_setopt ($ch,CURLOPT_ENCODING,$ENCODING);
    }
    if($this->sleep_time>0){
      sleep($this->sleep_time);
    }
    $page = curl_exec ($ch);
    $this->pagetext=$page;
    $this->ch_curl=$ch;
    return $page;
  }
 
protected function if_get_parse($url){
    $path_file_bd=$this->cache_dir.md5($this->url).$this->pref_file_b;
    $path_file_site=$this->cache_dir.md5($this->url).$this->pref_file_s;
    if(file_exists($path_file_bd) and file_exists($path_file_site)){
      $bd=unserialize(file_get_contents($path_file_bd));
      if($bd['time']>time()){
    $page=file_get_contents($path_file_site);
    if($bd['charset']!=$this->charset){
      iconv($bd['charset'],$this->charset,$page);
    }
      $this->pagetext='';
      return $page;
      } else {
      unlink($path_file_bd); unlink($path_file_site);
      $this->if_get_parse($url);
      }
    } else {
      $page=$this->get_followlocation($url);
      $page=preg_replace("#^([^\<]*)<(.*)#i","<\\2",$page);
      $charset_page=$this->charset_page_parse();
      if($this->charset!=''&&$charset_page!=$this->charset){
    $page=iconv($charset_page,$this->charset,$page);
      }
      if($page!=''){
        $this->puts_content($page);
      }
      $this->close_curle();
      $this->pagetext='';
      return $page;
    }
  }
 
protected function get_followlocation($url){
    $page=$this->get_parse($url);
    if(preg_match("#Location\:\s?(.+)\s#i",$page)){
      preg_match_all("#Location\:\s?(.+)\s#isU",$page,$link);
      $page=$this->get_followlocation(trim($link[1][0]));
    }
    return $page;
  }
 
protected function puts_content($text){
    if($this->on_cached==1){
    $path_file_bd=$this->cache_dir.md5($this->url).$this->pref_file_b;
    $path_file_site=$this->cache_dir.md5($this->url).$this->pref_file_s;
    $bd['time']=time()+$this->cache_time_limit;
    $bd['charset']=$this->charset;
    $bd['url']=$this->url;
    $bd['time_load']=time();
    file_put_contents($path_file_site,$text);
    file_put_contents($path_file_bd,serialize($bd));
  }}
 
protected function charset_page_parse(){
    $content_type=curl_getinfo($this->ch_curl,CURLINFO_CONTENT_TYPE);
    if(preg_match("#charset=(.+)\s*#is",$content_type)){
      preg_match_all("#charset=(.+)\s*#is",$content_type,$chars);
      $charset=$chars[1][0];
    } else {
    if(preg_match("#charset=(\'?|\"?)(.+)(\'|\"|\s)#isU",$this->pagetext)){
      preg_match_all("#charset=(\'?|\"?)(.+)(\'|\"|\s)#isU",$this->pagetext,$chars);
      $charset=$chars[2][0];
    } else {
    $charset=$this->charset_ifno;
    }
    }
    return $charset;
  }
 
public function get($url){
    $this->url=$url;
    $namedirurl=parse_url($this->url);
	
    return $this->if_get_parse($this->url);
  }
 
protected function close_curle(){
    curl_close ($this->ch_curl);
  }
  
  function _initUserAgent() {
		if ($this->userAgent!='random') return true;
		$browsers = array(
			'Mozilla/4.0 (compatible; MSIE 5.5; Windows NT 5.0)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows 98)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.0.3705)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 2.0.50727; .NET CLR 1.1.4322)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; Avant Browser; .NET CLR 2.0.50727)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; en) Opera 9.10',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; FunWebProducts)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; MRA 4.8 (build 01709); Maxthon; .NET CLR 1.1.4322; .NET CLR 2.0.50727)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; ru) Opera 8.50',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; ru) Opera 8.54',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0; .NET CLR 2.0.50727)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322; .NET CLR 2.0.50727; .NET CLR 3.0.04506.30)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322; .NET CLR 2.0.50727; MAXTHON 2.0)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 2.0.50727)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 2.0.50727; .NET CLR 1.1.4322; InfoPath.1)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 2.0.50727; .NET CLR 3.0.04506.30)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 2.0.50727; .NET CLR 3.0.04506.30; InfoPath.2; .NET CLR 1.1.4322; MAXTHON 2.0)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; InfoPath.1)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; InfoPath.2)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; MRA 4.7 (build 01670); .NET CLR 1.1.4322)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; MRA 4.7 (build 01670); InfoPath.1)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; MRA 4.8 (build 01709))',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; MRA 4.8 (build 01709); .NET CLR 1.1.4322)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; MRA 4.8 (build 01709); .NET CLR 2.0.50727; InfoPath.2; .NET CLR 1.1.4322; .NET CLR 3.0.04506.30)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; MRA 4.8 (build 01709); Maxthon; .NET CLR 2.0.50727; .NET CLR 1.1.4322; .NET CLR 3.0.04506.30)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; MyIE2; InfoPath.1)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; MyIE2; MRA 4.8 (build 01709); .NET CLR 1.1.4322; InfoPath.1)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.2; .NET CLR 1.1.4322)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.2; SV1; .NET CLR 1.1.4322)',
			'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.2; SV1; .NET CLR 1.1.4322; .NET CLR 2.0.50727)',
			'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)',
			'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.1.4322)',
			'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.1.4322; .NET CLR 2.0.50727; InfoPath.1)',
			'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 2.0.50727)',
			'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 2.0.50727; .NET CLR 1.1.4322; InfoPath.1)',
			'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Avant Browser; Avant Browser; .NET CLR 1.1.4322)',
			'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Maxthon)',
			'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Maxthon; Avant Browser; InfoPath.2)',
			'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Maxthon; MyIE2; .NET CLR 1.0.3705; .NET CLR 2.0.50727; InfoPath.2)',
			'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; MRA 4.6 (build 01425); InfoPath.1)',
			'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; MRA 4.8 (build 01709))',
			'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; MRA 4.8 (build 01709); .NET CLR 1.1.4322; InfoPath.1)',
			'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; MRA 4.8 (build 01709); Avant Browser)',
			'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; MRA 4.9 (build 01863); .NET CLR 2.0.50727)',
			'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; MyIE2)',
			'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; MyIE2; .NET CLR 2.0.50727; InfoPath.1; .NET CLR 1.1.4322; MEGAUPLOAD 1.0)',
			'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0; SLCC1; .NET CLR 2.0.50727; Media Center PC 5.0; .NET CLR 3.0.04506; InfoPath.2; .NET CLR 1.1.4322)',
			'Mozilla/5.0 (Windows; U; Windows NT 5.1; bg; rv:1.8.1.3) Gecko/20070309 Firefox/2.0.0.3',
			'Mozilla/5.0 (Windows; U; Windows NT 5.1; de; rv:1.8.0.11) Gecko/20070312 Firefox/1.5.0.11',
			'Mozilla/5.0 (Windows; U; Windows NT 5.1; de; rv:1.8.1.3) Gecko/20070309 Firefox/2.0.0.3',
			'Mozilla/5.0 (Windows; U; Windows NT 5.1; de; rv:1.8.1.4) Gecko/20070515 Firefox/2.0.0.4',
			'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-GB; rv:1.8.1.3) Gecko/20070309 Firefox/2.0.0.3',
			'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.3) Gecko/20070309 Firefox/2.0.0.3',
			'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.4) Gecko/20070515 Firefox/2.0.0.4',
			'Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.8.0.11) Gecko/20070312 Firefox/1.5.0.11',
			'Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.8.1.3) Gecko/20070309 Firefox/2.0.0.3',
			'Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.8.1.4) Gecko/20070515 Firefox/2.0.0.4',
			'Mozilla/5.0 (Windows; U; Windows NT 5.1; ru-RU; rv:1.7.12) Gecko/20050919 Firefox/1.0.7',
			'Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.8.0.1) Gecko/20060313 Fedora/1.5.0.1-9 Firefox/1.5.0.1 pango-text',
			'Mozilla/5.0 (X11; U; Linux i686; ru; rv:1.8) Gecko/20060112 ASPLinux/1.5-1.2am Firefox/1.5',
			'Opera/8.54 (Windows NT 5.1; U; en)',
			'Opera/9.00 (Windows NT 5.1; U; ru)',
			'Opera/9.01 (Windows NT 5.1; U; ru)',
			'Opera/9.02 (Windows NT 5.0; U; ru)',
			'Opera/9.02 (Windows NT 5.1; U; ru)',
			'Opera/9.02 (Windows NT 5.2; U; en)',
			'Opera/9.10 (Windows NT 5.1; U; ru)',
			'Opera/9.20 (Windows NT 5.1; U; en)',
			'Opera/9.20 (Windows NT 5.1; U; ru)',
			'Opera/9.21 (Windows NT 5.1; U; ru)',
		);
		$this->userAgent = $browsers[array_rand($browsers)];
		return true;
	}
  
}


?>