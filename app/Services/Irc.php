<?php
// https://console.cloud.tencent.com/api/explorer?Product=nlp&Version=2019-04-08&Action=ChatBot&SignVersion=
// https://cloud.tencent.com/document/product/271/39416
// https://cloud.tencent.com/product/icr?fromSource=gwzcw.3893384.3893384.3893384
// 对话机器人 ICR

// 对话机器人（Conversation Robot，ICR），是基于人工智能技术，面向企业场景的 AI 服务，可应用于智能客服、服务咨询、业务办理等场景。本产品旨在帮助企业快速构建，满足自身业务诉求的对话机器人，从而减少企业人力成本或解决服务不及时问题。用户可通过对话机器人用户端引擎，实现高准确率的对话服务。
// 免费额度
// 基础自然语言处理，将为每个腾讯云账号提供每天50万次的免费调用额度，当日剩余免费调用量不累积结转至第二天，每个自然日重置50万次免费额度。

// 闲聊服务基于腾讯领先的NLP引擎能力、数据运算能力和千亿级互联网语料数据的支持，同时集成了广泛的知识问答能力，可实现上百种自定义属性配置，以及儿童语言风格及说话方式，从而让聊天变得更睿智、简单和有趣。

// 默认接口请求频率限制：20次/秒。

namespace App\Services;

use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Common\Exception\TencentCloudSDKException;
use TencentCloud\Nlp\V20190408\NlpClient;
use TencentCloud\Nlp\V20190408\Models\ChatBotRequest;

final class Icr {
  public function run($query='你好')
  {
		try {
			$SecretId = config('services.tencentcloud.secretId');
			$SecretKey = config('services.tencentcloud.secretKey');
	    $cred = new Credential($SecretId, $SecretKey);
	    $httpProfile = new HttpProfile();
	    $httpProfile->setEndpoint("nlp.tencentcloudapi.com");

	    $clientProfile = new ClientProfile();
	    $clientProfile->setHttpProfile($httpProfile);
	    $client = new NlpClient($cred, "ap-guangzhou", $clientProfile);

	    $req = new ChatBotRequest();
	    
	    $params = array(
	        "Query" => $query
	    );
	    $req->fromJsonString(json_encode($params));

	    return $client->ChatBot($req);
		}
		catch(TencentCloudSDKException $e) {
		  echo $e;
		}
  }
}