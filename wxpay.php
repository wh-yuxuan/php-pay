<?php


const v3key = '******';//v3秘钥
const v2key = '******';
const pr = 'apiclient_key.pem';//商户私钥文件，是个路径
const mchid = '******';//商户号
const serial_no = '******';//商家证书序列号
const Serial_wx = '******';//微信平台证书序列号
const appid = '*****';//appid可以是小程序或者公众号
const wx_pub = 'wxcrt.pub';//微信支付平台秘钥，是个路径

/*
 * 微信签名验签
 *
 */

class wxpay {

    public $hd ='';//请求头信息,随时要随时那
    /*
     * 微信支付类
     */
    /**
     * 获取token，必要的数据
     * @param string $url 请求的url地址
     * @param string $data 请求的主体
     * @param false $get 默认使用post方法
     * @param string $sh_pr RSA商户秘钥
     * @return string 返回token
     */
    private function get_token($url,$sh_pr,$data = "",$get = false)
    {
        /*
                              HTTP请求方法\n
                              URL\n
                              请求时间戳\n
                              请求随机串\n
                              请求报文主体\n
                               签名值 signature
        */
        $url_parts = parse_url($url);
        $canonical_url = ($url_parts['path'] . (!empty($url_parts['query']) ? "?${url_parts['query']}" : ""));//url
//    var_dump($canonical_url);exit;
        $nonce_str = $this->get_str();//请求随机串
        $timestamp = time();//请求时间戳

        if($get == true ){
            //echo '这是GET方法';exit;
            $data = '';//强制清空数据
            $sign_str = "GET"."\n".
                $canonical_url."\n".
                $timestamp."\n".
                $nonce_str."\n"."\n";
//        var_dump($sign_str);

        }elseif ($get == false && isset($data)==true) {
            //echo '这是post方法';exit;
            //$data = '';//强制清空数据
            $sign_str = "POST"."\n".
                $canonical_url."\n".
                $timestamp."\n".
                $nonce_str."\n".
                $data."\n";
        }
        //ar_dump($sign_str);exit;
        $rr = openssl_pkey_get_private(file_get_contents($sh_pr));
        switch ($rr){
            case false;
                echo '秘钥错误';
                break;
        }
        //ar_dump($sign_str);exit;
        openssl_sign($sign_str, $sign, file_get_contents($sh_pr), 'sha256WithRSAEncryption');
        $sign = base64_encode($sign);
        return sprintf('mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"', mchid, $nonce_str, $timestamp, serial_no, $sign);
    }
//上面是获取token，下面是方法
    /*
     *
     * 下面是post方法，获取数据
     *
     */
    /**
     * http访问数据
     * @param string $url 请求的url
     * @param array $setheader 设置请求头
     * @param string $data post请求的数据
     * @return false|string
     */
    private function http_pg($url,$setheader,$data =null)
{

        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);//设置请求网址
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);//返回数据流不输出
        curl_setopt($ch,CURLOPT_HEADER,1);//返回header信息
        curl_setopt($ch,CURLOPT_HTTPHEADER,$setheader);//设置header信息
        //上面是get请求，还有post请求

        if(isset($data)<>false){
//            echo 'post方法';exit;
            curl_setopt($ch,CURLOPT_POST,1);
            curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
            //$crt = curl_exec($ch);
        }
//        echo 'GET方法';exit;
        $crt = curl_exec($ch);
//        var_dump($crt);exit;
        // var_dump($crt);exit;
        // var_dump(curl_getinfo($ch, CURLINFO_HTTP_CODE));exit;
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) ) {
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header = substr($crt, 0, $headerSize);//把header信息单独拿出来
            $body = substr($crt, $headerSize);}//把返回的数据拿出来
        $this->hd = $header;
        curl_close($ch);
        $this->$ch=$header;
        return $body;
    }

    /**
     * 把请求头变成数组
     * @param $s_header 请求头
     * @return string|string[]
     */
    private function get_header($s_header){
        $arr = array_filter(explode("\n", $s_header));
        unset($arr[0]);
        $wx =[];


        for ($i = 1; $i <= count($arr); ++$i) {
            $str = explode(": ",$arr[$i]);
            $wx[$str[0]] = $str[1];
        }
        $wx = array_filter($wx);
        $wx = str_replace("\r","",$wx);
        return $wx;

    }

    /**
     * native支付，返回qrcode链接
     * @param array $data 要发送的数据,数组格式
     * @param bool $type 默认严格模式，对收发的数据进行验证签名
     * @return array 返回数组
     */
    public function v3_native($data,$type = true)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/pay/transactions/native';
        $data =json_encode($data,256);
        $token = $this->get_token($url,pr,$data,false);

        $setheader = array('Authorization: WECHATPAY2-SHA256-RSA2048 '.$token,
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/106.0.0.0 Safari/537.36');
        $res =  $this->http_pg($url,$setheader,$data);
        $code = json_decode($res,true);
        if($type == true){
            //代表严格模式，需要进行验证签名
//            var_dump($this->wx_pub);exit();
            $jg = $this->v3_verify($res,$this->hd,file_get_contents(wx_pub));
//            var_dump($code['code_url']);exit();
            if ($jg === 1){
                return array('code'=>'SUCCESS','code_url'=>$code['code_url']);
            }else{
                return array('code'=>'FAIL，不返回数据');
            }
        }
        return array('code'=>'数据未验证','code_url'=>$code['code_url']);
    }

    /**
     * 只返预交易ID，剩下的需要微信浏览器内置执行调起支付
     * @param array $data 请求的数组
     * @param bool $type true默认微严格模式
     * @return array|string[] 返回数组
     */
    public function v3_jsapi($data,$type = true)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/pay/transactions/jsapi';
        $data =json_encode($data,256);
        $token = $this->get_token($url,pr,$data,false);
        $setheader = array('Authorization: WECHATPAY2-SHA256-RSA2048 '.$token,
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/106.0.0.0 Safari/537.36');
        $res= $this->http_pg($url,$setheader,$data);
        $_res = json_decode($res,true);

//        var_dump($res);exit;
        if ($type == true){
            $jg =  $this->v3_verify($res,$this->hd,file_get_contents(wx_pub) );
            if($jg == 1){
                return array('code'=>'SUCCESS','prepay_id'=>$_res['prepay_id']);
            }
            return array('code'=>'FAIL');
        }
        return array('code'=>'数据未验证','prepay_id'=>$_res['prepay_id']);
    }
    //获取微信证书

    /**
     * 返回证书和公钥
     * @param bool $type 默认严格模式，对收发数据进行验签
     */
    public function v3_getcrt($type = true)
    {
        $this->hd = '';//清空之前的数据
        $url = 'https://api.mch.weixin.qq.com/v3/certificates';
        $token = array(
            'Authorization: WECHATPAY2-SHA256-RSA2048 '.$this->get_token($url,pr,'',true),
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/106.0.0.0 Safari/537.36'
        );
        $_crs = $this->http_pg($url,$token);
        //var_dump($crs);
        $crs = json_decode($_crs,true);//把返回的数据解析成数组
        $m = $crs['data'][0]['encrypt_certificate']['ciphertext'];//密文，需要解密
        $m = base64_decode($m);//必须需要解码，不然会失败
        $add_data = $crs['data'][0]['encrypt_certificate']['associated_data'];//额外数据
        $nonce = $crs['data'][0]['encrypt_certificate']['nonce'];//解密字符串
        $crt = sodium_crypto_aead_aes256gcm_decrypt($m,$add_data,$nonce,v3key);//解密后获取的是证书
        $res = openssl_pkey_get_public($crt);//从证书里面读取公钥资源
        $ttr= openssl_pkey_get_details($res);//解析并获取公钥
        $ttr  = $ttr['key'];//证书公钥
        $wx_k = $this->v3_verify($_crs,$this->hd,$ttr);

            if($wx_k === 1){
                return array('code'=>'SUCCESS','证书'=>$crt,'微信支付公钥'=>$ttr);
            }else{
                return 'FAIL，不返回数据';
            }
        }

    /**
     * 验证签名
     * @param string $body 请求主体
     * @param string $theader 返回的请求头
     * @param string $pub 位置支付平台证书公钥
     * @return int 返回1代表成功
     */
    public function v3_verify($body,$theader,$pub)
    {
       if(is_array($theader)==true){
           $v_sj =$theader['Wechatpay-Timestamp']."\n".
               $theader['Wechatpay-Nonce']."\n".
               $body."\n";
           
            $jm = base64_decode($theader['Wechatpay-Signature']);
            // var_dump($v_sj,$jm,$pub,);exit;
           $wx_k = openssl_verify($v_sj,$jm,$pub,'sha256WithRSAEncryption');
        //   var_dump(openssl_error_string(),$wx_k);exit;
           return $wx_k;
       }
        $str =  $this->get_header($theader);
            $v_sj =$str['Wechatpay-Timestamp']."\n".
                $str['Wechatpay-Nonce']."\n".
                $body."\n";
            $wx_k = openssl_verify($v_sj,base64_decode($str['Wechatpay-Signature']),$pub,'sha256WithRSAEncryption');
            return $wx_k;
    }

    /**
     * 获取加密后的名字
     * @param string $name 原先的名字
     * @return string 返回加密后的名字
     */
    private function get_name($name)
    {
//        var_dump(file_get_contents(wx_pub));exit();
        openssl_public_encrypt($name,$vname,file_get_contents(wx_pub),OPENSSL_PKCS1_OAEP_PADDING);
//        var_dump(base64_encode($vname));
        return base64_encode($vname);
    }

    //商家转账到零钱/**

    /**
     * 商家转账到零钱
     * @param array $data 请求数据合集
     * @param bool $type 默认是严格模式
     * @return array|string[] 返回数组
     */
    public function v3_batches($data,$type = true)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/transfer/batches';
        //var_dump(isset($data['transfer_detail_list'][0]['user_name']));
        if (isset($data['transfer_detail_list'][0]['user_name'])==true){
            //代表填写了姓名
            $name =$data['transfer_detail_list'][0]['user_name'];
            $name =  $this->get_name($name);
//            var_dump($name);
             $data['transfer_detail_list'][0]['user_name'] = $name ;
        }
        $data = json_encode($data,256);

        $token =  $this->get_token($url,pr,$data,false);
        $setheader = array('Authorization: WECHATPAY2-SHA256-RSA2048 '.$token,
            'Accept: application/json',
            'Content-Type: application/json',
            'Wechatpay-Serial: '.Serial_wx,
            'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/106.0.0.0 Safari/537.36');
//        var_dump($setheader);exit;
        $res = $this->http_pg($url,$setheader,$data);
        $_res = json_decode($res,true);

        if($type == true){
            $jg = $this->v3_verify($res,$this->hd,file_get_contents(wx_pub) );
            if($jg == 1){
                return array('code'=>'SUCCESS',$_res);
            }else{
                return array('code'=>'FAIL，不返回数据');
        }

        }
        return array('code'=>'未验证数据',$_res);
    }

    /**
     * 发放代金券
     * @param array $data
     * @param bool $type
     */
    public function v3_coupons($data,$type = true)
    {
        $url = "https://api.mch.weixin.qq.com/v3/marketing/favor/users/{$data['openid']}/coupons";
        unset($data['openid']);
        $data = json_encode($data,256);
//        var_dump($url);exit;
        $token =  $this->get_token($url,pr,$data,false);
        $setheader = array('Authorization: WECHATPAY2-SHA256-RSA2048 '.$token,
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/106.0.0.0 Safari/537.36');
//        var_dump($setheader,$token,$data);exit;
        $res = $this->http_pg($url,$setheader,$data);
        $_res = json_decode($res,true);
//        var_dump($res);
        if($type == true){
            $jg = $this->v3_verify($res,$this->hd,file_get_contents(wx_pub) );
            if($jg == 1){
                return array('code'=>'SUCCESS',$_res);
            }else{
                return array('code'=>'FAIL，不返回数据');
            }

        }
        return array('code'=>'未验证数据',$_res);
    }

    /**
     * 设置消息通知地址API
     * @param array $data
     * @param bool $type
     */
    public function v3_callbacks($data,$type = true)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/marketing/favor/callbacks';
        $data = json_encode($data,256);
        $token =  $this->get_token($url,pr,$data,false);
        $setheader = array('Authorization: WECHATPAY2-SHA256-RSA2048 '.$token,
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/106.0.0.0 Safari/537.36');
        $res = $this->http_pg($url,$setheader,$data);
        $_res = json_decode($res,true);
//        var_dump($_res);
        if($type == true){
            $jg = $this->v3_verify($res,$this->hd,file_get_contents(wx_pub) );
            if($jg == 1){
                return array('code'=>'SUCCESS',$_res);
            }else{
                return array('code'=>'FAIL，不返回数据');
            }

        }
        return array('code'=>'未验证数据',$_res);

    }


    /**
     * 解密数据
     * @param string $body
     * @return array $obj
     */
    public function aead_256_gmc($body)
    {
        $crs = json_decode($body,true);//把返回的数据解析成数组
        $m = $crs['resource']['ciphertext'];//密文，需要解密
        $m = base64_decode($m);//必须需要解码，不然会失败
        $add_data = $crs['resource']['associated_data'];//额外数据
        $nonce = $crs['resource']['nonce'];//解密字符串
//var_dump($m,$add_data,$nonce,v3key);exit();
        $data = sodium_crypto_aead_aes256gcm_decrypt($m,$add_data,$nonce,v3key);//解密后获取的是证书
        $obj = json_decode($data,true);
        return $obj;
    }

    /**
     * 生成随机字符串
     * @return string
     */
    public function get_str()
    {//生成随机字符串，刚好32位

        // print_r(hash_algos());exit;
        $mm = hash('tiger192,3',time(),1);
        // $mm = hash('md5',$mm,1);
        
        // var_dump(base64_encode($mm));
        return base64_encode($mm);
    }

    /**
     * 生成订单号
     * @return string
     */
    public function get_out()
    {
    //生成订单号
    return date('YmdHis').mt_rand(1000,9999).time();
    }

    /**
     * 创建商家券
     * @param array $data 请求参数合集
     * @param bool $type 严格模式
     */
    public function v3_stocks($data,$type = true)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/marketing/busifavor/stocks';
        $data = json_encode($data,256);
        $token =  $this->get_token($url,pr,$data,false);
        $setheader = array('Authorization: WECHATPAY2-SHA256-RSA2048 '.$token,
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/106.0.0.0 Safari/537.36');
        $res = $this->http_pg($url,$setheader,$data);
        $_res = json_decode($res,true);
//        var_dump($_res);
        if($type == true){
            $jg = $this->v3_verify($res,$this->hd,file_get_contents(wx_pub) );
            if($jg == 1){
                return array('code'=>'SUCCESS',$_res);
            }else{
                return array('code'=>'FAIL，不返回数据');
            }
        }
        return array('code'=>'未验证数据',$_res);
    }

    /**
     * v2 sha256签名
     * @param array $data 待签名的数组
     * @param string $key v2密钥
     */
    public function SHA256($data,$key)
    {
        ksort($data);
        $str_sign = urldecode(http_build_query($data));
        $str_sign = $str_sign .'&key='.$key;
        $sign = hash_hmac('sha256',$str_sign,$key);
        return strtoupper($sign);
    }

    /**
     * h5发券,返回的是链接，让用户扫码访问，一张券只能领取一次
     * @param array $data 请求参数合集
     */
    public function h5_getcouponinfo($data)
    {
        $url = 'https://action.weixin.qq.com/busifavor/getcouponinfo?';
        $url = $url. urldecode(http_build_query($data));
        return $url;
    }

    /**
     * 核销商家券
     * @param array $data
     * @param bool $type
     */
    public function coupons_use($data,$type = true)
    {
        $url = 'https://api.mch.weixin.qq.com/v3/marketing/busifavor/coupons/use';
        $data = json_encode($data,256);
        $token =  $this->get_token($url,pr,$data,false);
        $setheader = array('Authorization: WECHATPAY2-SHA256-RSA2048 '.$token,
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/106.0.0.0 Safari/537.36');
        $res = $this->http_pg($url,$setheader,$data);
        $_res = json_decode($res,true);
//        var_dump($_res);
        if($type == true){
            $jg = $this->v3_verify($res,$this->hd,file_get_contents(wx_pub) );
            if($jg == 1){
                return array('code'=>'SUCCESS',$_res);
            }else{
                return array('code'=>'FAIL，不返回数据');
            }
        }
        return array('code'=>'未验证数据',$_res);
    }
    
    
//这是结束符
}
