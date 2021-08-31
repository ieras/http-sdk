<?php
/**
 * 可爱猫|http-sdk 完整例子
 * @author: 遗忘悠剑
 * @Date: 2020/12/20
 *
 * http://www.uera.cn/robot.php
 * http://r.iknov.com/robot.php
 * @update 2021-01-06 若插件无更新，该demo不再升级～
 */

$is_remote = isset($_REQUEST['remote']) ? trim($_REQUEST['remote']) : 0;
//如果在机器人本机运行，修改为127.0.0.1或者localhost，若外网访问改为运行机器人的服务器外网ip
$robot = Robot::init('122.114.162.223',8090);
if($is_remote == 1){
    //控制机器人，只需要在这后面跟上相关指令即可！目前插件没有通信限制
    //例如:?remote=1&event=GetGroupList 这样就获取群列表了(具体参数可以看文档，也可以看下面的remote方法里的介绍,功能看request方法介绍)
    $robot->remote();
}else{
    // 接收机器人消息并返回处理方案
    //具体如何处理看下面的response方法
    $robot->index();
}

/*-------下面是逻辑功能开发区域------*/

class Robot{

    private $host;
    private $port;
    private $authorization_file = './authorization.txt';//通信鉴权密钥存储路径
    private $authorization;

    private $robot_master = [//机器人主人 后面的程序 你可以自由判断是否必须主人才可操作 自行发挥
        'sundreamer',
    ];
    private $events = [//开发了新功能，就需要在对应的事件下面加入进去例如【'music' => 1】指的是点歌插件=>开启(1 开启 0 关闭)
        'EventLogin' => [//新的账号登录成功/下线时

        ],
        'EventGroupMsg'=> [//群消息事件（收到群消息时，运行这里）
            'music' => 1,
            'douyin' =>1,
        ],
        'EventFriendMsg'=> [//私聊消息事件（收到私聊消息时，运行这里）
            'music' => 1,
            'douyin' =>1,
        ],
        'EventReceivedTransfer'=> [//收到转账事件（收到好友转账时，运行这里）
        ],
        'EventScanCashMoney'=> [//面对面收款（二维码收款时，运行这里）

        ],
        'EventFriendVerify'=> [//好友请求事件（插件3.0版本及以上）
        ],
        'EventContactsChange'=> [//朋友变动事件（插件4.0版本及以上，当前为测试版，还未启用，留以备用）

        ],
        'EventGroupMemberAdd'=> [//群成员增加事件（新人进群）
        ],
        'EventGroupMemberDecrease'=> [//群成员减少事件（群成员退出）
        ],
        'EventSysMsg'=> [//系统消息事件

        ],
    ];

    /**
     * @param string $host
     * @param int $port
     * @return object
     */
    public static function init($host = '127.0.0.1', $port = 8090)
    {
        return new static($host, $port);
    }

    /**
     * @param string $host
     * @param int $port
     */
    public function __construct($host = '127.0.0.1', $port = 8090)
    {
        $this->host = $host;
        $this->port = $port;
        if(!is_file($this->authorization_file))
            $this->setAuthorization();
        $this->authorization = $this->getAuthorization();
    }

    /**
     * 程序入口，返回空白Json或具有操作命令的数据
     * 该方法不需要动
     * @return string 符合可爱猫|http-sdk插件的操作数据结构json
     */
    public function index(){
        header("Content-type: text/html; charset=utf-8");
        date_default_timezone_set("PRC");//设置下时区
        $data = file_get_contents('php://input');//接收原始数据;
        //file_put_contents('./wxmsg.log',$data."\r\n",FILE_APPEND);//记录接收消息log
        $rec_arr = json_decode($data,true);//把接收的json转为数组
        $this->checkAuthorization();//检测通信鉴权，并维护其值
        echo json_encode($this->response($rec_arr));
    }

    /**
     * 控制机器人接口
     * 该方法不需要动
     * @return string 符合openHttpApi插件的操作数据结构json
     */
    public function remote(){
        header("Content-type: text/html; charset=utf-8");
        date_default_timezone_set("PRC");//设置下时区
        $param = [//若想使用同步处理，也就是你接收完事件要如何处理，那么你就要完善下面这个数组
            "event" => isset($_REQUEST['event']) ? trim($_REQUEST['event']) : "SendTextMsg",
            "robot_wxid" => isset($_REQUEST['robot_wxid']) ? trim($_REQUEST['robot_wxid']) : 'wxid_6mkmsto8tyvf52',//wxid_6mkmsto8tyvf52 wxid_5hxa04j4z6pg22
            "group_wxid" => isset($_REQUEST['group_wxid']) ? trim($_REQUEST['group_wxid']) : '18221469840@chatroom',
            "member_wxid" => isset($_REQUEST['member_wxid']) ? trim($_REQUEST['member_wxid']) : '',
            "member_name" => isset($_REQUEST['member_name']) ? trim($_REQUEST['member_name']) : '',
            "to_wxid" => isset($_REQUEST['to_wxid']) ? trim($_REQUEST['to_wxid']) : '18221469840@chatroom',
            "msg" => isset($_REQUEST['msg']) ? trim($_REQUEST['msg']) : "你好啊！"
        ];
        echo json_encode($this->request($param));
    }

    /**
     * 收到机器人的信息，告诉它怎么做
     * @param array $request
     * @return string
     *
     * request
     * >>>  event 事件名称
     * >>>  robot_wxid 机器人id
     * >>>  robot_name 机器人昵称 一般空值
     * >>>  type 1/文本消息 3/图片消息 34/语音消息  42/名片消息  43/视频 47/动态表情 48/地理位置  49/分享链接  2000/转账 2001/红包  2002/小程序  2003/群邀请
     * >>>  from_wxid 来源群id
     * >>>  from_name 来源群名称
     * >>>  final_from_wxid 具体发消息的群成员id/私聊时用户id
     * >>>  final_from_name 具体发消息的群成员昵称/私聊时用户昵称
     * >>>  to_wxid 发给谁，往往是机器人自己(也可能别的成员收到消息)
     * >>>  money 金额，只有"EventReceivedTransfer"事件才有该参数
     * >>>  msg 消息体(str/json) 不同事件和不同type都不一样，自己去试验吧
     *
     * request.event
     * >>>  EventLogin'://新的账号登录成功/下线时
     * >>>  EventGroupMsg'://群消息事件（收到群消息时，运行这里）
     * >>>  EventFriendMsg'://私聊消息事件（收到私聊消息时，运行这里）
     * >>>  EventReceivedTransfer'://收到转账事件（收到好友转账时，运行这里）
     * >>>  EventScanCashMoney'://面对面收款（二维码收款时，运行这里）
     * >>>  EventFriendVerify'://好友请求事件（插件3.0版本及以上）
     * >>>  EventContactsChange'://朋友变动事件（插件4.0版本及以上，当前为测试版，还未启用，留以备用）
     * >>>  EventGroupMemberAdd'://群成员增加事件（新人进群）
     * >>>  EventGroupMemberDecrease'://
     */
    public function response($request){
        $response = ["event" => ""];//event空时，机器人不处理消息
        $functions = $this->events[$request['event']];
        if(empty($functions))//若没处理方法，直接返回空数据告知机器人不处理即可！
            return $response;

        foreach ($functions as $func => $is_on){
            if($is_on){
                $response = call_user_func([$this,$func],$request);
                if($response !== false)
                    break;//只要一个成功就跳出循环
            }
        }
        //处理完事件返回要怎么做
        return $response;
    }

    public function music($request){
        $key = ['点歌','我想听','来一首'];
        $msg = trim($request['msg']);
        foreach ($key as $v){
            if($this->startWith($msg,$v)){
                $name = trim(str_replace($v,'',$msg));//把 key的前缀词替换为空
                return [
                    "event" => "SendMusicMsg",
                    "robot_wxid" => $request['robot_wxid'],
                    "to_wxid" => $request['from_wxid'] ? $request['from_wxid'] : $request['final_from_wxid'],
                    "member_wxid" => '',
                    "member_name" => '',
                    "group_wxid" => '',
                    "msg" => ['name'=>$name,'type'=>0],
                ];
            }
        }
        return false;
    }

    public function douyin($request){
        $key = ['抖音','抖音视频','抖'];
        $msg = trim($request['msg']);
        foreach ($key as $v){
            if($this->startWith($msg,$v)){
                $dou['link'] = trim(str_replace($v,'',$msg));//把用户发的消息截取为url
                $dou_json = $this->sendHttp(http_build_query($dou),'http://qsy.988g.cn/ajax/analyze.php');
                //file_put_contents('./dou_json.txt',$dou_json);
                $dou_arr = json_decode($dou_json,true);
                //var_dump($dou_arr['data']['cover']);
                //$v_file = file_get_contents('/tmp/dou/'.basename($dou_arr['data']['downurl']),$dou_arr['data']['downurl']);
                $link = [
                    'title' => $dou_arr['data']['voidename'],
                    'text' => $dou_arr['data']['voidename'],
                    'target_url' => $dou_arr['data']['downurl'],
                    'pic_url' => $dou_arr['data']['cover'],
                    'icon_url' => $dou_arr['data']['cover'],
                ];
                //发送分享链接 robot_wxid, to_wxid(群/好友), msg(title, text, target_url, pic_url, icon_url)
                return [
                    "event" => "SendLinkMsg",
                    "robot_wxid" => $request['robot_wxid'],
                    "to_wxid" => $request['from_wxid'] ? $request['from_wxid'] : $request['final_from_wxid'],
                    "member_wxid" => '',
                    "member_name" => '',
                    "group_wxid" => '',
                    "msg" => $link,
                ];
            }
        }
        return false;
    }


    /**
     * 命令机器人去做某事
     * @param array $param
     * @param string $authorization
     * @return string
     *
     * param
     * >>>  event 事件名称
     * >>>  robot_wxid 机器人id
     * >>>  group_wxid 群id
     * >>>  member_wxid 群艾特人id
     * >>>  member_name 群艾特人昵称
     * >>>  to_wxid 接收方(群/好友)
     * >>>  msg 消息体(str/json)
     *
     * param.event
     * >>> SendTextMsg 发送文本消息 robot_wxid to_wxid(群/好友) msg
	 * >>> 下面的几个文件类型的消息path为服务器里的路径如"D:/a.jpg"，会优先使用，文件不存在则使用 url(网络地址)
     * >>> SendImageMsg 发送图片消息 robot_wxid to_wxid(群/好友) msg(name[md5值或其他唯一的名字，包含扩展名例如1.jpg], url,patch)
     * >>> SendVideoMsg 发送视频消息 robot_wxid to_wxid(群/好友) msg(name[md5值或其他唯一的名字，包含扩展名例如1.mp4], url,patch)
     * >>> SendFileMsg 发送文件消息 robot_wxid to_wxid(群/好友) msg(name[md5值或其他唯一的名字，包含扩展名例如1.txt], url,patch)
     * >>> SendEmojiMsg 发送动态表情 robot_wxid to_wxid(群/好友) msg(name[md5值或其他唯一的名字，包含扩展名例如1.gif], url,patch)
     * >>> SendGroupMsgAndAt 发送群消息并艾特(4.4只能艾特一人) robot_wxid, group_wxid, member_wxid, member_name, msg
     * >>> SendLinkMsg 发送分享链接 robot_wxid, to_wxid(群/好友), msg(title, text, target_url, pic_url, icon_url)
     * >>> SendMusicMsg 发送音乐分享 robot_wxid, to_wxid(群/好友), msg(music_name, type)
	 * >>> SendCardMsg 发送名片消息(被禁用) robot_wxid to_wxid(群/好友) msg(微信号)
	 * >>> SendMiniApp 发送小程序 robot_wxid to_wxid(群/好友) msg(小程序消息的xml内容)
     * >>> GetRobotName 取登录账号昵称 robot_wxid
     * >>> GetRobotHeadimgurl 取登录账号头像 robot_wxid
     * >>> GetLoggedAccountList 取登录账号列表 不需要参数
     * >>> GetFriendList 取好友列表 robot_wxid msg(is_refresh,out_rawdata)//是否更新缓存 是否原始数据
     * >>> GetGroupList 取群聊列表 robot_wxid(不传返回全部机器人的)，msg(is_refresh)
     * >>> GetGroupMemberList 取群成员列表 robot_wxid, group_wxid msg(is_refresh)
     * >>> GetGroupMemberInfo 取群成员详细 robot_wxid, group_wxid, member_wxid msg(is_refresh)
     * >>> AcceptTransfer 接收好友转账 robot_wxid, to_wxid, msg
     * >>> AgreeGroupInvite 同意群聊邀请 robot_wxid, msg
     * >>> AgreeFriendVerify 同意好友请求 robot_wxid, msg
     * >>> EditFriendNote 修改好友备注 robot_wxid, to_wxid, msg
     * >>> DeleteFriend 删除好友 robot_wxid, to_wxid
     * >>> GetAppInfo 取插件信息 无参数
     * >>> GetAppDir 取应用目录 无
     * >>> AddAppLogs 添加日志 msg
     * >>> ReloadApp 重载插件 无
     * >>> RemoveGroupMember 踢出群成员 robot_wxid, group_wxid, member_wxid
     * >>> EditGroupName 修改群名称 robot_wxid, group_wxid, msg
     * >>> EditGroupNotice 修改群公告 robot_wxid, group_wxid, msg
     * >>> BuildNewGroup 建立新群 robot_wxid, msg(好友Id用"|"分割)
     * >>> QuitGroup 退出群聊 robot_wxid, group_wxid
     * >>> InviteInGroup 邀请加入群聊 robot_wxid, group_wxid, to_wxid
     */
    public function request($param){
        //处理完事件返回要怎么做
        $headers = [
            'Content-Type:application/json;charset=utf-8',
        ];
        if($this->authorization)
            $headers[] = "Authorization:{$this->authorization}";
        return json_decode($this->sendHttp(json_encode($param),null,$headers),true);
    }

    /**
     * 聊天内容是否以关键词xx开头
     *
     * @param  string $str  聊天内容
     * @param  string $pattern  关键词
     * @return boolean  true/false
     */
    public function startWith($str,$pattern) {
        return strpos($str,$pattern) === 0 ? true : false;
    }

    /**
     * 发送 HTTP 请求
     *
     * @param  string  $params 请求参数,会原样发送
     * @param  string $url     请求地址
     * @param  array  $headers 请求头
     * @param  int    $timeout 超时时间
     * @param  string $method  请求方法 post / get
     * @return string  结果数据(Body原始数据，一般为json字符串)
     */
    public function sendHttp($params, $url = null, $headers = null, $method = 'post', $timeout = 3)
    {
        $url = $url ? $url : $this->host.':'.$this->port;

        $curl = curl_init();
        if ('get' == strtolower($method)) {//以GET方式发送请求
            curl_setopt($curl, CURLOPT_URL, "{$url}?{$params}");
        } else {//以POST方式发送请求
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_POST, 1);//post提交方式
            curl_setopt($curl, CURLOPT_POSTFIELDS, $params);//设置传送的参数
        }
        /* $headers 格式
        $headers = [
            'Content-Type:application/json;charset=utf-8',
            'Authorization:Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI5MjU3NTczMS0zMWVlLTQxM2UtYTcwZS1mMmMyNDk3Y2M4ODAiLCJqdGkiOiI0MTA4MGQ2NjZhMDY5ZjRkNjQzOTg0M2NiMDhiOWE5ZTE1YzRiNzA3ZTE0MzA1NGEyZmI4MTgxOGQ1NjYxOTc2NDczY2I5MTk1MzI5ODU1YyIsImlhdCI6MTYwOTE1MTYyNiwibmJmIjoxNjA5MTUxNjI2LCJleHAiOjE2MTE4MzAwMjMsInN1YiI6IjEiLCJzY29wZXMiOltdfQ.i0R_gQuJ6iNK8g4RaF4paBQ4GUxnoQ-0uOjEy4cc3o1_sN4imj-k5ocnHsPdV2e467XJXBmoIKGAlh1RDuKnA6ksa1arhM78YjqRRwjw5jICnQ1O8PM-hYiAOF33X32UeHujVskGgYobFmtgUERZP--69qkdlxxpgmfQBkGwE1-XJH4VjcX82xHvxtiC0O56krpmYP7N9EimVcIc6ciKV_inlM8epI8Io5JKddRppIga3e04nV5hujb0m8bN5rD32l7mOeYRyTNhZAaovbjAvjWSFrPCz4LoXXDyxUDEmfBKxUd1JFNHfdWBo3dFMCh9-MSuKdSVY0LqeKKf9FKoiYNBIETYgsdOIq_QKhoJsrumC2y_IZ6iwQEpaRrH2Y6dzUKzfisBc2dBBeFEmOIo4ZB-HajBcRNfnnue60RMCXs_GrczQ5np8P5CzhqdHomHA9VxbhyvzjO-qAB76lgaxaOVC4w7p_h74nXOY5HMMzK7_DTbwiMMGtpX2S_aN4Z2yuVEK9h0c8JBqGN-Theb7ZHznP-NTgCyBkmzx-FtF6Pmahgp7kYv6trrSNd0WdKpQn4XBaXbVKINaobtCd0QONnFcGf3svUg8Lfoyy-r3B8y7nh94-2iNBfvPlgqzwrBdhmEEMnz6oJXCscu-d9z6a8L8cQty3YgFSzNEbh1YoI'
        ];
        */
        if(!empty($headers))
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_HEADER, false);//是否打印服务器返回的header
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);//要求结果为字符串且输出到屏幕上
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout);//设置等待时间
        $res = curl_exec($curl);//运行curl
        $err = curl_error($curl);

        if (false === $res || !empty($err)) {
            $Errno = curl_errno($curl);
            $Info = curl_getinfo($curl);
            curl_close($curl);
            print_r($Info);
            return $err. ' result: ' . $res . 'error_msg: '.$Errno;
        }
        curl_close($curl);//关闭curl

        return $res;
    }

    /**
     * 获取headers Nginx和Apache
     * @return array
     * @author 遗忘悠剑
     */
    private function getHeaders() {
        $headers = [];
        if (!function_exists('getallheaders')) {
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-',
                        ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
                }
            }
        }else{
            $headers = getallheaders();
        }
        return $headers;
    }

    /**
     * 设置Authorization并返回
     * @param string $authorization
     * @return string
     * @author 遗忘悠剑
     */
    private function setAuthorization($authorization = ''){
        file_put_contents($this->authorization_file,$authorization);
        $this->authorization = $authorization;
        return $this->authorization;
    }
    /**
     * 获取Authorization
     * @return string
     * @author 遗忘悠剑
     */
    private function getAuthorization(){
        $this->authorization = file_get_contents($this->authorization_file) ?:'';
        return $this->authorization;
    }

    /**
     * 检测Authorization并返回
     * @return string
     * @author 遗忘悠剑
     */
    private function checkAuthorization(){
        $headers = $this->getHeaders();
        if(!empty($headers['Authorization']) && $headers['Authorization'] != $this->getAuthorization())
            return $this->setAuthorization($headers['Authorization'] ?: '');
    }
}
